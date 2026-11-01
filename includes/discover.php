<?php if (!function_exists('h')) { http_response_code(403); exit; } // only included by a page that loaded auth.php
// Shared by the discovery pages (home, book page, shelf): book queries with rating, and the price-tagged card.

// Whitelist for the "back" address of POST forms (wishlist.php uses the same pattern for the pages it may return to).
const BACK_PATTERN = '/^(index|book|shelf)\.php(\?[A-Za-z0-9_=&%.+-]*)?$/';
// The page the visitor is on, as a safe "back" value (falls back to the catalogue).
function page_back($onlyIndex = false) {
    $self = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
    $back = $self . ($qs !== '' ? '?' . $qs : '');
    if ($onlyIndex) return preg_match('/^index\.php(\?[A-Za-z0-9_=&%.+-]*)?$/', $back) ? $back : 'index.php'; // cart.php accepts only this shape
    return preg_match(BACK_PATTERN, $back) ? $back : 'index.php';
}

// The six newest books wear the "ใหม่" sticker.
function newest_ids($conn) {
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        $res = mysqli_query($conn, 'SELECT book_id FROM lib_books ORDER BY book_id DESC LIMIT 6');
        while ($res && ($r = mysqli_fetch_row($res))) $ids[] = (int)$r[0];
    }
    return $ids;
}

// Books with category, average rating and review count. $where / $order are fixed SQL written by the caller (never user text);
// every user value goes in $params. Returns rows ready for book_card().
function discover_books($conn, $where, $types, $params, $order, $limit = 12, $offset = 0) {
    $new = newest_ids($conn); // before the statement below: a second query while its rows are unread fails with "Commands out of sync"
    // Rating and review count come from small subqueries for the rows shown. The whole-table summaries are joined only when the sort needs them.
    $joinR = strpos($order, 'r.') !== false ? 'LEFT JOIN (SELECT book_id, AVG(rating) a, COUNT(*) n FROM lib_reviews GROUP BY book_id) r ON r.book_id = b.book_id' : '';
    $joinL = strpos($order, 'lc.') !== false ? 'LEFT JOIN (SELECT book_id, COUNT(*) n FROM lib_loans WHERE status <> \'pending\' GROUP BY book_id) lc ON lc.book_id = b.book_id' : '';
    $sql = 'SELECT b.book_id, b.title, b.author, b.isbn, b.rent_per_day, b.copies_available, b.category_id, c.name,
                   COALESCE((SELECT AVG(rating) FROM lib_reviews WHERE book_id = b.book_id), 0), (SELECT COUNT(*) FROM lib_reviews WHERE book_id = b.book_id)
            FROM lib_books b JOIN lib_categories c ON c.category_id = b.category_id ' . $joinR . ' ' . $joinL . '
            WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?';
    $stmt = mysqli_prepare($conn, $sql);
    $params[] = (int)$limit; $params[] = (int)$offset;
    mysqli_stmt_bind_param($stmt, $types . 'ii', ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $title, $author, $isbn, $rent, $left, $catId, $cat, $avg, $cnt);
    $rows = [];
    while (mysqli_stmt_fetch($stmt)) $rows[] = ['id' => (int)$id, 'title' => $title, 'author' => $author, 'isbn' => $isbn, 'rent' => $rent, 'left' => (int)$left,
        'cat_id' => (int)$catId, 'cat' => $cat, 'avg' => (float)$avg, 'cnt' => (int)$cnt, 'isnew' => in_array((int)$id, $new, true)];
    mysqli_stmt_close($stmt);
    return $rows;
}

// Books for a list of ids (whole numbers from the DB), in the order given.
function books_by_ids($conn, $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $rows = discover_books($conn, 'b.book_id IN (' . implode(',', $ids) . ')', '', [], 'b.book_id', count($ids));
    $by = [];
    foreach ($rows as $r) $by[$r['id']] = $r;
    $out = [];
    foreach ($ids as $id) if (isset($by[$id])) $out[] = $by[$id];
    return $out;
}

// Ids of the books this member keeps on the "want to read" shelf.
function wished_ids($conn, $userId) {
    $ids = [];
    if ((int)$userId < 1) return $ids;
    $stmt = mysqli_prepare($conn, 'SELECT book_id FROM lib_wishlist WHERE user_id = ?');
    $uid = (int)$userId;
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $bid);
    while (mysqli_stmt_fetch($stmt)) $ids[] = (int)$bid;
    mysqli_stmt_close($stmt);
    return $ids;
}

// Five stars + average + count. No reviews yet = a quiet line, so every card keeps the same height.
function rating_html($avg, $cnt) {
    if ($cnt < 1) return '<span class="rating-none muted small">ยังไม่มีรีวิว</span>';
    $full = (int)round($avg);
    $s = '<span class="rating" role="img" aria-label="' . h(rating_text($avg)) . ' จาก ' . (int)$cnt . ' รีวิว">';
    for ($i = 1; $i <= 5; $i++) $s .= '<span class="' . ($i <= $full ? '' : 'off') . '">' . icon('star-fill', 15) . '</span>';
    return $s . '<b>' . h(number_format($avg, 1)) . '</b><span class="muted small">(' . (int)$cnt . ')</span></span>';
}

// Heart button: a POST form for members (works without JavaScript), a login link for guests.
function heart_html($bookId, $title, $wished, $back, $remove = false) {
    if (empty($_SESSION['user_id'])) {
        return '<a class="heart-btn" href="login.php" aria-label="เข้าสู่ระบบเพื่อบันทึก ' . h($title) . ' ไว้อ่าน">' . icon('heart', 20) . '</a>';
    }
    $label = ($wished ? 'เอาออกจากรายการอยากอ่าน ' : 'บันทึกไว้อ่าน ') . $title;
    return '<form action="wishlist.php" method="post" class="heart-form"' . ($remove ? ' data-remove' : '') . '>'
        . '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><input type="hidden" name="book_id" value="' . (int)$bookId . '"><input type="hidden" name="back" value="' . h($back) . '">'
        . '<button type="submit" class="heart-btn" aria-pressed="' . ($wished ? 'true' : 'false') . '" aria-label="' . h($label) . '" data-t-on="เอาออกจากรายการอยากอ่าน ' . h($title) . '" data-t-off="บันทึกไว้อ่าน ' . h($title) . '">'
        . icon($wished ? 'heart-fill' : 'heart', 20) . '</button></form>';
}

// The book as a price-tagged card on a zone-coloured board. $o: 'cart_label' (button text), 'remove' (heart removes the card from the shelf).
function book_card($b, $wishedIds, $o = []) {
    $zc = zone_class($b['cat_id']);
    $out = $b['left'] < 1;
    $back = page_back();
    $price = rtrim(rtrim(money($b['rent']), '0'), '.');
    ob_start(); ?>
<article class="book-card">
<div class="zwrap <?= $zc ?>">
  <div class="cover-wrap">
    <a class="cover-link" href="book.php?id=<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true"><?= cover_html($b['isbn'], $b['title'], $b['id'], false) ?><span class="zl-sticker"><?= h(zone_letter($b['cat_id'])) ?></span></a>
    <?php if ($out): ?><span class="burst out">หมด</span><?php elseif (!empty($b['isnew'])): ?><span class="burst new">ใหม่</span><?php endif; ?>
    <span class="price-tag"><span class="price">฿<?= h($price) ?><small>/วัน</small></span></span>
  </div>
  <?= heart_html($b['id'], $b['title'], in_array((int)$b['id'], $wishedIds, true), $back, !empty($o['remove'])) ?>
  <div class="body">
    <span class="zone-chip"><b aria-hidden="true"><?= h(zone_letter($b['cat_id'])) ?></b><?= h($b['cat']) ?></span>
    <h3><a href="book.php?id=<?= (int)$b['id'] ?>"><?= h($b['title']) ?></a></h3>
    <span class="author"><?= h($b['author']) ?></span>
    <?= rating_html($b['avg'], $b['cnt']) ?>
    <div class="foot">
      <?php if (!$out): ?>
      <form action="cart.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="action" value="add"><input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="back" value="<?= h(page_back(true)) ?>">
        <button type="submit" class="btn btn-outline btn-sm" aria-label="<?= h(($o['cart_label'] ?? 'ใส่ตะกร้า') . ' ' . $b['title']) ?>"><?= icon('cart', 16) ?><?= h($o['cart_label'] ?? 'ใส่ตะกร้า') ?></button></form>
      <?php else: ?><span class="badge b-bad">ถูกเช่าหมด</span><?php endif; ?>
    </div>
  </div>
</div>
</article>
<?php
    return ob_get_clean();
}
