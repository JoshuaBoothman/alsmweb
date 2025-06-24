<?php
// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';
session_start(); // Start the session to handle cart data later

// --- INITIALIZE VARIABLES ---
$product = null;
$variants = [];
$attributes = [];
$error_message = '';
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- VALIDATE PRODUCT ID ---
if (!$product_id) {
    $error_message = "No product selected.";
} else {
    // --- DATA FETCHING ---
    try {
        // 1. Fetch the main product details
        $sql_product = "SELECT * FROM products WHERE product_id = :product_id AND is_active = 1 AND is_deleted = 0";
        $stmt_product = $pdo->prepare($sql_product);
        $stmt_product->execute([':product_id' => $product_id]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Product not found or is unavailable.");
        }

        // 2. Fetch all variants for this product, along with their options
        $sql_variants = "
            SELECT 
                pv.variant_id, pv.sku, pv.price, pv.stock_quantity,
                pvo.option_id,
                a.name AS attribute_name,
                ao.value AS option_value
            FROM product_variants AS pv
            JOIN product_variant_options AS pvo ON pv.variant_id = pvo.variant_id
            JOIN attribute_options AS ao ON pvo.option_id = ao.option_id
            JOIN attributes AS a ON ao.attribute_id = a.attribute_id
            WHERE pv.product_id = :product_id AND pv.is_active = 1
            ORDER BY pv.variant_id, a.name";
        
        $stmt_variants = $pdo->prepare($sql_variants);
        $stmt_variants->execute([':product_id' => $product_id]);
        $results = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Process the results into a structured array for easy use in HTML
        // This creates an array of variants, where each variant has an array of its options.
        $temp_variants = [];
        foreach ($results as $row) {
            $temp_variants[$row['variant_id']]['details'] = [
                'sku' => $row['sku'],
                'price' => $row['price'],
                'stock_quantity' => $row['stock_quantity']
            ];
            $temp_variants[$row['variant_id']]['options'][$row['attribute_name']] = $row['option_value'];
        }
        $variants = $temp_variants;

        // 4. Create a unique list of attributes and options for the dropdowns
        foreach ($variants as $variant) {
            foreach ($variant['options'] as $attr_name => $opt_value) {
                $attributes[$attr_name][] = $opt_value;
            }
        }
        foreach ($attributes as $key => $value) {
            $attributes[$key] = array_unique($value);
            sort($attributes[$key]);
        }

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// --- PAGE SETUP ---
$page_title = $product ? htmlspecialchars($product['product_name']) : 'Product Not Found';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="merchandise.php" class="btn btn-primary">Back to Merchandise</a>
    <?php elseif ($product): ?>
        <div class="row">
            <!-- Product Image Column -->
            <div class="col-md-6">
                <img src="<?= htmlspecialchars($product['image_path'] ?? 'https://placehold.co/600x400?text=Product+Image') ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($product['product_name']) ?>">
            </div>

            <!-- Product Details Column -->
            <div class="col-md-6">
                <h2><?= htmlspecialchars($product['product_name']) ?></h2>
                <h4 class="text-success">$<?= htmlspecialchars(number_format($product['base_price'], 2)) ?></h4>
                <p class="lead"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

                <hr>

                <!-- Add to Cart Form -->
                <form action="cart_actions.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">

                    <?php if (!empty($attributes)): ?>
                        <h5>Options</h5>
                        <?php foreach ($attributes as $attr_name => $options_array): ?>
                            <div class="mb-3">
                                <label for="attr_<?= str_replace(' ', '_', $attr_name) ?>" class="form-label"><strong><?= htmlspecialchars($attr_name) ?>:</strong></label>
                                <select class="form-select" name="options[<?= htmlspecialchars($attr_name) ?>]" id="attr_<?= str_replace(' ', '_', $attr_name) ?>" required>
                                    <option value="">Select <?= htmlspecialchars($attr_name) ?></option>
                                    <?php foreach ($options_array as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label"><strong>Quantity:</strong></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="10" required>
                        </div>
                        <div class="col-md-8">
                            <button type="submit" class="btn btn-primary btn-lg w-100">Add to Cart</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
