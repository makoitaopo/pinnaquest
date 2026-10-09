<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
require_once __DIR__ . '/db.php';

function sessionHistoryResponse(bool $success, string $error = ''): void
{
    echo json_encode(['success' => $success, 'error' => $error]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    sessionHistoryResponse(false, 'Not signed in');
}
if (($_SESSION['role'] ?? '') !== 'teacher') {
    sessionHistoryResponse(false, 'Teacher account required');
}

$sessionId = intval($_POST['session_id'] ?? $_POST['id'] ?? 0);
if (!$sessionId) {
    sessionHistoryResponse(false, 'Invalid session');
}

// The teacher history page currently exposes the teacher's session list. Delete
// the complete session tree so no orphaned rankings or answers remain.
$exists = $conn->query("SELECT id FROM synchro_sessions WHERE id=$sessionId LIMIT 1");
if (!$exists || $exists->num_rows === 0) {
    sessionHistoryResponse(false, 'This session history no longer exists.');
}

$queries = [
    "DELETE FROM synchro_answers WHERE session_id=$sessionId",
    "DELETE FROM synchro_scores WHERE session_id=$sessionId",
    "DELETE FROM synchro_participants WHERE session_id=$sessionId",
    "DELETE FROM synchro_questions WHERE session_id=$sessionId",
    "DELETE FROM synchro_game_state WHERE session_id=$sessionId",
    "DELETE FROM synchro_sessions WHERE id=$sessionId",
];
foreach ($queries as $query) {
    if (!$conn->query($query)) {
        error_log('Session history delete failed: ' . $conn->error);
        sessionHistoryResponse(false, 'The session history could not be deleted.');
    }
}

sessionHistoryResponse(true);