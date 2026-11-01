<?php
require 'auth.php'; require 'config/db.php';
require_login();
// One order: ?id=<order id>. The owner sees their own order; staff can open any order and get the action buttons.
$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId < 1) { http_response_code(404); exit('ไม่พบใบสั่งเช่า <a href="my-orders.php">กลับ</a>'); }
$me = (int)$_SESSION['user_id'];

$sql = 'SELECT o.user_id, o.ordered_at, o.rent_days, o.rent_total, o.deposit_total, o.ship_name, o.ship_phone, o.ship_address,
               o.pay_status, o.pay_note, o.pay_reported_at, o.pay_confirmed_at, o.courier, o.tracking_no, o.shipped_at, o.status, u.full_name, u.email
        FROM lib_orders o JOIN lib_users u ON u.user_id = o.user_id WHERE o.order_id = ?';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $ownerId, $orderedAt, $days, $rentTotal, $depositTotal, $shipName, $shipPhone, $shipAddress, $pay, $payNote, $payReportedAt, $payConfirmedAt, $courier, $trackingNo, $shippedAt, $status, $ownerName, $ownerEmail);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$isOwner = $found && (int)$ownerId === $me;
// Not yours and not staff: the page behaves as if the order does not exist (no hint that the number is real)
if (!$found || (!$isOwner && !is_staff())) { http_response_code(404); exit('ไม่พบใบสั่งเช่า <a href="my-orders.php">กลับ</a>'); }
if (!$isOwner) require_staff(); // staff rights are checked against the database, not the session copy
$staffView = is_staff();

// The books of this order, with fee maths worked out in SQL from the dates (nothing extra is stored)
$sql = "SELECT l.loan_id, l.book_id, b.title, b.isbn, l.status, l.daily_rate, l.deposit, l.due_date, l.returned_at, l.renew_count,
               CASE WHEN l.status = 'shipped' THEN GREATEST(0, DATEDIFF(CURDATE(), l.due_date))
                    WHEN l.status = 'returned' THEN GREATEST(0, DATEDIFF(l.returned_at, l.due_date)) ELSE 0 END AS late_days
        FROM lib_loans l JOIN lib_books b ON b.book_id = l.book_id WHERE l.order_id = ? ORDER BY l.loan_id";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $lId, $lBook, $lTitle, $lIsbn, $lStatus, $lRate, $lDeposit, $lDue, $lReturned, $lRenew, $lLate);
$loans = [];
$nShipped = 0;
while (mysqli_stmt_fetch($stmt)) {
    $fee = $lLate * $lRate + $lRenew * 7 * $lRate;   // late days x rate, plus 7 days of rent for each renewal
    $loans[] = ['id' => $lId, 'book' => $lBook, 'title' => $lTitle, 'isbn' => $lIsbn, 'status' => $lStatus, 'rate' => $lRate, 'deposit' => $lDeposit,
                'due' => $lDue, 'late' => $lLate, 'renew' => $lRenew, 'fee' => $fee,
                'refund' => $lStatus === 'returned' ? max(0, $lDeposit - $fee) : 0];
    if ($lStatus === 'shipped') $nShipped++;
}
mysqli_stmt_close($stmt);

$st = order_stage($status, $pay, $shippedAt, $nShipped);
$open = $status === 'open';
$canCancel = $open && $shippedAt === null;
$steps = ['สั่งเช่า', 'ชำระเงิน', 'เตรียมจัดส่ง', 'อยู่ระหว่างเช่า', 'คืนแล้ว'];
$loanLabels = ['pending' => ['รอส่งพัสดุ', 'b-warn'], 'shipped' => ['อยู่กับคุณ', 'b-info'], 'returned' => ['คืนแล้ว', 'b-ok'], 'lost' => ['สูญหาย', 'b-bad']];
$grand = $rentTotal + $depositTotal;
$couriers = ['ไปรษณีย์ไทย', 'Kerry Express', 'Flash Express', 'J&T Express', 'อื่นๆ'];

$pageTitle = 'ใบสั่งเช่า ' . order_no($orderId);
require 'includes/header.php';
?>
<nav class="crumbs" aria-label="เส้นทาง"><a href="<?= $isOwner ? 'my-orders.php' : 'rentals.php' ?>"><?= $isOwner ? 'การเช่าของฉัน' : 'คำสั่งเช่า' ?></a> / <?= h(order_no($orderId)) ?></nav>
<div class="page-head">
  <div><h1>ใบสั่งเช่า <?= h(order_no($orderId)) ?></h1><p>สั่งเมื่อ <?= h(date('d/m/Y H:i', strtotime($orderedAt))) ?> &middot; เช่า <?= (int)$days ?> วัน<?= $staffView && !$isOwner ? ' &middot; ผู้เช่า: ' . h($ownerName) . ' (' . h($ownerEmail) . ')' : '' ?></p></div>
  <span class="badge <?= h($st[1]) ?>" style="font-size:1rem"><?= h($st[0]) ?></span>
</div>

<?php if ($status !== 'cancelled'): ?>
<ol class="stepper" aria-label="ขั้นตอน">
  <?php foreach ($steps as $i => $label): $n = $i + 1; $cls = $n === 1 || $n < $st[2] || $st[2] === 6 ? 'done' : ($n === $st[2] ? 'now' : ''); ?>
  <li class="<?= $cls ?>" data-n="<?= (int)$n ?>"<?= $cls === 'now' ? ' aria-current="step"' : '' ?>><?= h($label) ?></li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>

<div class="checkout">
  <div>
    <section class="card" aria-label="รายการหนังสือ">
      <h3>หนังสือในใบสั่งเช่า</h3>
      <?php if (!$loans): ?><p class="muted">ไม่มีรายการ (ใบสั่งเช่านี้ถูกยกเลิก)</p><?php else: ?>
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>หนังสือ</th><th>สถานะ</th><th>กำหนดคืน</th><th class="num">ค่าปรับ</th><th class="num">มัดจำ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($loans as $l): $lab = $loanLabels[$l['status']]; ?>
        <tr>
          <td><a href="book.php?id=<?= (int)$l['book'] ?>"><strong><?= h($l['title']) ?></strong></a><br><span class="muted small">฿<?= h(money($l['rate'])) ?>/วัน</span></td>
          <td><span class="badge <?= $lab[1] ?>"><?= h($lab[0]) ?></span><?php if ($l['status'] === 'shipped' && $l['late'] > 0): ?><br><span class="badge b-bad">เกิน <?= (int)$l['late'] ?> วัน</span><?php endif; ?></td>
          <td><?= $l['status'] === 'pending' ? '<span class="muted small">เริ่มนับวันที่ส่งพัสดุ</span>' : h(date('d/m/Y', strtotime($l['due']))) ?><?= $l['renew'] ? '<br><span class="muted small">ต่ออายุแล้ว</span>' : '' ?></td>
          <td class="num"><?= $l['fee'] > 0 ? '฿' . h(money($l['fee'])) : '-' ?></td>
          <td class="num">฿<?= h(money($l['deposit'])) ?><?= $l['status'] === 'returned' ? '<br><span class="small b-ok badge">คืนให้ ฿' . h(money($l['refund'])) . '</span>' : '' ?><?= $l['status'] === 'lost' ? '<br><span class="small badge b-bad">ไม่คืนมัดจำ</span>' : '' ?></td>
          <td>
            <?php if ($isOwner && $l['status'] === 'shipped' && $l['renew'] < 1 && $l['late'] < 1): ?>
            <form action="renew.php" method="post"><?php csrf_field(); ?><input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-outline btn-sm" type="submit" data-confirm="ต่ออายุ 7 วัน คิดค่าเช่าเพิ่ม ฿<?= h(money($l['rate'] * 7)) ?> (หักจากมัดจำตอนคืน)">ต่ออายุ +7 วัน</button></form>
            <?php endif; ?>
            <?php if ($staffView && $l['status'] === 'shipped'): ?>
            <form action="order-action.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= (int)$orderId ?>"><input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
              <button class="btn btn-primary btn-sm" type="submit" name="action" value="receive">รับคืนแล้ว</button>
              <button class="btn btn-danger btn-sm" type="submit" name="action" value="lost" data-confirm="แจ้งหนังสือเล่มนี้สูญหาย? จะไม่คืนมัดจำและหักออกจากสต็อก">สูญหาย</button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php foreach ($loans as $l) if ($l['status'] === 'returned' && $l['fee'] > $l['deposit']): ?><div class="alert alert-err">เล่ม "<?= h($l['title']) ?>" ค่าปรับเกินมัดจำ ลูกค้าค้างชำระ ฿<?= h(money($l['fee'] - $l['deposit'])) ?></div><?php endif; ?>
      <p class="muted small">ค่าปรับ = จำนวนวันที่เกินกำหนด x ราคาเช่าต่อวัน + ค่าเช่าต่ออายุ (7 วัน x ราคาเช่าต่อวัน) หักจากมัดจำ ส่วนที่เหลือร้านโอนคืนหลังได้รับหนังสือ</p>
      <?php endif; ?>
    </section>

    <?php if ($shippedAt !== null): ?>
    <section class="card" aria-label="การจัดส่ง"><h3>การจัดส่ง</h3>
      <dl class="facts"><dt>ส่งเมื่อ</dt><dd><?= h(date('d/m/Y', strtotime($shippedAt))) ?></dd><dt>ขนส่ง</dt><dd><?= h($courier) ?></dd><dt>เลขพัสดุ</dt><dd><strong><?= h($trackingNo) ?></strong></dd></dl>
      <p class="muted small">ค้นหาสถานะพัสดุจากเลขนี้ที่เว็บไซต์ของบริษัทขนส่ง ส่งหนังสือกลับร้านภายในวันครบกำหนด</p>
    </section>
    <?php endif; ?>
  </div>

  <aside>
    <section class="card" aria-label="ยอดเงิน">
      <h3>ยอดเงิน</h3>
      <div class="sum"><span>ค่าเช่า <?= (int)$days ?> วัน</span><span>฿<?= h(money($rentTotal)) ?></span><span>ค่ามัดจำ</span><span>฿<?= h(money($depositTotal)) ?></span><span class="total">ยอดโอนทั้งหมด</span><span class="total">฿<?= h(money($grand)) ?></span></div>
      <p style="margin-top:.8rem"><span class="badge <?= $pay === 'confirmed' ? 'b-ok' : ($pay === 'reported' ? 'b-info' : 'b-warn') ?>"><?= $pay === 'confirmed' ? 'ชำระเงินแล้ว' : ($pay === 'reported' ? 'แจ้งโอนแล้ว รอตรวจสอบ' : 'ยังไม่ชำระ') ?></span></p>
      <?php if ($pay === 'reported' || $pay === 'confirmed'): ?><p class="muted small"><?= $payNote !== '' ? 'หมายเหตุจากผู้โอน: ' . h($payNote) : '' ?></p><?php endif; ?>

      <?php if ($open && $pay === 'unpaid' && $isOwner): ?>
      <div class="paybox"><strong>โอนเงินมาที่</strong>
        <dl><dt>ธนาคาร</dt><dd><?= h($shop['bank']) ?></dd><dt>เลขบัญชี</dt><dd><?= h($shop['account_no']) ?></dd><dt>ชื่อบัญชี</dt><dd><?= h($shop['account_name']) ?></dd><?php if ($shop['promptpay'] !== ''): ?><dt>พร้อมเพย์</dt><dd><?= h($shop['promptpay']) ?></dd><?php endif; ?><dt>ยอดโอน</dt><dd class="price">฿<?= h(money($grand)) ?></dd></dl>
      </div>
      <form action="order-action.php" method="post"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= (int)$orderId ?>"><input type="hidden" name="action" value="report_pay">
        <div class="field"><label for="pay_note">รายละเอียดการโอน (ไม่บังคับ)</label><input type="text" id="pay_note" name="pay_note" maxlength="80" placeholder="เช่น โอนเวลา 14:30 จากกสิกร"></div>
        <button type="submit" class="btn btn-accent btn-block">แจ้งโอนเงินแล้ว</button></form>
      <p class="muted small">ใบสั่งเช่าที่ไม่ชำระภายใน 48 ชั่วโมงจะถูกยกเลิกอัตโนมัติ</p>
      <?php elseif ($open && $pay === 'reported' && $isOwner): ?><div class="alert alert-info">ร้านกำลังตรวจสอบการโอนของคุณ แล้วจะจัดส่งหนังสือให้</div><?php endif; ?>

      <?php if ($staffView && $open && $pay !== 'confirmed'): ?>
      <form action="order-action.php" method="post" style="margin-top:.8rem"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= (int)$orderId ?>"><input type="hidden" name="action" value="confirm_pay"><button type="submit" class="btn btn-primary btn-block" data-confirm="ยืนยันว่าได้รับเงิน ฿<?= h(money($grand)) ?> แล้ว?">ยืนยันได้รับเงินแล้ว</button></form>
      <?php endif; ?>

      <?php if ($staffView && $open && $pay === 'confirmed' && $shippedAt === null): ?>
      <form action="order-action.php" method="post" style="margin-top:1rem"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= (int)$orderId ?>"><input type="hidden" name="action" value="ship">
        <h3>จัดส่งพัสดุ</h3>
        <div class="field"><label for="courier">ขนส่ง</label><select id="courier" name="courier"><?php foreach ($couriers as $c): ?><option><?= h($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="tracking_no">เลขพัสดุ</label><input type="text" id="tracking_no" name="tracking_no" maxlength="40" required pattern="[A-Za-z0-9-]{6,40}"><p class="hint">ตัวอักษรและตัวเลข 6-40 ตัว</p></div>
        <button type="submit" class="btn btn-accent btn-block">บันทึกว่าส่งพัสดุแล้ว</button></form>
      <?php endif; ?>
    </section>

    <section class="card" aria-label="ที่อยู่จัดส่ง"><h3>ที่อยู่จัดส่ง</h3>
      <p style="margin:0"><strong><?= h($shipName) ?></strong><br><?= h($shipPhone) ?><br><?= nl2br(h($shipAddress)) ?></p></section>

    <?php if ($canCancel && ($staffView || ($isOwner && $pay !== 'confirmed'))): ?>
    <form action="order-action.php" method="post" style="margin-top:1rem"><?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= (int)$orderId ?>"><input type="hidden" name="action" value="cancel">
      <button type="submit" class="btn btn-danger btn-block" data-confirm="ยกเลิกใบสั่งเช่านี้? หนังสือจะกลับเข้าชั้น<?= $pay === 'confirmed' ? ' ต้องโอนเงินคืนลูกค้า ฿' . money($grand) : '' ?>">ยกเลิกใบสั่งเช่า</button></form>
    <?php endif; ?>
  </aside>
</div>
<?php require 'includes/footer.php'; ?>
