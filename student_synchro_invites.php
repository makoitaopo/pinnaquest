<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
};

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    $respond(401, ['success' => false, 'message' => 'Student sign-in is required.']);
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

$studentId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $expireStmt = $conn->prepare(
        "UPDATE synchro_invitations i
         INNER JOIN synchro_sessions s ON s.id = i.session_id
         SET i.status = 'expired', i.responded_at = NOW()
         WHERE i.student_user_id = ? AND i.status IN ('pending', 'accepted')
           AND s.status <> 'waiting'"
    );
    if ($expireStmt) {
        $expireStmt->bind_param('i', $studentId);
        $expireStmt->execute();
        $expireStmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT i.id, i.status, i.created_at, s.title,
                COALESCE(NULLIF(t.display_name, ''), NULLIF(t.full_name, ''), 'Your teacher') AS teacher_name
         FROM synchro_invitations i
         INNER JOIN synchro_sessions s ON s.id = i.session_id
         LEFT JOIN users t ON t.id = s.teacher_user_id
         WHERE i.student_user_id = ? AND i.status IN ('pending', 'accepted')
           AND s.status = 'waiting'
           AND NOT EXISTS (
               SELECT 1 FROM synchro_participants p
               WHERE p.session_id = i.session_id AND p.user_id = i.student_user_id
           )
         ORDER BY i.created_at DESC
         LIMIT 30"
    );
    if (!$stmt) {
        $conn->close();
        $respond(500, ['success' => false, 'message' => 'Could not load invitations.']);
    }
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $invitations = [];
    while ($row = $result->fetch_assoc()) {
        $invitations[] = [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'title' => (string) $row['title'],
            'teacher_name' => (string) $row['teacher_name'],
            'created_at' => (string) $row['created_at'],
        ];
    }
    $stmt->close();
    $conn->close();
    $respond(200, ['success' => true, 'invitations' => $invitations]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    $respond(405, ['success' => false, 'message' => 'Use GET or POST.']);
}

$inviteId = (int) ($_POST['invite_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
if ($inviteId < 1 || !in_array($action, ['accept', 'decline'], true)) {
    $conn->close();
    $respond(400, ['success' => false, 'message' => 'Invalid invitation response.']);
}

$conn->begin_transaction();
$stmt = $conn->prepare(
    "SELECT i.session_id, i.status AS invite_status,
            s.status AS session_status, s.room_code
     FROM synchro_invitations i
     INNER JOIN synchro_sessions s ON s.id = i.session_id
     WHERE i.id = ? AND i.student_user_id = ?
     LIMIT 1
     FOR UPDATE"
);
if (!$stmt) {
    $conn->rollback();
    $conn->close();
    $respond(500, ['success' => false, 'message' => 'Could not check the invitation.']);
}
$stmt->bind_param('ii', $inviteId, $studentId);
$stmt->execute();
$invitation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invitation || !in_array($invitation['invite_status'], ['pending', 'accepted'], true)) {
    $conn->rollback();
    $conn->close();
    $respond(404, ['success' => false, 'message' => 'This invitation is no longer available.']);
}

if ($invitation['session_status'] !== 'waiting') {
    $expire = $conn->prepare(
        "UPDATE synchro_invitations SET status = 'expired', responded_at = NOW()
         WHERE id = ? AND student_user_id = ? AND status IN ('pending', 'accepted')"
    );
    if ($expire) {
        $expire->bind_param('ii', $inviteId, $studentId);
        $expire->execute();
        $expire->close();
    }
    $conn->commit();
    $conn->close();
    $respond(409, ['success' => false, 'message' => 'The quiz has already started.']);
}

$newStatus = $action === 'accept' ? 'accepted' : 'declined';
$alreadyAccepted = $invitation['invite_status'] === 'accepted' && $action === 'accept';
if (!$alreadyAccepted) {
    $update = $conn->prepare(
        "UPDATE synchro_invitations
         SET status = ?, responded_at = NOW()
         WHERE id = ? AND student_user_id = ? AND status IN ('pending', 'accepted')"
    );
    if (!$update) {
        $conn->rollback();
        $conn->close();
        $respond(500, ['success' => false, 'message' => 'Could not save your response.']);
    }
    $update->bind_param('sii', $newStatus, $inviteId, $studentId);
    $ok = $update->execute() && $update->affected_rows === 1;
    $update->close();
    if (!$ok) {
        $conn->rollback();
        $conn->close();
        $respond(409, ['success' => false, 'message' => 'This invitation has already been answered.']);
    }
}
$conn->commit();

if ($action === 'accept') {
    $_SESSION['current_session_id'] = (int) $invitation['session_id'];
    $_SESSION['joined_room_code'] = (string) $invitation['room_code'];
}

$conn->close();
$respond(200, ['success' => true, 'accepted' => $action === 'accept']);
