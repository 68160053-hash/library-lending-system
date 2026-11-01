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
