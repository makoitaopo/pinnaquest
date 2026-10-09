<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if (!isset($_GET['session_id'])) {
    echo json_encode(["error" => "No session ID provided"]);
    exit();
}

$session_id = intval($_GET['session_id']);

// 1. Kunin ang settings ng session (Anong material? Ilang items? Anong difficulty?)
$session_stmt = $conn->prepare("SELECT material_id, item_count, difficulty FROM synchro_sessions WHERE id = ?");
$session_stmt->bind_param("i", $session_id);
$session_stmt->execute();
$session_data = $session_stmt->get_result()->fetch_assoc();

if (!$session_data) {
    echo json_encode(["error" => "Session not found"]);
    exit();
}

$material_id = $session_data['material_id'];
$limit = $session_data['item_count'];
$difficulty = $session_data['difficulty'];

// 2. Kunin ang mga questions na tugma sa settings
// Ginagamit natin ang RAND() para random ang pagkakasunod-sunod ng tanong bawat laro
$query = "SELECT id, question_text, option_a, option_b, option_c, option_d 
          FROM questions 
          WHERE material_id = ? AND difficulty = ? 
          ORDER BY RAND() 
          LIMIT ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("isi", $material_id, $difficulty, $limit);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

echo json_encode($questions);
$conn->close();
?>