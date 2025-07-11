<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$product = null;
$variant = null;
$attributes = [];
$selected_option_ids = [];

$variant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

// --- VALIDATE IDs ---
if (!$variant_id || !$product_id) {
    header("Location: manage_products.php?error=invalidids");
    exit();
}

// --- DATA FETCHING (for initial page load) ---
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    try {
        // Fetch product name
        $stmt_product = $pdo->prepare("SELECT product_name FROM products WHERE product_id = :product_id");
        $stmt_product->execute([':product_id' => $product_id]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

        // Fetch variant details
        $stmt_variant = $pdo->prepare("SELECT * FROM product_variants WHERE variant_id = :variant_id");
        $stmt_variant->execute([':variant_id' => $variant_id]);
        $variant = $stmt_variant->fetch(PDO::FETCH_ASSOC);
        
        if (!$product || !$variant) throw new Exception("Product or Variant not found.");

        // Fetch all available attributes and their options
        $stmt_attrs = $pdo->query("SELECT a.attribute_id, a.name, ao.option_id, ao.value FROM attributes a JOIN attribute_options ao ON a.attribute_id = ao.attribute_id ORDER BY a.name, ao.value");
        $all_options = $stmt_attrs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_options as $option) {
            $attributes[$option['name']]['attribute_id'] = $option['attribute_id'];
            $attributes[$option['name']]['options'][] = ['option_id' => $option['option_id'], 'value' => $option['value']];
        }

        // Fetch the currently selected options for this variant
        $stmt_selected = $pdo->prepare("SELECT option_id FROM product_variant_options WHERE variant_id = :variant_id");
        $stmt_selected->execute([':variant_id' => $variant_id]);
        $selected_option_ids = $stmt_selected->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
        $error_message = "Error fetching data: " . $e->getMessage();
    }
}


// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    // Re-fetch product name for context in case of error
    $stmt_product = $pdo->prepare("SELECT product_name FROM products WHERE product_id = :product_id");
    $stmt_product->execute([':product_id' => $product_id]);
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    // Repopulate attributes for form redisplay
    $stmt_attrs = $pdo->query("SELECT a.attribute_id, a.name, ao.option_id, ao.value FROM attributes a JOIN attribute_options ao ON a.attribute_id = ao.attribute_id ORDER BY a.name, ao.value");
    $all_options = $stmt_attrs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_options as $option) {
        $attributes[$option['name']]['attribute_id'] = $option['attribute_id'];
        $attributes[$option['name']]['options'][] = ['option_id' => $option['option_id'], 'value' => $option['value']];
    }
    
    // Get submitted data
    $variant_id_post = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT);
    $sku = trim($_POST['sku']);
    $price = trim($_POST['price']);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
    $selected_options = $_POST['options'] ?? [];
    
    // --- Validation ---
    $errors = [];
    if ($variant_id_post !== $variant_id) $errors[] = "Variant ID mismatch.";
    if ($stock_quantity === false || $stock_quantity < 0) $errors[] = "Stock quantity must be a non-negative integer.";
    if (!empty($price) && !is_numeric($price)) $errors[] = "Price must be a number if specified.";
    if (empty($selected_options)) $errors[] = "A variant must have at least one option selected.";

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Step 1: Update the main variant record
            $sql_variant = "UPDATE product_variants SET sku = :sku, price = :price, stock_quantity = :stock_quantity WHERE variant_id = :variant_id";
            $stmt_variant = $pdo->prepare($sql_variant);
            $stmt_variant->execute([
                ':sku' => !empty($sku) ? $sku : null,
                ':price' => !empty($price) ? $price : null,
                ':stock_quantity' => $stock_quantity,
                ':variant_id' => $variant_id
            ]);

            // Step 2: Delete old option links for this variant
            $stmt_delete_links = $pdo->prepare("DELETE FROM product_variant_options WHERE variant_id = :variant_id");
            $stmt_delete_links->execute([':variant_id' => $variant_id]);

            // Step 3: Insert the new option links
            $sql_link = "INSERT INTO product_variant_options (variant_id, option_id) VALUES (:variant_id, :option_id)";
            $stmt_link = $pdo->prepare($sql_link);
            foreach ($selected_options as $option_id) {
                if (!empty($option_id)) { // Ensure we don't insert empty values
                    $stmt_link->execute([':variant_id' => $variant_id, ':option_id' => $option_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Variant updated successfully!";
            header("Location: manage_variants.php?product_id=" . $product_id);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Database transaction failed: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }

    // Repopulate variant data for form redisplay on error
    $variant = $_POST;
    $selected_option_ids = $selected_options;
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Variant - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($product && $variant): ?>
            <h1 class="mb-4">Edit Variant for: <strong><?= htmlspecialchars($product['product_name']) ?></strong></h1>

            <form action="edit_variant.php?id=<?= $variant_id ?>&product_id=<?= $product_id ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="variant_id" value="<?= $variant_id ?>">

                <div class="card p-3 mb-4">
                    <h5>Variant Attributes</h5>
                    <div class="row">
                        <?php foreach ($attributes as $name => $data): ?>
                            <div class="col-md-4 mb-3">
                                <label for="option_<?= $data['attribute_id'] ?>" class="form-label"><strong><?= htmlspecialchars($name) ?></strong></label>
                                <select class="form-select" name="options[]" id="option_<?= $data['attribute_id'] ?>">
                                    <option value="">-- Not Applicable --</option>
                                    <?php foreach ($data['options'] as $option): ?>
                                        <option value="<?= $option['option_id'] ?>" <?= in_array($option['option_id'], $selected_option_ids) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option['value']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card p-3">
                    <h5>Variant Details</h5>
                     <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sku" class="form-label">SKU (Optional)</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?= htmlspecialchars($variant['sku'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label">Specific Price (Optional)</label>
                             <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($variant['price'] ?? '') ?>" placeholder="Overrides base price">
                            </div>
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="stock_quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" value="<?= htmlspecialchars($variant['stock_quantity'] ?? 0) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update Variant</button>
                    <a href="manage_variants.php?product_id=<?= $product_id ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>