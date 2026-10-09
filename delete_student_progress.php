<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/synchro_identity.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not signed in']);
    exit();
}

ensureSynchroIdentitySchema($conn);
$nickname = trim($_POST['nickname'] ?? '');
$userId = intval($_POST['user_id'] ?? 0);
if ($nickname === '') {
    echo json_encode(['success' => false, 'error' => 'Missing student']);
    exit();
}

$nicknameEsc = $conn->real_escape_string($nickname);
$scoreWhere = $userId > 0 ? "user_id=$userId" : "nickname='$nicknameEsc'";
$sessionRes = $conn->query("SELECT DISTINCT session_id FROM synchro_scores WHERE $scoreWhere");
$sessionIds = [];
$nicknames = [$nickname];
if ($userId > 0) {
    $namesRes = $conn->query("SELECT DISTINCT nickname FROM synchro_scores WHERE user_id=$userId");
    $nicknames = [];
    if ($namesRes) {
        while ($nameRow = $namesRes->fetch_assoc()) $nicknames[] = $nameRow['nickname'];
    }
}
$nicknameValues = array_map(
    fn($name) => "'" . $conn->real_escape_string($name) . "'",
    array_values(array_unique(array_filter($nicknames)))
);
if ($sessionRes) {
    while ($row = $sessionRes->fetch_assoc()) $sessionIds[] = intval($row['session_id']);
}

// Progress is teacher-facing and the existing report is not scoped to a
// teacher. Remove only this student's participation records, not the session.
if ($nicknameValues) {
    $nicknameList = implode(',', $nicknameValues);
    $conn->query("DELETE FROM synchro_answers WHERE participant_nickname IN ($nicknameList)");
    $conn->query("DELETE FROM synchro_participants WHERE nickname IN ($nicknameList)");
}
$conn->query("DELETE FROM synchro_scores WHERE $scoreWhere");

echo json_encode(['success' => true, 'deleted_sessions' => count($sessionIds)]);