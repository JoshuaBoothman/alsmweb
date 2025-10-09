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
$product = null;
$variants = [];
$error_message = '';
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

// --- VALIDATE PRODUCT ID ---
if (!$product_id) {
    // If no product_id is provided, redirect back to the main products page.
    header("Location: manage_products.php?error=noproductid");
    exit();
}

// --- FORM PROCESSING FOR STOCK UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['stock'])) {
    $stock_levels = $_POST['stock'];
    
    try {
        $pdo->beginTransaction();

        $sql_update = "UPDATE product_variants SET stock_quantity = :quantity WHERE variant_id = :variant_id AND product_id = :product_id";
        $stmt_update = $pdo->prepare($sql_update);

        foreach ($stock_levels as $variant_id => $quantity) {
            // Basic validation
            $variant_id = filter_var($variant_id, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

            if ($variant_id !== false && $quantity !== false) {
                $stmt_update->execute([
                    ':quantity' => $quantity,
                    ':variant_id' => $variant_id,
                    ':product_id' => $product_id // Security check
                ]);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Stock levels updated successfully.";
        // Redirect to prevent form resubmission
        header("Location: manage_variants.php?product_id=" . $product_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        // Set an error message to be displayed
        $error_message = "Database error: Could not update stock levels. " . $e->getMessage();
    }
}




// --- DATA FETCHING ---
try {
    // 1. Fetch the parent product's name for the page title and context.
    $sql_product = "SELECT product_name FROM products WHERE product_id = :product_id";
    $stmt_product = $pdo->prepare($sql_product);
    $stmt_product->execute([':product_id' => $product_id]);
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // If the product ID is invalid, stop and show an error.
        throw new Exception("Product not found.");
    }

    // 2. Fetch all variants for this specific product.
    // This is a more advanced query that joins all the new variant tables together.
    // GROUP_CONCAT is a powerful MySQL function that aggregates multiple rows into a single string,
    // which is perfect for displaying the combined options like "Color: Red, Size: Large".
    $sql_variants = "
        SELECT 
            pv.variant_id,
            pv.sku,
            pv.price,
            pv.stock_quantity,
            GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) ORDER BY a.name SEPARATOR ', ') AS options_string
        FROM 
            product_variants AS pv
        LEFT JOIN 
            product_variant_options AS pvo ON pv.variant_id = pvo.variant_id
        LEFT JOIN 
            attribute_options AS ao ON pvo.option_id = ao.option_id
        LEFT JOIN 
            attributes AS a ON ao.attribute_id = a.attribute_id
        WHERE 
            pv.product_id = :product_id
        GROUP BY 
            pv.variant_id
        ORDER BY 
            pv.sort_order ASC";
            
    $stmt_variants = $pdo->prepare($sql_variants);
    $stmt_variants->execute([':product_id' => $product_id]);
    $variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>
<?php
// --- HEADER ---
$page_title = 'Manage Variants';
require_once __DIR__ . '/../templates/header.php';
?>
<body>
    <div class="container mt-5">
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <a href="manage_products.php" class="btn btn-secondary">Back to Products</a>
        <?php else: ?>
            <h1 class="mb-4">
                Manage Variants for: <strong><?= htmlspecialchars($product['product_name']) ?></strong>
            </h1>

            <?php
            // Display success messages from session after redirects
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <div class="d-flex justify-content-between mb-3">
                <a href="manage_products.php" class="btn btn-secondary">&laquo; Back to Products</a>
                <div>
                    <!-- This button will eventually link to a page to manage attributes like "Color", "Size" globally -->
                    <a href="manage_attributes.php" class="btn btn-outline-secondary">Manage Attributes</a>
                    <a href="add_variant.php?product_id=<?= $product_id ?>" class="btn btn-success">Add New Variant</a>
                </div>
            </div>

            <form action="manage_variants.php?product_id=<?= $product_id ?>" method="POST">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th> <th>Variant (Options)</th>
                            <th>SKU</th>
                            <th>Specific Price</th>
                            <th style="width: 120px;">Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-variants">
                        <?php foreach ($variants as $variant): ?>
                            <tr data-id="<?= $variant['variant_id'] ?>">
                                
                                <td>
                                    <span class="handle" style="cursor: grab;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-grip-vertical" viewBox="0 0 16 16">
                                            <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                                        </svg>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($variant['options_string'] ?? 'Base Product') ?></td>
                                <td><?= htmlspecialchars($variant['sku'] ?? 'N/A') ?></td>
                                <td><?= $variant['price'] !== null ? '$' . htmlspecialchars(number_format($variant['price'], 2)) : '(Uses Base Price)' ?></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm stock-input" 
                                        name="stock[<?= $variant['variant_id'] ?>]" 
                                        value="<?= htmlspecialchars($variant['stock_quantity']) ?>" 
                                        data-variant-id="<?= $variant['variant_id'] ?>"
                                        min="0">
                                </td>
                                <td>
                                    <a href="edit_variant.php?id=<?= $variant['variant_id'] ?>&product_id=<?= $product_id ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <a href="delete_variant.php?id=<?= $variant['variant_id'] ?>&product_id=<?= $product_id ?>" class="btn btn-danger btn-sm">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($variants)): ?>
                    <button type="submit" class="btn btn-primary">Update All Stock Levels</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('sortable-variants');
    
    // 1. Initialize SortableJS on the table body
    const sortable = new Sortable(tableBody, {
        handle: '.handle', // This is the line you add
        animation: 150,
        
        // 2. This function runs after the user drops a row
        onEnd: function (evt) {
            // Get an array of all the variant IDs in their new order
            const newOrder = sortable.toArray();
            
            // 3. Send this new order to a PHP script on the server
            fetch('/alsmweb/public_html/api/api_update_sort_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order: newOrder })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Sort order saved successfully!');
                    // You could add a temporary "Saved!" notification here
                } else {
                    console.error('Failed to save sort order.');
                    alert('Error: Could not save the new order.');
                }
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>