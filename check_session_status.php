<?php
// check_session_status.php - BULLETPROOF VERSION
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["status" => "waiting"]);
    exit();
}

$session_id = intval($_GET['session_id'] ?? 0);
if (!$session_id) {
    echo json_encode(["status" => "waiting"]);
    exit();
}

// PRIMARY: Check synchro_sessions.status (always exists in your DB)
$result = $conn->query("SELECT status FROM synchro_sessions WHERE id = $session_id");
if ($result && $result->num_rows > 0) {
    $db_status = $result->fetch_assoc()['status'];
    $go_statuses = ['started', 'active', 'question', 'results', 'leaderboard', 'finished'];
    if (in_array($db_status, $go_statuses)) {
        echo json_encode(["status" => "started"]);
        $conn->close();
        exit();
    }
}

// SECONDARY: Check synchro_game_state if table exists (new tables from SQL update)
$tbl = $conn->query("SHOW TABLES LIKE 'synchro_game_state'");
if ($tbl && $tbl->num_rows > 0) {
    $sr = $conn->query("SELECT phase FROM synchro_game_state WHERE session_id = $session_id");
    if ($sr && $sr->num_rows > 0) {
        $phase = $sr->fetch_assoc()['phase'];
        if (in_array($phase, ['question', 'results', 'leaderboard', 'finished'])) {
            echo json_encode(["status" => "started"]);
            $conn->close();
            exit();
        }
    }
}

echo json_encode(["status" => "waiting"]);
$conn->close();
?>