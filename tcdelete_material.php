<?php
// teacher_delete_material.php
session_start();
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $id = intval($_GET['id']);
    $user_id = intval($_SESSION['user_id']);

    // 1. Kunin ang path mula sa teacher_materials table (dapat match ang user_id)
    $get_file = $conn->query("SELECT file_path FROM teacher_materials WHERE id = $id AND user_id = $user_id");
    $file_data = $get_file->fetch_assoc();

    if ($file_data) {
        // Burahin ang actual file sa server folder
        if (file_exists($file_data['file_path'])) {
            unlink($file_data['file_path']);
        }

        // 2. Burahin ang record sa teacher_materials table
        $conn->query("DELETE FROM teacher_materials WHERE id = $id AND user_id = $user_id");
    }
}

header("Location: teacher_materials.php");
exit();
?>