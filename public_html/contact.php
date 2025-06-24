<?php 
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
            
            <form id="contactForm" novalidate>
                <div class="mb-3">
                    <label for="contactName" class="form-label">Your Name</label>
                    <input type="text" class="form-control" id="contactName" required>
                </div>
                <div class="mb-3">
                    <label for="contactEmail" class="form-label">Your Email</label>
                    <input type="email" class="form-control" id="contactEmail" required>
                </div>
                <div class="mb-3">
                    <label for="contactMessage" class="form-label">Message</label>
                    <textarea class="form-control" id="contactMessage" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
</main>

<?php 
// Include the footer template
require_once __DIR__ . '/../templates/footer.php'; 
?>
