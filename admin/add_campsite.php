<?php
// admin/add_campsite.php

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
$campground_name = 'Unknown Campground';
// This page requires a campground_id from the URL to know where to add the new site.
$campground_id = filter_input(INPUT_GET, 'campground_id', FILTER_VALIDATE_INT);

if (!$campground_id) {
    header("Location: manage_campgrounds.php?error=nocampgroundid");
    exit();
}

// --- DATA FETCHING for context ---
try {
    // Fetch the parent campground's name to display in the title.
    $stmt = $pdo->prepare("SELECT name FROM campgrounds WHERE campground_id = :id");
    $stmt->execute([':id' => $campground_id]);
    $campground = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($campground) {
        $campground_name = $campground['name'];
    } else {
        throw new Exception("The specified campground could not be found.");
    }
} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}


// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Re-validate the campground_id from the hidden form field for security.
    $campground_id_post = filter_input(INPUT_POST, 'campground_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price_per_night = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $errors = [];

    // Validation
    if ($campground_id_post !== $campground_id) $errors[] = "Campground ID mismatch.";
    if (empty($name)) $errors[] = "Campsite Name/Number is required.";
    if ($price_per_night === false || $price_per_night < 0) {
        $errors[] = "Price per night must be a valid, non-negative number.";
    }
    
    // If validation passes, insert the new campsite into the database.
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO campsites (campground_id, name, description, price_per_night, is_active) 
                    VALUES (:campground_id, :name, :description, :price_per_night, :is_active)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':campground_id' => $campground_id,
                ':name' => $name,
                ':description' => $description,
                ':price_per_night' => $price_per_night,
                ':is_active' => $is_active
            ]);

            $_SESSION['success_message'] = "Campsite '".htmlspecialchars($name)."' was created successfully!";
            header("Location: manage_campsites.php?campground_id=" . $campground_id);
            exit();
        } catch (PDOException $e) {
            // Handle potential duplicate name errors gracefully
            if ($e->errorInfo[1] == 1062) { // 1062 is the MySQL error code for duplicate entry
                 $error_message = "Error: A campsite with this name/number already exists in this campground.";
            } else {
                $error_message = "Database Error: Could not create the campsite. " . $e->getMessage();
            }
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// --- HEADER ---
$page_title = 'Add New Campsite';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Add New Site to: <strong><?= htmlspecialchars($campground_name) ?></strong></h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <form action="add_campsite.php?campground_id=<?= $campground_id ?>" method="POST">
        <!-- Hidden field to pass the campground_id during POST -->
        <input type="hidden" name="campground_id" value="<?= $campground_id ?>">

        <div class="mb-3">
            <label for="name" class="form-label">Campsite Name / Number</label>
            <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Site A5, Unpowered 22" required>
        </div>
        
        <div class="mb-3">
            <label for="price_per_night" class="form-label">Price Per Night</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="price_per_night" name="price_per_night" step="0.01" min="0" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description (Optional)</label>
            <textarea class="form-control" id="description" name="description" rows="3" placeholder="e.g., Close to amenities, shady spot"></textarea>
        </div>
        
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" checked>
            <label class="form-check-label" for="is_active">
                Active (available for booking)
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Save Campsite</button>
        <a href="manage_campsites.php?campground_id=<?= $campground_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
