<?php 
require_once '../config/db_config.php';

$events = [];
$error_message = '';

try {
    // Select all events that are not soft-deleted and order them by start date
    $sql = "SELECT event_id, event_name, description, start_date, end_date, location 
            FROM events 
            WHERE event_IsDeleted = 0 
            ORDER BY start_date ASC";
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching events: " . $e->getMessage();
}

// Define the page title
$page_title = 'ALSM - Events';

// Include the header template
require_once __DIR__ . '/../templates/header.php'; 
?>

<main class="container mt-4">
    <div class="p-4 p-md-5 mb-4 rounded text-bg-dark">
        <div class="col-md-6 px-0">
            <h1 class="display-4 fst-italic">Upcoming Events</h1>
            <p class="lead my-3">Stay up to date with all the major events hosted by Australian Large Scale Models. See details for each event below.</p>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php elseif (empty($events)): ?>
        <div class="alert alert-info">
            <p>There are no upcoming events scheduled at this time. Please check back soon!</p>
        </div>
    <?php else: ?>
        <div class="row g-5">
            <div class="col-md-12">
                <?php foreach ($events as $event): ?>
                    <article class="blog-post mb-4">
                        <h2 class="blog-post-title"><?= htmlspecialchars($event['event_name']) ?></h2>
                        <p class="blog-post-meta">
                            <?= date('F j, Y', strtotime($event['start_date'])) ?> to <?= date('F j, Y', strtotime($event['end_date'])) ?>
                            at <strong><?= htmlspecialchars($event['location']) ?></strong>
                        </p>
                        <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                        <a href="event_detail.php?id=<?= $event['event_id'] ?>" class="btn btn-primary">View Event Details & Sub-Events</a>
                        <hr>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
// Include the footer template
require_once __DIR__ . '/../templates/footer.php'; 
?>
