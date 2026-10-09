<?php
// submit_answer.php
// Answers are locked first and revealed/scored only when the teacher changes
// the session to the results phase through teacher_control.php.

header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB error"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "POST only"]);
    exit();
}

$session_id    = intval($_POST['session_id'] ?? 0);
$question_id   = intval($_POST['question_id'] ?? 0);
$nickname_raw  = trim($_POST['nickname'] ?? '');
$answer_raw    = trim($_POST['answer'] ?? '');
$time_taken_ms = max(0, intval($_POST['time_taken_ms'] ?? 0));
$powerup_used  = trim($_POST['powerup'] ?? '');

$nickname     = $conn->real_escape_string($nickname_raw);
$answer_given = $conn->real_escape_string(strtoupper($answer_raw));

if (!$session_id || !$question_id || !$nickname_raw || !$answer_raw) {
    echo json_encode(["success" => false, "error" => "Missing fields"]);
    exit();
}

// Only the participant who actually joined this session may submit.
$participant_res = $conn->query(
    "SELECT nickname FROM synchro_participants
     WHERE session_id = $session_id AND nickname = '$nickname'
     LIMIT 1"
);
if (!$participant_res || $participant_res->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Participant is not in this session"]);
    exit();
}

// Answers are accepted only for the question currently controlled by the
// teacher. This prevents late answers from leaking into a later question.
$state_res = $conn->query(
    "SELECT current_question, phase, question_started_at
     FROM synchro_game_state WHERE session_id = $session_id LIMIT 1"
);
$state = $state_res ? $state_res->fetch_assoc() : null;
if (!$state || $state['phase'] !== 'question') {
    echo json_encode(["success" => false, "error" => "Question is not accepting answers"]);
    exit();
}

$question_res = $conn->query(
    "SELECT id, session_id, question_order, time_limit
     FROM synchro_questions
     WHERE id = $question_id AND session_id = $session_id
     LIMIT 1"
);
$question = $question_res ? $question_res->fetch_assoc() : null;

if (!$question || intval($state['current_question']) !== intval($question['question_order'])) {
    echo json_encode(["success" => false, "error" => "Question is no longer active"]);
    exit();
}

// Do not accept an answer after the server-side timer has expired. The
// teacher can still reveal the results and score all answers already locked.
if (!empty($state['question_started_at'])) {
    $started = strtotime($state['question_started_at']);
    $elapsed = time() - $started;
    if ($elapsed > intval($question['time_limit'])) {
        echo json_encode(["success" => false, "error" => "Answer time has ended"]);
        exit();
    }
}

// The client blocks duplicates too, but this server check is required for
// refreshes, retries, and two rapid requests arriving at the same time.
$existing = $conn->query(
    "SELECT id FROM synchro_answers
     WHERE session_id = $session_id
       AND question_id = $question_id
       AND participant_nickname = '$nickname'
     LIMIT 1"
);
if ($existing && $existing->num_rows > 0) {
    echo json_encode(["success" => false, "error" => "Already answered"]);
    exit();
}

// Negative points_earned means "pending" and is never shown as a score.
// -1 = normal answer, -2 = double points, -3 = immunity skip,
// -4 = streak saver, -5 = double points + streak saver.
$hasDoublePoints = str_contains($powerup_used, 'double_points');
$hasStreakSaver  = str_contains($powerup_used, 'streak_saver');
$pending_marker = -1;
if ($answer_given === 'SKIP' || $powerup_used === 'shield_skip') {
    $pending_marker = -3;
} elseif ($hasDoublePoints && $hasStreakSaver) {
    $pending_marker = -5;
} elseif ($hasDoublePoints) {
    $pending_marker = -2;
} elseif ($hasStreakSaver) {
    $pending_marker = -4;
}

$inserted = $conn->query(
    "INSERT INTO synchro_answers
        (session_id, question_id, participant_nickname, answer_given,
         is_correct, time_taken_ms, points_earned)
     VALUES
        ($session_id, $question_id, '$nickname', '$answer_given',
         0, $time_taken_ms, $pending_marker)"
);

if (!$inserted) {
    echo json_encode(["success" => false, "error" => "Could not lock answer"]);
    $conn->close();
    exit();
}

echo json_encode([
    "success" => true,
    "pending" => true,
    "message" => "Answer locked. Waiting for the teacher to reveal results.",
]);

$conn->close();
?>