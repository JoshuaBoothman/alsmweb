<?php

// Force PHP to display all errors for this script
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Deep Environment Diagnostic</h1>";

// --- Credentials from our config file ---
$db_host = 'localhost';
$db_user = 'alsm_user';
$db_pass = 'Password123'; // The simple password we reset to

echo "<p><strong>Attempting to connect to MySQL server with these credentials:</strong><br>";
echo "Host: " . $db_host . "<br>";
echo "User: " . $db_user . "<br>";
echo "Password: " . $db_pass . "</p><hr>";


try {
    // --- Step 1: Connect to the MySQL SERVER itself ---
    $pdo = new PDO("mysql:host=" . $db_host, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green; font-weight:bold;'>Step 1 SUCCESS: Connected to the MySQL server process.</p>";

    // --- Step 2: Ask the server to list all databases it knows about ---
    echo "<p><strong>Step 2: Asking the server to list all available databases...</strong></p>";
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Databases that the user '" . $db_user . "' can see:</h3>";
    echo "<pre>";
    print_r($databases);
    echo "</pre>";
    echo "<hr>";

} catch(PDOException $e) {
    // This will catch any connection or query errors
    echo "<p style='color:red; font-weight:bold;'>DIAGNOSTIC FAILED. The script could not complete.</p>";
    echo "<p>The error message is: <strong>" . $e->getMessage() . "</strong></p>";
    echo "<hr>";
}

echo "<h2>PHP Configuration Info</h2>";
phpinfo();

?>