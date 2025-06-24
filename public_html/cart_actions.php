<?php
// --- CONFIGURATION AND SESSION START ---
require_once '../config/db_config.php';
session_start();

// Initialize the cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- MAIN ACTION HANDLER ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// --- HANDLE ADD TO CART ---
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
            $option_count = count($selected_options_post);
            $in_clause = implode(',', array_fill(0, $option_count, '?'));
            
            $sql = "SELECT pvo.variant_id
                    FROM product_variant_options pvo
                    JOIN attribute_options ao ON pvo.option_id = ao.option_id
                    WHERE ao.value IN ($in_clause) AND pvo.variant_id IN (
                        SELECT variant_id FROM product_variants WHERE product_id = ?
                    )
                    GROUP BY pvo.variant_id
                    HAVING COUNT(DISTINCT pvo.option_id) = ?";

            $params = array_values($selected_options_post);
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
        $cart_item_key = $variant_id;
        if (isset($_SESSION['cart'][$cart_item_key])) {
            $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cart_item_key] = [
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

// --- HANDLE UPDATE QUANTITY ---
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($variant_id && $quantity > 0 && isset($_SESSION['cart'][$variant_id])) {
        // Update the quantity for the specific item in the cart
        $_SESSION['cart'][$variant_id]['quantity'] = $quantity;
    }
    // Redirect back to the cart page
    header('Location: view_cart.php?status=updated');
    exit();
}

// --- HANDLE REMOVE FROM CART ---
if ($action === 'remove' && isset($_GET['variant_id'])) {
    $variant_id = filter_input(INPUT_GET, 'variant_id', FILTER_VALIDATE_INT);
    
    if ($variant_id && isset($_SESSION['cart'][$variant_id])) {
        // Remove the specific item from the cart array
        unset($_SESSION['cart'][$variant_id]);
    }
    // Redirect back to the cart page
    header('Location: view_cart.php?status=removed');
    exit();
}


// Fallback redirect if no valid action is provided
header('Location: merchandise.php');
exit();
