<?php
// Helpers for the staff pages only (dashboard, coupons). Load with require_once __DIR__ . '/includes/staff.php'.

// Rows of a prepared query as arrays. $types/$params bind the values; the SQL text itself must be fixed text.
function staff_rows($conn, $sql, $types = '', $params = []) {
    $st = mysqli_prepare($conn, $sql);
    if (!$st) return [];
    if ($types !== '') mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $meta = mysqli_stmt_result_metadata($st);
    $row = []; $bind = [];
    while ($meta && ($f = mysqli_fetch_field($meta))) $bind[] = &$row[$f->name];
    $out = [];
    if ($bind) {
        mysqli_stmt_bind_result($st, ...$bind);
        while (mysqli_stmt_fetch($st)) { $c = []; foreach ($row as $k => $v) $c[$k] = $v; $out[] = $c; }
    }
    mysqli_stmt_close($st);
    return $out;
}
// First row (or an empty array)
function staff_row($conn, $sql, $types = '', $params = []) { $r = staff_rows($conn, $sql, $types, $params); return $r ? $r[0] : []; }

// Period switch: only 7, 30 or 90 are accepted, anything else falls back to 30.
function staff_days() { $d = $_GET['days'] ?? ''; return is_string($d) && in_array($d, ['7', '30', '90'], true) ? (int)$d : 30; }

// "12 พ.ค." style short Thai date from Y-m-d
function staff_short_date($ymd) {
    $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $t = strtotime($ymd);
    return (int)date('j', $t) . ' ' . $m[(int)date('n', $t)];
}

// Daily revenue bars as inline SVG (no text inside, so it scales cleanly on a phone) plus a hidden data table.
// $byDay: ['Y-m-d' => amount]. $today: 'Y-m-d' from the database clock.
function staff_revenue_chart($byDay, $days, $today) {
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) $dates[] = date('Y-m-d', strtotime("$today -$i day"));
    $max = 0; $total = 0;
    foreach ($dates as $d) { $v = (float)($byDay[$d] ?? 0); $max = max($max, $v); $total += $v; }
    $w = 10; $bars = '';
    foreach ($dates as $i => $d) {
        $v = (float)($byDay[$d] ?? 0);
        $hh = $max > 0 ? $v / $max * 96 : 0;
        $tip = staff_short_date($d) . ': ฿' . money($v);
        $bars .= '<rect class="bar" x="' . ($i * $w + 1) . '" y="' . round(100 - $hh, 2) . '" width="' . ($w - 2) . '" height="' . round($hh, 2) . '" rx="1"><title>' . h($tip) . '</title></rect>';
    }
    $svg = '<svg class="chart-svg" viewBox="0 0 ' . ($days * $w) . ' 100" preserveAspectRatio="none" role="img" aria-labelledby="rv-t rv-d">'
         . '<title id="rv-t">รายได้รายวัน ' . (int)$days . ' วันล่าสุด</title>'
         . '<desc id="rv-d">รวม ฿' . h(money($total)) . ' สูงสุดต่อวัน ฿' . h(money($max)) . '</desc>'
         . '<line class="grid" x1="0" x2="' . ($days * $w) . '" y1="4" y2="4"/><line class="grid" x1="0" x2="' . ($days * $w) . '" y1="52" y2="52"/><line class="base" x1="0" x2="' . ($days * $w) . '" y1="100" y2="100"/>'
         . $bars . '</svg>';
    $axis = '<div class="chart-axis"><span>' . h(staff_short_date($dates[0])) . '</span><span>' . h(staff_short_date($dates[intdiv(count($dates), 2)])) . '</span><span>' . h(staff_short_date($dates[count($dates) - 1])) . '</span></div>';
    $top = '<div class="chart-top">สูงสุด ฿' . h(number_format($max)) . ' ต่อวัน</div>';
    $tbl = '<table class="sr-only"><caption>รายได้รายวัน (บาท)</caption><thead><tr><th>วันที่</th><th>รายได้</th></tr></thead><tbody>';
    foreach ($dates as $d) $tbl .= '<tr><td>' . h($d) . '</td><td>' . h(money($byDay[$d] ?? 0)) . '</td></tr>';
    $tbl .= '</tbody></table>';
    return $top . '<div class="chart-box">' . $svg . '</div>' . $axis . $tbl;
}

// One horizontal bar (SVG) filled to $pct percent, in the zone colour of $zoneClass (z1..z5).
function staff_hbar($pct, $zoneClass, $label) {
    $pct = max(0, min(100, (float)$pct));
    return '<svg class="hbar ' . h($zoneClass) . '" viewBox="0 0 100 8" preserveAspectRatio="none" role="img" aria-label="' . h($label) . '"><rect class="bg" width="100" height="8" rx="2"/><rect class="fg" width="' . round($pct, 2) . '" height="8" rx="2"/></svg>';
}
