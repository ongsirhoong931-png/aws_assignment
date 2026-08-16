<?php
require_once __DIR__ . '/config.php';

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    die('Invalid order ID.');
}

$stmt = $pdo->prepare("
    SELECT o.id, e.title, e.description, e.date, e.time, e.venue 
    FROM orders o 
    JOIN events e ON o.event_id = e.id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$item = $stmt->fetch();

if (!$item) {
    die('Event details not found.');
}

$event_start = date('Ymd\THis', strtotime($item['date'] . ' ' . $item['time']));
$event_end = date('Ymd\THis', strtotime($item['date'] . ' ' . $item['time'] . ' +2 hours'));
$filename = "event_" . $item['id'] . ".ics";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Event Ticketing System//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "BEGIN:VEVENT\r\n";
echo "UID:" . uniqid() . "@eventsystem.com\r\n";
echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
echo "DTSTART:" . $event_start . "\r\n";
echo "DTEND:" . $event_end . "\r\n";
echo "SUMMARY:" . addcslashes($item['title'], ",;") . "\r\n";
echo "DESCRIPTION:" . addcslashes($item['description'] ?? '', ",;") . "\r\n";
echo "LOCATION:" . addcslashes($item['venue'], ",;") . "\r\n";
echo "STATUS:CONFIRMED\r\n";
echo "END:VEVENT\r\n";
echo "END:VCALENDAR\r\n";
exit;