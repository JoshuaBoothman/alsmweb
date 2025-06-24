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
$attribute = null;
$options = [];
$error_message = '';
$attribute_id = filter_input(INPUT_GET, 'attribute_id', FILTER_VALIDATE_INT);

// --- VALIDATE ATTRIBUTE ID ---
if (!$attribute_id) {
    header("Location: manage_attributes.php?error=noattributeid");
    exit();
}

// --- DATA FETCHING ---
try {
    // 1. Fetch the parent attribute's name for context
    $sql_attr = "SELECT name FROM attributes WHERE attribute_id = :attribute_id";
    $stmt_attr = $pdo->prepare($sql_attr);
    $stmt_attr->execute([':attribute_id' => $attribute_id]);
    $attribute = $stmt_attr->fetch(PDO::FETCH_ASSOC);

    if (!$attribute) {
        throw new Exception("Attribute not found.");
    }

    // 2. Fetch all options belonging to this attribute
    $sql_opts = "SELECT option_id, value FROM attribute_options WHERE attribute_id = :attribute_id ORDER BY value ASC";
    $stmt_opts = $pdo->prepare($sql_opts);
    $stmt_opts->execute([':attribute_id' => $attribute_id]);
    $options = $stmt_opts->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Options - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <a href="manage_attributes.php" class="btn btn-secondary">Back to Attributes</a>
        <?php else: ?>
            <h1 class="mb-4">
                Manage Options for: <strong><?= htmlspecialchars($attribute['name']) ?></strong>
            </h1>

            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <div class="d-flex justify-content-between mb-3">
                <a href="manage_attributes.php" class="btn btn-secondary">&laquo; Back to Attributes</a>
                <a href="add_option.php?attribute_id=<?= $attribute_id ?>" class="btn btn-success">Add New Option</a>
            </div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Option Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($options)): ?>
                        <?php foreach ($options as $option): ?>
                            <tr>
                                <td><?= htmlspecialchars($option['value']) ?></td>
                                <td>
                                    <a href="edit_option.php?id=<?= $option['option_id'] ?>&attribute_id=<?= $attribute_id ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <a href="delete_option.php?id=<?= $option['option_id'] ?>&attribute_id=<?= $attribute_id ?>" class="btn btn-danger btn-sm">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">No options found for this attribute.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>