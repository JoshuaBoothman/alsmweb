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

// --- NEW: CSRF Validation ---
// Check if the token sent from the JavaScript fetch call matches the one in the session.
if (
    !isset($data['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Invalid security token.']);
    exit();
}
// Note: We don't unset the token here, as the user might need to retry the checkout process.
// The token will be unset in place_order.php after a successful payment.


// Basic validation for address fields
if (empty($data['first_name']) || empty($data['last_name']) || empty($data['address'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing address information.']);
    exit();
}

// Sanitize and construct the shipping address string
// Note: FILTER_SANITIZE_STRING is deprecated. Using htmlspecialchars is a safer alternative for preventing XSS.
$first_name = trim(htmlspecialchars($data['first_name']));
$last_name = trim(htmlspecialchars($data['last_name']));
$address_line_1 = trim(htmlspecialchars($data['address']));

// Store the formatted address in the user's session
$_SESSION['shipping_address'] = "{$first_name} {$last_name}\n{$address_line_1}";

// Send a success response
echo json_encode(['success' => true, 'message' => 'Address saved to session.']);
