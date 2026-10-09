<?php
// get_quiz_state.php - FIXED: sub-second precision for time_left so all clients stay in sync

header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit();
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$nickname_raw = trim($_GET['nickname'] ?? '');
$nickname = $conn->real_escape_string($nickname_raw);
if (!$session_id) {
    echo json_encode(["error" => "No session ID"]);
    exit();
}

// Get game state
$state_res = $conn->query("SELECT * FROM synchro_game_state WHERE session_id = $session_id");
if (!$state_res || $state_res->num_rows === 0) {
    echo json_encode(["phase" => "lobby", "current_question" => 0]);
    exit();
}
$state = $state_res->fetch_assoc();
$phase        = $state['phase'];
$current_q_idx = intval($state['current_question']);

// Total questions
$total_q_res     = $conn->query("SELECT COUNT(*) as cnt FROM synchro_questions WHERE session_id = $session_id");
$total_questions = intval($total_q_res->fetch_assoc()['cnt']);

$response = [
    "phase"                  => $phase,
    "current_question_index" => $current_q_idx,
    "total_questions"        => $total_questions,
];

// ── Current question data ────────────────────────────────────────────────────
if ($phase === 'question' || $phase === 'results') {
    $q_res = $conn->query(
        "SELECT * FROM synchro_questions 
         WHERE session_id = $session_id AND question_order = $current_q_idx"
    );

    if ($q_res && $q_res->num_rows > 0) {
        $q = $q_res->fetch_assoc();
        $response['question'] = [
            'id'         => $q['id'],
            'text'       => $q['question_text'],
            'type'       => $q['question_type'],
            'time_limit' => intval($q['time_limit']),
            'options'    => [
                'A' => $q['option_a'],
                'B' => $q['option_b'],
                'C' => $q['option_c'],
                'D' => $q['option_d'],
            ],
        ];

        // ── TIMER: use microtime for sub-second accuracy ──────────────────────
        if ($state['question_started_at']) {
            // PHP microtime(true) vs MySQL datetime → convert to float seconds
            $started_ts = strtotime($state['question_started_at']); // integer unix seconds
            $now_ms     = microtime(true);                           // float unix seconds
            $elapsed    = $now_ms - $started_ts;
            $time_left  = max(0, floatval($q['time_limit']) - $elapsed);

            $response['time_left'] = round($time_left, 2); // e.g. 14.73
            $response['elapsed']   = round($elapsed, 2);
        } else {
            $response['time_left'] = intval($q['time_limit']);
            $response['elapsed']   = 0;
        }

        // Reveal correct answer only in results phase
        if ($phase === 'results') {
            $response['question']['correct_answer'] = $q['correct_answer'];

            $dist_res = $conn->query(
                "SELECT answer_given, COUNT(*) as cnt 
                 FROM synchro_answers 
                 WHERE session_id = $session_id AND question_id = {$q['id']} 
                 GROUP BY answer_given"
            );
            $distribution = [];
            while ($row = $dist_res->fetch_assoc()) {
                $distribution[$row['answer_given']] = intval($row['cnt']);
            }
            $response['answer_distribution'] = $distribution;

            // Count finalized correct rows directly. Do not infer this from
            // the answer distribution because casing/spacing/punctuation in
            // the stored answer must not turn all respondents into correct
            // answers when the key has no exact distribution entry.
            $correct_res = $conn->query(
                "SELECT COUNT(*) as cnt
                 FROM synchro_answers
                 WHERE session_id = $session_id
                   AND question_id = {$q['id']}
                   AND is_correct = 1"
            );
            $response['correct_count'] = $correct_res
                ? intval($correct_res->fetch_assoc()['cnt'])
                : 0;

            $responded_res = $conn->query(
                "SELECT COUNT(*) as cnt FROM synchro_answers 
                 WHERE session_id = $session_id AND question_id = {$q['id']}"
            );
            $response['responded_count'] = intval($responded_res->fetch_assoc()['cnt']);

            // Only reveal the logged-in student's own finalized result here.
            // During the question phase, pending rows and correctness remain
            // hidden so students cannot copy from one another.
            if ($nickname_raw !== '') {
                $myAnswerRes = $conn->query(
                    "SELECT answer_given, is_correct, points_earned, time_taken_ms
                     FROM synchro_answers
                     WHERE session_id = $session_id
                       AND question_id = {$q['id']}
                       AND participant_nickname = '$nickname'
                     LIMIT 1"
                );
                if ($myAnswerRes && $myAnswerRes->num_rows > 0) {
                    $myAnswer = $myAnswerRes->fetch_assoc();
                    $response['my_answer'] = [
                        'submitted' => true,
                        'is_correct' => (bool) $myAnswer['is_correct'],
                        'points_earned' => max(0, (int) $myAnswer['points_earned']),
                        'time_taken_ms' => (int) $myAnswer['time_taken_ms'],
                        'pending' => (int) $myAnswer['points_earned'] < 0,
                        'correct_answer' => $q['correct_answer'],
                        'answer_given' => $myAnswer['answer_given'],
                    ];

                    $myScoreRes = $conn->query(
                        "SELECT streak FROM synchro_scores
                         WHERE session_id = $session_id
                           AND nickname = '$nickname'
                         LIMIT 1"
                    );
                    $response['my_answer']['streak'] = $myScoreRes
                        ? (int) (($myScoreRes->fetch_assoc()['streak'] ?? 0))
                        : 0;
                } else {
                    $response['my_answer'] = ['submitted' => false];
                }
            }
        } elseif ($phase === 'question' && $nickname_raw !== '') {
            // On refresh/reconnect, tell the student only that their answer
            // exists. Never return correctness or points during the live phase.
            $pendingRes = $conn->query(
                "SELECT points_earned
                 FROM synchro_answers
                 WHERE session_id = $session_id
                   AND question_id = {$q['id']}
                   AND participant_nickname = '$nickname'
                 LIMIT 1"
            );
            $response['my_answer'] = [
                'submitted' => $pendingRes && $pendingRes->num_rows > 0,
                'pending' => true,
            ];
        }
    }
}

// ── Leaderboard ──────────────────────────────────────────────────────────────
$lb_res = $conn->query(
    "SELECT nickname, avatar_key, total_score, correct_answers, streak 
     FROM synchro_scores 
     WHERE session_id = $session_id 
     ORDER BY total_score DESC 
     LIMIT 10"
);
$leaderboard = [];
while ($row = $lb_res->fetch_assoc()) {
    $leaderboard[] = $row;
}
$response['leaderboard'] = $leaderboard;

// ── Participant count ─────────────────────────────────────────────────────────
$pc_res = $conn->query(
    "SELECT COUNT(*) as cnt FROM synchro_participants WHERE session_id = $session_id"
);
$response['participant_count'] = intval($pc_res->fetch_assoc()['cnt']);

// ── Responded count for current question (teacher progress bar) ──────────────
if ($phase === 'question' && isset($response['question'])) {
    $qid      = $response['question']['id'];
    $resp_res = $conn->query(
        "SELECT COUNT(*) as cnt FROM synchro_answers 
         WHERE session_id = $session_id AND question_id = $qid"
    );
    $response['responded_count'] = intval($resp_res->fetch_assoc()['cnt']);
}

echo json_encode($response);
$conn->close();
?>