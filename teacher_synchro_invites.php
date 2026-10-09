<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
};

if (($_SESSION['role'] ?? '') !== 'teacher' || empty($_SESSION['user_id'])) {
    $respond(401, ['success' => false, 'message' => 'Teacher sign-in is required.']);
}

require_once __DIR__ . '/synchro_invite_support.php';

$conn = new mysqli('localhost', 'root', '', 'pinnaquest_db');
if ($conn->connect_error) {
    $respond(503, ['success' => false, 'message' => 'Could not connect to the database.']);
}
$conn->set_charset('utf8mb4');

try {
    ensureSynchroInviteSchema($conn);
} catch (Throwable $error) {
    error_log('Synchro invite schema setup failed: ' . $error->getMessage());
    $conn->close();
    $respond(500, ['success' => false, 'message' => 'Invitations are temporarily unavailable.']);
}

$teacherId = (int) $_SESSION['user_id'];
$sessionId = (int) ($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
if ($sessionId < 1) {
    $conn->close();
    $respond(400, ['success' => false, 'message' => 'A valid session is required.']);
}

$sessionStmt = $conn->prepare(
    "SELECT status FROM synchro_sessions
     WHERE id = ? AND teacher_user_id = ?
     LIMIT 1"
);
if (!$sessionStmt) {
    $conn->close();
    $respond(500, ['success' => false, 'message' => 'Could not check session access.']);
}
$sessionStmt->bind_param('ii', $sessionId, $teacherId);
$sessionStmt->execute();
$session = $sessionStmt->get_result()->fetch_assoc();
$sessionStmt->close();

if (!$session) {
    $conn->close();
    $respond(403, ['success' => false, 'message' => 'You cannot invite students to this session.']);
}
if ($session['status'] !== 'waiting') {
    $conn->close();
    $respond(409, ['success' => false, 'message' => 'Invites are only available before the quiz starts.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
    $pattern = '%' . $search . '%';
    $stmt = $conn->prepare(
        "SELECT u.id,
                COALESCE(NULLIF(u.display_name, ''), NULLIF(u.full_name, ''), u.email) AS student_name
         FROM users u
         WHERE u.role = 'student'
           AND (? = '' OR u.full_name LIKE ? OR u.first_name LIKE ?
                OR u.last_name LIKE ? OR u.email LIKE ?)
           AND NOT EXISTS (
               SELECT 1 FROM synchro_participants p
               WHERE p.session_id = ? AND p.user_id = u.id
           )
           AND NOT EXISTS (
               SELECT 1 FROM synchro_invitations i
               WHERE i.session_id = ? AND i.student_user_id = u.id
                 AND i.status IN ('pending', 'accepted')
           )
         ORDER BY student_name ASC
         LIMIT 50"
    );
    if (!$stmt) {
        $conn->close();
        $respond(500, ['success' => false, 'message' => 'Could not load student accounts.']);
    }
    $stmt->bind_param(
        'sssssii',
        $search,
        $pattern,
        $pattern,
        $pattern,
        $pattern,
        $sessionId,
        $sessionId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['student_name'],
        ];
    }
    $stmt->close();
    $conn->close();
    $respond(200, ['success' => true, 'students' => $students]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    $respond(405, ['success' => false, 'message' => 'Use GET or POST.']);
}

$requestedIds = json_decode((string) ($_POST['student_ids'] ?? ''), true);
if (!is_array($requestedIds)) {
    $conn->close();
    $respond(400, ['success' => false, 'message' => 'Choose at least one student.']);
}
$studentIds = array_values(array_unique(array_filter(
    array_map('intval', $requestedIds),
    static fn (int $id): bool => $id > 0
)));
$studentIds = array_slice($studentIds, 0, 100);
if (!$studentIds) {
    $conn->close();
    $respond(400, ['success' => false, 'message' => 'Choose at least one student.']);
}

$insertStmt = $conn->prepare(
    "INSERT INTO synchro_invitations (session_id, student_user_id, status)
     SELECT ?, u.id, 'pending'
     FROM users u
     WHERE u.id = ? AND u.role = 'student'
       AND NOT EXISTS (
           SELECT 1 FROM synchro_participants p
           WHERE p.session_id = ? AND p.user_id = u.id
       )
     ON DUPLICATE KEY UPDATE
       created_at = IF(status IN ('declined', 'expired'), CURRENT_TIMESTAMP, created_at),
       responded_at = IF(status IN ('declined', 'expired'), NULL, responded_at),
       status = IF(status IN ('declined', 'expired'), 'pending', status)"
);
if (!$insertStmt) {
    $conn->close();
    $respond(500, ['success' => false, 'message' => 'Could not send the invitations.']);
}

$sentCount = 0;
$conn->begin_transaction();
foreach ($studentIds as $studentId) {
    $insertStmt->bind_param('iii', $sessionId, $studentId, $sessionId);
    if (!$insertStmt->execute()) {
        $conn->rollback();
        $insertStmt->close();
        $conn->close();
        $respond(500, ['success' => false, 'message' => 'Could not send all invitations.']);
    }
    if ($insertStmt->affected_rows > 0) {
        $sentCount++;
    }
}
$conn->commit();
$insertStmt->close();
$conn->close();

$respond(200, [
    'success' => true,
    'sent_count' => $sentCount,
    'message' => $sentCount > 0
        ? "$sentCount invitation(s) sent."
        : 'The selected students already have an invite or have joined.',
]);
