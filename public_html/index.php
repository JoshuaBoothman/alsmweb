<?php 
// Define the page title for the <title> tag in the header
$page_title = 'ALSM - Home';

// Include the header template
require_once __DIR__ . '/../templates/header.php'; 
?>

<main class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h2>Welcome to Australian Large Scale Models!</h2>
            <p>Your hub for all event information, merchandise, and bookings. This is the main content area, and it takes up 8 of the 12 available columns on medium-sized screens and larger.</p>
        </div>
        <div class="col-md-4">
            <div id="news-box" class="p-3 bg-light rounded">
                <h4>Latest News</h4>
                <p id="news-content" class="mb-0">Some quick updates or announcements could go here.</p>
                <button id="update-news-btn" class="btn btn-sm btn-outline-primary mt-2">Update News</button>
            </div>
        </div>
    </div>
</main>

<?php 
// Include the footer template
require_once __DIR__ . '/../templates/footer.php'; 
?>
