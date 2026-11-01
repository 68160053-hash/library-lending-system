<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check from the database: members get 403
expire_unpaid_orders($conn);

// The tab shown. The SQL piece for each tab is fixed text written here; the URL only picks a key from this list.
$shippedBooks = "EXISTS (SELECT 1 FROM lib_loans l WHERE l.order_id = o.order_id AND l.status = 'shipped')";
$tabs = [
    'review'    => ['รอตรวจสอบการโอน', "o.status = 'open' AND o.pay_status = 'reported'"],
    'unpaid'    => ['รอชำระเงิน', "o.status = 'open' AND o.pay_status = 'unpaid'"],
    'ship'      => ['เตรียมจัดส่ง', "o.status = 'open' AND o.pay_status = 'confirmed' AND o.shipped_at IS NULL"],
    'renting'   => ['อยู่ระหว่างเช่า', "o.status = 'open' AND o.shipped_at IS NOT NULL AND $shippedBooks"],
    'done'      => ['เสร็จสิ้น', "o.status = 'open' AND o.shipped_at IS NOT NULL AND NOT $shippedBooks"],
    'cancelled' => ['ยกเลิก', "o.status = 'cancelled'"],
    'all'       => ['ทั้งหมด', '1 = 1'],
];
$tabIn = is_string($_GET['tab'] ?? '') ? ($_GET['tab'] ?? '') : '';
$tab = isset($tabs[$tabIn]) ? $tabIn : 'review';
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
$qNum = (int)preg_replace('/\D/', '', $q);      // "#00012" or "12" finds order 12
$like = '%' . addcslashes($q, '%_\\') . '%';

// number on each tab
$counts = [];
foreach ($tabs as $key => $t) {
    $res = mysqli_query($conn, 'SELECT COUNT(*) FROM lib_orders o WHERE ' . $t[1]);
    $row = $res ? mysqli_fetch_row($res) : [0];
    $counts[$key] = (int)$row[0];
}

$sql = 'SELECT o.order_id, o.ordered_at, u.full_name, o.rent_days, o.rent_total, o.deposit_total, o.pay_status, o.status, o.shipped_at,
               (SELECT COUNT(*) FROM lib_loans l WHERE l.order_id = o.order_id) AS n_items,
               (SELECT COUNT(*) FROM lib_loans l WHERE l.order_id = o.order_id AND l.status = \'shipped\') AS n_shipped
        FROM lib_orders o JOIN lib_users u ON u.user_id = o.user_id
        WHERE (' . $tabs[$tab][1] . ') AND (CHAR_LENGTH(?) = 0 OR o.order_id = ? OR u.full_name LIKE ? OR u.email LIKE ?)
        ORDER BY o.order_id DESC LIMIT 200';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'siss', $q, $qNum, $like, $like);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $orderId, $orderedAt, $member, $days, $rentTotal, $depositTotal, $pay, $status, $shippedAt, $nItems, $nShipped);
$orders = [];
while (mysqli_stmt_fetch($stmt)) $orders[] = compact('orderId', 'orderedAt', 'member', 'days', 'rentTotal', 'depositTotal', 'pay', 'status', 'shippedAt', 'nItems', 'nShipped');
mysqli_stmt_close($stmt);

$pageTitle = 'คำสั่งเช่า';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>คำสั่งเช่า</h1><p>ตรวจการโอน จัดส่งพัสดุ และรับคืนหนังสือ</p></div>
  <form action="rentals.php" method="get" class="search" style="max-width:320px"><input type="hidden" name="tab" value="all"><label class="sr-only" for="oq">ค้นหา</label><input type="text" id="oq" name="q" value="<?= h($q) ?>" maxlength="60" placeholder="เลขที่ใบสั่ง ชื่อ หรืออีเมล"><button type="submit">ค้นหา</button></form></div>
<nav class="tabs" aria-label="กรองตามสถานะ">
  <?php foreach ($tabs as $key => $t): ?><a href="rentals.php?tab=<?= h($key) ?>" class="<?= $key === $tab ? 'on' : '' ?>"><?= h($t[0]) ?> (<?= (int)$counts[$key] ?>)</a><?php endforeach; ?>
</nav>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <caption class="sr-only">ใบสั่งเช่า: <?= h($tabs[$tab][0]) ?></caption>
  <thead><tr><th>เลขที่</th><th>วันที่สั่ง</th><th>ผู้เช่า</th><th class="num">เล่ม</th><th class="num">ยอดโอน</th><th>การชำระเงิน</th><th>สถานะ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): $st = order_stage($o['status'], $o['pay'], $o['shippedAt'], $o['nShipped']); ?>
  <tr>
    <td><a href="order.php?id=<?= (int)$o['orderId'] ?>"><strong><?= h(order_no($o['orderId'])) ?></strong></a></td>
    <td><?= h(date('d/m/Y H:i', strtotime($o['orderedAt']))) ?></td>
    <td><?= h($o['member']) ?></td>
    <td class="num"><?= (int)$o['nItems'] ?></td>
    <td class="num">฿<?= h(money($o['rentTotal'] + $o['depositTotal'])) ?></td>
    <td><span class="badge <?= $o['pay'] === 'confirmed' ? 'b-ok' : ($o['pay'] === 'reported' ? 'b-info' : 'b-warn') ?>"><?= $o['pay'] === 'confirmed' ? 'ชำระแล้ว' : ($o['pay'] === 'reported' ? 'แจ้งโอนแล้ว' : 'ยังไม่ชำระ') ?></span></td>
    <td><span class="badge <?= h($st[1]) ?>"><?= h($st[0]) ?></span></td>
    <td><a class="btn btn-outline btn-sm" href="order.php?id=<?= (int)$o['orderId'] ?>">เปิด</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="8" class="center muted" style="padding:2rem">ไม่มีใบสั่งเช่าในหมวดนี้</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php require 'includes/footer.php'; ?>
