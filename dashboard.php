<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // staff and admin only, rights read from the database
require_once __DIR__ . '/includes/staff.php';
expire_unpaid_orders($conn);

$days = staff_days();            // 7 / 30 / 90, from a fixed whitelist
$back = $days - 1;               // the period includes today
$today = (string)staff_row($conn, 'SELECT CURDATE() AS d')['d'];

// Right now (no period)
$review = (int)staff_row($conn, "SELECT COUNT(*) AS n FROM lib_orders WHERE status = 'open' AND pay_status = 'reported'")['n'];
$toShip = (int)staff_row($conn, "SELECT COUNT(*) AS n FROM lib_orders WHERE status = 'open' AND pay_status = 'confirmed' AND shipped_at IS NULL")['n'];
$unpaid = (int)staff_row($conn, "SELECT COUNT(*) AS n FROM lib_orders WHERE status = 'open' AND pay_status = 'unpaid'")['n'];
$now = staff_row($conn, "SELECT COUNT(*) AS n, IFNULL(SUM(deposit), 0) AS held, IFNULL(SUM(due_date < CURDATE()), 0) AS late FROM lib_loans WHERE status = 'shipped'");
$out = (int)$now['n']; $held = (float)$now['held']; $late = (int)$now['late'];

// The chosen period
$rev = staff_row($conn, "SELECT IFNULL(SUM(rent_total - discount_total), 0) AS s FROM lib_orders WHERE status = 'open' AND pay_status = 'confirmed' AND pay_confirmed_at >= CURDATE() - INTERVAL ? DAY", 'i', [$back]);
$revenue = (float)$rev['s'];
$nOrders = (int)staff_row($conn, "SELECT COUNT(*) AS n FROM lib_orders WHERE status = 'open' AND ordered_at >= CURDATE() - INTERVAL ? DAY", 'i', [$back])['n'];
$ret = staff_row($conn, "SELECT COUNT(*) AS n, IFNULL(SUM(returned_at <= due_date), 0) AS ok FROM lib_loans WHERE status = 'returned' AND returned_at >= CURDATE() - INTERVAL ? DAY", 'i', [$back]);
$onTime = (int)$ret['n'] > 0 ? round((int)$ret['ok'] / (int)$ret['n'] * 100) : null;   // null = nothing returned yet, shown as a dash
$rep = staff_row($conn, "SELECT COUNT(*) AS m, IFNULL(SUM(c >= 2), 0) AS r FROM (SELECT user_id, COUNT(*) AS c FROM lib_orders WHERE status = 'open' AND ordered_at >= CURDATE() - INTERVAL ? DAY GROUP BY user_id) t", 'i', [$back]);
$repeat = (int)$rep['m'] > 0 ? round((int)$rep['r'] / (int)$rep['m'] * 100) : null;

$byDay = [];
foreach (staff_rows($conn, "SELECT DATE(pay_confirmed_at) AS d, SUM(rent_total - discount_total) AS s FROM lib_orders WHERE status = 'open' AND pay_status = 'confirmed' AND pay_confirmed_at >= CURDATE() - INTERVAL ? DAY GROUP BY DATE(pay_confirmed_at)", 'i', [$back]) as $r) $byDay[$r['d']] = (float)$r['s'];

// Revenue per zone: each book's share of its order's net rent (rate x days, scaled by the order's discount)
$zones = [];
foreach (staff_rows($conn, 'SELECT category_id, name FROM lib_categories ORDER BY category_id') as $c) $zones[$c['category_id']] = ['name' => $c['name'], 'rev' => 0.0];
foreach (staff_rows($conn, "SELECT b.category_id, SUM(l.daily_rate * o.rent_days * (o.rent_total - o.discount_total) / NULLIF(o.rent_total, 0)) AS s
        FROM lib_loans l JOIN lib_orders o ON o.order_id = l.order_id JOIN lib_books b ON b.book_id = l.book_id
        WHERE o.status = 'open' AND o.pay_status = 'confirmed' AND o.pay_confirmed_at >= CURDATE() - INTERVAL ? DAY GROUP BY b.category_id", 'i', [$back]) as $r)
    if (isset($zones[$r['category_id']])) $zones[$r['category_id']]['rev'] = (float)$r['s'];
$zoneMax = max(array_merge([0], array_column($zones, 'rev')));

$top = staff_rows($conn, "SELECT b.book_id, b.title, b.isbn, b.category_id, COUNT(*) AS n,
        (SELECT AVG(r.rating) FROM lib_reviews r WHERE r.book_id = b.book_id) AS av, (SELECT COUNT(*) FROM lib_reviews r WHERE r.book_id = b.book_id) AS rc
        FROM lib_loans l JOIN lib_orders o ON o.order_id = l.order_id JOIN lib_books b ON b.book_id = l.book_id
        WHERE o.status = 'open' AND l.borrowed_at >= CURDATE() - INTERVAL ? DAY
        GROUP BY b.book_id, b.title, b.isbn, b.category_id ORDER BY n DESC, b.title LIMIT 8", 'i', [$back]);

$lowStock = staff_rows($conn, 'SELECT book_id, title, copies_available, copies_total FROM lib_books WHERE copies_available <= 1 ORDER BY copies_available, title LIMIT 8');
$waiting = staff_rows($conn, "SELECT o.order_id, u.full_name, o.rent_total - o.discount_total + o.deposit_total AS amount, o.pay_reported_at FROM lib_orders o JOIN lib_users u ON u.user_id = o.user_id WHERE o.status = 'open' AND o.pay_status = 'reported' ORDER BY o.pay_reported_at LIMIT 6");
$lateRows = staff_rows($conn, "SELECT l.order_id, u.full_name, b.title, DATEDIFF(CURDATE(), l.due_date) AS days_late
        FROM lib_loans l JOIN lib_users u ON u.user_id = l.user_id JOIN lib_books b ON b.book_id = l.book_id
        WHERE l.status = 'shipped' AND l.due_date < CURDATE() ORDER BY l.due_date LIMIT 5");

$pageTitle = 'แดชบอร์ด'; $pageCss = ['staff'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>แดชบอร์ดเจ้าหน้าที่</h1><p>ภาพรวมวันนี้ <?= h(date('d/m/Y')) ?></p></div>
  <nav class="tabs period" aria-label="ช่วงเวลา">
    <?php foreach ([7, 30, 90] as $d): ?><a href="dashboard.php?days=<?= $d ?>"<?= $d === $days ? ' class="on" aria-current="page"' : '' ?>><?= $d ?> วัน</a><?php endforeach; ?>
  </nav></div>

<h2 class="sec">ต้องจัดการตอนนี้</h2>
<div class="kpis">
  <a class="kpi <?= $review ? 'warn' : '' ?>" href="rentals.php?tab=review"><?= icon('clock', 20) ?><b><?= $review ?></b><span>รอตรวจสอบการโอน</span></a>
  <a class="kpi" href="rentals.php?tab=ship"><?= icon('truck', 20) ?><b><?= $toShip ?></b><span>เตรียมจัดส่ง</span></a>
  <a class="kpi" href="rentals.php?tab=unpaid"><?= icon('receipt', 20) ?><b><?= $unpaid ?></b><span>รอลูกค้าชำระ</span></a>
  <a class="kpi" href="rentals.php?tab=renting"><?= icon('book', 20) ?><b><?= $out ?></b><span>เล่มที่อยู่กับผู้เช่า</span></a>
  <a class="kpi <?= $late ? 'bad' : '' ?>" href="overdue.php"><?= icon('alert', 20) ?><b><?= $late ?></b><span>เล่มเกินกำหนดคืน</span></a>
  <div class="kpi"><?= icon('shield', 20) ?><b>฿<?= h(number_format($held)) ?></b><span>มัดจำที่ถืออยู่</span></div>
</div>

<h2 class="sec">ผลงาน <?= $days ?> วันล่าสุด</h2>
<div class="kpis">
  <div class="kpi"><?= icon('chart', 20) ?><b>฿<?= h(money($revenue)) ?></b><span>ค่าเช่าที่รับแล้ว (หลังหักส่วนลด)</span></div>
  <div class="kpi"><?= icon('receipt', 20) ?><b><?= $nOrders ?></b><span>ใบสั่งเช่า</span></div>
  <div class="kpi"><?= icon('check', 20) ?><b><?= $onTime === null ? '-' : (int)$onTime . '%' ?></b><span>คืนตรงเวลา<?= $onTime === null ? ' (ยังไม่มีการคืน)' : '' ?></span></div>
  <div class="kpi"><?= icon('refresh', 20) ?><b><?= $repeat === null ? '-' : (int)$repeat . '%' ?></b><span>ลูกค้าเช่าซ้ำ (2 ใบขึ้นไป)</span></div>
</div>

<div class="cols-2">
  <section class="card"><h3>รายได้รายวัน</h3>
    <?php if ($revenue <= 0): ?><p class="empty">ยังไม่มีรายได้ในช่วงนี้</p><?php else: ?><?= staff_revenue_chart($byDay, $days, $today) ?><?php endif; ?></section>
  <section class="card"><h3>รายได้ตามโซน</h3>
    <?php if ($zoneMax <= 0): ?><p class="empty">ยังไม่มีรายได้ในช่วงนี้</p><?php else: ?>
    <ul class="zonelist">
      <?php foreach ($zones as $cid => $z): $zc = zone_class($cid); $pct = $zoneMax > 0 ? $z['rev'] / $zoneMax * 100 : 0; ?>
      <li class="<?= h($zc) ?>"><div class="zrow"><span class="zchip"><?= h(zone_letter($cid)) ?></span><span class="zname"><?= h($z['name']) ?></span><b class="num">฿<?= h(number_format($z['rev'])) ?></b></div>
        <?= staff_hbar($pct, $zc, $z['name'] . ' ฿' . number_format($z['rev'])) ?></li>
      <?php endforeach; ?>
    </ul><?php endif; ?></section>
</div>

<section class="card gap"><h3>หนังสือเช่าสูงสุด <?= $days ?> วัน</h3>
  <?php if (!$top): ?><p class="empty">ยังไม่มีการเช่าในช่วงนี้</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl stack"><thead><tr><th>หนังสือ</th><th class="num">เช่า (ครั้ง)</th><th class="num">คะแนนเฉลี่ย</th></tr></thead><tbody>
  <?php foreach ($top as $t): ?><tr class="<?= h(zone_class($t['category_id'])) ?>">
    <td class="full"><div class="bookcell"><span class="thumb"><?= cover_html($t['isbn'], $t['title'], $t['book_id'], false) ?></span><a href="book-form.php?id=<?= (int)$t['book_id'] ?>"><strong><?= h($t['title']) ?></strong></a></div></td>
    <td class="num" data-label="เช่า (ครั้ง)"><?= (int)$t['n'] ?></td>
    <td class="num" data-label="คะแนนเฉลี่ย"><?= (int)$t['rc'] > 0 ? h(number_format((float)$t['av'], 1)) . ' <span class="muted small">(' . (int)$t['rc'] . ')</span>' : '<span class="muted">ยังไม่มีรีวิว</span>' ?></td>
  </tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

<div class="cols-2 gap">
  <section class="card"><h3>รอตรวจสอบการโอน</h3>
    <?php if (!$waiting): ?><p class="empty">ไม่มีรายการค้าง</p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($waiting as $w): ?><tr><td><a href="order.php?id=<?= (int)$w['order_id'] ?>"><?= h(order_no($w['order_id'])) ?></a></td><td><?= h($w['full_name']) ?></td><td class="num">฿<?= h(money($w['amount'])) ?></td><td class="muted small"><?= h(date('d/m H:i', strtotime($w['pay_reported_at']))) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?></section>
  <section class="card"><h3>เกินกำหนดคืน <?php if ($late): ?><a class="more" href="overdue.php">ดูทั้งหมด (<?= $late ?>)</a><?php endif; ?></h3>
    <?php if (!$lateRows): ?><p class="empty">ไม่มีหนังสือเกินกำหนด</p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($lateRows as $r): ?><tr><td><?= h($r['title']) ?><br><span class="muted small"><?= h($r['full_name']) ?></span></td><td class="num"><span class="badge b-bad"><?= (int)$r['days_late'] ?> วัน</span></td><td><?php if ($r['order_id']): ?><a class="btn btn-outline btn-sm" href="order.php?id=<?= (int)$r['order_id'] ?>">เปิด</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?></section>
</div>

<section class="card gap"><h3>หนังสือใกล้หมดชั้น</h3>
  <?php if (!$lowStock): ?><p class="empty">ทุกเล่มยังมีมากกว่า 1 เล่ม</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>ชื่อเรื่อง</th><th class="num">ว่าง/ทั้งหมด</th><th></th></tr></thead><tbody>
  <?php foreach ($lowStock as $b): ?><tr><td><?= h($b['title']) ?></td><td class="num"><span class="badge <?= $b['copies_available'] < 1 ? 'b-bad' : 'b-warn' ?>"><?= (int)$b['copies_available'] ?>/<?= (int)$b['copies_total'] ?></span></td><td class="right"><a class="btn btn-outline btn-sm" href="book-form.php?id=<?= (int)$b['book_id'] ?>">เพิ่มจำนวนเล่ม</a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></section>
<?php require 'includes/footer.php'; ?>
