<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // staff and admin only, rights read from the database
expire_unpaid_orders($conn);

// One number from a query that has no user input (so mysqli_query is safe here)
function one_number($conn, $sql) { $res = mysqli_query($conn, $sql); $row = $res ? mysqli_fetch_row($res) : null; return $row ? $row[0] : 0; }
$review = one_number($conn, "SELECT COUNT(*) FROM lib_orders WHERE status = 'open' AND pay_status = 'reported'");
$toShip = one_number($conn, "SELECT COUNT(*) FROM lib_orders WHERE status = 'open' AND pay_status = 'confirmed' AND shipped_at IS NULL");
$unpaid = one_number($conn, "SELECT COUNT(*) FROM lib_orders WHERE status = 'open' AND pay_status = 'unpaid'");
$out = one_number($conn, "SELECT COUNT(*) FROM lib_loans WHERE status = 'shipped'");
$late = one_number($conn, "SELECT COUNT(*) FROM lib_loans WHERE status = 'shipped' AND due_date < CURDATE()");
$revenue = one_number($conn, "SELECT IFNULL(SUM(rent_total), 0) FROM lib_orders WHERE status = 'open' AND pay_status = 'confirmed' AND pay_confirmed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$held = one_number($conn, "SELECT IFNULL(SUM(deposit), 0) FROM lib_loans WHERE status = 'shipped'");

// Orders per day for the last 7 days (days with no order show as 0)
$perDay = [];
$res = mysqli_query($conn, 'SELECT DATE(ordered_at), COUNT(*) FROM lib_orders WHERE ordered_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(ordered_at)');
while ($res && ($row = mysqli_fetch_row($res))) $perDay[$row[0]] = (int)$row[1];
$bars = []; $maxBar = 1;
for ($i = 6; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $bars[] = [date('d/m', strtotime($d)), $perDay[$d] ?? 0]; $maxBar = max($maxBar, $perDay[$d] ?? 0); }

$lowStock = [];
$res = mysqli_query($conn, 'SELECT book_id, title, copies_available, copies_total FROM lib_books WHERE copies_available <= 1 ORDER BY copies_available, title LIMIT 8');
while ($res && ($row = mysqli_fetch_assoc($res))) $lowStock[] = $row;
$waiting = [];
$res = mysqli_query($conn, "SELECT o.order_id, u.full_name, o.rent_total + o.deposit_total, o.pay_reported_at FROM lib_orders o JOIN lib_users u ON u.user_id = o.user_id WHERE o.status = 'open' AND o.pay_status = 'reported' ORDER BY o.pay_reported_at LIMIT 6");
while ($res && ($row = mysqli_fetch_row($res))) $waiting[] = $row;

$pageTitle = 'แดชบอร์ด';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>แดชบอร์ดเจ้าหน้าที่</h1><p>ภาพรวมวันนี้ <?= h(date('d/m/Y')) ?></p></div></div>
<div class="kpis">
  <a class="kpi <?= $review ? 'warn' : '' ?>" href="rentals.php?tab=review" style="text-decoration:none;color:inherit"><b><?= (int)$review ?></b><span>รอตรวจสอบการโอน</span></a>
  <a class="kpi" href="rentals.php?tab=ship" style="text-decoration:none;color:inherit"><b><?= (int)$toShip ?></b><span>เตรียมจัดส่ง</span></a>
  <a class="kpi" href="rentals.php?tab=unpaid" style="text-decoration:none;color:inherit"><b><?= (int)$unpaid ?></b><span>รอลูกค้าชำระ</span></a>
  <a class="kpi" href="rentals.php?tab=renting" style="text-decoration:none;color:inherit"><b><?= (int)$out ?></b><span>เล่มที่อยู่กับผู้เช่า</span></a>
  <a class="kpi <?= $late ? 'bad' : '' ?>" href="overdue.php" style="text-decoration:none;color:inherit"><b><?= (int)$late ?></b><span>เล่มเกินกำหนดคืน</span></a>
  <div class="kpi"><b>฿<?= h(number_format((float)$revenue)) ?></b><span>ค่าเช่าที่รับแล้วเดือนนี้</span></div>
  <div class="kpi"><b>฿<?= h(number_format((float)$held)) ?></b><span>มัดจำที่ถืออยู่</span></div>
</div>
<div class="cols-2">
  <section class="card"><h3>ใบสั่งเช่า 7 วันล่าสุด</h3>
    <div class="bars" role="img" aria-label="จำนวนใบสั่งเช่าต่อวัน 7 วันล่าสุด">
      <?php foreach ($bars as $b): ?><div><i style="height:<?= (int)round($b[1] / $maxBar * 100) ?>%"></i><b><?= (int)$b[1] ?></b><?= h($b[0]) ?></div><?php endforeach; ?>
    </div></section>
  <section class="card"><h3>รอตรวจสอบการโอน</h3>
    <?php if (!$waiting): ?><p class="muted">ไม่มีรายการค้าง</p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($waiting as $w): ?><tr><td><a href="order.php?id=<?= (int)$w[0] ?>"><?= h(order_no($w[0])) ?></a></td><td><?= h($w[1]) ?></td><td class="num">฿<?= h(money($w[2])) ?></td><td class="muted small"><?= h(date('d/m H:i', strtotime($w[3]))) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?></section>
</div>
<section class="card" style="margin-top:1rem"><h3>หนังสือใกล้หมดชั้น</h3>
  <?php if (!$lowStock): ?><p class="muted">ทุกเล่มยังมีมากกว่า 1 เล่ม</p><?php else: ?>
  <table class="tbl"><thead><tr><th>ชื่อเรื่อง</th><th class="num">ว่าง/ทั้งหมด</th><th></th></tr></thead><tbody>
  <?php foreach ($lowStock as $b): ?><tr><td><?= h($b['title']) ?></td><td class="num"><span class="badge <?= $b['copies_available'] < 1 ? 'b-bad' : 'b-warn' ?>"><?= (int)$b['copies_available'] ?>/<?= (int)$b['copies_total'] ?></span></td><td><a class="btn btn-outline btn-sm" href="book-form.php?id=<?= (int)$b['book_id'] ?>">เพิ่มจำนวนเล่ม</a></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?></section>
<?php require 'includes/footer.php'; ?>
