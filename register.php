<?php
include('db.php');

function redirectWithAuthNotice(string $message): void
{
    header('Location: loginpanel.php?notice=' . rawurlencode($message) . '&notice_type=error');
    exit();
}

/**
 * Password policy:
 * - at least 8 characters
 * - at least 1 uppercase letter
 * - at least 1 number
 * - at least 1 special character
 */
function isPasswordValid(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name_raw = trim($_POST['first_name'] ?? '');
    $last_name_raw  = trim($_POST['last_name'] ?? '');
    $email_raw      = strtolower(trim($_POST['email'] ?? ''));
    $password       = $_POST['password'] ?? '';
    $role           = trim($_POST['role'] ?? '');
    $privacy_consent = $_POST['privacy_consent'] ?? '';

    if ($first_name_raw === '' || $last_name_raw === '') {
        redirectWithAuthNotice('Please enter both your first name and last name.');
    }

    if (mb_strlen($first_name_raw) > 100 || mb_strlen($last_name_raw) > 100) {
        redirectWithAuthNotice('First name and last name must each be under 100 characters.');
    }

    if (
        $email_raw === '' ||
        strlen($email_raw) > 254 ||
        filter_var($email_raw, FILTER_VALIDATE_EMAIL) === false
    ) {
        redirectWithAuthNotice('Please enter a valid email address.');
    }

    if (!in_array($role, ['student', 'teacher'], true)) {
        redirectWithAuthNotice('Invalid account role.');
    }

    // ── Data Privacy Act consent check (RA 10173) ──────────────────
    if ($privacy_consent !== 'on') {
        redirectWithAuthNotice('You must agree to the Data Privacy Notice before creating an account.');
    }

    // ── Password strength check ─────────────────────────────────────
    if (!isPasswordValid($password)) {
        redirectWithAuthNotice('Password must be at least 8 characters long and include at least 1 uppercase letter, 1 number, and 1 special character.');
    }

    // Do not allow two accounts to share the same login email.
    $email_check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $email_check->bind_param('s', $email_raw);
    $email_check->execute();
    $email_check->store_result();
    if ($email_check->num_rows > 0) {
        $email_check->close();
        redirectWithAuthNotice('That email address is already registered.');
    }
    $email_check->close();

    $full_name_raw = trim($first_name_raw . ' ' . $last_name_raw);

    $first_name = mysqli_real_escape_string($conn, $first_name_raw);
    $last_name  = mysqli_real_escape_string($conn, $last_name_raw);
    $full_name  = mysqli_real_escape_string($conn, $full_name_raw);
    $email      = mysqli_real_escape_string($conn, $email_raw);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $consent_timestamp = date('Y-m-d H:i:s');

    $sql = "INSERT INTO users (full_name, first_name, last_name, email, password, role, privacy_consent_at) 
            VALUES ('$full_name', '$first_name', '$last_name', '$email', '$hashed_password', '$role', '$consent_timestamp')";

    if ($conn->query($sql) === TRUE) {
        header("Location: loginpanel.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>