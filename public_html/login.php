<?php
// public_html/login.php

// ALL PHP code for this page must go at the very top.
session_start();

// If the user is already logged in, redirect them to their profile page
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit(); // Stop script execution
}

// Include the database configuration file and security helpers
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../lib/functions/security_helpers.php'; // Include CSRF helpers

$error_message = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token
    validate_csrf_token();

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
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the user was found AND the password matches the hash
            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct! Start the user session.
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Check for a redirect URL from a previous attempt to access a protected page
                $redirect_url = $_SESSION['redirect_url'] ?? 'profile.php';
                unset($_SESSION['redirect_url']); // Clear it after use

                header("Location: " . $redirect_url);
                exit();

            } else {
                $error_message = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Generate a CSRF token for the form
generate_csrf_token();

// --- PAGE SETUP ---
$page_title = 'ALSM - Login';
require_once __DIR__ . '/../templates/header.php'; // Use the standard header
?>

<main class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <h2 class="mb-4">User Login</h2>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): // Display error messages from other pages ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <!-- Add the hidden CSRF token field -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
                <a href="register.php" class="btn btn-link">Don't have an account? Register</a>
            </form>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; // Use the standard footer
?>
