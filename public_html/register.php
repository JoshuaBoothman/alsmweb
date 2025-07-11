<?php
// public_html/register.php

session_start();

// If the user is already logged in, redirect them away from the registration page.
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

// Include necessary files
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../lib/functions/security_helpers.php';

$error_message = '';
$success_message = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token
    validate_csrf_token();

    // Get the form data and perform server-side validation
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];

    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $sql = "SELECT COUNT(*) FROM Users WHERE username = :username OR email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Username or email already taken.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // If no errors, proceed with inserting the new user
    if (empty($errors)) {
        // Hash the password for security
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO Users (username, email, password_hash) VALUES (:username, :email, :password_hash)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $password_hash
            ]);

            $success_message = "Registration successful! You can now <a href='login.php' class='alert-link'>log in</a>.";

        } catch(PDOException $e){
            $error_message = "Could not register user. " . $e->getMessage();
        }
    } else {
        // If there were errors, build an error message
        $error_message = implode('<br>', $errors);
    }
}

// Generate a CSRF token for the form
generate_csrf_token();

// --- PAGE SETUP ---
$page_title = 'ALSM - Register';
require_once __DIR__ . '/../templates/header.php'; // Use the standard header
?>

<main class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <h2 class="mb-4">Register a New Account</h2>
            
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>

            <?php if (empty($success_message)): // Only show the form if registration was not successful ?>
            <form action="register.php" method="POST">
                <!-- Add the hidden CSRF token field -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Must be at least 8 characters long.</div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
                <a href="login.php" class="btn btn-link">Already have an account? Login</a>
            </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; // Use the standard footer
?>
