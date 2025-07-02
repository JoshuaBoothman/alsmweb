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


// --- HANDLE ADD EVENT REGISTRATION TO CART ---
if ($action === 'add_registration_to_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Security Check: A registration must be in progress.
    if (!isset($_SESSION['event_registration_in_progress'])) {
        header("Location: events.php?error=noreginprogress");
        exit();
    }
    
    // 2. Get the registration data from the session.
    $registration_data = $_SESSION['event_registration_in_progress'];
    
    // 3. Get the sub-event selections from the form submission.
    $sub_event_selections = $_POST['sub_event_registrations'] ?? [];
    $registration_data['sub_events'] = $sub_event_selections;
    
    // 4. Add the complete registration package to the main cart.
    // We use a unique key to identify this registration package.
    $cart_item_key = 'registration_' . $registration_data['event_id'];
    $_SESSION['cart'][$cart_item_key] = [
        'type' => 'registration',
        'details' => $registration_data
    ];
    
    // 5. Clean up the temporary session variable.
    unset($_SESSION['event_registration_in_progress']);
    
    // 6. Redirect to the cart to view the result.
    header('Location: view_cart.php?status=registration_added');
    exit();
}


// --- HANDLE ADD CAMPSITE BOOKING TO CART ---
if ($action === 'add_booking' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=loginrequired');
        exit();
    }

    $campsite_id = filter_input(INPUT_POST, 'campsite_id', FILTER_VALIDATE_INT);
    $check_in = trim($_POST['check_in']);
    $check_out = trim($_POST['check_out']);
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];

    if (!$campsite_id || !$check_in || !$check_out || !$num_guests || $num_guests < 1) {
        header('Location: campsite_booking.php?error=invaliddata');
        exit();
    }
    
    try {
        $sql_check = "SELECT campsite_id FROM bookings 
                      WHERE campsite_id = :campsite_id AND status IN ('Confirmed', 'Pending')
                      AND check_in_date < :check_out AND check_out_date > :check_in";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([':campsite_id' => $campsite_id, ':check_in' => $check_in, ':check_out' => $check_out]);
        if ($stmt_check->fetch()) {
            $_SESSION['error_message'] = "Sorry, that campsite was booked while you were deciding. Please select another.";
            header("Location: campsite_booking.php?check_in=$check_in&check_out=$check_out");
            exit();
        }

        $stmt_price = $pdo->prepare("SELECT price_per_night FROM campsites WHERE campsite_id = :campsite_id");
        $stmt_price->execute([':campsite_id' => $campsite_id]);
        $site_details = $stmt_price->fetch(PDO::FETCH_ASSOC);

        if (!$site_details) throw new Exception("Campsite details could not be found.");
        
        $price_per_night = $site_details['price_per_night'];
        $check_in_dt = new DateTime($check_in);
        $check_out_dt = new DateTime($check_out);
        $nights = $check_out_dt->diff($check_in_dt)->days;
        $total_price = $nights * $price_per_night;

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
        $error = "This product has required options that were not selected.";
    }

    if (!$error && $variant_id) {
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
    $cart_key = $_POST['cart_key'];
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($cart_key && $quantity > 0 && isset($_SESSION['cart'][$cart_key]) && $_SESSION['cart'][$cart_key]['type'] === 'merchandise') {
        $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
    }
    header('Location: view_cart.php?status=updated');
    exit();
}

// --- HANDLE REMOVE ITEM FROM CART (MERCH, BOOKING, OR REGISTRATION) ---
if ($action === 'remove' && isset($_GET['key'])) {
    $cart_key = $_GET['key'];
    
    if (isset($_SESSION['cart'][$cart_key])) {
        $item_type = $_SESSION['cart'][$cart_key]['type'];

        if ($item_type === 'campsite') {
            $booking_id = $_SESSION['cart'][$cart_key]['booking_id'];
            $stmt_del = $pdo->prepare("DELETE FROM bookings WHERE booking_id = :id AND status = 'Pending Basket'");
            $stmt_del->execute([':id' => $booking_id]);
        }
        // If it's a registration, there's no database record to delete yet, so we just remove from session.
        
        unset($_SESSION['cart'][$cart_key]);
    }
    header('Location: view_cart.php?status=removed');
    exit();
}


// Fallback redirect if no valid action is provided
header('Location: index.php');
exit();
