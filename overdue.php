<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check: members get 403

// Only books already shipped can be overdue. Fee = late days x the daily rate saved on the rental (plus any renewal fee).
$sql = "SELECT l.order_id, u.full_name, u.phone, b.title, l.due_date,
               DATEDIFF(CURDATE(), l.due_date) AS days_late,
               DATEDIFF(CURDATE(), l.due_date) * l.daily_rate AS fine, l.deposit
        FROM lib_loans l
        JOIN lib_users u ON u.user_id = l.user_id
        JOIN lib_books b ON b.book_id = l.book_id
        WHERE l.status = 'shipped' AND l.due_date < CURDATE()
        ORDER BY l.due_date";
$result = mysqli_query($conn, $sql);
$rows = [];
while ($result && ($r = mysqli_fetch_assoc($result))) $rows[] = $r;

$pageTitle = 'เกินกำหนดคืน';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>หนังสือเกินกำหนดคืน</h1><p>ค่าปรับ = วันที่เกินกำหนด x ราคาเช่าต่อวันของเล่มนั้น (หักจากมัดจำตอนรับคืน)</p></div></div>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <caption class="sr-only">หนังสือที่เกินกำหนดคืน</caption>
  <thead><tr><th>ผู้เช่า</th><th>เบอร์โทร</th><th>หนังสือ</th><th>กำหนดคืน</th><th class="num">เกิน</th><th class="num">ค่าปรับ</th><th class="num">มัดจำ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $over = $r['fine'] >= $r['deposit']; ?>
  <tr>
    <td><?= h($r['full_name']) ?></td><td><?= h($r['phone']) ?></td><td><?= h($r['title']) ?></td>
    <td><?= h(date('d/m/Y', strtotime($r['due_date']))) ?></td>
    <td class="num"><span class="badge b-bad"><?= (int)$r['days_late'] ?> วัน</span></td>
    <td class="num">฿<?= h(money($r['fine'])) ?></td>
    <td class="num">฿<?= h(money($r['deposit'])) ?><?= $over ? ' <span class="badge b-bad">ค่าปรับเกินมัดจำ</span>' : '' ?></td>
    <td><?php if ($r['order_id']): ?><a class="btn btn-outline btn-sm" href="order.php?id=<?= (int)$r['order_id'] ?>">เปิดใบสั่งเช่า</a><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="center muted" style="padding:2rem">ไม่มีหนังสือเกินกำหนด</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require 'includes/footer.php'; ?>
