<?php
// save_synchro_questions.php
// Called by init_synchro_process.php after AI generates questions.
// XP awarding happens later, once the participant's session is finished.
// Stores questions into synchro_questions table and initializes game state

header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "POST only"]);
    exit();
}

$session_id = intval($_POST['session_id'] ?? 0);
$questions_json = $_POST['questions_json'] ?? '';

if (!$session_id || !$questions_json) {
    echo json_encode(["success" => false, "error" => "Missing data"]);
    exit();
}

$questions = json_decode($questions_json, true);
if (!$questions || !is_array($questions)) {
    echo json_encode(["success" => false, "error" => "Invalid JSON"]);
    exit();
}

// Get quiz_type from session
$session_res = $conn->query("SELECT quiz_type FROM synchro_sessions WHERE id = $session_id");
$session_data = $session_res->fetch_assoc();
$quiz_type = $session_data['quiz_type'] ?? 'multiple_choice';

// Clear old questions for this session (in case of retry)
$conn->query("DELETE FROM synchro_questions WHERE session_id = $session_id");

// Insert each question
$questions_ordered = $questions;
shuffle($questions_ordered);
$order = 0;
foreach ($questions_ordered as $q) {
    $order++;
    $question_text = $conn->real_escape_string($q['question'] ?? '');
    $q_type = (strpos($quiz_type, 'identif') !== false) ? 'identification' : 'multiple_choice';

    if ($q_type === 'multiple_choice') {
        $options = $q['options'] ?? ['', '', '', ''];
        $opt_a = $conn->real_escape_string($options[0] ?? '');
        $opt_b = $conn->real_escape_string($options[1] ?? '');
        $opt_c = $conn->real_escape_string($options[2] ?? '');
        $opt_d = $conn->real_escape_string($options[3] ?? '');
        
        // Determine correct answer letter
        $answer_index = intval($q['answer_index'] ?? 0);
        $letters = ['A', 'B', 'C', 'D'];
        $correct_answer = $conn->real_escape_string($letters[$answer_index] ?? 'A');
        
        $sql = "INSERT INTO synchro_questions 
            (session_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_answer, question_type, time_limit) 
            VALUES ($session_id, $order, '$question_text', '$opt_a', '$opt_b', '$opt_c', '$opt_d', '$correct_answer', 'multiple_choice', 20)";
    } else {
        // Identification — answer is the answer text
        $correct_answer = $conn->real_escape_string($q['answer'] ?? '');
        $sql = "INSERT INTO synchro_questions 
            (session_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_answer, question_type, time_limit) 
            VALUES ($session_id, $order, '$question_text', '', '', '', '', '$correct_answer', 'identification', 30)";
    }
    
    $conn->query($sql);
}

// Initialize game state
$check = $conn->query("SELECT session_id FROM synchro_game_state WHERE session_id = $session_id");
if ($check->num_rows > 0) {
    $conn->query("UPDATE synchro_game_state SET current_question = 0, phase = 'lobby', question_started_at = NULL WHERE session_id = $session_id");
} else {
    $conn->query("INSERT INTO synchro_game_state (session_id, current_question, phase) VALUES ($session_id, 0, 'lobby')");
}

echo json_encode(["success" => true, "questions_inserted" => $order]);
$conn->close();
?>