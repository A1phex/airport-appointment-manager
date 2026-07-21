<?php
// Week-view helpers. Pure functions, no output.

// Parse a ?week=YYYY-MM-DD parameter (or any Y-m-d string) and return the
// Monday of that week. Falls back to the current week on missing/invalid input.
function cal_week_start(?string $param): DateTimeImmutable
{
    $day = null;
    if ($param) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $param);
        if ($parsed && $parsed->format('Y-m-d') === $param) {
            $day = $parsed;
        }
    }
    if (!$day) {
        $day = new DateTimeImmutable('today');
    }
    // format('N') is 1 (Mon) .. 7 (Sun); subtracting N-1 days always lands on
    // Monday, unlike strtotime('monday this week') which is ambiguous on Sundays.
    return $day->modify('-' . ((int)$day->format('N') - 1) . ' days');
}

// The 7 days of the week beginning at $start.
function cal_week_days(DateTimeImmutable $start): array
{
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = $start->modify("+{$i} days");
    }
    return $days;
}

// Group a slot result set into ['Y-m-d' => [row, ...]].
function cal_group_by_day(mysqli_result $slots): array
{
    $byDay = [];
    while ($row = $slots->fetch_assoc()) {
        $byDay[$row['slot_date']][] = $row;
    }
    return $byDay;
}

// Normalize a ?view= parameter to 'week' or 'list'.
function cal_view(?string $param): string
{
    return $param === 'list' ? 'list' : 'week';
}

// Query string preserving the current week + view, for links and redirects.
function cal_qs(DateTimeImmutable $weekStart, string $view, array $extra = []): string
{
    return http_build_query(array_merge([
        'week' => $weekStart->format('Y-m-d'),
        'view' => $view,
    ], $extra));
}
