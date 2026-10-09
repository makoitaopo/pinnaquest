<?php
// add_participant.php - FIXED
// Called by student_waiting_room.php after student picks nickname + avatar.
// Saves to DB and — critically — updates SESSION with game nickname and avatar
// so student_quiz_game.php can read them correctly.
 
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/synchro_identity.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
 
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit();
}
ensureSynchroIdentitySchema($conn);
$user_id = intval($_SESSION['user_id'] ?? 0);
 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['session_id'])) {
    $session_id = intval($_POST['session_id']);
    $nickname   = trim($conn->real_escape_string($_POST['nickname']));
    $avatar_key = $conn->real_escape_string($_POST['avatar_key']);
 
    if (empty($nickname) || empty($avatar_key) || !$session_id) {
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit();
    }

    // Lock the session row while checking and saving. A student may have
    // opened the setup screen while the room was waiting, then clicked READY
    // UP after the teacher started the quiz.
    $conn->begin_transaction();
    $session_check = $conn->query(
        "SELECT status FROM synchro_sessions
         WHERE id = $session_id
         LIMIT 1
         FOR UPDATE"
    );
    $session_row = $session_check ? $session_check->fetch_assoc() : null;
    if (!$session_row) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Quiz session not found.']);
        exit();
    }
    if ($session_row['status'] !== 'waiting') {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'error' => 'This quiz has already started. You can no longer join.'
        ]);
        exit();
    }
 
    // ── Update SESSION ───────────────────────────────────────────────────────
    // FIX: use 'user_avatar' (not 'selected_avatar_key') — student_quiz_game.php
    //      reads $_SESSION['user_avatar'], so the names must match.
    $_SESSION['user_name']   = $nickname;
    $_SESSION['user_avatar'] = $avatar_key;
 
    // ── Upsert participant row ───────────────────────────────────────────────
    $check = $conn->query(
        "SELECT id FROM synchro_participants 
         WHERE session_id = '$session_id' AND nickname = '$nickname'"
    );
 
    if ($check && $check->num_rows > 0) {
        // Already exists → update avatar in case they changed it
        $conn->query(
            "UPDATE synchro_participants
             SET avatar_key = '$avatar_key', user_id = " . ($user_id ?: 'NULL') . "
             WHERE session_id = '$session_id' AND nickname = '$nickname'"
        );
    } else {
        $conn->query(
            "INSERT INTO synchro_participants (session_id, nickname, avatar_key, user_id)
             VALUES ('$session_id', '$nickname', '$avatar_key', " . ($user_id ?: 'NULL') . ")"
        );
    }

    $conn->commit();
 
    echo json_encode(['success' => true, 'nickname' => $nickname, 'avatar' => $avatar_key]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
 
$conn->close();
?>