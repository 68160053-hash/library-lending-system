<?php
require 'auth.php'; require 'config/db.php';
require_login();
expire_unpaid_orders($conn);
$userId = (int)$_SESSION['user_id']; // always from the session, never from the URL: a member only ever sees their own orders

// Reminder banner: books of this member that are overdue or due within 2 days
$stmt = mysqli_prepare($conn, "SELECT SUM(due_date < CURDATE()), SUM(due_date >= CURDATE() AND due_date <= CURDATE() + INTERVAL 2 DAY) FROM lib_loans WHERE user_id = ? AND status = 'shipped'");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $nLate, $nSoon);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$sql = "SELECT o.order_id, o.ordered_at, o.rent_days, o.rent_total, o.deposit_total, o.pay_status, o.status, o.shipped_at,
               (SELECT COUNT(*) FROM lib_loans l WHERE l.order_id = o.order_id) AS n_items,
               (SELECT COUNT(*) FROM lib_loans l WHERE l.order_id = o.order_id AND l.status = 'shipped') AS n_shipped,
               (SELECT GROUP_CONCAT(b.title SEPARATOR ' · ') FROM lib_loans l JOIN lib_books b ON b.book_id = l.book_id WHERE l.order_id = o.order_id) AS titles
        FROM lib_orders o WHERE o.user_id = ? ORDER BY o.order_id DESC LIMIT 100";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $orderId, $orderedAt, $days, $rentTotal, $depositTotal, $pay, $status, $shippedAt, $nItems, $nShipped, $titles);
$orders = [];
while (mysqli_stmt_fetch($stmt)) $orders[] = compact('orderId', 'orderedAt', 'days', 'rentTotal', 'depositTotal', 'pay', 'status', 'shippedAt', 'nItems', 'nShipped', 'titles');
mysqli_stmt_close($stmt);

$pageTitle = 'การเช่าของฉัน';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>การเช่าของฉัน</h1><p>ติดตามสถานะ ชำระเงิน และต่ออายุได้จากหน้ารายละเอียดของแต่ละใบสั่งเช่า</p></div><a class="btn btn-primary" href="index.php">เลือกหนังสือเพิ่ม</a></div>
<?php if ($nLate > 0): ?><div class="alert alert-err" role="alert">มีหนังสือเกินกำหนดคืน <?= (int)$nLate ?> เล่ม ค่าปรับคิดวันละเท่าราคาเช่า กรุณาส่งคืนโดยเร็ว</div><?php endif; ?>
<?php if ($nSoon > 0): ?><div class="alert alert-info" role="status">มีหนังสือครบกำหนดคืนภายใน 2 วัน <?= (int)$nSoon ?> เล่ม ต่ออายุได้ 1 ครั้งในหน้ารายละเอียด</div><?php endif; ?>
<?php if (!$orders): ?>
<div class="card empty"><h3>ยังไม่มีรายการเช่า</h3><p>เลือกหนังสือเล่มแรกของคุณได้เลย</p><a class="btn btn-primary" href="index.php">ไปที่ร้าน</a></div>
<?php else: foreach ($orders as $o): $st = order_stage($o['status'], $o['pay'], $o['shippedAt'], $o['nShipped']); ?>
<article class="card">
  <div class="card-title">
    <div><strong><a href="order.php?id=<?= (int)$o['orderId'] ?>">ใบสั่งเช่า <?= h(order_no($o['orderId'])) ?></a></strong> <span class="muted small">&middot; <?= h(date('d/m/Y H:i', strtotime($o['orderedAt']))) ?> &middot; <?= (int)$o['days'] ?> วัน</span></div>
    <span class="badge <?= h($st[1]) ?>"><?= h($st[0]) ?></span>
  </div>
  <p class="muted" style="margin:0 0 .5rem"><?= $o['status'] === 'cancelled' ? 'ใบสั่งเช่านี้ถูกยกเลิก' : h($o['titles'] ?? '') ?></p>
  <div class="card-title" style="margin:0">
    <span>ยอดโอน <strong>฿<?= h(money($o['rentTotal'] + $o['depositTotal'])) ?></strong> <span class="muted small">(ค่าเช่า ฿<?= h(money($o['rentTotal'])) ?> + มัดจำ ฿<?= h(money($o['depositTotal'])) ?>)</span></span>
    <a class="btn btn-outline btn-sm" href="order.php?id=<?= (int)$o['orderId'] ?>"><?= $o['pay'] === 'unpaid' && $o['status'] === 'open' ? 'ชำระเงิน' : 'ดูรายละเอียด' ?></a>
  </div>
</article>
<?php endforeach; endif; ?>
<?php require 'includes/footer.php'; ?>
