<?php
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/synchro_identity.php';

function historyResponse(bool $success, string $error = ''): void
{
    echo json_encode([
        'success' => $success,
        'error' => $error,
    ]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    historyResponse(false, 'Not signed in');
}

ensureSynchroIdentitySchema($conn);
$userId = intval($_SESSION['user_id']);
$type = $_POST['type'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$id || !in_array($type, ['solo', 'synchro'], true)) {
    historyResponse(false, 'Invalid history item');
}

$deleted = false;
if ($type === 'solo') {
    $owned = $conn->query("SELECT id FROM solo_quiz_results WHERE id=$id AND user_id=$userId LIMIT 1");
    if ($owned && $owned->num_rows > 0) {
        $conn->query("DELETE FROM solo_quiz_answers WHERE result_id=$id");
        $awardTable = $conn->query("SHOW TABLES LIKE 'quiz_xp_awards'");
        if ($awardTable && $awardTable->num_rows > 0) {
            $conn->query("DELETE FROM quiz_xp_awards WHERE attempt_id=$id");
        }
        $deleted = (bool) $conn->query("DELETE FROM solo_quiz_results WHERE id=$id AND user_id=$userId");
    }
} else {
    $owned = $conn->query("SELECT session_id, nickname FROM synchro_scores WHERE id=$id AND user_id=$userId LIMIT 1");
    if ($owned && ($row = $owned->fetch_assoc())) {
        $sessionId = intval($row['session_id']);
        $nickname = $conn->real_escape_string($row['nickname']);
        $conn->query("DELETE FROM synchro_answers WHERE session_id=$sessionId AND participant_nickname='$nickname'");
        $deleted = (bool) $conn->query("DELETE FROM synchro_scores WHERE id=$id AND user_id=$userId");
        if (!$deleted) {
            error_log('Quiz history delete failed: ' . $conn->error);
            historyResponse(false, 'The Synchro quiz history could not be deleted.');
        }
        $conn->query("DELETE FROM synchro_participants WHERE session_id=$sessionId AND user_id=$userId");
    }
}

if (!$deleted) {
    historyResponse(false, 'This quiz history was not found or no longer belongs to this account.');
}
historyResponse(true);