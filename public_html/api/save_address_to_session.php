<?php
// /public_html/api/save_address_to_session.php
header('Content-Type: application/json');
session_start();

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
    exit();
}

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Basic validation
if (empty($data['first_name']) || empty($data['last_name']) || empty($data['address'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing address information.']);
    exit();
}

// Sanitize and construct the shipping address string
$first_name = trim(filter_var($data['first_name'], FILTER_SANITIZE_STRING));
$last_name = trim(filter_var($data['last_name'], FILTER_SANITIZE_STRING));
$address_line_1 = trim(filter_var($data['address'], FILTER_SANITIZE_STRING));

// Store the formatted address in the user's session
$_SESSION['shipping_address'] = "{$first_name} {$last_name}\n{$address_line_1}";

// Send a success response
echo json_encode(['success' => true, 'message' => 'Address saved to session.']);
