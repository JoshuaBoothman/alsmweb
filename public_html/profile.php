<?php
// Must be the very first thing on the page
session_start();

// 1. Page Protection: Check if the user is logged in.
// If the user_id session variable is not set, redirect them to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit(); // Stop the script from executing further
}

// 2. Fetch User Data
// Include the database configuration
require_once __DIR__ . '/../config/db_config.php';

$user = null; // Initialize user variable

try {
    // Prepare a SQL statement to get the user's details
    $sql = "SELECT username, email, first_name, last_name, created_at FROM Users WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    
    // Bind the user_id from the session to the placeholder
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    
    // Fetch the user data
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Optional: handle database errors, maybe redirect to an error page
    die("Error: Could not retrieve user data. " . $e->getMessage());
}

// A final check: if for some reason the user was not found in the DB, log them out.
if (!$user) {
    // This could happen if the user was deleted from the DB while their session was active.
    header("Location: logout.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALSM - Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">ALSM</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
        <p>This is your profile page. You are logged in.</p>

        <div class="card">
            <div class="card-header">
                Your Details
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></li>
                <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></li>
                <li class="list-group-item"><strong>Member Since:</strong> <?php echo date("d M Y", strtotime($user['created_at'])); ?></li>
            </ul>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>