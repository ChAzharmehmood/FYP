<?php
/**
 * Month calendar of duties. Usage:
 *   echo duty_calendar_html($duties, $year, $month, $linkBase);
 * $duties rows need: id, start_time, end_time, status, duty_description, staff_name (optional).
 * Overnight duties appear on every day they touch.
 */
declare(strict_types=1);

function duty_calendar_html(array $duties, int $year, int $month, ?string $linkBase = 'edit_duty.php?id='): string
{
    $first = mktime(0, 0, 0, $month, 1, $year);
    $days  = (int) date('t', $first);
    $startDow = (int) date('N', $first); // 1 = Monday
    $byDay = [];
    foreach ($duties as $d) {
        $s = strtotime((string) $d['start_time']);
        $e = strtotime((string) $d['end_time']);
        for ($t = strtotime(date('Y-m-d', $s)); $t <= $e; $t += 86400) {
            if ((int) date('n', $t) === $month && (int) date('Y', $t) === $year) {
                $byDay[(int) date('j', $t)][] = $d;
            }
        }
    }
    $prev = date('Y-m', strtotime('-1 month', $first));
    $next = date('Y-m', strtotime('+1 month', $first));
    $h = '<div class="d-flex justify-content-between align-items-center mb-2">';
    $h .= '<a class="btn btn-sm btn-outline-secondary" href="' . e(query_link(['month' => $prev])) . '">&laquo; ' . date('M Y', strtotime($prev . '-01')) . '</a>';
    $h .= '<strong>' . date('F Y', $first) . '</strong>';
    $h .= '<a class="btn btn-sm btn-outline-secondary" href="' . e(query_link(['month' => $next])) . '">' . date('M Y', strtotime($next . '-01')) . ' &raquo;</a></div>';
    $h .= '<div class="cal-grid">';
    foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dn) {
        $h .= '<div class="small fw-semibold text-center text-muted">' . $dn . '</div>';
    }
    for ($i = 1; $i < $startDow; $i++) {
        $h .= '<div class="cal-day other-month"></div>';
    }
    $today = date('Y-m-d');
    for ($day = 1; $day <= $days; $day++) {
        $isToday = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year)) === $today;
        $h .= '<div class="cal-day' . ($isToday ? ' border-primary' : '') . '"><div class="d">' . $day . '</div>';
        foreach ($byDay[$day] ?? [] as $d) {
            $label = (isset($d['staff_name']) ? $d['staff_name'] . ': ' : '') . date('H:i', strtotime((string) $d['start_time'])) . ' ' . $d['duty_description'];
            $cls = 'cal-item ' . e((string) $d['status']);
            $h .= $linkBase
                ? '<a class="' . $cls . '" href="' . e(app_url($linkBase . (int) $d['id'])) . '" title="' . e($label) . '">' . e($label) . '</a>'
                : '<span class="' . $cls . '" title="' . e($label) . '">' . e($label) . '</span>';
        }
        $h .= '</div>';
    }
    $h .= '</div>';
    return $h;
}

/** Parse ?month=YYYY-MM into [year, month], defaulting to the current month. */
function calendar_month_param(): array
{
    $m = get_str('month', 7);
    if (preg_match('/^(\d{4})-(\d{2})$/', $m, $mm) && (int) $mm[2] >= 1 && (int) $mm[2] <= 12) {
        return [(int) $mm[1], (int) $mm[2]];
    }
    return [(int) date('Y'), (int) date('n')];
}
