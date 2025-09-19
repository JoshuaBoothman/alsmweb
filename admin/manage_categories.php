<?php
// --- SECURITY AND INITIALIZATION ---
session_start();

// Ensure user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- DATABASE LOGIC & PAGE SETUP ---
require_once '../config/db_config.php';

$categories = []; // Initialize an empty array for categories
$error_message = '';

try {
    // Corrected SQL to use the 'categories' table and lowercase column names
    $sql = "SELECT category_id, category_name, description FROM categories ORDER BY category_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If the query fails, set an error message to display
    $error_message = "Database Error: Could not fetch categories. " . $e->getMessage();
}
?>
<?php
// --- HEADER ---
$page_title = 'Manage Categories';
require_once __DIR__ . '/../templates/header.php';
?>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Category Management</h1>

        <?php
        // Display a success message if one was set in the session
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        // Display an error message if the database query failed
        if ($error_message) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
        }
        ?>

        <a href="add_category.php" class="btn btn-success mb-3">Add New Category</a>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= htmlspecialchars($category['category_name']) ?></td>
                            <td><?= htmlspecialchars($category['description']) ?></td>
                            <td>
                                <a href="edit_category.php?id=<?= $category['category_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_category.php?id=<?= $category['category_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No categories found. Click "Add New Category" to create one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>