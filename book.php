<?php
require 'auth.php'; require 'config/db.php'; require_once 'includes/discover.php';
// Book detail page: ?id=<book id>. Anyone can look; adding to the cart is a POST to cart.php.
$bookId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($bookId === false || $bookId < 1) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้ <a href="index.php">กลับหน้าร้าน</a>'); }

$stmt = mysqli_prepare($conn, 'SELECT b.title, b.author, b.isbn, b.description, b.copies_total, b.copies_available, b.rent_per_day, b.deposit, c.category_id, c.name
                               FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id WHERE b.book_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $title, $author, $isbn, $desc, $total, $left, $rent, $deposit, $catId, $catName);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
if (!$found) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้ <a href="index.php">กลับหน้าร้าน</a>'); }

$uid = (int)($_SESSION['user_id'] ?? 0);
$wishedIds = wished_ids($conn, $uid);

// Rating summary: how many reviews gave 1..5 stars
$dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$stmt = mysqli_prepare($conn, 'SELECT rating, COUNT(*) FROM lib_reviews WHERE book_id = ? GROUP BY rating');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $rStar, $rN);
while (mysqli_stmt_fetch($stmt)) if (isset($dist[(int)$rStar])) $dist[(int)$rStar] = (int)$rN;
mysqli_stmt_close($stmt);
$revCount = array_sum($dist);
$revAvg = $revCount ? array_sum(array_map(function ($s, $n) { return $s * $n; }, array_keys($dist), $dist)) / $revCount : 0;

// Latest reviews (name, stars, text, date)
$reviews = [];
$stmt = mysqli_prepare($conn, 'SELECT u.full_name, r.rating, r.body, r.created_at, r.user_id, r.review_id FROM lib_reviews r JOIN lib_users u ON u.user_id = r.user_id WHERE r.book_id = ? ORDER BY r.created_at DESC, r.review_id DESC LIMIT 20');
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $rName, $rRating, $rBody, $rAt, $rUser, $rId);
while (mysqli_stmt_fetch($stmt)) $reviews[] = ['name' => $rName, 'rating' => (int)$rRating, 'body' => $rBody, 'at' => $rAt, 'mine' => (int)$rUser === $uid && $uid > 0, 'id' => (int)$rId];
mysqli_stmt_close($stmt);

// May this member review? Only after a RETURNED loan of this book. They may edit their own review.
$canReview = false; $myRating = 0; $myBody = '';
if ($uid > 0) {
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM lib_loans WHERE user_id = ? AND book_id = ? AND status = 'returned' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $bookId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $canReview = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if ($canReview) {
        $stmt = mysqli_prepare($conn, 'SELECT rating, body FROM lib_reviews WHERE user_id = ? AND book_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $bookId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $myRating, $myBody);
        if (!mysqli_stmt_fetch($stmt)) { $myRating = 0; $myBody = ''; }
        mysqli_stmt_close($stmt);
    }
}

// People who rented this book also rented ... (same members' other loans); padded with the same category when too few
$alsoIds = [];
$stmt = mysqli_prepare($conn, "SELECT l2.book_id FROM lib_loans l1 JOIN lib_loans l2 ON l2.user_id = l1.user_id AND l2.book_id <> l1.book_id
                               WHERE l1.book_id = ? AND l1.status <> 'pending' AND l2.status <> 'pending' GROUP BY l2.book_id ORDER BY COUNT(DISTINCT l2.user_id) DESC, l2.book_id DESC LIMIT 6");
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $aId);
while (mysqli_stmt_fetch($stmt)) $alsoIds[] = (int)$aId;
mysqli_stmt_close($stmt);
$alsoTitle = $alsoIds ? 'คนที่เช่าเล่มนี้ก็เช่า' : 'หนังสือหมวดเดียวกัน';
if (count($alsoIds) < 6) {
    $stmt = mysqli_prepare($conn, 'SELECT book_id FROM lib_books WHERE category_id = ? AND book_id <> ? ORDER BY copies_available > 0 DESC, book_id DESC LIMIT 12');
    mysqli_stmt_bind_param($stmt, 'ii', $catId, $bookId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $sId);
    while (mysqli_stmt_fetch($stmt) && count($alsoIds) < 6) if (!in_array((int)$sId, $alsoIds, true)) $alsoIds[] = (int)$sId;
    mysqli_stmt_close($stmt);
}
$related = books_by_ids($conn, $alsoIds);

$inCart = in_array((int)$bookId, cart_ids(), true);
$zc = zone_class($catId);
$pageTitle = $title;
$pageCss = ['discover']; $pageJs = ['discover'];
require 'includes/header.php';

// The add-to-cart control, used in the buy box and in the phone bar
function buy_button($bookId, $inCart, $left, $class) {
    ob_start();
    if ($left > 0 && !$inCart): ?>
      <form action="cart.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="add"><input type="hidden" name="book_id" value="<?= (int)$bookId ?>"><input type="hidden" name="then" value="cart">
        <button type="submit" class="btn btn-accent <?= $class ?>"><?= icon('cart', 18) ?>ใส่ตะกร้าและไปชำระเงิน</button></form>
    <?php elseif ($inCart): ?>
      <a class="btn btn-primary <?= $class ?>" href="cart.php">อยู่ในตะกร้าแล้ว ไปที่ตะกร้า</a>
    <?php else: ?><button class="btn btn-outline <?= $class ?>" type="button" disabled>ถูกเช่าหมดแล้ว</button><?php endif;
    return ob_get_clean();
}
?>
<nav class="crumbs" aria-label="เส้นทาง"><a href="index.php">หนังสือ</a> / <a href="index.php?category=<?= (int)$catId ?>"><?= h($catName) ?></a> / <?= h($title) ?></nav>
<div class="detail bk <?= $zc ?>">
  <div class="bk-cover">
    <div class="cover-box"><?= cover_html($isbn, $title, $bookId) ?><span class="zl-sticker" aria-hidden="true"><?= h(zone_letter($catId)) ?></span><?= heart_html($bookId, $title, in_array((int)$bookId, $wishedIds, true), 'book.php?id=' . (int)$bookId) ?></div>
  </div>
  <div class="bk-main">
    <span class="zone-chip"><b aria-hidden="true"><?= h(zone_letter($catId)) ?></b><?= h($catName) ?></span>
    <h1><?= h($title) ?></h1>
    <p class="muted">โดย <?= h($author) ?></p>
    <p class="bk-meta"><?= rating_html($revAvg, $revCount) ?>
      <?php if ($left > 0): ?><span class="badge b-ok">ว่างให้เช่า <?= (int)$left ?> จาก <?= (int)$total ?> เล่ม</span><?php else: ?><span class="badge b-bad">ถูกเช่าหมดแล้ว</span><?php endif; ?></p>
    <p class="desc"><?= $desc ? h($desc) : '<span class="muted">ยังไม่มีเรื่องย่อของหนังสือเล่มนี้</span>' ?></p>
    <dl class="facts"><dt>ISBN</dt><dd><?= h($isbn) ?></dd><dt>ผู้แต่ง</dt><dd><?= h($author) ?></dd><dt>หมวด</dt><dd><?= h($catName) ?></dd></dl>
  </div>
  <aside class="card buybox bk-buy" aria-label="ราคาเช่า">
    <h3>เลือกระยะเวลาเช่า</h3>
    <div class="plans"><?php foreach ([7, 14, 30] as $d): ?>
      <div class="plan<?= $d === 14 ? ' pop' : '' ?>"><span class="days-label"><?= (int)$d ?> วัน</span><b>฿<?= h(money($rent * $d + $deposit)) ?></b><small>ค่าเช่า ฿<?= h(money($rent * $d)) ?> + มัดจำ ฿<?= h(money($deposit)) ?></small></div>
    <?php endforeach; ?></div>
    <p class="muted small">มัดจำคืนให้เมื่อร้านได้รับหนังสือ ค่าเช่า ฿<?= h(money($rent)) ?> ต่อวัน เกินกำหนดคิดวันละเท่าราคาเช่า หักจากมัดจำ</p>
    <?= buy_button($bookId, $inCart, $left, 'btn-block') ?>
  </aside>
</div>

<section class="card reviews" id="reviews" aria-labelledby="rv-title">
  <h2 id="rv-title">รีวิวจากผู้เช่า</h2>
  <div class="rv-grid">
    <div class="rv-sum">
      <?php if ($revCount): ?>
      <p class="rv-avg"><b class="num"><?= h(number_format($revAvg, 1)) ?></b><span class="muted"> / 5</span></p>
      <?= rating_html($revAvg, $revCount) ?>
      <ul class="dist" aria-label="สัดส่วนคะแนน">
        <?php for ($s = 5; $s >= 1; $s--): ?><li><span><?= $s ?> ดาว</span><span class="dist-bar" aria-hidden="true"><i style="width:<?= (int)round($dist[$s] * 100 / $revCount) ?>%"></i></span><span class="num"><?= (int)$dist[$s] ?></span></li><?php endfor; ?>
      </ul>
      <?php else: ?><p class="muted">ยังไม่มีรีวิว เป็นคนแรกที่เล่าให้คนอื่นฟังหลังอ่านจบ</p><?php endif; ?>
    </div>
    <div class="rv-main">
      <?php if ($canReview): ?>
      <form action="review.php" method="post" class="rv-form">
        <?php csrf_field(); ?><input type="hidden" name="book_id" value="<?= (int)$bookId ?>">
        <fieldset class="stars"><legend><?= $myRating ? 'แก้รีวิวของคุณ' : 'ให้คะแนนเล่มนี้' ?></legend>
          <?php for ($s = 5; $s >= 1; $s--): ?><input type="radio" name="rating" id="st<?= $s ?>" value="<?= $s ?>"<?= $myRating === $s ? ' checked' : '' ?> required><label for="st<?= $s ?>" title="<?= $s ?> ดาว"><?= icon('star-fill', 28) ?><span class="sr-only"><?= $s ?> ดาว</span></label><?php endfor; ?>
        </fieldset>
        <div class="field"><label for="rb">เล่าให้คนอื่นฟัง (ไม่บังคับ)</label><textarea id="rb" name="body" maxlength="400" rows="4" placeholder="อ่านแล้วเป็นอย่างไร เหมาะกับใคร"><?= h($myBody) ?></textarea><div class="hint">ไม่เกิน 400 ตัวอักษร</div></div>
        <button type="submit" class="btn btn-primary"><?= $myRating ? 'บันทึกการแก้ไข' : 'ส่งรีวิว' ?></button>
      </form>
      <?php else: ?>
      <p class="alert alert-info"><?= icon('info', 20) ?><span>รีวิวได้เมื่อเคยเช่าและส่งคืนเล่มนี้แล้ว<?= $uid ? '' : ' (เข้าสู่ระบบก่อน)' ?></span></p>
      <?php endif; ?>
      <?php if ($reviews): ?>
      <ul class="rv-list">
        <?php foreach ($reviews as $rv): ?>
        <li>
          <div class="rv-top"><strong><?= h($rv['name']) ?></strong><?= $rv['mine'] ? ' <span class="badge">รีวิวของคุณ</span>' : '' ?>
            <span class="rating" role="img" aria-label="<?= (int)$rv['rating'] ?> จาก 5 ดาว"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= $rv['rating'] ? '' : 'off' ?>"><?= icon('star-fill', 15) ?></span><?php endfor; ?></span>
            <time class="muted small" datetime="<?= h(substr($rv['at'], 0, 10)) ?>"><?= h(date('j/n/Y', strtotime($rv['at']))) ?></time></div>
          <?php if ($rv['body'] !== ''): ?><p><?= nl2br(h($rv['body'])) ?></p><?php endif; ?>
          <?php if (is_staff()): ?><form action="review.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= (int)$rv['id'] ?>"><input type="hidden" name="book_id" value="<?= (int)$bookId ?>"><button class="btn btn-danger btn-sm" type="submit" data-confirm="ลบรีวิวนี้?">ลบรีวิว</button></form><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="shelf-row" aria-label="<?= h($alsoTitle) ?>">
  <div class="row-head"><h2><?= h($alsoTitle) ?></h2></div>
  <div class="rail"><?php foreach ($related as $r) echo book_card($r, $wishedIds); ?></div>
</section>
<?php endif; ?>

<?php if ($left > 0 && !$inCart): ?>
<div class="buybar" role="region" aria-label="เช่าเล่มนี้">
  <span class="buybar-price"><span class="price-tag"><span class="price">฿<?= h(rtrim(rtrim(money($rent), '0'), '.')) ?><small>/วัน</small></span></span></span>
  <?= buy_button($bookId, $inCart, $left, '') ?>
</div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
