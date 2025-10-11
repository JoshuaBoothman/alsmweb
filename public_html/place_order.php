<?php
// public_html/place_order.php

require_once '../config/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- PRE-CHECKS ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

// --- INITIALIZE VARIABLES ---
$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$shipping_address = $_SESSION['shipping_address'] ?? 'No Address Provided';
$grand_total = 0;
$merch_total = 0;
$booking_total = 0;
$rego_total = 0;

$last_merch_order_id = null;
$confirmed_booking_ids = [];
$last_event_registration_id = null;
$attendee_id_map = [];

// --- DATABASE TRANSACTION ---


$pdo->beginTransaction();

try {
    // --- Pre-calculate totals for each category ---
    $sql_types = "SELECT type_id, price FROM attendee_types";
    $attendee_type_prices = $pdo->query($sql_types)->fetchAll(PDO::FETCH_KEY_PAIR);
    $sql_sub_events = "SELECT sub_event_id, cost FROM subevents";
    $sub_event_costs = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_KEY_PAIR);
    $sql_merch_prices = "SELECT pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price, p.product_id FROM product_variants pv JOIN products p ON pv.product_id = p.product_id";
    $merch_prices_map_raw = $pdo->query($sql_merch_prices)->fetchAll(PDO::FETCH_ASSOC);
    $merch_prices_map = array_column($merch_prices_map_raw, null, 'variant_id');

    foreach ($cart as $key => $item) {
        switch ($item['type']) {
            case 'merchandise':
                $price = $merch_prices_map[$item['variant_id']]['final_price'] ?? 0;
                $merch_total += $price * $item['quantity'];
                break;
            case 'campsite':
                $booking_total += $item['total_price'];
                break;
            case 'registration':
                $current_rego_total = 0;
                if (!empty($item['details']['attendees'])) {
                    foreach ($item['details']['attendees'] as $attendee) {
                        $current_rego_total += $attendee_type_prices[$attendee['type_id']] ?? 0;
                    }
                }
                if (!empty($item['details']['sub_events'])) {
                    foreach ($item['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                        $current_rego_total += count($attendee_indices) * ($sub_event_costs[$sub_event_id] ?? 0);
                    }
                }
                $rego_total += $current_rego_total;
                break;
        }
    }
    $grand_total = $merch_total + $booking_total + $rego_total;

    // --- PART 1: CREATE PAYMENT RECORD FIRST ---
    // $stripe_transaction_id = $_SESSION['stripe_payment_intent_id'] ?? 'pi_placeholder_' . uniqid();
    // $sql_payment = "INSERT INTO payments (user_id, gateway_name, gateway_transaction_id, payment_status, amount, currency) VALUES (?, 'Stripe', ?, 'successful', ?, 'aud')";
    // $stmt_payment = $pdo->prepare($sql_payment);
    // $stmt_payment->execute([$user_id, $stripe_transaction_id, $grand_total]);
    // $payment_id = $pdo->lastInsertId();

    // --- PART 2: PROCESS AND LINK ITEMS ---

    // Process Merchandise Orders
    $merch_items = array_filter($cart, fn($item) => $item['type'] === 'merchandise');
    if (!empty($merch_items)) {
        // $sql_order = "INSERT INTO orders (user_id, total_amount, shipping_address, order_status, payment_id) VALUES (?, ?, ?, 'paid', ?)";
        // $stmt_order = $pdo->prepare($sql_order);
        // $stmt_order->execute([$user_id, $merch_total, $shipping_address, $payment_id]);
        $sql_order = "INSERT INTO orders (user_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, 'pending_payment')";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$user_id, $merch_total, $shipping_address]);
        $last_merch_order_id = $pdo->lastInsertId();
        
        $sql_order_item = "INSERT INTO orderitems (order_id, product_id, variant_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?, ?)";
        $stmt_order_item = $pdo->prepare($sql_order_item);
        $sql_update_stock = "UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE variant_id = ?";
        $stmt_update_stock = $pdo->prepare($sql_update_stock);

        foreach ($merch_items as $key => $item) {
            $variant_id = $item['variant_id'];
            $product_id = $merch_prices_map[$variant_id]['product_id'] ?? 0;
            $price = $merch_prices_map[$variant_id]['final_price'] ?? 0;
            $stmt_order_item->execute([$last_merch_order_id, $product_id, $variant_id, $item['quantity'], $price]);
            $stmt_update_stock->execute([$item['quantity'], $variant_id]);
        }
        // $pdo->prepare("UPDATE payments SET order_id = ? WHERE payment_id = ?")->execute([$last_merch_order_id, $payment_id]);
    }

    // Process Campsite Bookings
    $booking_items = array_filter($cart, fn($item) => $item['type'] === 'campsite');
    if (!empty($booking_items)) {
        $confirmed_booking_ids = array_column($booking_items, 'booking_id');
        $primary_booking_id = $confirmed_booking_ids[0];
        
        $in_clause_bookings = implode(',', array_fill(0, count($confirmed_booking_ids), '?'));
        // **THE FIX**: Removed the attempt to update a non-existent payment_id column.
        // $sql_update_booking = "UPDATE bookings SET status = 'Confirmed' WHERE booking_id IN ($in_clause_bookings) AND user_id = ?";
        $sql_update_booking = "UPDATE bookings SET status = 'Pending' WHERE booking_id IN ($in_clause_bookings) AND user_id = ?";
        $stmt_update_booking = $pdo->prepare($sql_update_booking);
        $params = array_merge($confirmed_booking_ids, [$user_id]);
        $stmt_update_booking->execute($params);
        
        // This is the correct way to link them.
        // $pdo->prepare("UPDATE payments SET booking_id = ? WHERE payment_id = ?")->execute([$primary_booking_id, $payment_id]);
    }
    
    // --- Process Sub-Event Addons ---
    // 4. Process Sub-Event Addons
    $addon_packages = array_filter($_SESSION['cart'], fn($item) => $item['type'] === 'sub_event_addon');
    if (!empty($addon_packages)) {
        // Fetch all sub-event details in one go for efficiency
        $sql_sub_events = "SELECT sub_event_id, sub_event_name, cost FROM subevents";
        $sub_event_details_map = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        foreach ($addon_packages as $key => $package) {
            $addon_total = 0;
            $addon_details_display = []; // For creating a descriptive summary

            // Fetch the names of the attendees in this specific registration
            $sql_attendees = "SELECT attendee_id, CONCAT(first_name, ' ', surname) FROM attendees WHERE eventreg_id = ?";
            $stmt_attendees = $pdo->prepare($sql_attendees);
            $stmt_attendees->execute([$package['registration_id']]);
            $attendee_names_map = $stmt_attendees->fetchAll(PDO::FETCH_KEY_PAIR);

            // Loop through the selections to calculate total and build summary
            foreach($package['details'] as $sub_event_id => $attendee_ids) {
                $cost = $sub_event_details_map[$sub_event_id]['cost'] ?? 0;
                $sub_event_name = $sub_event_details_map[$sub_event_id]['sub_event_name'] ?? 'Unknown Sub-Event';
                
                $attendee_names_for_this_sub = [];
                foreach ($attendee_ids as $attendee_id) {
                    $addon_total += $cost;
                    $attendee_names_for_this_sub[] = $attendee_names_map[$attendee_id] ?? 'Unknown Attendee';
                }
                // Create a nice summary line, e.g., "Competition A (Josh Boothman, Jane Doe)"
                $addon_details_display[] = htmlspecialchars($sub_event_name) . ' (' . implode(', ', $attendee_names_for_this_sub) . ')';
            }

            $cart_items[$key] = [
                'type' => 'sub_event_addon',
                'name' => 'Sub-Event Add-on',
                'options' => 'For registration #' . $package['registration_id'],
                'details' => implode('<br>', $addon_details_display), // Use our new summary
                'subtotal' => $addon_total
            ];
            $cart_total += $addon_total;
        }
    }

    // Process Event Registrations
    $registration_package = current(array_filter($cart, fn($item) => $item['type'] === 'registration'));
    if ($registration_package) {
        $details = $registration_package['details'];
        // $sql_rego = "INSERT INTO eventregistrations (user_id, event_id, total_cost, payment_status, payment_id) VALUES (?, ?, ?, 'Paid', ?)";
        // $sql_rego = "INSERT INTO eventregistrations (user_id, event_id, total_cost, payment_status) VALUES (?, ?, ?, 'pending')";
        // $stmt_rego = $pdo->prepare($sql_rego);
        // $stmt_rego->execute([$user_id, $details['event_id'], $rego_total, $payment_id]);
        $sql_rego = "INSERT INTO eventregistrations (user_id, event_id, total_cost, payment_status) VALUES (?, ?, ?, 'pending')";
        $stmt_rego = $pdo->prepare($sql_rego);
        $stmt_rego->execute([$user_id, $details['event_id'], $rego_total]);
        $last_event_registration_id = $pdo->lastInsertId();
        
        foreach ($details['attendees'] as $index => $attendee_data) {
            $sql_attendee = "INSERT INTO attendees (eventreg_id, type_id, first_name, surname, email, phone, address, suburb, state, postcode, dob, aus_number, flight_line_duty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_attendee = $pdo->prepare($sql_attendee);
            $stmt_attendee->execute([ $last_event_registration_id, $attendee_data['type_id'], $attendee_data['first_name'], $attendee_data['surname'], $attendee_data['email'] ?? null, $attendee_data['phone'] ?? null, $attendee_data['address'] ?? null, $attendee_data['suburb'] ?? null, $attendee_data['state'] ?? null, $attendee_data['postcode'] ?? null, empty($attendee_data['dob']) ? null : $attendee_data['dob'], $attendee_data['aus_number'] ?? null, $attendee_data['flight_line_duty'] ?? 0 ]);
            $attendee_id = $pdo->lastInsertId();
            if (!empty($attendee_data['planes'])) {
                foreach ($attendee_data['planes'] as $plane_data) {
                    $sql_plane = "INSERT INTO attendee_planes (attendee_id, plane_model, cert_number, cert_expiry) VALUES (?, ?, ?, ?)";
                    $stmt_plane = $pdo->prepare($sql_plane);
                    $stmt_plane->execute([ $attendee_id, $plane_data['plane_model'], $plane_data['cert_number'] ?? null, empty($plane_data['cert_expiry']) ? null : $plane_data['cert_expiry'] ]);
                }
            }
            $attendee_id_map[$index] = $attendee_id;
        }
        if (!empty($details['sub_events'])) {
            foreach ($details['sub_events'] as $sub_event_id => $attendee_indices) {
                foreach ($attendee_indices as $index) {
                    $sql_sub_rego = "INSERT INTO attendee_subevent_registrations (attendee_id, sub_event_id) VALUES (?, ?)";
                    $stmt_sub_rego = $pdo->prepare($sql_sub_rego);
                    $stmt_sub_rego->execute([$attendee_id_map[$index], $sub_event_id]);
                }
            }
        }
    }

    $pdo->commit();

    // --- PART 3: CLEAN UP AND REDIRECT ---
    $_SESSION['last_event_registration_id'] = $last_event_registration_id;
    $_SESSION['last_booking_ids'] = $confirmed_booking_ids;
    $_SESSION['last_order_id'] = $last_merch_order_id;
    
    // unset($_SESSION['cart'], $_SESSION['shipping_address'], $_SESSION['stripe_payment_intent_id']);
    unset($_SESSION['cart'], $_SESSION['shipping_address']);
    header('Location: order_confirmation.php');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'There was a problem saving your order. Error: ' . $e->getMessage();
    header('Location: checkout.php');
    exit();
}
