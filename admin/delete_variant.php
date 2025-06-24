<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$variant_details = '';
$variant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT); // For redirection

// --- VALIDATE IDs ---
if (!$variant_id || !$product_id) {
    header("Location: manage_products.php?error=invalidids");
    exit();
}

// Helper function to check if the variant has been ordered.
function isVariantInUse($pdo, $variant_id) {
    $sql = "SELECT COUNT(*) FROM orderitems WHERE variant_id = :variant_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':variant_id' => $variant_id]);
    return $stmt->fetchColumn() > 0;
}

// Helper function to get the variant's descriptive string
function getVariantDetails($pdo, $variant_id) {
    $sql = "SELECT GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) ORDER BY a.name SEPARATOR ', ') AS options_string
            FROM product_variants pv
            LEFT JOIN product_variant_options pvo ON pv.variant_id = pvo.variant_id
            LEFT JOIN attribute_options ao ON pvo.option_id = ao.option_id
            LEFT JOIN attributes a ON ao.attribute_id = a.attribute_id
            WHERE pv.variant_id = :variant_id
            GROUP BY pv.variant_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':variant_id' => $variant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['options_string'] : 'Unknown Variant';
}

// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $variant_id_post = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT);

    if ($variant_id_post === $variant_id) {
        if (isVariantInUse($pdo, $variant_id)) {
            $error_message = "Cannot delete this variant because it is part of one or more past orders.";
        } else {
            try {
                // The ON DELETE CASCADE constraint will automatically remove entries
                // from product_variant_options when the variant is deleted.
                $sql = "DELETE FROM product_variants WHERE variant_id = :variant_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':variant_id' => $variant_id]);

                $_SESSION['success_message'] = "The variant was successfully deleted.";
                header("Location: manage_variants.php?product_id=" . $product_id);
                exit();
            } catch (PDOException $e) {
                $error_message = "Database Error: Could not delete the variant. " . $e->getMessage();
            }
        }
    } else {
        $error_message = "ID mismatch. Deletion failed.";
    }

// --- INITIAL PAGE LOAD (GET REQUEST) ---
} else {
    if (isVariantInUse($pdo, $variant_id)) {
        $error_message = "Cannot delete this variant because it is part of one or more past orders. To make it unavailable, please edit it and set its stock to 0.";
    } else {
        $variant_details = getVariantDetails($pdo, $variant_id);
        if ($variant_details === 'Unknown Variant') {
             $error_message = "Could not find the specified variant.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Variant - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Delete Variant</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <a href="manage_variants.php?product_id=<?= $product_id ?>" class="btn btn-secondary">Back to Variants</a>
        <?php else: ?>
            <div class="alert alert-warning">
                <h4 class="alert-heading">Are you sure?</h4>
                <p>You are about to permanently delete the variant with the following options: <strong><?= htmlspecialchars($variant_details) ?></strong>.</p>
                <hr>
                <p class="mb-0">This action cannot be undone.</p>
            </div>

            <form action="delete_variant.php?id=<?= $variant_id ?>&product_id=<?= $product_id ?>" method="POST">
                <input type="hidden" name="variant_id" value="<?= htmlspecialchars($variant_id) ?>">
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
                <a href="manage_variants.php?product_id=<?= $product_id ?>" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>