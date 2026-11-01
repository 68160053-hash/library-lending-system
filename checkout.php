<?php
require 'auth.php'; require 'config/db.php'; require_once __DIR__ . '/includes/orders.php';
require_login();
expire_unpaid_orders($conn);
auto_lose_overdue($conn);
$userId = (int)$_SESSION['user_id'];
$ids = cart_ids();
if (!$ids) { flash('err', 'ตะกร้ายังว่างอยู่'); header('Location: cart.php'); exit; }
$overdueN = member_overdue_count($conn, $userId); // a member with a book past its due date cannot rent more

// The member's saved details fill the form in
$stmt = mysqli_prepare($conn, 'SELECT full_name, phone, default_address FROM lib_users WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $uName, $uPhone, $uAddress);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$f = ['days' => (int)($_GET['days'] ?? 7), 'name' => $uName, 'phone' => $uPhone, 'address' => $uAddress];
$couponIn = (string)($_GET['coupon'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Step 1: read and check everything on the server (the browser's own checks can be bypassed). Prices are never read from the form.
    $f['days'] = (int)($_POST['days'] ?? 0);
    $f['name'] = trim((string)($_POST['ship_name'] ?? ''));
    $f['phone'] = trim((string)($_POST['ship_phone'] ?? ''));
    $f['address'] = trim((string)($_POST['ship_address'] ?? ''));
    $couponIn = (string)($_POST['coupon'] ?? '');
    if (($_POST['do'] ?? '') === 'apply') {
        // "ใช้โค้ด" / changing the days only recalculates the price on this page; nothing is saved
    } elseif ($overdueN > 0) $error = 'คุณมีหนังสือเกินกำหนดคืน ' . $overdueN . ' เล่ม ต้องส่งคืนก่อนจึงจะเช่าเล่มใหม่ได้';
    elseif (!in_array($f['days'], [7, 14, 30], true)) $error = 'กรุณาเลือกระยะเวลาเช่า';
    elseif (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 100) $error = 'ชื่อผู้รับต้องยาว 2-100 ตัวอักษร';
    elseif (!preg_match('/^[0-9+ -]{9,20}$/', $f['phone'])) $error = 'เบอร์โทรไม่ถูกต้อง (ตัวเลข 9-20 ตัว)';
    elseif (mb_strlen($f['address']) < 10 || mb_strlen($f['address']) > 255) $error = 'ที่อยู่จัดส่งต้องยาว 10-255 ตัวอักษร';
    elseif (contact_has_overdue($conn, $userId, $f['phone'], $f['address'])) $error = 'ไม่สามารถสั่งเช่าด้วยที่อยู่หรือเบอร์โทรนี้ได้ในขณะนี้ กรุณาติดต่อร้าน';
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
    if ($error === '' && ($_POST['do'] ?? '') !== 'apply') {
        // Step 3: take one copy of every book out of stock. Each UPDATE checks and changes the number in one go,
        // so two customers can never both get the last copy. If any book is gone, nothing is kept (rollback).
        $lines = []; $rentTotal = 0; $depositTotal = 0; $gone = false; $guessed = false;
        sort($ids); // same lock order for everyone, so two carts with the same books can never deadlock
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
            // Step 3b: discounts. Bundle first, then the coupon on what is left of the rent. The coupon is used up by one UPDATE that
            // checks the limit in its WHERE, so when two people race for the last use only one of them gets it.
            $discount = bundle_discount($rentTotal, count($lines)); $couponId = 0; $couponCode = null;
            if (trim($couponIn) !== '') {
                $cc = coupon_check($conn, $couponIn, $userId, $rentTotal, $rentTotal - $discount);
                if (!$cc['ok']) { $error = $cc['error']; $guessed = true; }
                else {
                    $stmt = mysqli_prepare($conn, 'UPDATE lib_coupons SET used_count = used_count + 1 WHERE coupon_id = ? AND active = 1 AND (max_uses IS NULL OR used_count < max_uses)');
                    mysqli_stmt_bind_param($stmt, 'i', $cc['coupon_id']);
                    mysqli_stmt_execute($stmt);
                    $took = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);
                    if ($took !== 1) $error = 'โค้ดนี้ถูกใช้ครบจำนวนสิทธิ์แล้ว';
                    else { $couponId = $cc['coupon_id']; $couponCode = $cc['code']; $discount += $cc['discount']; }
                }
            }
            if ($error !== '') {
                mysqli_rollback($conn);
                if ($guessed) log_attempt($conn, 'coupon', 'u' . $userId); // written after the rollback, or the guess would be erased with it
            } else {
                // Step 4: the order (rent_total is the GROSS rent), then one rental row per book
                $stmt = mysqli_prepare($conn, 'INSERT INTO lib_orders (user_id, rent_days, rent_total, discount_total, coupon_code, deposit_total, ship_name, ship_phone, ship_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iiddsdsss', $userId, $f['days'], $rentTotal, $discount, $couponCode, $depositTotal, $f['name'], $f['phone'], $f['address']);
                $ok = mysqli_stmt_execute($stmt);
                $orderId = mysqli_stmt_insert_id($stmt);
                mysqli_stmt_close($stmt);
                if ($ok && $couponId > 0) {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO lib_coupon_uses (coupon_id, user_id, order_id) VALUES (?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'iii', $couponId, $userId, $orderId);
                    $ok = mysqli_stmt_execute($stmt);
                    if (!$ok && mysqli_stmt_errno($stmt) === 1062) $error = 'คุณใช้โค้ดนี้ไปแล้ว (ใช้ได้คนละครั้ง)';
                    mysqli_stmt_close($stmt);
                }
                foreach ($lines as $ln) {
                    if (!$ok) break;
                    $stmt = mysqli_prepare($conn, "INSERT INTO lib_loans (order_id, user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit) VALUES (?, ?, ?, CURDATE(), CURDATE() + INTERVAL ? DAY, 'pending', ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'iiiidd', $orderId, $userId, $ln[0], $f['days'], $ln[1], $ln[2]);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
                if (!$ok) { mysqli_rollback($conn); if ($error === '') $error = 'สั่งเช่าไม่สำเร็จ ลองใหม่อีกครั้ง'; }
                else {
                    // remember phone and address for next time (only fills empty fields, never overwrites)
                    $stmt = mysqli_prepare($conn, "UPDATE lib_users SET phone = IF(phone = '', ?, phone), default_address = IF(default_address = '', ?, default_address) WHERE user_id = ?");
                    mysqli_stmt_bind_param($stmt, 'ssi', $f['phone'], $f['address'], $userId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    mysqli_commit($conn);
                    $_SESSION['cart'] = [];
                    flash('ok', 'สั่งเช่าเรียบร้อย กรุณาชำระเงินภายใน 48 ชั่วโมง แล้วกด "แจ้งโอนเงิน"');
                    header('Location: order.php?id=' . (int)$orderId); exit;
                }
            }
        }
    }
}
if (!in_array($f['days'], [7, 14, 30], true)) $f['days'] = 7;

$items = [];
$res = mysqli_query($conn, 'SELECT book_id, category_id, title, isbn, rent_per_day, deposit, copies_available FROM lib_books WHERE book_id IN (' . implode(',', $ids) . ')'); // $ids are whole numbers from cart_ids()
while ($res && ($row = mysqli_fetch_assoc($res))) $items[] = $row;
$rentTotal = 0; $depositTotal = 0;
foreach ($items as $it) { $rentTotal += $it['rent_per_day'] * $f['days']; $depositTotal += $it['deposit']; }
$coupon = null;
if (trim($couponIn) !== '') $coupon = coupon_check($conn, $couponIn, $userId, $rentTotal, $rentTotal - bundle_discount($rentTotal, count($items)));
$p = order_pricing($rentTotal, $depositTotal, count($items), $coupon && $coupon['ok'] ? $coupon['discount'] : 0);
$nudge = bundle_nudge(count($items));

$pageTitle = 'ชำระเงิน';
$pageCss = ['orders']; $pageJs = ['orders'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>ยืนยันการเช่า</h1><p>กรอกที่อยู่จัดส่ง แล้วชำระเงินตามยอดในขั้นตอนถัดไป</p></div></div>
<?php if ($overdueN > 0): ?><div class="alert alert-err" role="alert"><?= icon('clock', 20) ?><span>คุณมีหนังสือเกินกำหนดคืน <?= (int)$overdueN ?> เล่ม ต้องส่งคืนก่อนจึงจะเช่าเล่มใหม่ได้ <a href="my-orders.php">ดูรายการที่ต้องคืน</a></span></div>
<?php elseif ($error): ?><div class="alert alert-err" role="alert"><?= h($error) ?></div><?php endif; ?>
<form action="checkout.php" method="post" class="checkout">
  <?php csrf_field(); ?>
  <section class="card" aria-label="ข้อมูลจัดส่ง">
    <h3>ข้อมูลจัดส่ง</h3>
    <div class="field"><label for="ship_name">ชื่อผู้รับ</label><input type="text" id="ship_name" name="ship_name" maxlength="100" autocomplete="name" value="<?= h($f['name']) ?>" required></div>
    <div class="field"><label for="ship_phone">เบอร์โทร</label><input type="tel" id="ship_phone" name="ship_phone" maxlength="20" autocomplete="tel" value="<?= h($f['phone']) ?>" required><p class="hint">ให้ขนส่งใช้ติดต่อตอนส่งพัสดุ</p></div>
    <div class="field"><label for="ship_address">ที่อยู่จัดส่ง</label><textarea id="ship_address" name="ship_address" maxlength="255" autocomplete="shipping street-address" required placeholder="บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด รหัสไปรษณีย์"><?= h($f['address']) ?></textarea></div>
    <h3>ระยะเวลาเช่า</h3>
    <div class="days"><?php foreach ([7, 14, 30] as $d): ?><label><input type="radio" name="days" value="<?= (int)$d ?>"<?= $f['days'] === $d ? ' checked' : '' ?> data-recalc><?= (int)$d ?> วัน</label><?php endforeach; ?></div>
    <p class="muted small" style="margin-top:.6rem">วันเช่านับจากวันที่ร้านส่งพัสดุ เลือกจำนวนวันแล้วราคาด้านขวาจะคำนวณใหม่ให้</p>
  </section>
  <aside class="card" aria-label="สรุปคำสั่งเช่า">
    <h3>สรุป</h3>
    <ul class="lines cart-lines mini"><?php foreach ($items as $it): ?><li class="<?= h(zone_class($it['category_id'])) ?>"><span class="cover-tile"><?= cover_html($it['isbn'], $it['title'], $it['book_id'], false) ?></span><div><strong><?= h($it['title']) ?></strong><br><span class="small muted">฿<?= h(money($it['rent_per_day'])) ?>/วัน</span></div><span></span></li><?php endforeach; ?></ul>
    <?php if ($nudge !== ''): ?><p class="nudge"><?= icon('gift', 20) ?><span><?= h($nudge) ?></span> <a href="cart.php">กลับไปตะกร้า</a></p><?php endif; ?>
    <div class="field coupon-field"><label for="coupon">โค้ดส่วนลด</label>
      <div class="coupon-row"><input type="text" id="coupon" name="coupon" maxlength="30" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="เช่น WELCOME10" value="<?= h($couponIn) ?>"><button type="submit" name="do" value="apply" class="btn btn-outline" formnovalidate>ใช้โค้ด</button></div>
      <?php if ($coupon && $coupon['ok']): ?><p class="hint coupon-ok"><?= icon('check', 16) ?>ใช้โค้ด <?= h($coupon['code']) ?> ลดค่าเช่า ฿<?= h(money($p['coupon'])) ?></p>
      <?php elseif ($coupon): ?><p class="hint coupon-err" role="alert"><?= h($coupon['error']) ?></p><?php endif; ?></div>
    <?= price_sum_html($p, $f['days'], $coupon && $coupon['ok'] ? $coupon['code'] : '', 'โอนรวม') ?>
    <p style="margin-top:1rem"><button type="submit" class="btn btn-accent btn-block"<?= $overdueN > 0 ? ' disabled' : '' ?>>ยืนยันการเช่า</button></p>
    <p class="muted small">ยังไม่ต้องโอนตอนนี้ ระบบจะแสดงวิธีชำระเงินในขั้นตอนถัดไป และสงวนหนังสือไว้ให้ 48 ชั่วโมง</p>
  </aside>
</form>
<?php require 'includes/footer.php'; ?>
