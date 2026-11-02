<?php
require 'auth.php'; require 'config/db.php'; require_once __DIR__ . '/includes/orders.php';
// The cart is a list of book ids kept in the session (max 5 books). Changing it is a POST with a CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
    // Where to go afterwards: only a catalogue address of a fixed shape (never a free URL from the form)
    $back = (string)($_POST['back'] ?? '');
    $go = preg_match('/^index\.php(\?[A-Za-z0-9_=&%.+-]*)?$/', $back) ? $back : 'index.php';
    if (($_POST['then'] ?? '') === 'cart') { $d = (string)($_POST['days'] ?? ''); $go = 'cart.php' . (in_array($d, ['7', '14', '30'], true) ? '?days=' . $d : ''); }
    $ids = cart_ids();
    if ($action === 'add' && $bookId !== false && $bookId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT copies_available FROM lib_books WHERE book_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $left);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if (!$found) flash('err', 'ไม่พบหนังสือเล่มนี้');
        elseif ($left < 1) flash('err', 'หนังสือเล่มนี้ถูกเช่าหมดแล้ว');
        elseif (in_array($bookId, $ids, true)) flash('ok', 'หนังสือเล่มนี้อยู่ในตะกร้าแล้ว');
        elseif (count($ids) >= 5) flash('err', 'ตะกร้าใส่ได้สูงสุด 5 เล่มต่อการสั่ง');
        else { $ids[] = $bookId; $_SESSION['cart'] = $ids; flash('ok', 'เพิ่มลงตะกร้าแล้ว'); }
    } elseif ($action === 'remove' && $bookId !== false) {
        $_SESSION['cart'] = array_values(array_diff($ids, [$bookId]));
        flash('ok', 'เอาออกจากตะกร้าแล้ว');
        $go = 'cart.php';
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        flash('ok', 'ล้างตะกร้าแล้ว');
        $go = 'cart.php';
    }
    header('Location: ' . $go); exit;
}


$days = (int)($_GET['days'] ?? 7);
if (!in_array($days, [7, 14, 30], true)) $days = 7;
$ids = cart_ids();
$items = [];
if ($ids) {
    // The ids are whole numbers made by cart_ids(), so joining them into IN (...) cannot inject anything.
    $res = mysqli_query($conn, 'SELECT book_id, category_id, title, author, isbn, rent_per_day, deposit, copies_available FROM lib_books WHERE book_id IN (' . implode(',', $ids) . ')');
    while ($res && ($row = mysqli_fetch_assoc($res))) $items[] = $row;
}
$rentTotal = 0; $depositTotal = 0; $blocked = false;
foreach ($items as $it) { $rentTotal += $it['rent_per_day'] * $days; $depositTotal += $it['deposit']; if ($it['copies_available'] < 1) $blocked = true; }

// Optional coupon (a read-only preview: the coupon is only used up when the order is placed)
$couponIn = get_str('coupon');
$coupon = null;
if ($items && trim($couponIn) !== '') {
    $bundle = bundle_discount($rentTotal, count($items));
    $coupon = coupon_check($conn, $couponIn, (int)($_SESSION['user_id'] ?? 0), $rentTotal, $rentTotal - $bundle);
}
$p = order_pricing($rentTotal, $depositTotal, count($items), $coupon && $coupon['ok'] ? $coupon['discount'] : 0);
$goUrl = 'checkout.php?days=' . (int)$days . ($coupon && $coupon['ok'] ? '&coupon=' . urlencode($coupon['code']) : '');
$nudge = bundle_nudge(count($items));

$pageTitle = 'ตะกร้า';
$pageCss = ['orders']; $pageJs = ['orders'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>ตะกร้าของคุณ</h1><p><?= count($items) ?> เล่ม</p></div></div>
<?php if (!$items): ?>
<div class="card empty"><h3>ตะกร้ายังว่างอยู่</h3><p>เลือกหนังสือที่อยากอ่านแล้วกด "+ ตะกร้า"</p><a class="btn btn-primary" href="index.php">ไปเลือกหนังสือ</a></div>
<?php else: ?>
<div class="checkout">
  <section class="card" aria-label="รายการในตะกร้า">
    <ul class="lines cart-lines">
      <?php foreach ($items as $it): ?>
      <li class="<?= h(zone_class($it['category_id'])) ?>">
        <a class="cover-tile" href="book.php?id=<?= (int)$it['book_id'] ?>"><?= cover_html($it['isbn'], $it['title'], $it['book_id'], false) ?></a>
        <div><a href="book.php?id=<?= (int)$it['book_id'] ?>"><strong><?= h($it['title']) ?></strong></a><br><span class="muted small"><?= h($it['author']) ?></span><br>
          <span class="small">฿<?= h(money($it['rent_per_day'])) ?>/วัน &middot; มัดจำ ฿<?= h(money($it['deposit'])) ?></span>
          <?php if ($it['copies_available'] < 1): ?><br><span class="badge b-bad">ถูกเช่าหมดแล้ว กรุณาเอาออก</span><?php endif; ?></div>
        <form action="cart.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="book_id" value="<?= (int)$it['book_id'] ?>"><button type="submit" class="btn btn-danger btn-sm"><?= icon('x', 16) ?>เอาออก</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($nudge !== ''): ?><p class="nudge"><?= icon('gift', 20) ?><span><?= h($nudge) ?></span> <a href="index.php">เลือกหนังสือเพิ่ม</a></p><?php endif; ?>
    <form action="cart.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-outline btn-sm" data-confirm="ล้างตะกร้าทั้งหมด?">ล้างตะกร้า</button></form>
  </section>
  <aside class="card" aria-label="สรุปราคา">
    <form action="cart.php" method="get">
      <h3>ระยะเวลาเช่า</h3>
      <div class="days"><?php foreach ([7, 14, 30] as $d): ?><label><input type="radio" name="days" value="<?= (int)$d ?>"<?= $days === $d ? ' checked' : '' ?> data-autosubmit><?= (int)$d ?> วัน</label><?php endforeach; ?></div>
      <div class="field coupon-field"><label for="coupon">โค้ดส่วนลด</label>
        <div class="coupon-row"><input type="text" id="coupon" name="coupon" maxlength="30" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="เช่น WELCOME10" value="<?= h($couponIn) ?>"><button type="submit" class="btn btn-outline">ใช้โค้ด</button></div>
        <?php if ($coupon && $coupon['ok']): ?><p class="hint coupon-ok"><?= icon('check', 16) ?>ใช้โค้ด <?= h($coupon['code']) ?> ลดค่าเช่า ฿<?= h(money($p['coupon'])) ?></p>
        <?php elseif ($coupon): ?><p class="hint coupon-err" role="alert"><?= h($coupon['error']) ?></p><?php endif; ?></div>
      <noscript><p><button type="submit" class="btn btn-outline btn-sm">คำนวณใหม่</button></p></noscript>
    </form>
    <h3 style="margin-top:1.2rem">สรุป</h3>
    <?= price_sum_html($p, $days, $coupon && $coupon['ok'] ? $coupon['code'] : '', 'โอนรวม') ?>
    <p class="muted small" style="margin-top:.8rem">มัดจำคืนหลังร้านได้รับหนังสือ หักค่าปรับถ้าส่งคืนช้า ส่วนลดคิดจากค่าเช่าเท่านั้น</p>
    <?php if ($blocked): ?><p class="alert alert-err">มีหนังสือที่ถูกเช่าหมด กรุณาเอาออกก่อน</p>
    <?php else: ?><a class="btn btn-accent btn-block" href="<?= h($goUrl) ?>">ไปชำระเงิน</a><?php endif; ?>
  </aside>
</div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
