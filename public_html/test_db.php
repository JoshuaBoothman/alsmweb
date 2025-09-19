<?php
// A focused script to test the database connection using the project's config file.

echo "<h1>Definitive Database Connection Test</h1>";

// Define the path to the config file
$config_path = __DIR__ . '/../config/db_config.php';

echo "<p>Attempting to include configuration file from: <code>" . htmlspecialchars($config_path) . "</code></p>";

if (!file_exists($config_path)) {
    die("<p style='color:red; font-weight:bold;'>FATAL ERROR: The config file was not found at the specified path. Please check the path is correct.</p>");
}

// Include the configuration file
require_once $config_path;

echo "<h2 style='margin-top: 2rem;'>Configuration Loaded</h2>";
echo "<p>The following connection details were loaded from <code>db_config.php</code>:</p>";
echo "<ul>";
echo "<li><strong>DB_HOST:</strong> " . (defined('DB_HOST') ? htmlspecialchars(DB_HOST) : 'NOT DEFINED') . "</li>";
echo "<li><strong>DB_NAME:</strong> " . (defined('DB_NAME') ? htmlspecialchars(DB_NAME) : 'NOT DEFINED') . "</li>";
echo "<li><strong>DB_USER:</strong> " . (defined('DB_USER') ? htmlspecialchars(DB_USER) : 'NOT DEFINED') . "</li>";
echo "<li><strong>DB_PASS:</strong> " . (defined('DB_PASS') ? '<i>(is set)</i>' : 'NOT SET') . "</li>";
echo "</ul>";

echo "<hr style='margin: 2rem 0;'>";

echo "<h2>Connection Attempt</h2>";

try {
    // We will ignore the $pdo object from the config file and create a new one here
    // to ensure we capture the connection error within this script's try-catch block.
    $dsn = "mysql:host=" . DB_HOST . ";port=3307;dbname=" . DB_NAME;
    echo "<p>Using the following DSN string: <code>" . htmlspecialchars($dsn) . "</code></p>";

    $test_pdo = new PDO($dsn, DB_USER, DB_PASS);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p style='color:green; font-weight:bold; font-size: 1.2rem;'>✅ SUCCESS: A new PDO connection was established successfully!</p>";

    // Perform a simple query to be 100% sure
    $stmt = $test_pdo->query("SELECT 1");
    if ($stmt->fetchColumn() == 1) {
        echo "<p style='color:green; font-weight:bold;'>✅ QUERY OK: A simple query (SELECT 1) was executed successfully.</p>";
    } else {
         echo "<p style='color:orange; font-weight:bold;'>⚠️ QUERY WARNING: Connection was made, but a simple query failed.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold; font-size: 1.2rem;'>❌ FAILURE: The connection attempt failed.</p>";
    echo "<p>The database returned the following error:</p>";
    echo "<pre style='background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 1rem;'>";
    echo "<strong>Error Code:</strong> " . $e->getCode() . "\n";
    echo "<strong>Error Message:</strong> " . htmlspecialchars($e->getMessage());
    echo "</pre>";
}

?>