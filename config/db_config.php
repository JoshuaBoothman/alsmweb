<?php
/*
* DATABASE CONFIGURATION
*
* This file defines the constants needed to connect to the database.
*/

// --- IMPORTANT ---
// It is strongly recommended to create a new MySQL user for this application
// instead of using the default 'root' user.
// You can do this from the main phpMyAdmin page under "User accounts".

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'alsm_user'); // <-- Replace with the username you create
define('DB_PASS', 'Password123'); // <-- Replace with your new password
define('DB_NAME', 'alsm_db'); // Using the database name you chose

// --- DATABASE CONNECTION (PDO) ---
try {
    // Create a new PDO instance (the connection object)
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    // Set PDO to throw exceptions on error for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e){
    // If the connection fails, stop the script and show a generic error.
    // In a real production environment, you would log this error instead of showing it.
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}
?>