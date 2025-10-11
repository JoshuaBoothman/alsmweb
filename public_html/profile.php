<?php
// public_html/profile.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../config/db_config.php';

$user = null;
$bookings = [];
$event_registrations = [];
$error_message = '';

try {
    // Fetch the user's details
    $sql_user = "SELECT username, email, first_name, last_name, created_at FROM Users WHERE user_id = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: logout.php");
        exit();
    }

    // Fetch confirmed campsite bookings
    $sql_bookings = "SELECT b.booking_id, b.check_in_date, b.check_out_date, b.status, cs.name AS campsite_name, cg.name AS campground_name
                    FROM bookings b
                    JOIN campsites cs ON b.campsite_id = cs.campsite_id
                    JOIN campgrounds cg ON cs.campground_id = cg.campground_id
                    WHERE b.user_id = :user_id AND b.status != 'Pending Basket'
                    ORDER BY b.check_in_date DESC";
    $stmt_bookings = $pdo->prepare($sql_bookings);
    $stmt_bookings->execute(['user_id' => $_SESSION['user_id']]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

    // Fetch event registrations
    $sql_event_regos = "SELECT er.registration_id, er.registration_date, er.total_cost, er.payment_status, e.event_name
                        FROM eventregistrations er
                        JOIN events e ON er.event_id = e.event_id
                        WHERE er.user_id = :user_id
                        ORDER BY er.registration_date DESC";
    $stmt_event_regos = $pdo->prepare($sql_event_regos);
    $stmt_event_regos->execute(['user_id' => $_SESSION['user_id']]);
    $event_registrations = $stmt_event_regos->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $error_message = "Database Error: Could not retrieve your profile data. " . $e->getMessage();
}

$page_title = 'Your Profile';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <h1 class="mb-4">Welcome, <?= htmlspecialchars($user['username']); ?>!</h1>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">Your Details</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Username:</strong> <?= htmlspecialchars($user['username']); ?></li>
                    <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></li>
                    <li class="list-group-item"><strong>Member Since:</strong> <?= date("d M Y", strtotime($user['created_at'])); ?></li>
                </ul>
            </div>
        </div>

        <div class="col-md-8">
            <h3>My Event Registrations</h3>
            <?php if (!empty($event_registrations)): ?>
                <?php foreach($event_registrations as $rego): ?>
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <strong>Event: <?= htmlspecialchars($rego['event_name']) ?></strong>
                            <span>Status: <span class="badge bg-success"><?= htmlspecialchars($rego['payment_status']) ?></span></span>
                        </div>
                        <div class="card-body">
                           <p>Registered on: <?= date("d M Y", strtotime($rego['registration_date'])) ?></p>
                           <p>Total Cost: $<?= number_format($rego['total_cost'], 2) ?></p>

                           <div class="card-body">
                                <p>Registered on: <?= date("d M Y", strtotime($rego['registration_date'])) ?></p>
                                <p>Total Cost: $<?= number_format($rego['total_cost'], 2) ?></p>
                                <a href="add_sub_events.php?registration_id=<?= $rego['registration_id'] ?>" class="btn btn-info btn-sm">
                                    Add/View Sub-Events
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>You have not registered for any events yet.</p>
            <?php endif; ?>


            <h3 class="mt-4">My Campsite Bookings</h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Campground</th>
                            <th>Site</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?= htmlspecialchars($booking['campground_name']) ?></td>
                                    <td><?= htmlspecialchars($booking['campsite_name']) ?></td>
                                    <td><?= date("d M Y", strtotime($booking['check_in_date'])) ?></td>
                                    <td><?= date("d M Y", strtotime($booking['check_out_date'])) ?></td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($booking['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">You have not made any campsite bookings yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <h3 class="mt-4">My Merchandise Orders</h3>
            <p>Your merchandise order history will be displayed here in a future update.</p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
