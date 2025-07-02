<?php
// admin/manage_settings.php

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
$success_message = '';
$settings = [];

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the junior_max_age from the form and validate it.
    $junior_max_age = filter_input(INPUT_POST, 'junior_max_age', FILTER_VALIDATE_INT);

    if ($junior_max_age !== false && $junior_max_age > 0) {
        try {
            // Use an "UPSERT" operation: UPDATE the existing key, or INSERT if it doesn't exist.
            // This is more robust than a simple UPDATE.
            $sql = "INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (:key, :value)
                    ON DUPLICATE KEY UPDATE setting_value = :value";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':key' => 'junior_max_age',
                ':value' => $junior_max_age
            ]);

            $success_message = "Settings updated successfully!";

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update settings. " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid value for Junior Max Age. Please enter a positive number.";
    }
}

// --- DATA FETCHING for page load ---
try {
    // Fetch all settings from the database and put them into an associative array
    // where the key is the setting_key (e.g., $settings['junior_max_age']).
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $all_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_settings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }

} catch (PDOException $e) {
    $error_message = "Database Error: Could not load settings. " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage System Settings';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Manage System Settings</h1>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form action="manage_settings.php" method="POST">
        <div class="card">
            <div class="card-header">
                Registration Settings
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="junior_max_age" class="form-label">Junior Max Age</label>
                    <input type="number" class="form-control" id="junior_max_age" name="junior_max_age" value="<?= htmlspecialchars($settings['junior_max_age'] ?? '17') ?>" required>
                    <div class="form-text">
                        Define the maximum age for a person to be considered a "Junior" attendee. This is inclusive (e.g., 17 means 17 and under).
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Save Settings</button>
    </form>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
