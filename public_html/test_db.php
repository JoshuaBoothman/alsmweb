<?php

// This will force all errors to be displayed on the screen for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";
echo "<p>Attempting to include config file and connect...</p>";

// Include our configuration file.
// The script will attempt the PDO connection defined inside db_config.php
require_once __DIR__ . '/../config/db_config.php';

// If the script gets to this line without dying from an error in the file above,
// it means the connection was successful.
echo "<hr>";
echo "<h2 style='color:green;'>SUCCESS!</h2>";
echo "<p>The connection to the MySQL server was successful using the credentials provided.</p>";
echo "<p>Successfully connected to the database named <strong>" . DB_NAME . "</strong> as the user <strong>" . DB_USER . "</strong>.</p>";

?>