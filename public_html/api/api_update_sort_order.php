<?php
// api_update_sort_order.php

session_start();
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$ordered_ids = $data['order'] ?? [];

if (empty($ordered_ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No order data received.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // CORRECTED SQL: Update the new 'sort_order' column in the 'product_variants' table
    $sql = "UPDATE product_variants SET sort_order = :sort_order WHERE variant_id = :variant_id";
    $stmt = $pdo->prepare($sql);

    foreach ($ordered_ids as $i => $variant_id) {
        $stmt->execute([
            ':sort_order' => $i,
            ':variant_id' => filter_var($variant_id, FILTER_SANITIZE_NUMBER_INT)
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Variant sort order updated.']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}