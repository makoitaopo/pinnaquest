<?php
// login.php - FIXED: Sets $_SESSION['user_name'] so Synchro-Quiz and other pages work
session_start();
include('db.php');

function redirectWithAuthNotice(string $message): void
{
    header('Location: loginpanel.php?notice=' . rawurlencode($message) . '&notice_type=error');
    exit();
}
 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email'], $_POST['password'], $_POST['role'])) {
        $email_raw = strtolower(trim($_POST['email']));
        $password  = $_POST['password'];
        $role_raw  = trim($_POST['role']);

        if (
            $email_raw === '' ||
            strlen($email_raw) > 254 ||
            filter_var($email_raw, FILTER_VALIDATE_EMAIL) === false
        ) {
            redirectWithAuthNotice('Please enter a valid email address.');
        }

        if (!in_array($role_raw, ['student', 'teacher'], true)) {
            redirectWithAuthNotice('Invalid account role.');
        }

        $email = mysqli_real_escape_string($conn, $email_raw);
        $role  = mysqli_real_escape_string($conn, $role_raw);
 
        $sql    = "SELECT * FROM users WHERE email='$email' AND role='$role'";
        $result = $conn->query($sql);
 
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
 
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = $user['role'];
                // FIX: Save full_name so all pages (synchro, dashboard, etc.) can read it
                $_SESSION['user_name'] = $user['full_name'];
 
                if ($user['role'] == 'student') {
                    header("Location: studentdashboard.php");
                } else {
                    header("Location: teacherdashboard.php");
                }
                exit();
            } else {
                redirectWithAuthNotice('Invalid password.');
            }
        } else {
            redirectWithAuthNotice('No account found with that email and role.');
        }
    } else {
        redirectWithAuthNotice('Please complete all required fields.');
    }
}
?>