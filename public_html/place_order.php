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

$last_merch_order_id = null;
$confirmed_booking_ids = [];
$last_event_registration_id = null;

// --- DATABASE TRANSACTION ---
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();

try {
    // --- Pre-calculate total for payment record ---
    // This logic duplicates the calculation from the previous step as a server-side security check.
    $sql_types = "SELECT type_id, price FROM attendee_types";
    $attendee_type_prices = $pdo->query($sql_types)->fetchAll(PDO::FETCH_KEY_PAIR);
    $sql_sub_events = "SELECT sub_event_id, cost FROM subevents";
    $sub_event_costs = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($cart as $key => $item) {
        switch ($item['type']) {
            case 'merchandise':
                // This price calculation is simplified here; a full check would be more robust.
                // We rely on the total calculated on the previous page for the payment amount.
                break;
            case 'campsite':
                $grand_total += $item['total_price'];
                break;
            case 'registration':
                foreach ($item['details']['attendees'] as $attendee) {
                    $grand_total += $attendee_type_prices[$attendee['type_id']] ?? 0;
                }
                foreach ($item['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                    $grand_total += count($attendee_indices) * ($sub_event_costs[$sub_event_id] ?? 0);
                }
                break;
        }
    }


    // --- PART 1: PROCESS EVENT REGISTRATIONS ---
    $registration_package = current(array_filter($cart, fn($item) => $item['type'] === 'registration'));
    if ($registration_package) {
        $details = $registration_package['details'];
        
        // Create the master event registration record
        $sql_rego = "INSERT INTO eventregistrations (user_id, event_id, total_cost, payment_status) VALUES (?, ?, ?, 'Paid')";
        $stmt_rego = $pdo->prepare($sql_rego);
        $stmt_rego->execute([$user_id, $details['event_id'], $grand_total]);
        $last_event_registration_id = $pdo->lastInsertId();

        // Loop through and insert each attendee
        foreach ($details['attendees'] as $index => $attendee_data) {
            $sql_attendee = "INSERT INTO attendees (eventreg_id, type_id, first_name, surname, email, phone, address, suburb, state, postcode, dob, arrival_date, departure_date, emergency_contact_name, emergency_contact_phone, dietary_reqs, notes, aus_number, flight_line_duty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_attendee = $pdo->prepare($sql_attendee);
            $stmt_attendee->execute([
                $last_event_registration_id,
                $attendee_data['type_id'],
                $attendee_data['first_name'],
                $attendee_data['surname'],
                $attendee_data['email'] ?? null,
                $attendee_data['phone'] ?? null,
                $attendee_data['address'] ?? null,
                $attendee_data['suburb'] ?? null,
                $attendee_data['state'] ?? null,
                $attendee_data['postcode'] ?? null,
                empty($attendee_data['dob']) ? null : $attendee_data['dob'],
                empty($attendee_data['arrival_date']) ? null : $attendee_data['arrival_date'],
                empty($attendee_data['departure_date']) ? null : $attendee_data['departure_date'],
                $attendee_data['emergency_contact_name'] ?? null,
                $attendee_data['emergency_contact_phone'] ?? null,
                $attendee_data['dietary_reqs'] ?? null,
                $attendee_data['notes'] ?? null,
                $attendee_data['aus_number'] ?? null,
                $attendee_data['flight_line_duty'] ?? 0
            ]);
            $attendee_id = $pdo->lastInsertId();

            // Insert planes for this attendee
            if (!empty($attendee_data['planes'])) {
                foreach ($attendee_data['planes'] as $plane_data) {
                    $sql_plane = "INSERT INTO attendee_planes (attendee_id, plane_model, cert_number, cert_expiry) VALUES (?, ?, ?, ?)";
                    $stmt_plane = $pdo->prepare($sql_plane);
                    $stmt_plane->execute([
                        $attendee_id,
                        $plane_data['plane_model'],
                        $plane_data['cert_number'] ?? null,
                        empty($plane_data['cert_expiry']) ? null : $plane_data['cert_expiry']
                    ]);
                }
            }
            // Store the new attendee_id against its original index for sub-event linking
            $attendee_id_map[$index] = $attendee_id;
        }

        // Link attendees to sub-events
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

    // --- PART 2: PROCESS CAMPSITE BOOKINGS ---
    $booking_items = array_filter($cart, fn($item) => $item['type'] === 'campsite');
    if (!empty($booking_items)) {
        $confirmed_booking_ids = array_column($booking_items, 'booking_id');
        $in_clause_bookings = implode(',', array_fill(0, count($confirmed_booking_ids), '?'));
        $sql_update_booking = "UPDATE bookings SET status = 'Confirmed' WHERE booking_id IN ($in_clause_bookings) AND user_id = ?";
        $stmt_update_booking = $pdo->prepare($sql_update_booking);
        $params = $confirmed_booking_ids;
        $params[] = $user_id;
        $stmt_update_booking->execute($params);
    }

    // --- PART 3: CREATE PAYMENT RECORD ---
    $stripe_transaction_id = $_SESSION['stripe_payment_intent_id'] ?? 'pi_placeholder_' . uniqid();
    $sql_payment = "INSERT INTO payments (order_id, user_id, gateway_name, gateway_transaction_id, payment_status, amount, currency) VALUES (?, ?, 'Stripe', ?, 'successful', ?, 'aud')";
    $stmt_payment = $pdo->prepare($sql_payment);
    $stmt_payment->execute([$last_merch_order_id, $user_id, $stripe_transaction_id, $grand_total]);
    $payment_id = $pdo->lastInsertId();
    
    // Link payment to event registration if it exists
    if ($last_event_registration_id) {
        $stmt_link_payment = $pdo->prepare("UPDATE eventregistrations SET payment_id = ? WHERE registration_id = ?");
        $stmt_link_payment->execute([$payment_id, $last_event_registration_id]);
    }


    $pdo->commit();

    // --- PART 4: CLEAN UP AND REDIRECT ---
    $_SESSION['last_event_registration_id'] = $last_event_registration_id;
    $_SESSION['last_booking_ids'] = $confirmed_booking_ids;
    $_SESSION['last_order_id'] = $last_merch_order_id;
    
    unset($_SESSION['cart']);
    unset($_SESSION['shipping_address']);
    unset($_SESSION['stripe_payment_intent_id']);

    header('Location: order_confirmation.php');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'There was a problem saving your order. Error: ' . $e->getMessage();
    header('Location: checkout.php');
    exit();
}
