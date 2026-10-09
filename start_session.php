<?php
// start_session.php - FIXED VERSION
// Called by teacher_lobby.php when teacher clicks "Start Quest"
// CRITICAL: Must set status = 'started' so student waiting room detects it

header('Content-Type: application/json');
require_once __DIR__ . '/synchro_identity.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB error"]);
    exit();
}
ensureSynchroIdentitySchema($conn);

if (isset($_POST['session_id'])) {
    $session_id = intval($_POST['session_id']);

    // CRITICAL FIX: Set status to 'started' - this is what student_waiting_room.php polls for
    $conn->query("UPDATE synchro_sessions SET status = 'started' WHERE id = $session_id");

    if ($conn->affected_rows >= 0) {
        // Also init game state if the new tables exist
        $tbl = $conn->query("SHOW TABLES LIKE 'synchro_game_state'");
        if ($tbl && $tbl->num_rows > 0) {
            $check = $conn->query("SELECT session_id FROM synchro_game_state WHERE session_id = $session_id");
            if ($check && $check->num_rows > 0) {
                $conn->query("UPDATE synchro_game_state SET phase = 'lobby' WHERE session_id = $session_id");
            } else {
                $conn->query("INSERT INTO synchro_game_state (session_id, current_question, phase) VALUES ($session_id, 0, 'lobby')");
            }
        }

        // Also init scores table if it exists
        $tbl2 = $conn->query("SHOW TABLES LIKE 'synchro_scores'");
        if ($tbl2 && $tbl2->num_rows > 0) {
            $parts = $conn->query("SELECT nickname, avatar_key, user_id FROM synchro_participants WHERE session_id = $session_id");
            if ($parts) {
                while ($p = $parts->fetch_assoc()) {
                    $nick = $conn->real_escape_string($p['nickname']);
                    $av   = $conn->real_escape_string($p['avatar_key'] ?? '');
                    $uid  = intval($p['user_id'] ?? 0);
                    $conn->query("INSERT IGNORE INTO synchro_scores (session_id, nickname, avatar_key, user_id, total_score, correct_answers, streak)
                                  VALUES ($session_id, '$nick', '$av', " . ($uid ?: 'NULL') . ", 0, 0, 0)");
                    if ($uid) {
                        $conn->query("UPDATE synchro_scores SET user_id=$uid WHERE session_id=$session_id AND nickname='$nick'");
                    }
                }
            }
        }

        echo json_encode(["success" => true, "session_id" => $session_id]);
    } else {
        echo json_encode(["success" => false, "error" => "Update failed: " . $conn->error]);
    }
}
$conn->close();
?>