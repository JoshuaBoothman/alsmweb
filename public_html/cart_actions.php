<?php
// public_html/cart_actions.php

// --- CONFIGURATION AND HELPERS ---
require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php'; // Include CSRF helpers
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
    validate_csrf_token(); // CSRF Check

    if (!isset($_SESSION['event_registration_in_progress'])) {
        header("Location: events.php?error=noreginprogress");
        exit();
    }
    
    $registration_data = $_SESSION['event_registration_in_progress'];
    $sub_event_selections = $_POST['sub_event_registrations'] ?? [];
    $registration_data['sub_events'] = $sub_event_selections;
    
    $cart_item_key = 'registration_' . $registration_data['event_id'];
    $_SESSION['cart'][$cart_item_key] = [
        'type' => 'registration',
        'details' => $registration_data
    ];
    
    unset($_SESSION['event_registration_in_progress']);
    
    header('Location: view_cart.php?status=registration_added');
    exit();
}


// --- HANDLE ADD CAMPSITE BOOKING TO CART ---
if ($action === 'add_booking' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token(); // CSRF Check

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=loginrequired');
        exit();
    }

    // Get first name and surname from the form
    $first_name = trim($_POST['first_name']);
    $surname = trim($_POST['surname']);

    $campsite_id = filter_input(INPUT_POST, 'campsite_id', FILTER_VALIDATE_INT);
    $check_in = trim($_POST['check_in']);
    $check_out = trim($_POST['check_out']);
    $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];

    // Modified: Added first_name and surname to the validation check
    if (!$campsite_id || !$check_in || !$check_out || !$num_guests || $num_guests < 1 || empty($first_name) || empty($surname)) {
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

        // Modified: Added first_name and surname to the SQL INSERT statement
        $sql_insert = "INSERT INTO bookings (user_id, campsite_id, check_in_date, check_out_date, num_guests, total_price, status, first_name, surname) 
               VALUES (:user_id, :campsite_id, :check_in, :check_out, :num_guests, :total_price, 'Pending Basket', :first_name, :surname)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':user_id' => $user_id,
            ':campsite_id' => $campsite_id,
            ':check_in' => $check_in,
            ':check_out' => $check_out,
            ':num_guests' => $num_guests,
            ':total_price' => $total_price,
            ':first_name' => $first_name,
            ':surname' => $surname
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
    validate_csrf_token(); // CSRF Check

    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity_to_add = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $selected_options_post = $_POST['options'] ?? [];

    if (!$product_id || !$quantity_to_add || $quantity_to_add <= 0) {
        header('Location: merchandise.php?error=invaliddata');
        exit();
    }

    $variant_id = null;
    $error = null;

    if (!empty($selected_options_post)) {
        try {
            // Find the variant_id based on selected options (your existing logic is good)
            $option_values = array_values($selected_options_post);
            $option_count = count($option_values);
            $placeholders = implode(',', array_fill(0, $option_count, '?'));
            $sql_find_variant = "SELECT pv.variant_id, pv.stock_quantity
                                FROM product_variant_options pvo
                                JOIN attribute_options ao ON pvo.option_id = ao.option_id
                                JOIN product_variants pv ON pvo.variant_id = pv.variant_id
                                WHERE ao.value IN ($placeholders) AND pv.product_id = ?
                                GROUP BY pv.variant_id, pv.stock_quantity
                                HAVING COUNT(DISTINCT pvo.option_id) = ?";
            $params = array_merge($option_values, [$product_id, $option_count]);
            $stmt = $pdo->prepare($sql_find_variant);
            $stmt->execute($params);
            $variant_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($variant_data) {
                $variant_id = $variant_data['variant_id'];
                $stock_available = $variant_data['stock_quantity'];
                
                // **NEW**: Stock Check Logic
                $cart_item_key = 'merch_' . $variant_id;
                $quantity_in_cart = $_SESSION['cart'][$cart_item_key]['quantity'] ?? 0;
                
                if (($quantity_in_cart + $quantity_to_add) > $stock_available) {
                    $error = "Cannot add to cart. Only {$stock_available} item(s) in stock.";
                }

            } else {
                $error = "The selected combination of options is not available.";
            }
        } catch (PDOException $e) {
            $error = "Database error finding variant: " . $e->getMessage();
        }
    } else {
        // This logic needs adjustment if you have products WITHOUT variants
        $error = "This product has required options that were not selected.";
    }

    if (!$error && $variant_id) {
        $cart_item_key = 'merch_' . $variant_id;
        if (isset($_SESSION['cart'][$cart_item_key])) {
            $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity_to_add;
        } else {
            $_SESSION['cart'][$cart_item_key] = [
                'type' => 'merchandise',
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity' => $quantity_to_add
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
    validate_csrf_token(); // CSRF Check

    $cart_key = $_POST['cart_key'];
    $new_quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($cart_key && $new_quantity > 0 && isset($_SESSION['cart'][$cart_key]) && $_SESSION['cart'][$cart_key]['type'] === 'merchandise') {
        // **NEW**: Stock check on update
        $variant_id = $_SESSION['cart'][$cart_key]['variant_id'];
        $stmt_stock = $pdo->prepare("SELECT stock_quantity FROM product_variants WHERE variant_id = ?");
        $stmt_stock->execute([$variant_id]);
        $stock_available = $stmt_stock->fetchColumn();

        if ($new_quantity <= $stock_available) {
            $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
        } else {
            $_SESSION['error_message'] = "Update failed. Only {$stock_available} item(s) in stock.";
        }
    }
    header('Location: view_cart.php?status=updated');
    exit();
}

// --- HANDLE REMOVE ITEM FROM CART (MERCH, BOOKING, OR REGISTRATION) ---
if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token(); // CSRF Check

    $cart_key = $_POST['key'];
    
    if (isset($_SESSION['cart'][$cart_key])) {
        $item_type = $_SESSION['cart'][$cart_key]['type'];

        if ($item_type === 'campsite') {
            $booking_id = $_SESSION['cart'][$cart_key]['booking_id'];
            $stmt_del = $pdo->prepare("DELETE FROM bookings WHERE booking_id = :id AND status = 'Pending Basket'");
            $stmt_del->execute([':id' => $booking_id]);
        }
        
        unset($_SESSION['cart'][$cart_key]);
    }
    header('Location: view_cart.php?status=removed');
    exit();
}


// Fallback redirect if no valid action is provided
header('Location: index.php');
exit();
