<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

$attributes = [];
$error_message = '';

try {
    // Fetch all attributes from the database
    $sql = "SELECT attribute_id, name FROM attributes ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch attributes. " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attributes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Manage Product Attributes</h1>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if ($error_message) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
        }
        ?>

        <div class="d-flex justify-content-between mb-3">
             <a href="manage_products.php" class="btn btn-secondary">&laquo; Back to Products</a>
             <a href="add_attribute.php" class="btn btn-success">Add New Attribute</a>
        </div>


        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Attribute Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($attributes)): ?>
                    <?php foreach ($attributes as $attribute): ?>
                        <tr>
                            <td><?= htmlspecialchars($attribute['name']) ?></td>
                            <td>
                                <!-- This will link to a page to manage the options (e.g., Red, Blue, Green for Color) -->
                                <a href="manage_options.php?attribute_id=<?= $attribute['attribute_id'] ?>" class="btn btn-info btn-sm">Manage Options</a>
                                <a href="edit_attribute.php?id=<?= $attribute['attribute_id'] ?>" class="btn btn-primary btn-sm">Edit Name</a>
                                <a href="delete_attribute.php?id=<?= $attribute['attribute_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">No attributes found. Click "Add New Attribute" to create one (e.g., 'Color', 'Size').</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>