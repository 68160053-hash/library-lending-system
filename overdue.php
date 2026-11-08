<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403

// Only books already shipped can be overdue. Fee = late days x the daily rate saved on the rental, plus 7 days of rent per renewal (same rule as order.php).
$sql = "SELECT l.order_id, u.full_name, u.phone, b.title, l.due_date,
               DATEDIFF(CURDATE(), l.due_date) AS days_late,
               (DATEDIFF(CURDATE(), l.due_date) + 7 * l.renew_count) * l.daily_rate AS fine, l.deposit, b.category_id
        FROM lib_loans l
        JOIN lib_users u ON u.user_id = l.user_id
        JOIN lib_books b ON b.book_id = l.book_id
        WHERE l.status = 'shipped' AND l.due_date < CURDATE()
        ORDER BY l.due_date";
$result = mysqli_query($conn, $sql);
$rows = [];
while ($result && ($r = mysqli_fetch_assoc($result))) $rows[] = $r;

$pageTitle = 'เกินกำหนดคืน'; $pageCss = ['staff'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>หนังสือเกินกำหนดคืน</h1><p>ค่าปรับ = วันที่เกินกำหนด x ราคาเช่าต่อวัน + ค่าเช่าต่ออายุ (7 วัน x ราคาเช่าต่อวัน ต่อครั้งที่ต่อ) หักจากมัดจำตอนรับคืน &middot; เกิน <?= (int)AUTO_LOST_DAYS ?> วันระบบตัดเป็นสูญหาย</p></div>
  <?php if ($rows): ?><span class="badge b-bad"><?= count($rows) ?> เล่ม</span><?php endif; ?></div>
<div class="card"><div class="tbl-wrap"><table class="tbl stack">
  <caption class="sr-only">หนังสือที่เกินกำหนดคืน</caption>
  <thead><tr><th>หนังสือ</th><th>ผู้เช่า</th><th>เบอร์โทร</th><th>กำหนดคืน</th><th class="num">เกิน</th><th class="num">ค่าปรับ</th><th class="num">มัดจำ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $over = $r['fine'] > $r['deposit']; $left = (int)AUTO_LOST_DAYS - (int)$r['days_late']; ?>
  <tr>
    <td class="full"><span class="zchip <?= h(zone_class($r['category_id'])) ?>"><?= h(zone_letter($r['category_id'])) ?></span> <strong><?= h($r['title']) ?></strong></td>
    <td data-label="ผู้เช่า"><?= h($r['full_name']) ?></td><td data-label="เบอร์โทร"><?= h($r['phone']) ?></td>
    <td data-label="กำหนดคืน"><?= h(date('d/m/Y', strtotime($r['due_date']))) ?></td>
    <td class="num" data-label="เกิน"><span class="badge b-bad"><?= (int)$r['days_late'] ?> วัน</span><br><span class="small <?= $left > 0 ? 'muted' : 'lostnow' ?>"><?= $left > 0 ? 'ถูกตัดเป็นสูญหายในอีก ' . $left . ' วัน' : 'ถึงกำหนดตัดเป็นสูญหายแล้ว' ?></span></td>
    <td class="num" data-label="ค่าปรับ">฿<?= h(money($r['fine'])) ?></td>
    <td class="num" data-label="มัดจำ">฿<?= h(money($r['deposit'])) ?><?= $over ? ' <span class="badge b-bad">ค่าปรับเกินมัดจำ</span>' : '' ?></td>
    <td class="full right"><?php if ($r['order_id']): ?><a class="btn btn-outline btn-sm" href="order.php?id=<?= (int)$r['order_id'] ?>">เปิดใบสั่งเช่า</a><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="center empty">ไม่มีหนังสือเกินกำหนด</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require 'includes/footer.php'; ?>
