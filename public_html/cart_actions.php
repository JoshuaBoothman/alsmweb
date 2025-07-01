<?php
// public_html/cart_actions.php

// --- CONFIGURATION AND SESSION START ---
require_once '../config/db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize the cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- MAIN ACTION HANDLER ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// --- HANDLE ADD CAMPSITE BOOKING TO CART ---
if ($action === 'add_booking' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Security Check: User must be logged in to book.
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=loginrequired');
        exit();
    }

    // 2. Get and validate all the data from the form.
    $campsite_id = filter_input(INPUT_POST, 'campsite_id', FILTER_VALIDATE_INT);
    $check_in = trim($_POST['check_in']);
    $check_out = trim($_POST['check_out']);
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];

    if (!$campsite_id || !$check_in || !$check_out || !$num_guests || $num_guests < 1) {
        header('Location: campsite_booking.php?error=invaliddata');
        exit();
    }
    
    // 3. Final availability check and get price
    try {
        // Check for overlapping bookings one last time to prevent race conditions.
        $sql_check = "SELECT campsite_id FROM bookings 
                      WHERE campsite_id = :campsite_id AND status IN ('Confirmed', 'Pending')
                      AND check_in_date < :check_out AND check_out_date > :check_in";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([':campsite_id' => $campsite_id, ':check_in' => $check_in, ':check_out' => $check_out]);
        if ($stmt_check->fetch()) {
            // If a booking is found, the site is not available.
            $_SESSION['error_message'] = "Sorry, that campsite was booked while you were deciding. Please select another.";
            header("Location: campsite_booking.php?check_in=$check_in&check_out=$check_out");
            exit();
        }

        // Get the price per night to calculate the total.
        $stmt_price = $pdo->prepare("SELECT price_per_night FROM campsites WHERE campsite_id = :campsite_id");
        $stmt_price->execute([':campsite_id' => $campsite_id]);
        $site_details = $stmt_price->fetch(PDO::FETCH_ASSOC);

        if (!$site_details) {
            throw new Exception("Campsite details could not be found.");
        }
        $price_per_night = $site_details['price_per_night'];

        // 4. Calculate total price
        $check_in_dt = new DateTime($check_in);
        $check_out_dt = new DateTime($check_out);
        $nights = $check_out_dt->diff($check_in_dt)->days;
        $total_price = $nights * $price_per_night;

        // 5. Insert a preliminary booking record with 'Pending Basket' status.
        $sql_insert = "INSERT INTO bookings (user_id, campsite_id, check_in_date, check_out_date, num_guests, total_price, status) 
                       VALUES (:user_id, :campsite_id, :check_in, :check_out, :num_guests, :total_price, 'Pending Basket')";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':user_id' => $user_id,
            ':campsite_id' => $campsite_id,
            ':check_in' => $check_in,
            ':check_out' => $check_out,
            ':num_guests' => $num_guests,
            ':total_price' => $total_price
        ]);
        $booking_id = $pdo->lastInsertId();

        // 6. Add the booking to the session cart.
        // We use a unique key to distinguish it from merchandise.
        $cart_item_key = 'booking_' . $booking_id;
        $_SESSION['cart'][$cart_item_key] = [
            'type' => 'campsite',
            'booking_id' => $booking_id,
            'campsite_id' => $campsite_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'nights' => $nights,
            'num_guests' => $num_guests,
            'total_price' => $total_price
        ];

        // 7. Redirect to the cart page.
        header('Location: view_cart.php?status=booking_added');
        exit();

    } catch (Exception $e) {
        header('Location: campsite_booking.php?error=' . urlencode($e->getMessage()));
        exit();
    }
}


// --- HANDLE ADD MERCHANDISE TO CART ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $selected_options_post = $_POST['options'] ?? [];

    if (!$product_id || !$quantity || $quantity <= 0) {
        header('Location: merchandise.php?error=invaliddata');
        exit();
    }

    $variant_id = null;
    $error = null;

    if (!empty($selected_options_post)) {
        try {
            // This logic finds the specific variant ID based on the combination of options selected.
            $option_values = array_values($selected_options_post);
            $option_count = count($option_values);
            
            $placeholders = implode(',', array_fill(0, $option_count, '?'));

            $sql = "SELECT pvo.variant_id
                    FROM product_variant_options pvo
                    JOIN attribute_options ao ON pvo.option_id = ao.option_id
                    WHERE ao.value IN ($placeholders) AND pvo.variant_id IN (
                        SELECT variant_id FROM product_variants WHERE product_id = ?
                    )
                    GROUP BY pvo.variant_id
                    HAVING COUNT(DISTINCT pvo.option_id) = ?";

            $params = $option_values;
            $params[] = $product_id;
            $params[] = $option_count;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $variant_id = $result['variant_id'];
            } else {
                $error = "The selected combination of options is not available.";
            }
        } catch (PDOException $e) {
            $error = "Database error finding variant: " . $e->getMessage();
        }
    } else {
        // This case handles products that might not have variants.
        // For simplicity, we assume all products have variants for now.
        // A more robust solution would check if a product has variants or not.
        $error = "This product has required options that were not selected.";
    }

    if (!$error && $variant_id) {
        // Merchandise items are keyed by their variant_id in the cart.
        $cart_item_key = 'merch_' . $variant_id;
        if (isset($_SESSION['cart'][$cart_item_key])) {
            $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cart_item_key] = [
                'type' => 'merchandise',
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity' => $quantity
            ];
        }
        header('Location: view_cart.php?status=added');
        exit();
    } else {
        $_SESSION['error_message'] = $error ?? 'Could not add item to cart.';
        header('Location: product_detail.php?id=' . $product_id);
        exit();
    }
}

// --- HANDLE UPDATE MERCHANDISE QUANTITY ---
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_key = $_POST['cart_key']; // e.g., 'merch_123'
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($cart_key && $quantity > 0 && isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
    }
    header('Location: view_cart.php?status=updated');
    exit();
}

// --- HANDLE REMOVE ITEM FROM CART (MERCH OR BOOKING) ---
if ($action === 'remove' && isset($_GET['key'])) {
    $cart_key = $_GET['key'];
    
    if (isset($_SESSION['cart'][$cart_key])) {
        // If it's a booking, we should also delete the 'Pending Basket' record.
        if ($_SESSION['cart'][$cart_key]['type'] === 'campsite') {
            $booking_id = $_SESSION['cart'][$cart_key]['booking_id'];
            $stmt_del = $pdo->prepare("DELETE FROM bookings WHERE booking_id = :id AND status = 'Pending Basket'");
            $stmt_del->execute([':id' => $booking_id]);
        }
        // Remove the item from the cart session.
        unset($_SESSION['cart'][$cart_key]);
    }
    header('Location: view_cart.php?status=removed');
    exit();
}


// Fallback redirect if no valid action is provided
header('Location: index.php');
exit();
