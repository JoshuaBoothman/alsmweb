<?php
// admin/manage_campgrounds.php

// --- SECURITY AND INITIALIZATION ---
session_start();
// This block ensures that only logged-in administrators can access this page.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

// --- DATA FETCHING ---
$campgrounds = [];
$error_message = '';
try {
    // This query fetches all campgrounds and joins with the events table
    // to display which event each campground belongs to.
    $sql = "SELECT c.campground_id, c.name, c.is_active, e.event_name 
            FROM campgrounds c
            JOIN events e ON c.event_id = e.event_id
            ORDER BY e.start_date DESC, c.name ASC";
    $stmt = $pdo->query($sql);
    $campgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch campgrounds. " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage Campgrounds';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage Campgrounds</h1>

    <?php
    // Display success or error messages from session after redirects
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="add_campground.php" class="btn btn-success">Add New Campground</a>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Campground Name</th>
                <th>Associated Event</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($campgrounds)): ?>
                <?php foreach ($campgrounds as $campground): ?>
                    <tr>
                        <td><?= htmlspecialchars($campground['name']) ?></td>
                        <td><?= htmlspecialchars($campground['event_name']) ?></td>
                        <td>
                            <?php if ($campground['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="manage_campsites.php?campground_id=<?= $campground['campground_id'] ?>" class="btn btn-info btn-sm">Manage Sites</a>
                            <a href="edit_campground.php?id=<?= $campground['campground_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_campground.php?id=<?= $campground['campground_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">No campgrounds found. <a href="add_campground.php">Add one now</a>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

---
---
