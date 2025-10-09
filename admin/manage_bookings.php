<?php
// admin/manage_bookings.php

// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$bookings = [];
$error_message = '';

// --- HANDLE STATUS UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $new_status = $_POST['status'];
    // A list of allowed statuses to prevent arbitrary updates.
    $allowed_statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

    if ($booking_id && in_array($new_status, $allowed_statuses)) {
        try {
            $sql_update = "UPDATE bookings SET status = :status WHERE booking_id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([':status' => $new_status, ':id' => $booking_id]);
            $_SESSION['success_message'] = "Booking #$booking_id status updated to '$new_status'.";
            // Redirect to the same page to prevent form resubmission on refresh
            header("Location: manage_bookings.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update booking status. " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid data provided for status update.";
    }
}


// --- DATA FETCHING ---
try {
    // This is a complex query that joins four tables to get all the necessary information.
    $sql = "SELECT 
                b.booking_id, b.check_in_date, b.check_out_date, b.total_price, b.status, b.booking_date,
                b.first_name, b.surname,
                u.username,
                cs.name AS campsite_name,
                cg.name AS campground_name
            FROM bookings b
            JOIN users u ON b.user_id = u.user_id
            JOIN campsites cs ON b.campsite_id = cs.campsite_id
            JOIN campgrounds cg ON cs.campground_id = cg.campground_id
            ORDER BY b.booking_date DESC";
    
    $stmt = $pdo->query($sql);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch bookings. " . $e->getMessage();
}

// Generate a CSRF token for the status update forms.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Manage All Bookings';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage All Bookings</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Booked For (User)</th>
                    <th>Campground</th>
                    <th>Site</th>
                    <th>Dates</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookings)): ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['booking_id']) ?></td>
                            <td>
                                <?= htmlspecialchars($booking['first_name'] . ' ' . $booking['surname']) ?>
                                <small class="d-block text-muted">(User: <?= htmlspecialchars($booking['username']) ?>)</small>
                            </td>
                            <td><?= htmlspecialchars($booking['campground_name']) ?></td>
                            <td><?= htmlspecialchars($booking['campsite_name']) ?></td>
                            <td><?= date('d M Y', strtotime($booking['check_in_date'])) . ' - ' . date('d M Y', strtotime($booking['check_out_date'])) ?></td>
                            <td>$<?= htmlspecialchars(number_format($booking['total_price'], 2)) ?></td>
                            <td><strong><?= htmlspecialchars($booking['status']) ?></strong></td>
                            <td>
                                <form action="manage_bookings.php" method="POST" class="d-flex">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                    <select name="status" class="form-select form-select-sm me-2">
                                        <option value="Pending" <?= ($booking['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                                        <option value="Confirmed" <?= ($booking['status'] == 'Confirmed') ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="Cancelled" <?= ($booking['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                        <option value="Completed" <?= ($booking['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No bookings found yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
