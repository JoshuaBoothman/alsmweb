<?php
// admin/booking_grid_report.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$event = null;
$campground = null;
$campsites = [];
$date_headers = [];
$booking_grid = []; // This will be our final 2D array for the grid

// --- INPUT VALIDATION ---
// This report needs an event_id and a campground_id from the URL to work.
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$campground_id = filter_input(INPUT_GET, 'campground_id', FILTER_VALIDATE_INT);

if (!$event_id || !$campground_id) {
    header("Location: manage_events.php?error=missing_ids");
    exit();
}


// --- DATA FETCHING & PROCESSING ---
try {
    // 1. Get Event and Campground details for the page title.
    $stmt_event = $pdo->prepare("SELECT * FROM events WHERE event_id = :id");
    $stmt_event->execute([':id' => $event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);

    $stmt_cg = $pdo->prepare("SELECT * FROM campgrounds WHERE campground_id = :id");
    $stmt_cg->execute([':id' => $campground_id]);
    $campground = $stmt_cg->fetch(PDO::FETCH_ASSOC);

    if (!$event || !$campground) {
        throw new Exception("Event or Campground not found.");
    }

    // 2. Generate an array of Date objects for the column headers.
    $period = new DatePeriod(
        new DateTime($event['start_date']),
        new DateInterval('P1D'),
        (new DateTime($event['end_date']))->modify('+1 day') // Include the end date
    );
    foreach ($period as $date) {
        $date_headers[] = $date;
    }

    // 3. Get all campsites for this campground to build the rows.
    $stmt_sites = $pdo->prepare("SELECT campsite_id, name FROM campsites WHERE campground_id = :id ORDER BY name ASC");
    $stmt_sites->execute([':id' => $campground_id]);
    $campsites = $stmt_sites->fetchAll(PDO::FETCH_ASSOC);

    // 4. Initialize our booking grid structure.
    // The keys of the array are the campsite IDs.
    foreach ($campsites as $site) {
        $booking_grid[$site['campsite_id']] = [
            'name' => $site['name'],
            'bookings' => [] // This will hold the bookings, keyed by date.
        ];
    }

    // 5. Get all relevant confirmed bookings.
    $sql_bookings = "SELECT b.campsite_id, b.first_name, b.surname, b.check_in_date, b.check_out_date
                     FROM bookings b
                     WHERE b.campsite_id IN (SELECT campsite_id FROM campsites WHERE campground_id = :cg_id)
                       AND b.status = 'Confirmed'
                       AND b.check_in_date <= :event_end AND b.check_out_date >= :event_start";
    
    $stmt_bookings = $pdo->prepare($sql_bookings);
    $stmt_bookings->execute([
        ':cg_id' => $campground_id,
        ':event_end' => $event['end_date'],
        ':event_start' => $event['start_date']
    ]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

    // 6. **THE CRUCIAL PART**: Process the list of bookings into the grid.
    foreach ($bookings as $booking) {
        $booker_name = htmlspecialchars($booking['first_name'] . ' ' . $booking['surname']);
        // Create a period for each individual booking.
        $booking_period = new DatePeriod(
            new DateTime($booking['check_in_date']),
            new DateInterval('P1D'),
            new DateTime($booking['check_out_date']) // Loop runs *until* the checkout date
        );

        // For each day the person is booked, add their name to our grid array.
        foreach ($booking_period as $day) {
            $date_key = $day->format('Y-m-d');
            $booking_grid[$booking['campsite_id']]['bookings'][$date_key] = $booker_name;
        }
    }

} catch (Exception $e) {
    $error_message = "Error generating report: " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Campsite Booking Grid';
require_once __DIR__ . '/../templates/header.php';
?>

<style>
    .booking-grid-table {
        border-collapse: collapse;
        width: 100%;
        table-layout: fixed;
    }
    .booking-grid-table th, .booking-grid-table td {
        border: 1px solid #ccc;
        padding: 8px;
        text-align: center;
        font-size: 0.8rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .booking-grid-table th {
        background-color: #f2f2f2;
    }
    .booking-grid-table .campsite-header {
        background-color: #e9ecef;
        font-weight: bold;
        text-align: left;
        width: 100px;
    }
    .booked-cell {
        background-color: #f8d7da;
        color: #721c24;
        font-weight: bold;
    }
</style>

<div class="container-fluid mt-4">
    <h1 class="mb-4">Campsite Booking Grid</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php elseif ($event && $campground): ?>
        <h3 class="mb-3">
            Event: <?= htmlspecialchars($event['event_name']) ?> | 
            Campground: <?= htmlspecialchars($campground['name']) ?>
        </h3>
        
        <div class="table-responsive">
            <table class="booking-grid-table">
                <thead>
                    <tr>
                        <th class="campsite-header">Campsite #</th>
                        <?php foreach ($date_headers as $date): ?>
                            <th><?= $date->format('d-M-Y') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($booking_grid as $site_id => $site_data): ?>
                        <tr>
                            <td class="campsite-header"><?= htmlspecialchars($site_data['name']) ?></td>
                            <?php foreach ($date_headers as $date): ?>
                                <?php
                                $date_key = $date->format('Y-m-d');
                                // Check if a booking exists for this site on this date
                                $booker_name = $site_data['bookings'][$date_key] ?? null;
                                ?>
                                <td class="<?= $booker_name ? 'booked-cell' : '' ?>">
                                    <?= $booker_name ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="manage_campgrounds.php" class="btn btn-secondary mt-3">&laquo; Back to Campgrounds</a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>