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
$attributes = [];
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

// --- VALIDATE PRODUCT ID ---
if (!$product_id) {
    header("Location: manage_products.php?error=noproductid");
    exit();
}

// --- DATA FETCHING (Product Name and Attributes with their Options) ---
try {
    // 1. Fetch the parent product name
    $sql_product = "SELECT product_name FROM products WHERE product_id = :product_id";
    $stmt_product = $pdo->prepare($sql_product);
    $stmt_product->execute([':product_id' => $product_id]);
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Product not found.");
    }

    // 2. Fetch all attributes and their options to build the form
    $sql_attrs = "SELECT a.attribute_id, a.name, ao.option_id, ao.value 
                  FROM attributes a
                  JOIN attribute_options ao ON a.attribute_id = ao.attribute_id
                  ORDER BY a.name, ao.value";
    $stmt_attrs = $pdo->query($sql_attrs);
    $all_options = $stmt_attrs->fetchAll(PDO::FETCH_ASSOC);

    // Group options by attribute for easy form generation
    foreach ($all_options as $option) {
        $attributes[$option['name']]['attribute_id'] = $option['attribute_id'];
        $attributes[$option['name']]['options'][] = [
            'option_id' => $option['option_id'],
            'value' => $option['value']
        ];
    }

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $product_id_post = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $sku = trim($_POST['sku']);
    $price = trim($_POST['price']);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
    $selected_options = $_POST['options'] ?? [];
    
    // --- Validation ---
    $errors = [];
    if ($product_id_post != $product_id) $errors[] = "Product ID mismatch.";
    if ($stock_quantity === false || $stock_quantity < 0) $errors[] = "Stock quantity must be a valid, non-negative integer.";
    if (!empty($price) && (!is_numeric($price) || $price < 0)) $errors[] = "Price must be a valid, non-negative number if specified.";
    if (empty($selected_options)) $errors[] = "You must select at least one option for the variant.";

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Step 1: Insert the main variant record
            $sql_variant = "INSERT INTO product_variants (product_id, sku, price, stock_quantity) VALUES (:product_id, :sku, :price, :stock_quantity)";
            $stmt_variant = $pdo->prepare($sql_variant);
            $stmt_variant->execute([
                ':product_id' => $product_id,
                ':sku' => !empty($sku) ? $sku : null,
                ':price' => !empty($price) ? $price : null,
                ':stock_quantity' => $stock_quantity
            ]);
            $variant_id = $pdo->lastInsertId();

            // Step 2: Link the selected options to the new variant
            $sql_link = "INSERT INTO product_variant_options (variant_id, option_id) VALUES (:variant_id, :option_id)";
            $stmt_link = $pdo->prepare($sql_link);
            foreach ($selected_options as $option_id) {
                $stmt_link->execute([
                    ':variant_id' => $variant_id,
                    ':option_id' => $option_id
                ]);
            }
            
            // If everything was successful, commit the transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "New variant was created successfully!";
            header("Location: manage_variants.php?product_id=" . $product_id);
            exit();

        } catch (Exception $e) {
            // If any part fails, roll back the transaction
            $pdo->rollBack();
            $error_message = "Database transaction failed: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Generate a CSRF token to be included in the form.
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Variant - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($product): ?>
            <h1 class="mb-4">Add Variant for: <strong><?= htmlspecialchars($product['product_name']) ?></strong></h1>

            <form action="add_variant.php?product_id=<?= $product_id ?>" method="POST">
                <!-- CSRF Token for security -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="product_id" value="<?= $product_id ?>">

                <div class="card p-3 mb-4">
                    <h5>Variant Attributes</h5>
                    <p class="text-muted">Select one option from each relevant attribute group to define this unique variant.</p>
                    <div class="row">
                        <?php if (!empty($attributes)): ?>
                            <?php foreach ($attributes as $name => $data): ?>
                                <div class="col-md-4 mb-3">
                                    <label for="option_<?= $data['attribute_id'] ?>" class="form-label"><strong><?= htmlspecialchars($name) ?></strong></label>
                                    <select class="form-select" name="options[]" id="option_<?= $data['attribute_id'] ?>">
                                        <option value="">-- Select <?= htmlspecialchars($name) ?> --</option>
                                        <?php foreach ($data['options'] as $option): ?>
                                            <option value="<?= $option['option_id'] ?>"><?= htmlspecialchars($option['value']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col">
                                <p class="text-warning">No attributes have been defined yet. Please <a href="manage_attributes.php">add attributes and options</a> first.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card p-3">
                    <h5>Variant Details</h5>
                     <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sku" class="form-label">SKU (Optional)</label>
                            <input type="text" class="form-control" id="sku" name="sku" placeholder="Unique product code">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label">Specific Price (Optional)</label>
                             <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" placeholder="Overrides base price">
                            </div>
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="stock_quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" value="0" required>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" <?= empty($attributes) ? 'disabled' : '' ?>>Save Variant</button>
                    <a href="manage_variants.php?product_id=<?= $product_id ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>