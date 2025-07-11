<?php
// admin/edit_campground.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$campground = null;
$events = [];
$campground_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$campground_id) {
    header("Location: manage_campgrounds.php");
    exit();
}

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $campground_id = filter_input(INPUT_POST, 'campground_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $current_map_path = $_POST['current_map_path'];
    $errors = [];

    if (empty($name) || !$event_id || !$campground_id) $errors[] = "Name and Event are required.";

    // --- Handle Image Upload ---
    $new_map_path = $current_map_path;
    if (isset($_FILES['map_image']) && $_FILES['map_image']['error'] == 0) {
        $upload_dir = '../public_html/assets/images/maps/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid('map-', true) . '-' . basename($_FILES['map_image']['name']);
        $target_file = $upload_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validation
        if (getimagesize($_FILES['map_image']['tmp_name']) === false) {
            $errors[] = "File is not a valid image.";
        } elseif ($_FILES['map_image']['size'] > 5000000) { // 5MB limit
            $errors[] = "Sorry, the map image is too large.";
        } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed for maps.";
        }

        if (empty($errors)) {
            if (move_uploaded_file($_FILES['map_image']['tmp_name'], $target_file)) {
                $new_map_path = '/alsmweb/public_html/assets/images/maps/' . $file_name;
                // Delete old map if it exists
                if ($current_map_path && file_exists('..' . $current_map_path)) {
                    unlink('..' . $current_map_path);
                }
            } else {
                $errors[] = "An error occurred while uploading the map image.";
            }
        }
    }


    if (empty($errors)) {
        try {
            $sql = "UPDATE campgrounds SET 
                        name = :name, 
                        description = :description, 
                        event_id = :event_id, 
                        is_active = :is_active,
                        map_image_path = :map_image_path
                    WHERE campground_id = :campground_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':event_id' => $event_id,
                ':is_active' => $is_active,
                ':map_image_path' => $new_map_path,
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
        $campground = $_POST;
    }
}

// --- DATA FETCHING (for initial load) ---
try {
    $stmt_events = $pdo->query("SELECT event_id, event_name FROM events ORDER BY start_date DESC");
    $events = $stmt_events->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$campground) {
        $stmt_camp = $pdo->prepare("SELECT * FROM campgrounds WHERE campground_id = :id");
        $stmt_camp->execute([':id' => $campground_id]);
        $campground = $stmt_camp->fetch(PDO::FETCH_ASSOC);
        if (!$campground) $error_message = "Campground not found.";
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();

$page_title = 'Edit Campground';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Edit Campground</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($campground): ?>
    <form action="edit_campground.php?id=<?= $campground_id ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="campground_id" value="<?= htmlspecialchars($campground['campground_id']) ?>">
        <input type="hidden" name="current_map_path" value="<?= htmlspecialchars($campground['map_image_path']) ?>">
        
        <div class="row">
            <div class="col-md-8">
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
            </div>
            <div class="col-md-4">
                <label class="form-label">Current Map Image</label>
                <img src="<?= htmlspecialchars($campground['map_image_path'] ?? 'https://placehold.co/400x300?text=No+Map') ?>" class="img-fluid rounded border mb-2" alt="Current map image">
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($campground['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="map_image" class="form-label">Upload New Map Image (Optional)</label>
            <input class="form-control" type="file" id="map_image" name="map_image">
            <div class="form-text">Upload a new image to replace the current one. Leave blank to keep the existing map.</div>
        </div>
        
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= !empty($campground['is_active']) ? 'checked' : '' ?>>
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
