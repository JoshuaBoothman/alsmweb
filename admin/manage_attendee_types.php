<?php
// admin/manage_attendee_types.php

// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

// --- DATA FETCHING ---
$attendee_types = [];
$error_message = '';
try {
    $sql = "SELECT * FROM attendee_types ORDER BY type_name ASC";
    $stmt = $pdo->query($sql);
    $attendee_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch attendee types. " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage Attendee Types';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage Attendee Types</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="add_attendee_type.php" class="btn btn-success">Add New Attendee Type</a>
    </div>

    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Type Name</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($attendee_types)): ?>
                <?php foreach ($attendee_types as $type): ?>
                    <tr>
                        <td><?= htmlspecialchars($type['type_name']) ?></td>
                        <td>$<?= htmlspecialchars(number_format($type['price'], 2)) ?></td>
                        <td>
                            <?php if ($type['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_attendee_type.php?id=<?= $type['type_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_attendee_type.php?id=<?= $type['type_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">No attendee types found. <a href="add_attendee_type.php">Add one now</a>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
