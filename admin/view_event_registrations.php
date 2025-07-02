<?php
// admin/view_event_registrations.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$events = [];
$attendees = [];
$selected_event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$error_message = '';

// --- DATA FETCHING for Event Dropdown ---
try {
    $stmt_events = $pdo->query("SELECT event_id, event_name FROM events WHERE event_IsDeleted = 0 ORDER BY start_date DESC");
    $events = $stmt_events->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch events. " . $e->getMessage();
}

// --- SEARCH & FILTER LOGIC ---
if ($selected_event_id) {
    try {
        // Base SQL query
        $sql = "SELECT 
                    a.attendee_id, a.first_name, a.surname, a.email, a.aus_number,
                    at.type_name,
                    er.registration_id
                FROM attendees a
                JOIN attendee_types at ON a.type_id = at.type_id
                JOIN eventregistrations er ON a.eventreg_id = er.registration_id
                WHERE er.event_id = :event_id";

        $params = [':event_id' => $selected_event_id];

        // Append filters from GET request
        $filters = $_GET['filter'] ?? [];
        if (!empty($filters['name'])) {
            $sql .= " AND (a.first_name LIKE :name OR a.surname LIKE :name)";
            $params[':name'] = '%' . $filters['name'] . '%';
        }
        if (!empty($filters['type_id'])) {
            $sql .= " AND a.type_id = :type_id";
            $params[':type_id'] = $filters['type_id'];
        }
        if (!empty($filters['aus_number'])) {
            $sql .= " AND a.aus_number LIKE :aus_number";
            $params[':aus_number'] = '%' . $filters['aus_number'] . '%';
        }
        
        // Note: Filtering by plane data requires more complex joins, will be added later.

        $sql .= " ORDER BY a.surname, a.first_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch attendees. " . $e->getMessage();
    }
}

// --- HEADER ---
$page_title = 'View Event Registrations';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">View Event Registrations</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Event Selection Form -->
    <div class="card bg-light p-3 mb-4">
        <form action="view_event_registrations.php" method="GET">
            <div class="mb-3">
                <label for="event_id" class="form-label"><strong>First, select an event to view:</strong></label>
                <select class="form-select" id="event_id" name="event_id" onchange="this.form.submit()">
                    <option value="">-- Select an Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= $event['event_id'] ?>" <?= ($selected_event_id == $event['event_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['event_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Attendee List & Filters (only shown if an event is selected) -->
    <?php if ($selected_event_id): ?>
        <hr>
        <h2>Attendees for <?= htmlspecialchars(array_column($events, 'event_name', 'event_id')[$selected_event_id]) ?></h2>

        <!-- Search/Filter Form -->
        <div class="card p-3 mb-4">
            <form action="view_event_registrations.php" method="GET">
                <input type="hidden" name="event_id" value="<?= $selected_event_id ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="filter_name" class="form-label">Filter by Name</label>
                        <input type="text" class="form-control" id="filter_name" name="filter[name]" value="<?= htmlspecialchars($_GET['filter']['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_aus_number" class="form-label">Filter by AUS Number</label>
                        <input type="text" class="form-control" id="filter_aus_number" name="filter[aus_number]" value="<?= htmlspecialchars($_GET['filter']['aus_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_type_id" class="form-label">Filter by Type</label>
                        <select class="form-select" id="filter_type_id" name="filter[type_id]">
                            <option value="">All Types</option>
                            <?php 
                            // Fetch types for filter dropdown
                            $types_stmt = $pdo->query("SELECT type_id, type_name FROM attendee_types ORDER BY type_name");
                            while($row = $types_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = (isset($_GET['filter']['type_id']) && $_GET['filter']['type_id'] == $row['type_id']) ? 'selected' : '';
                                echo "<option value='{$row['type_id']}' $selected>" . htmlspecialchars($row['type_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Attendee Table -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>AUS Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attendees)): ?>
                        <?php foreach ($attendees as $attendee): ?>
                            <tr>
                                <td><?= htmlspecialchars($attendee['surname'] . ', ' . $attendee['first_name']) ?></td>
                                <td><?= htmlspecialchars($attendee['email']) ?></td>
                                <td><?= htmlspecialchars($attendee['type_name']) ?></td>
                                <td><?= htmlspecialchars($attendee['aus_number'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="view_attendee_details.php?id=<?= $attendee['attendee_id'] ?>" class="btn btn-info btn-sm">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No attendees found for this event matching the criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
