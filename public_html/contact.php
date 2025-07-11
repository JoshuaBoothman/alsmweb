<?php
// public_html/contact.php

require_once '../lib/functions/security_helpers.php';
session_start();

// Generate a token for the form to use
generate_csrf_token();

// Retrieve any messages and form input from the session after a redirect, then clear them.
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
$form_input = $_SESSION['form_input'] ?? [];
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['form_input']);

// Define the page title for the <title> tag in the header
$page_title = 'ALSM - Contact Us';

// Include the header template
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Contact Us</h2>
            <p>Have a question? Fill out the form below and we'll get back to you.</p>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            
            <?php // Hide the form after a successful submission ?>
            <?php if (!$success_message): ?>
            <form id="contactForm" action="process_contact.php" method="POST">
                <!-- Add the hidden CSRF token field -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="mb-3">
                    <label for="contactName" class="form-label">Your Name</label>
                    <input type="text" class="form-control" id="contactName" name="contactName" value="<?= htmlspecialchars($form_input['contactName'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="contactEmail" class="form-label">Your Email</label>
                    <input type="email" class="form-control" id="contactEmail" name="contactEmail" value="<?= htmlspecialchars($form_input['contactEmail'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="contactMessage" class="form-label">Message</label>
                    <textarea class="form-control" id="contactMessage" name="contactMessage" rows="5" required><?= htmlspecialchars($form_input['contactMessage'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// Include the footer template
require_once __DIR__ . '/../templates/footer.php';
?>
