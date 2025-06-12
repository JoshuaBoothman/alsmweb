<?php
// This block of PHP code will run on the server BEFORE the HTML is sent
$message = ''; // Variable to hold success or error messages

// Check if the form was submitted by checking the REQUEST_METHOD
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Step 1: Include our database connection file
    // The path needs to go up one level from public_html to find the config folder
    require_once __DIR__ . '/../config/db_config.php';

    // Step 2: Get the form data and perform server-side validation
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
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Step 3: Check if username or email already exists in the database
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

    // Step 4: If there are no errors, proceed with inserting the new user
    if (empty($errors)) {
        // Hash the password for security BEFORE storing it
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Prepare the SQL INSERT statement
            $sql = "INSERT INTO Users (username, email, password_hash) VALUES (:username, :email, :password_hash)";
            $stmt = $pdo->prepare($sql);
            
            // Bind the parameters and execute the statement
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $password_hash
            ]);

            $message = "<div class='alert alert-success'>Registration successful! You can now log in.</div>";

        } catch(PDOException $e){
            $message = "<div class='alert alert-danger'>Could not register user. " . $e->getMessage() . "</div>";
        }
    } else {
        // If there were errors, build an error message
        $message = "<div class='alert alert-danger'><ul>";
        foreach ($errors as $error) {
            $message .= "<li>$error</li>";
        }
        $message .= "</ul></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALSM - Register</title>
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
                <h2>Register a New Account</h2>
                
                <?php if(!empty($message)) echo $message; ?>

                <form action="register.php" method="POST">
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
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Register</button>
                </form>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>