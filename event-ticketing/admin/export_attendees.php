<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Make sure admin is logged in
require_admin();

$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);


// =====================================================
// BUILD SQL
// =====================================================

$query = "
    SELECT
        o.id AS order_id,
        u.name AS attendee_name,
        u.email AS attendee_email,
        e.event_name AS event_title,
        o.quantity,
        o.total_price,
        o.created_at
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN events e ON o.event_id = e.id
";


// Filter by event if event_id exists
if ($event_id) {
    $query .= " WHERE e.id = ?";
}

$query .= " ORDER BY o.id DESC";


// =====================================================
// PREPARE QUERY
// =====================================================

$stmt = $conn->prepare($query);

if (!$stmt) {
    die('Database error: ' . $conn->error);
}


// =====================================================
// BIND EVENT ID
// =====================================================

if ($event_id) {
    $stmt->bind_param('i', $event_id);
}


// =====================================================
// EXECUTE
// =====================================================

$stmt->execute();

$result = $stmt->get_result();


// =====================================================
// CSV FILE NAME
// =====================================================

$filename = "attendees_export_";

if ($event_id) {
    $filename .= "event_" . $event_id . "_";
} else {
    $filename .= "all_";
}

$filename .= date('Ymd_His') . ".csv";


// =====================================================
// CSV HEADERS
// =====================================================

header('Content-Type: text/csv; charset=utf-8');

header(
    'Content-Disposition: attachment; filename="' . $filename . '"'
);


// =====================================================
// CREATE CSV
// =====================================================

$output = fopen('php://output', 'w');


// Column headings
fputcsv($output, [
    'Order ID',
    'Attendee Name',
    'Attendee Email',
    'Event',
    'Quantity',
    'Total Paid (RM)',
    'Booking Date'
]);


// =====================================================
// ADD DATA
// =====================================================

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [
        $row['order_id'],
        $row['attendee_name'],
        $row['attendee_email'],
        $row['event_title'],
        $row['quantity'],
        number_format((float)$row['total_price'], 2),
        $row['created_at']
    ]);
}


// =====================================================
// CLOSE
// =====================================================

fclose($output);

$stmt->close();

exit;