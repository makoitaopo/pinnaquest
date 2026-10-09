<?php

// save_quiz_result.php
// Called by quiz.php JS when the quiz ends (and, harmlessly, by the other
// solo game modes too — they just won't send a quiz_log, which is fine).
// Awards XP, saves result, checks + unlocks achievements, and — new —
// logs the student's name plus a per-question answer record for test-case
// / reporting purposes.

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/xp_policy.php';
require_once __DIR__ . '/mission_progress.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

// Browser retries, double-clicks, and refreshes must not create another XP
// award for the same completed attempt.
if (!empty($_SESSION['solo_result_saved']) && isset($_SESSION['solo_result_response'])) {
    echo json_encode($_SESSION['solo_result_response']);
    exit();
}

$conn    = new mysqli("localhost", "root", "", "pinnaquest_db");
$user_id = intval($_SESSION['user_id']);

$score           = intval($_POST['score']            ?? 0);
$total_questions = max(0, intval($_POST['total_questions'] ?? 1));
$correct_answers = max(0, min(intval($_POST['correct_answers'] ?? 0), $total_questions));
$quiz_title      = $conn->real_escape_string($_SESSION['quiz_data']['title'] ?? 'Solo Quiz');
$questions       = $_SESSION['quiz_data']['questions'] ?? [];
$quiz_key        = quizQuestionsKey(is_array($questions) ? $questions : []);
$quiz_key_sql    = $conn->real_escape_string($quiz_key);
$is_retake       = !empty($_SESSION['solo_retake_of']) ? 1 : 0;
$retake_of       = intval($_SESSION['solo_retake_of'] ?? 0);

ensureQuizXpSchema($conn);

// ── Resolve the student's display name (for the test-case record) ──────
$student_name = 'Student';
$name_res = $conn->query("SELECT COALESCE(display_name, full_name) AS name FROM users WHERE id = $user_id");
if ($name_res && $name_res->num_rows > 0) {
    $name_row = $name_res->fetch_assoc();
    if (!empty($name_row['name'])) $student_name = $name_row['name'];
}
$student_name_safe = $conn->real_escape_string($student_name);

// ── XP formula for solo quizzes ───────────────────────────────────
$perfect_xp   = soloPerfectXp($total_questions);
$attempt_xp   = soloAttemptXp($correct_answers, $total_questions);
$is_perfect   = ($total_questions > 0 && $correct_answers >= $total_questions);
$requested_xp = $attempt_xp;

if ($is_retake) {
    $previousEligible = soloEligibleXpBefore($conn, $user_id, $quiz_key, $retake_of);

    if ($previousEligible >= $perfect_xp) {
        // Na-perfect na dati ang quiz na ito → wala nang XP sa retakes.
        $requested_xp = 0;
    } else {
        // Ibigay lang ang aktwal na kinita ng retake na ito (0 correct = 0 XP),
        // pero naka-cap sa "kulang" papuntang perfect_xp.
        $remaining    = max(0, $perfect_xp - $previousEligible);
        $requested_xp = min($attempt_xp, $remaining);
    }
}

// ── Save result ───────────────────────────────────────────────────
$conn->query(
    "INSERT INTO solo_quiz_results
        (user_id, student_name, quiz_title, score, correct_answers, total_questions,
         xp_earned, quiz_key, perfect_xp, is_retake, retake_of)
     VALUES 
        ($user_id, '$student_name_safe', '$quiz_title', $score, $correct_answers, $total_questions,
         0, '$quiz_key_sql', $perfect_xp, $is_retake, " . ($retake_of > 0 ? $retake_of : 'NULL') . ")"
);
$result_id = $conn->insert_id;
unset($_SESSION['solo_quiz_order_initialized']);

// ── Save per-question answer log (quiz.php only sends this) ───────────
// quiz_log is a JSON array of:
//   { q, type, options, correct_answer, user_answer, is_correct }
// user_answer can be null if the student ran out of time / skipped.
$quiz_log_raw = $_POST['quiz_log'] ?? '[]';
$quiz_log     = json_decode($quiz_log_raw, true);
if (!is_array($quiz_log)) $quiz_log = [];

// ── Complete the log using the full question set stored in the session ──
// Kung natapos ang game nang maaga (Game Over / quit / time out), kulang ang
// quiz_log. Dinadagdagan natin ito ng mga tanong na hindi umabot, para buo
// ang bilang ng items kapag nag-retake.
$session_by_text = [];
foreach ($questions as $sq) {
    if (!is_array($sq)) continue;
    $k = trim((string)($sq['question'] ?? ''));
    if ($k !== '') $session_by_text[$k] = $sq;
}

$logged_keys = [];
foreach ($quiz_log as &$e) {
    if (!is_array($e)) continue;
    $k = trim((string)($e['q'] ?? ''));
    $logged_keys[$k] = true;

    // Repair empty options (e.g. Bubble Pop timeout/escape logs [])
    if (empty($e['options'])
        && ($e['type'] ?? '') !== 'fill_blank'
        && !empty($session_by_text[$k]['options'])) {
        $e['options'] = $session_by_text[$k]['options'];
    }
}
unset($e);

// Append questions the student never reached
foreach ($session_by_text as $k => $sq) {
    if (count($quiz_log) >= $total_questions) break;
    if (isset($logged_keys[$k])) continue;

    $is_mcq = !empty($sq['options']);
    $quiz_log[] = [
        'q'              => $sq['question'],
        'type'           => $is_mcq ? 'multiple_choice' : 'fill_blank',
        'options'        => $is_mcq ? array_values($sq['options']) : [],
        'correct_answer' => $sq['answer'] ?? '',
        'user_answer'    => null,   // NULL = never answered
        'is_correct'     => false,
    ];
}

if ($result_id && is_array($quiz_log) && count($quiz_log) > 0) {
    $qnum = 0;
    foreach ($quiz_log as $entry) {
        if (!is_array($entry)) continue;
        $qnum++;

        $q_text    = $conn->real_escape_string((string)($entry['q'] ?? ''));
        $q_type    = $conn->real_escape_string((string)($entry['type'] ?? 'multiple_choice'));

        $opts_arr  = (isset($entry['options']) && is_array($entry['options'])) ? $entry['options'] : [];
        $opts_json = $conn->real_escape_string(json_encode($opts_arr, JSON_UNESCAPED_UNICODE));

        $correct_ans = $conn->real_escape_string((string)($entry['correct_answer'] ?? ''));

        $user_ans_raw = $entry['user_answer'] ?? null;
        $user_ans_sql = ($user_ans_raw === null || $user_ans_raw === '')
            ? 'NULL'
            : "'" . $conn->real_escape_string((string)$user_ans_raw) . "'";

        $is_corr = !empty($entry['is_correct']) ? 1 : 0;

        $conn->query(
            "INSERT INTO solo_quiz_answers
                (result_id, question_number, question_text, question_type, options, correct_answer, user_answer, is_correct)
             VALUES
                ($result_id, $qnum, '$q_text', '$q_type', '$opts_json', '$correct_ans', $user_ans_sql, $is_corr)"
        );
    }
}

// ── Apply shared daily cap and duplicate protection ────────────────
$xp_earned = awardQuizXp($conn, $user_id, 'solo', $quiz_key, $result_id, $requested_xp);
$conn->query("UPDATE solo_quiz_results SET xp_earned = $xp_earned WHERE id = $result_id AND user_id = $user_id");

// ── Check and award achievements ──────────────────────────────────
checkAndAwardAchievements($conn, $user_id);

// ── Return new XP totals ──────────────────────────────────────────
$totals = quizXpTotals($conn, $user_id);

$response = [
    'success'      => true,
    'xp_earned'    => $xp_earned,
    'total_xp'     => $totals['total_xp'],
    'level'        => $totals['level'],
    'progress_pct' => $totals['progress_pct'],
    'result_id'    => $result_id,
];
$_SESSION['solo_result_saved'] = true;
$_SESSION['solo_result_response'] = $response;
unset($_SESSION['solo_retake_of']);
echo json_encode($response);

$conn->close();

// ── HELPER ────────────────────────────────────────────────────────
function checkAndAwardAchievements($conn, $user_id) {
    pinnaquestSyncMissionAchievements($conn, $user_id);
}
?>