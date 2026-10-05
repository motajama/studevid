<?php
require_once __DIR__ . '/_private/database.php';
header('Referrer-Policy: no-referrer');
$id = input_id($_GET, 'id');
$slot = booking_slot($pdo, $id, input_text($_GET, 'token'));
$zone = new DateTimeZone('Europe/Prague');
$start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $slot['date'] . ' ' . $slot['time'], $zone);
if (!$start) app_error(422, 'Date není platný.');
$end = $start->add(new DateInterval('PT45M'));
$utc = new DateTimeZone('UTC');
$cancel = 'https://www.example.org/konzultace/?' . http_build_query(['cancel'=>$id,'token'=>input_text($_GET,'token')]);
$location = match ($slot['form']) {
    'zoom' => 'Zoom: https://tinyurl.com/konzumotal-zoom',
    'teams' => 'MS Teams',
    default => 'Kancelář 5.45, Joštova 10, Brno',
};
function ics_escape(string $value): string {
    return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], $value);
}
$lines = [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//example.org//studevid//EN',
    'BEGIN:VEVENT', 'UID:consultation-' . $id . '@example.org',
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
    'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
    'SUMMARY:Consultations (Studevid Maintainers)',
    'DESCRIPTION:' . ics_escape('Stornování: ' . $cancel),
    'LOCATION:' . ics_escape($location),
    'ORGANIZER;CN=Studevid Maintainers:MAILTO:maintainer@example.org',
    'STATUS:CONFIRMED', 'END:VEVENT', 'END:VCALENDAR',
];
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="consultation.ics"');
foreach ($lines as $line) {
    while (strlen($line) > 73) {
        $part = mb_strcut($line, 0, 73, 'UTF-8');
        echo $part . "\r\n ";
        $line = substr($line, strlen($part));
    }
    echo $line . "\r\n";
}
