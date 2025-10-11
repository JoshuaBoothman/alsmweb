<?php
// admin/view_event_attendees.php

session_start();
// Standard security check to ensure only admins can access this page.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZATION ---
$attendees = [];
$event = null;
$error_message = '';

// Get and validate the event ID from the URL. This is crucial.
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$event_id) {
    header("Location: manage_events.php?error=noeventid");
    exit();
}

// --- FILTERING & SORTING SETUP ---
// Whitelist of columns that are safe to sort by to prevent SQL injection.
$sortable_columns = ['name', 'arrival_date', 'departure_date', 'type_name', 'age', 'suburb', 'state', 'aus_number', 'flight_line_duty'];
$sort_col = in_array($_GET['sort'] ?? '', $sortable_columns) ? $_GET['sort'] : 'surname'; // Default sort
$sort_dir = (strtoupper($_GET['dir'] ?? '') === 'DESC') ? 'DESC' : 'ASC'; // Default direction

// Get filter values from the URL query string.
$filters = [
    'name' => $_GET['filter']['name'] ?? '',
    'type' => $_GET['filter']['type'] ?? '',
    'suburb' => $_GET['filter']['suburb'] ?? '',
    'state' => $_GET['filter']['state'] ?? '',
    'aus_number' => $_GET['filter']['aus_number'] ?? '',
    'flight_duty' => $_GET['filter']['flight_duty'] ?? ''
];


// --- DATA FETCHING ---
try {
    // 1. Fetch the event details for the page header.
    $stmt_event = $pdo->prepare("SELECT event_name FROM events WHERE event_id = :event_id");
    $stmt_event->execute([':event_id' => $event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception("Event not found.");
    }

    // 2. Build the main SQL query with joins.
    // We calculate age directly in the query.
    $sql = "SELECT 
                a.attendee_id, a.first_name, a.surname, a.arrival_date, a.departure_date,
                a.suburb, a.state, a.aus_number, a.flight_line_duty, a.dob,
                TIMESTAMPDIFF(YEAR, a.dob, CURDATE()) AS age,
                at.type_name
            FROM attendees a
            JOIN attendee_types at ON a.type_id = at.type_id
            JOIN eventregistrations er ON a.eventreg_id = er.registration_id
            WHERE er.event_id = :event_id";

    $params = [':event_id' => $event_id];

    // 3. Dynamically add WHERE clauses for each active filter.
    if (!empty($filters['name'])) {
        $sql .= " AND (a.first_name LIKE :name OR a.surname LIKE :name)";
        $params[':name'] = '%' . $filters['name'] . '%';
    }
    if (!empty($filters['type'])) {
        $sql .= " AND at.type_name LIKE :type";
        $params[':type'] = '%' . $filters['type'] . '%';
    }
    if (!empty($filters['suburb'])) {
        $sql .= " AND a.suburb LIKE :suburb";
        $params[':suburb'] = '%' . $filters['suburb'] . '%';
    }
    if (!empty($filters['state'])) {
        $sql .= " AND a.state LIKE :state";
        $params[':state'] = '%' . $filters['state'] . '%';
    }
    if (!empty($filters['aus_number'])) {
        $sql .= " AND a.aus_number LIKE :aus_number";
        $params[':aus_number'] = '%' . $filters['aus_number'] . '%';
    }
    if ($filters['flight_duty'] !== '') {
        $sql .= " AND a.flight_line_duty = :flight_duty";
        $params[':flight_duty'] = $filters['flight_duty'];
    }

    // 4. Add the ORDER BY clause.
    // Special handling for sorting by the combined name.
    if ($sort_col === 'name') {
        $sql .= " ORDER BY a.surname {$sort_dir}, a.first_name {$sort_dir}";
    } else {
        $sql .= " ORDER BY {$sort_col} {$sort_dir}";
    }
    
    // 5. Execute the query and fetch the results.
    $stmt_attendees = $pdo->prepare($sql);
    $stmt_attendees->execute($params);
    $attendees = $stmt_attendees->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

/**
 * Helper function to generate table header links for sorting.
 * It preserves existing filters and toggles the sort direction.
 */
function sortableHeader($title, $column_name, $current_sort_col, $current_sort_dir, $filters, $event_id) {
    $dir = ($current_sort_col === $column_name && $current_sort_dir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort_col === $column_name) {
        $icon = $current_sort_dir === 'ASC' ? ' &uarr;' : ' &darr;';
    }
    $query_params = http_build_query([
        'event_id' => $event_id,
        'sort' => $column_name,
        'dir' => $dir,
        'filter' => $filters
    ]);
    return "<a class=\"link-light\" href=\"?{$query_params}\">{$title}{$icon}</a>";
}

// --- HEADER ---
$page_title = 'View Attendees';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid mt-4">
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_events.php" class="btn btn-secondary">&laquo; Back to Events</a>
    <?php elseif ($event): ?>
        <h1 class="mb-4">Attendees for: <strong><?= htmlspecialchars($event['event_name']) ?></strong></h1>

        <div class="card bg-light p-3 mb-4">
            <form action="view_event_attendees.php" method="GET">
                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2"><label class="form-label">Name</label><input type="text" name="filter[name]" class="form-control" value="<?= htmlspecialchars($filters['name']) ?>"></div>
                    <div class="col-md-2"><label class="form-label">Type</label><input type="text" name="filter[type]" class="form-control" value="<?= htmlspecialchars($filters['type']) ?>"></div>
                    <div class="col-md-1"><label class="form-label">Suburb</label><input type="text" name="filter[suburb]" class="form-control" value="<?= htmlspecialchars($filters['suburb']) ?>"></div>
                    <div class="col-md-1"><label class="form-label">State</label><input type="text" name="filter[state]" class="form-control" value="<?= htmlspecialchars($filters['state']) ?>"></div>
                    <div class="col-md-2"><label class="form-label">AUS Number</label><input type="text" name="filter[aus_number]" class="form-control" value="<?= htmlspecialchars($filters['aus_number']) ?>"></div>
                    <div class="col-md-2"><label class="form-label">Flight Duty</label><select name="filter[flight_duty]" class="form-select"><option value="">All</option><option value="1" <?= $filters['flight_duty'] === '1' ? 'selected' : '' ?>>Yes</option><option value="0" <?= $filters['flight_duty'] === '0' ? 'selected' : '' ?>>No</option></select></div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="view_event_attendees.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th><?= sortableHeader('Name', 'name', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('Arrival', 'arrival_date', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('Departure', 'departure_date', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('Type', 'type_name', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('Age', 'age', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('Suburb', 'suburb', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('State', 'state', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('AUS Num', 'aus_number', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th><?= sortableHeader('Flight Duty', 'flight_line_duty', $sort_col, $sort_dir, $filters, $event_id) ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendees)): ?>
                        <tr><td colspan="9" class="text-center">No attendees found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendees as $attendee): ?>
                            <tr>
                                <td><?= htmlspecialchars($attendee['surname'] . ', ' . $attendee['first_name']) ?></td>
                                <td><?= $attendee['arrival_date'] ? date('d M Y', strtotime($attendee['arrival_date'])) : 'N/A' ?></td>
                                <td><?= $attendee['departure_date'] ? date('d M Y', strtotime($attendee['departure_date'])) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($attendee['type_name']) ?></td>
                                <td><?= $attendee['dob'] ? htmlspecialchars($attendee['age']) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($attendee['suburb']) ?></td>
                                <td><?= htmlspecialchars($attendee['state']) ?></td>
                                <td><?= htmlspecialchars($attendee['aus_number'] ?? 'N/A') ?></td>
                                <td><?= $attendee['flight_line_duty'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                                <td>
                                    <a href="view_attendee_details.php?id=<?= $attendee['attendee_id'] ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="edit_attendee.php?id=<?= $attendee['attendee_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="manage_events.php" class="btn btn-secondary mt-3">&laquo; Back to Events List</a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>