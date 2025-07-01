<?php
// admin/edit_campground.php

// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$campground = null;
$events = [];
$campground_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- VALIDATE ID ---
if (!$campground_id) {
    header("Location: manage_campgrounds.php");
    exit();
}

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $campground_id = filter_input(INPUT_POST, 'campground_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $errors = [];

    if (empty($name) || !$event_id || !$campground_id) $errors[] = "Name and Event are required.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE campgrounds SET name = :name, description = :description, event_id = :event_id, is_active = :is_active WHERE campground_id = :campground_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':event_id' => $event_id,
                ':is_active' => $is_active,
                ':campground_id' => $campground_id
            ]);
            $_SESSION['success_message'] = "Campground updated successfully!";
            header("Location: manage_campgrounds.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
        // Repopulate form with attempted values
        $campground = $_POST;
    }
}

// --- DATA FETCHING (for initial load) ---
try {
    // Fetch events for the dropdown
    $stmt_events = $pdo->query("SELECT event_id, event_name FROM events ORDER BY start_date DESC");
    $events = $stmt_events->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch the campground to edit
    if (!$campground) { // Only fetch if not populated by POST error
        $stmt_camp = $pdo->prepare("SELECT * FROM campgrounds WHERE campground_id = :id");
        $stmt_camp->execute([':id' => $campground_id]);
        $campground = $stmt_camp->fetch(PDO::FETCH_ASSOC);
        if (!$campground) $error_message = "Campground not found.";
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Edit Campground';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Edit Campground</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($campground): ?>
    <form action="edit_campground.php?id=<?= $campground_id ?>" method="POST">
        <input type="hidden" name="campground_id" value="<?= $campground['campground_id'] ?>">
        
        <div class="mb-3">
            <label for="name" class="form-label">Campground Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($campground['name']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="event_id" class="form-label">Associated Event</label>
            <select class="form-select" id="event_id" name="event_id" required>
                <?php foreach ($events as $event): ?>
                    <option value="<?= $event['event_id'] ?>" <?= ($campground['event_id'] == $event['event_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($event['event_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($campground['description']) ?></textarea>
        </div>
        
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= ($campground['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">
                Active
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Update Campground</button>
        <a href="manage_campgrounds.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

---
---