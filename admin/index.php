<?php
session_start();

// Check if user is logged in AND is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // If not an admin, or not logged in, redirect to the main login page
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// Admin content goes below this line
echo "<h1>Welcome to the Admin Dashboard, " . htmlspecialchars($_SESSION['username']) . "!</h1>";
?>