<?php
$servername = "localhost";  // XAMPP's default MySQL server
$username = "root";         // XAMPP's default MySQL username
$password = "";             // Default XAMPP MySQL password (empty)
$dbname = "pinnaquest_db";  // The name of your database

// Establish the database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);  // If there's an error, show it
}
?>