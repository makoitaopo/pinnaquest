<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed"]));
}

// Kunin ang session_id mula sa AJAX request
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id > 0) {
    /** * UPDATE: Idinagdag ang 'avatar_key' sa SELECT query.
     * Ito ang magpapasa ng data sa Teacher Lobby para lumitaw ang avatar.
     */
    $result = $conn->query("SELECT nickname, avatar_key FROM synchro_participants WHERE session_id = $session_id ORDER BY joined_at DESC");
    
    $participants = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $participants[] = $row;
        }
    }
    
    // I-return bilang JSON format
    echo json_encode($participants);
} else {
    echo json_encode([]);
}

$conn->close();
?>