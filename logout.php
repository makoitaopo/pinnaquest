<?php
// logout.php — Destroys the session and redirects to the login page
session_start();
session_unset();
session_destroy();
header("Location: loginpanel.php");
exit();
?>