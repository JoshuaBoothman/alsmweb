<?php
// public_html/view_cart.php

require_once '../config/db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- INITIALIZE VARIABLES ---
$cart_items = [];
$cart_total = 0;
$error_message = '';

// --- PROCESS CART ---
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // Separate cart items by type for easier processing
    $merch_variant_ids = [];
    $booking_ids = [];
    $registration_packages = [];

    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['type'] === 'merchandise') {
            $merch_variant_ids[] = $item['variant_id'];
        } elseif ($item['type'] === 'campsite') {
            $booking_ids[] = $item['booking_id'];
        } elseif ($item['type'] === 'registration') {
            $registration_packages[$key] = $item;
        }
    }

    try {
        // 1. Process Merchandise Items
        if (!empty($merch_variant_ids)) {
            $in_clause = implode(',', array_fill(0, count($merch_variant_ids), '?'));
            $sql_merch = "
                SELECT 
                    p.product_id, p.product_name, p.image_path,
                    pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price,
                    (SELECT GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) SEPARATOR ', ') 
                     FROM product_variant_options pvo
                     JOIN attribute_options ao ON pvo.option_id = ao.option_id
                     JOIN attributes a ON ao.attribute_id = a.attribute_id
                     WHERE pvo.variant_id = pv.variant_id) AS options_string
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.product_id
                WHERE pv.variant_id IN ($in_clause)
            ";
            $stmt_merch = $pdo->prepare($sql_merch);
            $stmt_merch->execute($merch_variant_ids);
            $merch_results = $stmt_merch->fetchAll(PDO::FETCH_ASSOC);

            foreach ($merch_results as $item) {
                $cart_key = 'merch_' . $item['variant_id'];
                $quantity = $_SESSION['cart'][$cart_key]['quantity'];
                $subtotal = $item['final_price'] * $quantity;

                $cart_items[$cart_key] = [
                    'type' => 'merchandise',
                    'name' => $item['product_name'],
                    'image' => $item['image_path'],
                    'options' => $item['options_string'],
                    'quantity' => $quantity,
                    'price' => $item['final_price'],
                    'subtotal' => $subtotal
                ];
                $cart_total += $subtotal;
            }
        }

        // 2. Process Campsite Booking Items
        if (!empty($booking_ids)) {
            $in_clause_bookings = implode(',', array_fill(0, count($booking_ids), '?'));
            $sql_bookings = "
                SELECT b.booking_id, b.check_in_date, b.check_out_date, b.total_price, cs.name AS campsite_name, cg.name AS campground_name
                FROM bookings b
                JOIN campsites cs ON b.campsite_id = cs.campsite_id
                JOIN campgrounds cg ON cs.campground_id = cg.campground_id
                WHERE b.booking_id IN ($in_clause_bookings)
            ";
            $stmt_bookings = $pdo->prepare($sql_bookings);
            $stmt_bookings->execute($booking_ids);
            $booking_results = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

            foreach ($booking_results as $item) {
                $cart_key = 'booking_' . $item['booking_id'];
                $cart_items[$cart_key] = [
                    'type' => 'campsite',
                    'name' => 'Campsite Booking',
                    'options' => htmlspecialchars($item['campsite_name']) . ' (' . htmlspecialchars($item['campground_name']) . ')',
                    'details' => date('d M Y', strtotime($item['check_in_date'])) . ' to ' . date('d M Y', strtotime($item['check_out_date'])),
                    'price' => $item['total_price'],
                    'subtotal' => $item['total_price']
                ];
                $cart_total += $item['total_price'];
            }
        }

        // 3. Process Event Registration Packages
        if (!empty($registration_packages)) {
            // **FIXED**: Fetch all columns and build the array manually instead of using FETCH_KEY_PAIR.
            $sql_types = "SELECT type_id, type_name, price FROM attendee_types";
            $stmt_types = $pdo->query($sql_types);
            $attendee_types_raw = $stmt_types->fetchAll(PDO::FETCH_ASSOC);
            $attendee_types = [];
            foreach ($attendee_types_raw as $type) {
                $attendee_types[$type['type_id']] = $type;
            }

            $sql_sub_events = "SELECT sub_event_id, cost FROM subevents";
            $sub_event_costs = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($registration_packages as $key => $package) {
                $reg_total = 0;
                $attendee_summary = [];

                // Calculate cost for main attendees
                foreach ($package['details']['attendees'] as $attendee) {
                    $type_id = $attendee['type_id'];
                    $reg_total += $attendee_types[$type_id]['price'] ?? 0;
                    $attendee_summary[] = htmlspecialchars($attendee['first_name'] . ' ' . $attendee['surname']);
                }

                // Calculate cost for sub-events
                if (!empty($package['details']['sub_events'])) {
                    foreach ($package['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                        foreach ($attendee_indices as $index) {
                             $reg_total += $sub_event_costs[$sub_event_id] ?? 0;
                        }
                    }
                }

                $cart_items[$key] = [
                    'type' => 'registration',
                    'name' => 'Event Registration',
                    'options' => 'For: ' . implode(', ', $attendee_summary),
                    'subtotal' => $reg_total
                ];
                $cart_total += $reg_total;
            }
        }

    } catch (PDOException $e) {
        $error_message = "Error fetching cart details: " . $e->getMessage();
    }
}

// --- HEADER ---
$page_title = 'Your Shopping Cart';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <h1 class="mb-4">Your Shopping Cart</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php elseif (empty($cart_items)): ?>
        <div class="alert alert-info">
            <p>Your shopping cart is empty.</p>
            <a href="merchandise.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th colspan="2">Item</th>
                            <th>Price</th>
                            <th class="text-center">Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $key => $item): ?>
                            <?php if ($item['type'] === 'merchandise'): ?>
                                <!-- MERCHANDISE ITEM ROW -->
                                <tr>
                                    <td style="width: 100px;">
                                        <img src="<?= htmlspecialchars($item['image'] ?? 'https://placehold.co/100x100?text=No+Image') ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($item['name']) ?>">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($item['options']) ?></small>
                                    </td>
                                    <td>$<?= htmlspecialchars(number_format($item['price'], 2)) ?></td>
                                    <td class="text-center">
                                        <form action="cart_actions.php" method="POST" class="d-flex justify-content-center">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_key" value="<?= $key ?>">
                                            <input type="number" name="quantity" class="form-control" value="<?= $item['quantity'] ?>" min="1" style="width: 70px;">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary ms-2">Update</button>
                                        </form>
                                    </td>
                                    <td>$<?= htmlspecialchars(number_format($item['subtotal'], 2)) ?></td>
                                    <td>
                                        <a href="cart_actions.php?action=remove&key=<?= $key ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this item?')">&times;</a>
                                    </td>
                                </tr>
                            <?php elseif ($item['type'] === 'campsite'): ?>
                                <!-- CAMPSITE BOOKING ROW -->
                                <tr>
                                    <td style="width: 100px;"><img src="https://placehold.co/100x100/EBF5FB/17202A?text=Booking" class="img-fluid rounded" alt="Campsite Booking"></td>
                                    <td colspan="3">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                        <small class="text-muted"><?= $item['options'] ?><br><?= $item['details'] ?></small>
                                    </td>
                                    <td>$<?= htmlspecialchars(number_format($item['subtotal'], 2)) ?></td>
                                    <td><a href="cart_actions.php?action=remove&key=<?= $key ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">&times;</a></td>
                                </tr>
                             <?php elseif ($item['type'] === 'registration'): ?>
                                <!-- EVENT REGISTRATION ROW -->
                                <tr>
                                    <td style="width: 100px;"><img src="https://placehold.co/100x100/D4EDDA/155724?text=Rego" class="img-fluid rounded" alt="Event Registration"></td>
                                    <td colspan="3">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                        <small class="text-muted"><?= $item['options'] ?></small>
                                    </td>
                                    <td>$<?= htmlspecialchars(number_format($item['subtotal'], 2)) ?></td>
                                    <td><a href="cart_actions.php?action=remove&key=<?= $key ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">&times;</a></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Order Summary</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center fw-bold fs-4">
                                Total
                                <span>$<?= htmlspecialchars(number_format($cart_total, 2)) ?></span>
                            </li>
                        </ul>
                        <a href="checkout.php" class="btn btn-primary w-100 mt-3">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
