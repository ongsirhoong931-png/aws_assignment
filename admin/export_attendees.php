<?php
require_once __DIR__ . '/../config.php';

// Ensure user is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    die('Unauthorized access.');
}

$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

$query = "
    SELECT o.id AS order_id, u.name AS attendee_name, u.email AS attendee_email, 
           e.title AS event_title, o.seat_number, o.status AS order_status, 
           o.checked_in, o.created_at
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN events e ON o.event_id = e.id
";

$params = [];
if ($event_id) {
    $query .= " WHERE e.id = ?";
    $params[] = $event_id;
}
$query .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "attendees_export_" . ($event_id ? "event_{$event_id}_" : "all_") . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Headers
fputcsv($output, ['Order ID', 'Attendee Name', 'Attendee Email', 'Event Title', 'Seat Number', 'Order Status', 'Checked In', 'Booking Date']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['order_id'],
        $row['attendee_name'],
        $row['attendee_email'],
        $row['event_title'],
        $row['seat_number'] ?? 'N/A',
        $row['order_status'],
        $row['checked_in'] ? 'Yes' : 'No',
        $row['created_at']
    ]);
}

fclose($output);
exit;