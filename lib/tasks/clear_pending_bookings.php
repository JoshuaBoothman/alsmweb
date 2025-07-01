<?php
// lib/tasks/clear_pending_bookings.php

// This script is intended to be run automatically by a server cron job.
// It cleans out old, abandoned bookings from the database.

// Set the timezone to ensure date calculations are correct.
date_default_timezone_set('Australia/Hobart');

// We need to go up two levels to find the config file from lib/tasks/
require_once __DIR__ . '/../../config/db_config.php';

echo "--- Starting Cleanup Script at " . date('Y-m-d H:i:s') . " ---\n";

// Define how old a 'Pending Basket' booking can be before it's considered expired.
// 60 minutes is a common choice.
$expiration_minutes = 60;

try {
    // The SQL query to find and delete expired bookings.
    // It looks for bookings with the 'Pending Basket' status that were created
    // earlier than the defined expiration time.
    $sql = "DELETE FROM bookings 
            WHERE status = 'Pending Basket' 
            AND booking_date < (NOW() - INTERVAL :minutes MINUTE)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':minutes' => $expiration_minutes]);

    // rowCount() gives us the number of rows that were affected (deleted).
    $deleted_rows = $stmt->rowCount();

    if ($deleted_rows > 0) {
        echo "Success: Cleared " . $deleted_rows . " expired booking(s) from the database.\n";
    } else {
        echo "Info: No expired bookings found to clear.\n";
    }

} catch (PDOException $e) {
    // If there's an error, log it. In a real server, this output would
    // typically be written to a log file.
    echo "ERROR: Could not run cleanup script. " . $e->getMessage() . "\n";
}

echo "--- Cleanup Script Finished ---\n";

?>
