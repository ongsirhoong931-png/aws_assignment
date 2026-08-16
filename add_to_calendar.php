<?php

require 'config.php';
require 'auth.php';
require_login();

$uid = current_user_id();

// Get order ID
$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id < 1) {
    die('Invalid order ID.');
}

// Get order + event information
$stmt = $conn->prepare("
    SELECT
        o.id,
        o.user_id,
        e.event_name,
        e.event_date,
        e.venue
    FROM orders o
    JOIN events e ON e.id = o.event_id
    WHERE o.id = ?
      AND o.user_id = ?
    LIMIT 1
");

$stmt->bind_param('ii', $order_id, $uid);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Order not found.');
}


// Event information
$eventName = $order['event_name'];
$venue     = $order['venue'];
$eventDate = $order['event_date'];


// Convert event date to timestamp
$startTimestamp = strtotime($eventDate);

if ($startTimestamp === false) {
    die('Invalid event date.');
}


// Assume event duration is 2 hours
$endTimestamp = $startTimestamp + (2 * 60 * 60);


// Convert to iCalendar format
$dtStart = gmdate('Ymd\THis\Z', $startTimestamp);
$dtEnd   = gmdate('Ymd\THis\Z', $endTimestamp);
$dtStamp = gmdate('Ymd\THis\Z');


// Escape calendar text
function escape_ical($text)
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\;', $text);
    $text = str_replace(',', '\,', $text);
    $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);

    return $text;
}


$eventName = escape_ical($eventName);
$venue     = escape_ical($venue);

$description = escape_ical(
    'Student Society Event - Order #' . $order_id
);


// Generate calendar file
$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//Student Society Event Ticketing//EN\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";

$ics .= "BEGIN:VEVENT\r\n";

$ics .= "UID:order-" . $order_id . "@event-ticketing\r\n";
$ics .= "DTSTAMP:" . $dtStamp . "\r\n";
$ics .= "DTSTART:" . $dtStart . "\r\n";
$ics .= "DTEND:" . $dtEnd . "\r\n";

$ics .= "SUMMARY:" . $eventName . "\r\n";
$ics .= "LOCATION:" . $venue . "\r\n";
$ics .= "DESCRIPTION:" . $description . "\r\n";

$ics .= "STATUS:CONFIRMED\r\n";
$ics .= "SEQUENCE:0\r\n";

$ics .= "BEGIN:VALARM\r\n";
$ics .= "TRIGGER:-PT30M\r\n";
$ics .= "ACTION:DISPLAY\r\n";
$ics .= "DESCRIPTION:Event Reminder\r\n";
$ics .= "END:VALARM\r\n";

$ics .= "END:VEVENT\r\n";
$ics .= "END:VCALENDAR\r\n";


// Send calendar file to browser
header('Content-Type: text/calendar; charset=utf-8');
header(
    'Content-Disposition: attachment; filename="event_' .
    $order_id .
    '.ics"'
);
header('Content-Length: ' . strlen($ics));

echo $ics;
exit;