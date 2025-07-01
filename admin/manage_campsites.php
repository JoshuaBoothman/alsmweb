<?php
// admin/manage_campsites.php

// --- SECURITY AND INITIALIZATION ---
session_start();
// This block ensures that only logged-in administrators can access this page.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$campsites = [];
$campground_name = 'Unknown Campground';
$error_message = '';
// This page is context-dependent. It requires a campground_id from the URL.
$campground_id = filter_input(INPUT_GET, 'campground_id', FILTER_VALIDATE_INT);

// If no valid campground_id is provided, redirect the user.
if (!$campground_id) {
    header("Location: manage_campgrounds.php?error=nocampgroundid");
    exit();
}

// --- DATA FETCHING ---
try {
    // 1. Fetch the parent campground's name for the page title.
    $stmt_cg_name = $pdo->prepare("SELECT name FROM campgrounds WHERE campground_id = :id");
    $stmt_cg_name->execute([':id' => $campground_id]);
    $campground = $stmt_cg_name->fetch(PDO::FETCH_ASSOC);

    if ($campground) {
        $campground_name = $campground['name'];
    } else {
        throw new Exception("The specified campground could not be found.");
    }

    // 2. Fetch all campsites associated with this specific campground.
    $stmt_sites = $pdo->prepare("SELECT * FROM campsites WHERE campground_id = :id ORDER BY name ASC");
    $stmt_sites->execute([':id' => $campground_id]);
    $campsites = $stmt_sites->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage ' . htmlspecialchars($campground_name);
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage Sites for: <strong><?= htmlspecialchars($campground_name) ?></strong></h1>

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
    
    <div class="d-flex justify-content-between mb-3">
        <a href="manage_campgrounds.php" class="btn btn-secondary">&laquo; Back to All Campgrounds</a>
        <a href="add_campsite.php?campground_id=<?= $campground_id ?>" class="btn btn-success">Add New Campsite</a>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Campsite Name/Number</th>
                <th>Price Per Night</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($campsites)): ?>
                <?php foreach ($campsites as $campsite): ?>
                    <tr>
                        <td><?= htmlspecialchars($campsite['name']) ?></td>
                        <td>$<?= htmlspecialchars(number_format($campsite['price_per_night'], 2)) ?></td>
                        <td>
                            <?php if ($campsite['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_campsite.php?id=<?= $campsite['campsite_id'] ?>&campground_id=<?= $campground_id ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_campsite.php?id=<?= $campsite['campsite_id'] ?>&campground_id=<?= $campground_id ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">No individual campsites found for this campground. <a href="add_campsite.php?campground_id=<?= $campground_id ?>">Add one now</a>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
