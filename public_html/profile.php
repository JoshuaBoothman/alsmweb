<?php
// public_html/profile.php

// Must be the very first thing on the page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Page Protection: Check if the user is logged in.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Fetch User & Booking Data
require_once __DIR__ . '/../config/db_config.php';

$user = null;
$bookings = [];
$error_message = '';

try {
    // Fetch the user's details
    $sql_user = "SELECT username, email, first_name, last_name, created_at FROM Users WHERE user_id = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // This could happen if the user was deleted from the DB while their session was active.
        header("Location: logout.php");
        exit();
    }

    // Fetch the user's confirmed campsite bookings
    $sql_bookings = "SELECT 
                        b.booking_id, b.check_in_date, b.check_out_date, b.total_price, b.status,
                        cs.name AS campsite_name,
                        cg.name AS campground_name
                    FROM bookings b
                    JOIN campsites cs ON b.campsite_id = cs.campsite_id
                    JOIN campgrounds cg ON cs.campground_id = cg.campground_id
                    WHERE b.user_id = :user_id AND b.status != 'Pending Basket'
                    ORDER BY b.check_in_date DESC";
    
    $stmt_bookings = $pdo->prepare($sql_bookings);
    $stmt_bookings->execute(['user_id' => $_SESSION['user_id']]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not retrieve your profile data. " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Your Profile';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <h1 class="mb-4">Welcome, <?= htmlspecialchars($user['username']); ?>!</h1>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- User Details Column -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    Your Details
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Username:</strong> <?= htmlspecialchars($user['username']); ?></li>
                    <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></li>
                    <li class="list-group-item"><strong>Member Since:</strong> <?= date("d M Y", strtotime($user['created_at'])); ?></li>
                </ul>
            </div>
        </div>

        <!-- Bookings Column -->
        <div class="col-md-8">
            <h3>My Campsite Bookings</h3>
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
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch ($booking['status']) {
                                                    case 'Confirmed': echo 'bg-success'; break;
                                                    case 'Cancelled': echo 'bg-danger'; break;
                                                    case 'Completed': echo 'bg-secondary'; break;
                                                    default: echo 'bg-warning';
                                                }
                                            ?>">
                                            <?= htmlspecialchars($booking['status']) ?>
                                        </span>
                                    </td>
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
