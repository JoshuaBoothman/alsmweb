<?php
// admin/add_campground.php

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
$events = [];

// --- DATA FETCHING for Events Dropdown ---
try {
    $stmt = $pdo->query("SELECT event_id, event_name FROM events WHERE event_IsDeleted = 0 ORDER BY start_date DESC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch events. " . $e->getMessage();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $errors = [];

    // Validation
    if (empty($name)) $errors[] = "Campground Name is required.";
    if (!$event_id) $errors[] = "You must associate this campground with an event.";
    
    // If validation passes, insert into DB
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO campgrounds (name, description, event_id, is_active) VALUES (:name, :description, :event_id, :is_active)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':event_id' => $event_id,
                ':is_active' => $is_active
            ]);

            $_SESSION['success_message'] = "Campground '".htmlspecialchars($name)."' was created successfully!";
            header("Location: manage_campgrounds.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not create the campground. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
// --- HEADER ---
$page_title = 'Add New Campground';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Add New Campground</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <form action="add_campground.php" method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Campground Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>

        <div class="mb-3">
            <label for="event_id" class="form-label">Associated Event</label>
            <select class="form-select" id="event_id" name="event_id" required>
                <option value="">-- Select an Event --</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= $event['event_id'] ?>">
                        <?= htmlspecialchars($event['event_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
        </div>
        
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" checked>
            <label class="form-check-label" for="is_active">
                Active
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Save Campground</button>
        <a href="manage_campgrounds.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>

---
---