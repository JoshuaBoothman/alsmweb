<?php

// FORCE ERROR REPORTING FOR DEBUGGING
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ALL PHP code for this page must go at the very top.
// session_start() must be the very first thing called on the page.
session_start();


// If the user is already logged in, redirect them to their profile page
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit(); // Stop script execution
}

// Include the database configuration file
require_once __DIR__ . '/../config/db_config.php';

$error_message = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Username and password are required.";
    } else {
        try {
            // Find the user in the database by their username
            $sql = "SELECT user_id, username, password_hash, role FROM Users WHERE username = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['username' => $username]);
            
            // fetch() returns the user record, or false if not found
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the user was found AND the password matches the hash
            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct! Start the user session.
                
                // Regenerate the session ID for security
                session_regenerate_id(true);

                // Store user data in the session array
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect the user to their profile page
                header("Location: profile.php");
                exit(); // Important to stop the script after a redirect

            } else {
                // If login fails (user not found or password incorrect)
                $error_message = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALSM - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
         </nav>
    <main class="container mt-4">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <h2>User Login</h2>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>