<?php
// process_join.php - FIXED
// Only validates the room code and sets session vars.
// Participant nickname/avatar is chosen INSIDE student_waiting_room.php
// and saved via add_participant.php — NOT here.
session_start();
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_code = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['room_code'])));
 
    // Students may only enter a room before the teacher starts the quiz.
    $check_room = $conn->query(
        "SELECT id, status FROM synchro_sessions
         WHERE room_code = '$room_code'
         LIMIT 1"
    );
    $room = ($check_room && $check_room->num_rows > 0)
        ? $check_room->fetch_assoc()
        : null;
 
    if ($room && $room['status'] === 'waiting') {
        $session_id = $room['id'];
 
        // Save room info to session — nickname/avatar set later in waiting room
        $_SESSION['current_session_id'] = $session_id;
        $_SESSION['joined_room_code']   = $room_code;
 
        header("Location: student_waiting_room.php");
        exit();
    } else {
        $join_error = 'Invalid room code. Please check the code and try again.';
        if ($room && $room['status'] !== 'waiting') {
            $join_error = 'This quiz has already started. You can no longer join.';
        }
        $join_error_js = json_encode($join_error);
        echo "<script src=\"notification.js\"></script><script>
            pqNotifyThen($join_error_js, 'synchro_portal.php', 'error');
        </script>";
    }
}
$conn->close();
?>
 