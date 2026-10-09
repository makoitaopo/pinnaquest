<?php
// update_account.php — AJAX: update email and/or password
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$conn    = new mysqli("localhost", "root", "", "pinnaquest_db");
$user_id = intval($_SESSION['user_id']);

$action = trim($_POST['action'] ?? '');

// ── Change Email ───────────────────────────────────────────────────
if ($action === 'change_email') {
    $new_email = strtolower(trim($_POST['new_email'] ?? ''));
    $password  = $_POST['current_password'] ?? '';

    if (
        empty($new_email) ||
        strlen($new_email) > 254 ||
        !filter_var($new_email, FILTER_VALIDATE_EMAIL)
    ) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit();
    }

    // Verify current password
    $res  = $conn->query("SELECT password FROM users WHERE id = $user_id");
    $user = $res->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit();
    }

    // Check if email already taken
    $safe_email = $conn->real_escape_string($new_email);
    $check = $conn->query("SELECT id FROM users WHERE email='$safe_email' AND id != $user_id");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'That email is already in use']);
        exit();
    }

    $conn->query("UPDATE users SET email='$safe_email' WHERE id=$user_id");
    echo json_encode(['success' => true, 'message' => 'Email updated successfully']);
    exit();
}

// ── Change Password ────────────────────────────────────────────────
if ($action === 'change_password') {
    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new_pass) || empty($confirm)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit();
    }
    if (strlen($new_pass) < 8) {
        echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
        exit();
    }
    if ($new_pass !== $confirm) {
        echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
        exit();
    }

    $res  = $conn->query("SELECT password FROM users WHERE id = $user_id");
    $user = $res->fetch_assoc();
    if (!password_verify($current, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit();
    }

    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $safe   = $conn->real_escape_string($hashed);
    $conn->query("UPDATE users SET password='$safe' WHERE id=$user_id");
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>
