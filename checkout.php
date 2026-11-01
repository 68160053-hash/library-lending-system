<?php
require 'auth.php'; require 'config/db.php';
require_login();
expire_unpaid_orders($conn);
$userId = (int)$_SESSION['user_id'];
$ids = cart_ids();
if (!$ids) { flash('err', 'ตะกร้ายังว่างอยู่'); header('Location: cart.php'); exit; }

// The member's saved details fill the form in
$stmt = mysqli_prepare($conn, 'SELECT full_name, phone, default_address FROM lib_users WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $uName, $uPhone, $uAddress);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$f = ['days' => (int)($_GET['days'] ?? 7), 'name' => $uName, 'phone' => $uPhone, 'address' => $uAddress];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Step 1: read and check everything on the server (the browser's own checks can be bypassed)
    $f['days'] = (int)($_POST['days'] ?? 0);
    $f['name'] = trim((string)($_POST['ship_name'] ?? ''));
    $f['phone'] = trim((string)($_POST['ship_phone'] ?? ''));
    $f['address'] = trim((string)($_POST['ship_address'] ?? ''));
    if (!in_array($f['days'], [7, 14, 30], true)) $error = 'กรุณาเลือกระยะเวลาเช่า';
    elseif (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100) $error = 'ชื่อผู้รับต้องยาว 2-100 ตัวอักษร';
    elseif (!preg_match('/^[0-9+ -]{9,20}$/', $f['phone'])) $error = 'เบอร์โทรไม่ถูกต้อง (ตัวเลข 9-20 ตัว)';
    elseif (mb_strlen($f['address']) < 10 || mb_strlen($f['address']) > 255) $error = 'ที่อยู่จัดส่งต้องยาว 10-255 ตัวอักษร';
    else {
        // Step 2: at most 3 unpaid orders at a time (stops someone locking up all the books without paying).
        // The transaction starts here and locks the member's row, so parallel checkouts by one member line up one after another.
        mysqli_begin_transaction($conn);
        $stmt = mysqli_prepare($conn, 'SELECT user_id FROM lib_users WHERE user_id = ? FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM lib_orders WHERE user_id = ? AND status = 'open' AND pay_status = 'unpaid'");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $unpaid);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ($unpaid >= 3) { mysqli_rollback($conn); $error = 'คุณมีใบสั่งเช่าที่ยังไม่ชำระ 3 ใบแล้ว กรุณาชำระหรือยกเลิกก่อน'; }
    }
    if ($error === '') {
        // Step 3: take one copy of every book out of stock. Each UPDATE checks and changes the number in one go,
        // so two customers can never both get the last copy. If any book is gone, nothing is kept (rollback).
        $lines = []; $rentTotal = 0; $depositTotal = 0; $gone = false;
        foreach ($ids as $bookId) {
            $stmt = mysqli_prepare($conn, 'UPDATE lib_books SET copies_available = copies_available - 1 WHERE book_id = ? AND copies_available > 0');
            mysqli_stmt_bind_param($stmt, 'i', $bookId);
            mysqli_stmt_execute($stmt);
            $got = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($got !== 1) { $gone = true; break; }
            $stmt = mysqli_prepare($conn, 'SELECT rent_per_day, deposit FROM lib_books WHERE book_id = ?'); // the price now, copied into the rental
            mysqli_stmt_bind_param($stmt, 'i', $bookId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $rate, $dep);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            $lines[] = [$bookId, $rate, $dep];
            $rentTotal += $rate * $f['days']; $depositTotal += $dep;
        }
        if ($gone) {
            mysqli_rollback($conn);
            $error = 'มีหนังสือในตะกร้าถูกเช่าหมดพอดี กรุณาตรวจตะกร้าอีกครั้ง';
        } else {
            // Step 4: the order, then one rental row per book
            $stmt = mysqli_prepare($conn, 'INSERT INTO lib_orders (user_id, rent_days, rent_total, deposit_total, ship_name, ship_phone, ship_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iiddsss', $userId, $f['days'], $rentTotal, $depositTotal, $f['name'], $f['phone'], $f['address']);
            $ok = mysqli_stmt_execute($stmt);
            $orderId = mysqli_stmt_insert_id($stmt);
            mysqli_stmt_close($stmt);
            foreach ($lines as $ln) {
                if (!$ok) break;
                $stmt = mysqli_prepare($conn, "INSERT INTO lib_loans (order_id, user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit) VALUES (?, ?, ?, CURDATE(), CURDATE() + INTERVAL ? DAY, 'pending', ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iiiidd', $orderId, $userId, $ln[0], $f['days'], $ln[1], $ln[2]);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            if (!$ok) { mysqli_rollback($conn); $error = 'สั่งเช่าไม่สำเร็จ ลองใหม่อีกครั้ง'; }
            else {
                // remember phone and address for next time (only fills empty fields, never overwrites)
                $stmt = mysqli_prepare($conn, "UPDATE lib_users SET phone = IF(phone = '', ?, phone), default_address = IF(default_address = '', ?, default_address) WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssi', $f['phone'], $f['address'], $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                mysqli_commit($conn);
                $_SESSION['cart'] = [];
                flash('ok', 'สั่งเช่าเรียบร้อย กรุณาโอนเงินภายใน 48 ชั่วโมง แล้วกด "แจ้งโอนเงิน"');
                header('Location: order.php?id=' . (int)$orderId); exit;
            }
        }
    }
}
if (!in_array($f['days'], [7, 14, 30], true)) $f['days'] = 7;

$items = [];
$res = mysqli_query($conn, 'SELECT book_id, title, isbn, rent_per_day, deposit, copies_available FROM lib_books WHERE book_id IN (' . implode(',', $ids) . ')'); // $ids are whole numbers from cart_ids()
while ($res && ($row = mysqli_fetch_assoc($res))) $items[] = $row;
$rentTotal = 0; $depositTotal = 0;
foreach ($items as $it) { $rentTotal += $it['rent_per_day'] * $f['days']; $depositTotal += $it['deposit']; }

$pageTitle = 'ชำระเงิน';
require 'includes/header.php';
?>
<div class="page-head"><div><h1>ยืนยันการเช่า</h1><p>กรอกที่อยู่จัดส่ง แล้วโอนเงินตามยอดในขั้นตอนถัดไป</p></div></div>
<?php if ($error): ?><div class="alert alert-err" role="alert"><?= h($error) ?></div><?php endif; ?>
<form action="checkout.php" method="post" class="checkout">
  <?php csrf_field(); ?>
  <section class="card" aria-label="ข้อมูลจัดส่ง">
    <h3>ข้อมูลจัดส่ง</h3>
    <div class="field"><label for="ship_name">ชื่อผู้รับ</label><input type="text" id="ship_name" name="ship_name" maxlength="100" autocomplete="name" value="<?= h($f['name']) ?>" required></div>
    <div class="field"><label for="ship_phone">เบอร์โทร</label><input type="tel" id="ship_phone" name="ship_phone" maxlength="20" autocomplete="tel" value="<?= h($f['phone']) ?>" required><p class="hint">ให้ขนส่งใช้ติดต่อตอนส่งพัสดุ</p></div>
    <div class="field"><label for="ship_address">ที่อยู่จัดส่ง</label><textarea id="ship_address" name="ship_address" maxlength="255" autocomplete="shipping street-address" required placeholder="บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด รหัสไปรษณีย์"><?= h($f['address']) ?></textarea></div>
    <h3>ระยะเวลาเช่า</h3>
    <div class="days"><?php foreach ([7, 14, 30] as $d): ?><label><input type="radio" name="days" value="<?= (int)$d ?>"<?= $f['days'] === $d ? ' checked' : '' ?>><?= (int)$d ?> วัน</label><?php endforeach; ?></div>
    <p class="muted small" style="margin-top:.6rem">วันเช่านับจากวันที่ร้านส่งพัสดุ ราคาด้านขวาคำนวณตามระยะเวลาที่เลือกในหน้าตะกร้า (เปลี่ยนที่หน้าตะกร้าเพื่อดูราคาก่อน)</p>
  </section>
  <aside class="card" aria-label="สรุปคำสั่งเช่า">
    <h3>สรุป</h3>
    <ul class="lines"><?php foreach ($items as $it): ?><li><?= cover_html($it['isbn'], $it['title'], $it['book_id'], false) ?><div><strong><?= h($it['title']) ?></strong><br><span class="small muted">฿<?= h(money($it['rent_per_day'])) ?>/วัน</span></div><span></span></li><?php endforeach; ?></ul>
    <div class="sum">
      <span>ค่าเช่า <?= (int)$f['days'] ?> วัน</span><span>฿<?= h(money($rentTotal)) ?></span>
      <span>ค่ามัดจำ (คืนให้)</span><span>฿<?= h(money($depositTotal)) ?></span>
      <span class="total">โอนรวม</span><span class="total">฿<?= h(money($rentTotal + $depositTotal)) ?></span>
    </div>
    <p style="margin-top:1rem"><button type="submit" class="btn btn-accent btn-block">ยืนยันการเช่า</button></p>
    <p class="muted small">ยังไม่ต้องโอนตอนนี้ ระบบจะแสดงเลขบัญชีในขั้นตอนถัดไป และสงวนหนังสือไว้ให้ 48 ชั่วโมง</p>
  </aside>
</form>
<?php require 'includes/footer.php'; ?>
