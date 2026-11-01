<?php
// Small helpers shared by many pages. Loaded by auth.php, so every page has them.
// (A page never loads this file on its own.)

// 1234.5 -> "1,234.50"
function money($n) { return number_format((float)$n, 2); }
// Order number as shown to people: 12 -> "#00012"
function order_no($id) { return '#' . str_pad((string)(int)$id, 5, '0', STR_PAD_LEFT); }

// Book cover: the real picture assets/covers/<isbn>.jpg if we have it, otherwise a coloured box with the title.
function cover_html($isbn, $title, $bookId, $alt = true) {
    $colors = ['#0f4c81', '#7c2d12', '#365314', '#581c87', '#134e4a', '#78350f', '#1e3a8a', '#831843'];
    if (preg_match('/^[0-9A-Za-z-]{3,20}$/', (string)$isbn) && is_file(__DIR__ . '/../assets/covers/' . $isbn . '.jpg')) {
        return '<img class="cover" src="assets/covers/' . h($isbn) . '.jpg" alt="' . ($alt ? 'ปกหนังสือ ' . h($title) : '') . '" loading="lazy" width="200" height="300">';
    }
    return '<div class="cover-ph" style="background:' . $colors[(int)$bookId % count($colors)] . '" role="img" aria-label="ปกหนังสือ ' . h($title) . '">' . h(mb_substr($title, 0, 40)) . '</div>';
}

// One-time message shown at the top of the next page: flash('ok', 'บันทึกแล้ว') then redirect.
function flash($type, $msg) { $_SESSION['flash'] = [$type, $msg]; }

// The shopping cart lives in the session as a list of book ids (max 5, no duplicates).
function cart_ids() {
    $ids = [];
    foreach ((array)($_SESSION['cart'] ?? []) as $id) { $id = (int)$id; if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id; }
    return array_slice($ids, 0, 5);
}

// Where is an order in its life? Returns [label, badge class, step number 1-5 (6 = all finished)].
// $status: open/cancelled, $pay: unpaid/reported/confirmed, $shippedAt: date or null, $nShipped: books of this order still out with the renter
function order_stage($status, $pay, $shippedAt, $nShipped) {
    if ($status === 'cancelled') return ['ยกเลิกแล้ว', '', 0];
    if ($pay === 'unpaid') return ['รอชำระเงิน', 'b-warn', 2];
    if ($pay === 'reported') return ['รอตรวจสอบการโอน', 'b-info', 2];
    if ($shippedAt === null) return ['เตรียมจัดส่ง', 'b-info', 3];
    if ((int)$nShipped > 0) return ['อยู่ระหว่างเช่า', 'b-ok', 4];
    return ['เสร็จสิ้น', 'b-ok', 6];
}

// Cancel an order that has not been shipped yet: the copies go back on the shelf, the waiting rental rows are removed.
// $ownerId = the member's id (only the owner can cancel, and not after staff confirmed the money), or 0 when staff/the system cancels. Returns true/false.
function cancel_order($conn, $orderId, $ownerId) {
    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "SELECT order_id FROM lib_orders WHERE order_id = ? AND (? = 0 OR (user_id = ? AND pay_status <> 'confirmed')) AND status = 'open' AND shipped_at IS NULL FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'iii', $orderId, $ownerId, $ownerId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $found = mysqli_stmt_num_rows($stmt) === 1;
    mysqli_stmt_close($stmt);
    if (!$found) { mysqli_rollback($conn); return false; }
    $stmt = mysqli_prepare($conn, "UPDATE lib_books b JOIN lib_loans l ON l.book_id = b.book_id SET b.copies_available = b.copies_available + 1 WHERE l.order_id = ? AND l.status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'i', $orderId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "DELETE FROM lib_loans WHERE order_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'i', $orderId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "UPDATE lib_orders SET status = 'cancelled' WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $orderId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    // a coupon used by this order goes back to the pool (the DELETE's affected row guards against releasing twice)
    $stmt = mysqli_prepare($conn, 'SELECT coupon_id FROM lib_coupon_uses WHERE order_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $orderId); mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt, $couponId);
    $hadCoupon = mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    if ($hadCoupon) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM lib_coupon_uses WHERE order_id = ? AND coupon_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $orderId, $couponId); mysqli_stmt_execute($stmt);
        $freed = mysqli_stmt_affected_rows($stmt); mysqli_stmt_close($stmt);
        if ($freed === 1) {
            $stmt = mysqli_prepare($conn, 'UPDATE lib_coupons SET used_count = GREATEST(used_count, 1) - 1 WHERE coupon_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $couponId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
    }
    mysqli_commit($conn);
    return true;
}

// An order that stays unpaid for 48 hours is cancelled, so nobody can keep books locked without paying.
// Called from a few busy pages instead of a scheduled job (the university server has no cron for us).
function expire_unpaid_orders($conn) {
    $ids = [];
    $res = mysqli_query($conn, "SELECT order_id FROM lib_orders WHERE status = 'open' AND pay_status = 'unpaid' AND shipped_at IS NULL AND ordered_at < NOW() - INTERVAL 48 HOUR LIMIT 20");
    while ($res && ($row = mysqli_fetch_row($res))) $ids[] = (int)$row[0];
    foreach ($ids as $id) cancel_order($conn, $id, 0);
}

// ---- v3 shared helpers (several pages use these; keep their behaviour stable) ----

// In-web notification for one member. $kind: paid / shipped / due_soon / overdue / lost / info. $link: a page of ours (order.php?id=5).
function notify($conn, $userId, $kind, $message, $link = '') {
    $userId = (int)$userId;
    if ($userId < 1) return;
    $message = mb_substr((string)$message, 0, 200); $link = mb_substr((string)$link, 0, 120); $kind = mb_substr((string)$kind, 0, 20);
    $stmt = mysqli_prepare($conn, 'INSERT INTO lib_notifications (user_id, kind, message, link) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isss', $userId, $kind, $message, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query($conn, 'DELETE FROM lib_notifications WHERE read_at IS NOT NULL AND created_at < NOW() - INTERVAL 90 DAY LIMIT 50'); // keeps the table small
}

// How many notifications the member has not opened yet (shown on the bell).
function unread_notifications($conn, $userId) {
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM lib_notifications WHERE user_id = ? AND read_at IS NULL');
    $uid = (int)$userId;
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $n);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int)$n;
}

// Bundle discount on the RENT only (never the deposit): 3 books = 10%, 4 or more = 15%. Returns the baht amount to take off.
function bundle_discount($rentTotal, $bookCount) {
    $pct = $bookCount >= 4 ? 15 : ($bookCount >= 3 ? 10 : 0);
    return round($rentTotal * $pct / 100, 2);
}

// Overdue for this many days a rented book is treated as lost (deposit kept). One place to change the rule.
const AUTO_LOST_DAYS = 14;

// Star rating as text for screen readers and plain output: 4.5 -> "4.5 / 5".
function rating_text($avg) { return number_format((float)$avg, 1) . ' / 5'; }

// The fair hall has five coloured zones. A category's zone is its id folded onto 1-5: class "z1".."z5", letter "A".."E".
function zone_of($categoryId) { return (((int)$categoryId - 1) % 5 + 5) % 5 + 1; }
function zone_class($categoryId) { return 'z' . zone_of($categoryId); }
function zone_letter($categoryId) { return chr(64 + zone_of($categoryId)); }

// Inline icon from the sprite assets/icons.svg (drawn for this shop, one 2px stroke). $name e.g. 'cart', 'heart', 'bell'.
function icon($name, $size = 20) {
    $name = preg_replace('/[^a-z0-9-]/', '', (string)$name); $size = (int)$size;
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><use href="assets/icons.svg#' . $name . '"/></svg>';
}
