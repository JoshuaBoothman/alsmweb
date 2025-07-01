<?php
// public_html/campsite_booking.php

// --- CONFIGURATION AND INITIALIZATION ---
require_once '../config/db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- INITIALIZE VARIABLES ---
$page_title = 'ALSM - Campsite Booking';
$error_message = '';
$campgrounds_with_sites = [];
$unavailable_campsite_ids = [];

// --- GET DATES FROM FORM SUBMISSION ---
$check_in_date = $_GET['check_in'] ?? '';
$check_out_date = $_GET['check_out'] ?? '';
$dates_selected = !empty($check_in_date) && !empty($check_out_date);

// --- DATA FETCHING ---
try {
    // 1. If dates are selected, find which campsites are already booked for that range.
    if ($dates_selected) {
        if (strtotime($check_out_date) <= strtotime($check_in_date)) {
            throw new Exception("Check-out date must be after the check-in date.");
        }

        $sql_unavailable = "SELECT campsite_id FROM bookings 
                            WHERE status IN ('Confirmed', 'Pending')
                            AND check_in_date < :check_out
                            AND check_out_date > :check_in";
        
        $stmt_unavailable = $pdo->prepare($sql_unavailable);
        $stmt_unavailable->execute([
            ':check_in' => $check_in_date,
            ':check_out' => $check_out_date
        ]);
        $unavailable_campsite_ids = $stmt_unavailable->fetchAll(PDO::FETCH_COLUMN);
    }

    // 2. Fetch all active campsites and their parent campground details.
    // We now also fetch map_image_path.
    $sql_all_sites = "SELECT 
                        cs.campsite_id, cs.name AS campsite_name, cs.description, cs.price_per_night,
                        cg.campground_id, cg.name AS campground_name, cg.map_image_path
                      FROM campsites cs
                      JOIN campgrounds cg ON cs.campground_id = cg.campground_id
                      WHERE cs.is_active = 1 AND cg.is_active = 1
                      ORDER BY cg.name, cs.name";
    
    $stmt_all_sites = $pdo->query($sql_all_sites);
    $all_campsites_flat = $stmt_all_sites->fetchAll(PDO::FETCH_ASSOC);

    // 3. Group the flat list of campsites into a structured array by campground.
    foreach ($all_campsites_flat as $site) {
        $cg_id = $site['campground_id'];
        if (!isset($campgrounds_with_sites[$cg_id])) {
            $campgrounds_with_sites[$cg_id] = [
                'name' => $site['campground_name'],
                'map_image_path' => $site['map_image_path'],
                'sites' => []
            ];
        }
        $campgrounds_with_sites[$cg_id]['sites'][] = $site;
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// --- HEADER ---
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <h1 class="mb-4">Campsite Booking</h1>
    <p>Select your desired check-in and check-out dates to see campsite availability.</p>

    <!-- Date Selection Form -->
    <div class="card bg-light p-4 mb-5">
        <form action="campsite_booking.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="check_in" class="form-label">Check-in Date</label>
                <input type="date" class="form-control" id="check_in" name="check_in" value="<?= htmlspecialchars($check_in_date) ?>" required>
            </div>
            <div class="col-md-5">
                <label for="check_out" class="form-label">Check-out Date</label>
                <input type="date" class="form-control" id="check_out" name="check_out" value="<?= htmlspecialchars($check_out_date) ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Check Availability</button>
            </div>
        </form>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Availability Results -->
    <?php if ($dates_selected && !$error_message): ?>
        <h2 class="mb-4">Availability for <?= htmlspecialchars($check_in_date) ?> to <?= htmlspecialchars($check_out_date) ?></h2>
        
        <?php if (empty($campgrounds_with_sites)): ?>
            <p>No campsites have been configured by the administrator yet.</p>
        <?php else: ?>
            <?php foreach ($campgrounds_with_sites as $campground): ?>
                <div class="campground-group mb-5">
                    <h3><?= htmlspecialchars($campground['name']) ?></h3>
                    <hr>
                    <!-- Campground Map Placeholder -->
                    <div class="mb-4">
                        <img src="<?= htmlspecialchars($campground['map_image_path'] ?? 'https://placehold.co/1200x400/EBF5FB/17202A?text=Campground+Map+Coming+Soon') ?>" 
                             alt="Map of <?= htmlspecialchars($campground['name']) ?>" 
                             class="img-fluid rounded border">
                    </div>

                    <div class="row">
                        <?php foreach ($campground['sites'] as $site):
                            $is_available = !in_array($site['campsite_id'], $unavailable_campsite_ids);
                        ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 <?= $is_available ? '' : 'bg-light border-danger' ?>">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($site['campsite_name']) ?></h5>
                                        <p class="card-text flex-grow-1"><?= htmlspecialchars($site['description']) ?></p>
                                        <p class="card-text fs-4 fw-bold text-success">$<?= htmlspecialchars(number_format($site['price_per_night'], 2)) ?> / night</p>
                                        
                                        <div class="mt-auto">
                                            <?php if ($is_available): ?>
                                                <form action="cart_actions.php" method="POST">
                                                    <input type="hidden" name="action" value="add_booking">
                                                    <input type="hidden" name="campsite_id" value="<?= $site['campsite_id'] ?>">
                                                    <input type="hidden" name="check_in" value="<?= htmlspecialchars($check_in_date) ?>">
                                                    <input type="hidden" name="check_out" value="<?= htmlspecialchars($check_out_date) ?>">
                                                    
                                                    <div class="mb-2">
                                                        <label for="num_guests_<?= $site['campsite_id'] ?>" class="form-label">Number of Guests</label>
                                                        <input type="number" name="num_guests" id="num_guests_<?= $site['campsite_id'] ?>" class="form-control" value="1" min="1" max="8" required>
                                                    </div>

                                                    <?php if (isset($_SESSION['user_id'])): ?>
                                                        <button type="submit" class="btn btn-primary w-100">Book Now</button>
                                                    <?php else: ?>
                                                        <a href="login.php?redirect=campsite_booking.php" class="btn btn-secondary w-100">Login to Book</a>
                                                    <?php endif; ?>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-danger w-100" disabled>Booked</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
