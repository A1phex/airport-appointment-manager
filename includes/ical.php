<?php
// iCalendar / Google Calendar helpers. Pure functions, no output.

// Escape a text value for an ICS property (RFC 5545 3.3.11).
function ics_escape(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace(["\r\n", "\n", "\r"], '\\n', $text);
    return $text;
}

// Fold a full "NAME:value" content line at 75 octets (RFC 5545 3.1).
// Chunking at 60 characters keeps every segment under the limit even for
// multibyte text, and never splits a UTF-8 sequence.
function ics_fold(string $line): string
{
    if (strlen($line) <= 75) {
        return $line;
    }
    return implode("\r\n ", mb_str_split($line, 60));
}

// Start of a slot as a Berlin-local DateTimeImmutable.
function slot_start_dt(array $slot): DateTimeImmutable
{
    return new DateTimeImmutable(
        $slot['slot_date'] . ' ' . $slot['slot_time'],
        new DateTimeZone('Europe/Berlin')
    );
}

// "Add to Google Calendar" prefill URL. Times are floating local values
// interpreted in the ctz timezone (no trailing Z).
function gcal_url(string $title, DateTimeImmutable $start, int $durationMinutes, string $details): string
{
    $end = $start->modify("+{$durationMinutes} minutes");
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text'   => $title,
        'dates'  => $start->format('Ymd\THis') . '/' . $end->format('Ymd\THis'),
        'ctz'    => 'Europe/Berlin',
        'details' => $details,
    ]);
}
