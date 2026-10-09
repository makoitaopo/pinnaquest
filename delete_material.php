<?php
session_start();
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $id = intval($_GET['id']);
    $user_id = intval($_SESSION['user_id']);

    // 1. Kunin muna ang file path para mabura ang actual file sa folder
    //    (kailangan match ang user_id, para hindi mabura ng ibang account)
    $get_file = $conn->query("SELECT file_path FROM materials WHERE id = $id AND user_id = $user_id");
    $file_data = $get_file->fetch_assoc();

    if ($file_data) {
        // Burahin ang file sa server
        if (file_exists($file_data['file_path'])) {
            unlink($file_data['file_path']);
        }

        // 2. Burahin ang record sa database
        $conn->query("DELETE FROM materials WHERE id = $id AND user_id = $user_id");
    }
}

header("Location: materials.php");
exit();
?>