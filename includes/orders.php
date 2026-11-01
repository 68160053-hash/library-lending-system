<?php
// Order-side helpers: price breakdown, coupons, PromptPay QR payload, overdue rules and reminders.
// Loaded with require_once by cart.php, checkout.php, order.php and my-orders.php (after auth.php).

// ---- prices ----

// Full price breakdown. $rent = gross rent, $deposit = deposit, $count = books, $couponDiscount = what coupon_check() returned.
// The bundle discount and the coupon only touch the rent; the coupon applies after the bundle and never goes below zero rent.
function order_pricing($rent, $deposit, $count, $couponDiscount = 0) {
    $rent = round((float)$rent, 2);
    $bundle = min($rent, bundle_discount($rent, (int)$count));
    $coupon = min(max(0, round((float)$couponDiscount, 2)), round($rent - $bundle, 2));
    return ['rent' => $rent, 'bundle' => $bundle, 'coupon' => $coupon, 'deposit' => round((float)$deposit, 2),
            'total' => round($rent - $bundle - $coupon + (float)$deposit, 2)];
}

// The price table used by cart, checkout and the order page. $p from order_pricing(); $nudge = short tip under the table.
function price_sum_html($p, $days, $couponCode = '', $totalLabel = 'ยอดที่ต้องชำระ') {
    $o = '<div class="sum price-sum">';
    $o .= '<span>ค่าเช่า ' . (int)$days . ' วัน</span><span>฿' . h(money($p['rent'])) . '</span>';
    if ($p['bundle'] > 0) $o .= '<span class="disc">' . h($p['bundle_label'] ?? 'ส่วนลดเช่าหลายเล่ม') . '</span><span class="disc">-฿' . h(money($p['bundle'])) . '</span>';
    if ($p['coupon'] > 0) $o .= '<span class="disc">โค้ด ' . h($couponCode) . '</span><span class="disc">-฿' . h(money($p['coupon'])) . '</span>';
    $o .= '<span>ค่ามัดจำ (คืนให้)</span><span>฿' . h(money($p['deposit'])) . '</span>';
    $o .= '<span class="total">' . h($totalLabel) . '</span><span class="total">฿' . h(money($p['total'])) . '</span></div>';
    return $o;
}

// "Add one more book, take 10% off the rent": the next bundle step from $count books, or '' when already at the top.
function bundle_nudge($count) {
    if ($count >= 4) return '';
    if ($count === 3) return 'เพิ่มอีก 1 เล่ม ลดค่าเช่า 15%';
    if ($count >= 1) return 'เพิ่มอีก ' . (3 - $count) . ' เล่ม ลดค่าเช่า 10%';
    return '';
}

// ---- coupons ----

// Normalise what a person typed: trim, upper-case. Returns '' when it cannot be a code (A-Z 0-9, 4-30 chars).
function coupon_clean($code) {
    $code = strtoupper(trim((string)$code));
    return preg_match('/^[A-Z0-9]{4,30}$/', $code) ? $code : '';
}

// Check a coupon for this member. $rentTotal = gross rent (for min_rent); $base = rent after bundle discount (what the coupon is applied to).
// Returns ['ok'=>bool, 'error'=>string, 'coupon_id'=>int, 'code'=>string, 'discount'=>float]. Reads only; checkout consumes it atomically.
function coupon_check($conn, $code, $userId, $rentTotal, $base = null) {
    // Guessing codes is the attack here: 10 failed tries per 15 minutes per member (per IP for guests), then everything is refused.
    $key = $userId > 0 ? 'u' . (int)$userId : 'guest';
    $bad = function ($msg) use ($conn, $key) { log_attempt($conn, 'coupon', $key); return ['ok' => false, 'error' => $msg, 'coupon_id' => 0, 'code' => '', 'discount' => 0.0]; };
    if (too_many_attempts($conn, 'coupon', $key, 10, 15)) return ['ok' => false, 'error' => 'ลองโค้ดผิดหลายครั้งเกินไป กรุณารอ 15 นาที', 'coupon_id' => 0, 'code' => '', 'discount' => 0.0];
    $clean = coupon_clean($code);
    if ($clean === '') return $bad('รูปแบบโค้ดไม่ถูกต้อง (ตัวอักษรอังกฤษ/ตัวเลข 4-30 ตัว)');
    $base = $base === null ? (float)$rentTotal : (float)$base;
    $stmt = mysqli_prepare($conn, 'SELECT coupon_id, kind, value, min_rent, max_uses, used_count, active, (expires_on IS NOT NULL AND expires_on < CURDATE()) FROM lib_coupons WHERE code = ?');
    mysqli_stmt_bind_param($stmt, 's', $clean);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $cid, $kind, $value, $minRent, $maxUses, $used, $active, $expired);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$active) return $bad('ไม่พบโค้ดนี้ หรือโค้ดปิดใช้งานแล้ว');
    if ($expired) return $bad('โค้ดนี้หมดอายุแล้ว');
    if ((float)$rentTotal < (float)$minRent) return $bad('ค่าเช่าต้องไม่ต่ำกว่า ฿' . money($minRent) . ' ถึงจะใช้โค้ดนี้ได้');
    if ($maxUses !== null && (int)$used >= (int)$maxUses) return $bad('โค้ดนี้ถูกใช้ครบจำนวนสิทธิ์แล้ว');
    if ($userId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT 1 FROM lib_coupon_uses WHERE coupon_id = ? AND user_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $cid, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $mine = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if ($mine) return $bad('คุณใช้โค้ดนี้ไปแล้ว (ใช้ได้คนละครั้ง)');
    }
    if ($kind === 'percent') {
        if ($value < 1 || $value > 90) return $bad('โค้ดนี้ตั้งค่าไม่ถูกต้อง');
        $disc = round($base * $value / 100, 2);
    } else {
        if ($value <= 0) return $bad('โค้ดนี้ตั้งค่าไม่ถูกต้อง');
        $disc = round((float)$value, 2);
    }
    $disc = max(0, min($disc, round($base, 2))); // never more than the rent
    return ['ok' => true, 'error' => '', 'coupon_id' => (int)$cid, 'code' => $clean, 'discount' => (float)$disc];
}

// ---- PromptPay (EMVCo merchant-presented QR) ----

// CRC-16/CCITT-FALSE: poly 0x1021, init 0xFFFF, no reflection, no final xor. Returns 4 upper-case hex digits.
function crc16_ccitt($s) {
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $crc ^= ord($s[$i]) << 8;
        for ($b = 0; $b < 8; $b++) $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

// Text of the PromptPay QR for a phone (0XXXXXXXXX) or a 13-digit national id, with the amount fixed. '' when the target is not usable.
function promptpay_payload($target, $amount) {
    $digits = preg_replace('/\D/', '', (string)$target);
    if (strlen($digits) === 10 && $digits[0] === '0') { $tag = '01'; $acct = '0066' . substr($digits, 1); }
    elseif (strlen($digits) === 13) { $tag = '02'; $acct = $digits; }
    else return '';
    $amount = round((float)$amount, 2);
    if ($amount <= 0 || $amount > 9999999.99) return '';
    $tlv = function ($id, $val) { return $id . str_pad((string)strlen($val), 2, '0', STR_PAD_LEFT) . $val; };
    $merchant = $tlv('00', 'A000000677010111') . $tlv($tag, $acct);
    $s = $tlv('00', '01') . $tlv('01', '12') . $tlv('29', $merchant) . $tlv('53', '764') . $tlv('54', number_format($amount, 2, '.', '')) . $tlv('58', 'TH') . '6304';
    return $s . crc16_ccitt($s);
}

// ---- anti-non-return ----

// How many of this member's books are past their due date (still shipped).
function member_overdue_count($conn, $userId) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM lib_loans WHERE user_id = ? AND status = 'shipped' AND due_date < CURDATE()");
    $uid = (int)$userId;
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $n);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int)$n;
}

// Another account with an overdue book at the same phone or address (stops "register again, rent again").
function contact_has_overdue($conn, $userId, $phone, $address) {
    $uid = (int)$userId; $ph = preg_replace('/\D/', '', (string)$phone);
    $stmt = mysqli_prepare($conn, "SELECT 1 FROM lib_loans l JOIN lib_orders o ON o.order_id = l.order_id WHERE l.status = 'shipped' AND l.due_date < CURDATE() AND l.user_id <> ? AND (o.ship_address = ? OR REPLACE(REPLACE(o.ship_phone, '-', ''), ' ', '') = ?) LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iss', $uid, $address, $ph);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $hit = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $hit;
}

// Books overdue for AUTO_LOST_DAYS become 'lost' (deposit kept, one copy less in total). Like expire_unpaid_orders, called from busy pages.
// Each loan is flipped by one UPDATE that re-checks the state, so two page loads at once never count a loss twice. Returns how many were marked.
function auto_lose_overdue($conn, $limit = 50) {
    $days = (int)AUTO_LOST_DAYS; $limit = max(1, (int)$limit);
    $rows = [];
    $res = mysqli_query($conn, "SELECT l.loan_id, l.user_id, l.book_id, l.order_id, b.title FROM lib_loans l JOIN lib_books b ON b.book_id = l.book_id WHERE l.status = 'shipped' AND l.due_date <= CURDATE() - INTERVAL $days DAY LIMIT $limit");
    while ($res && ($r = mysqli_fetch_row($res))) $rows[] = $r;
    $marked = 0;
    foreach ($rows as [$loanId, $userId, $bookId, $orderId, $title]) {
        mysqli_begin_transaction($conn);
        $stmt = mysqli_prepare($conn, "UPDATE lib_loans SET status = 'lost' WHERE loan_id = ? AND status = 'shipped' AND due_date <= CURDATE() - INTERVAL $days DAY");
        mysqli_stmt_bind_param($stmt, 'i', $loanId);
        mysqli_stmt_execute($stmt);
        $done = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($done !== 1) { mysqli_rollback($conn); continue; }
        $stmt = mysqli_prepare($conn, 'UPDATE lib_books SET copies_total = copies_total - 1 WHERE book_id = ? AND copies_total > 0');
        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        $marked++;
        notify($conn, $userId, 'lost', 'หนังสือ "' . mb_substr($title, 0, 60) . '" เกินกำหนดคืนเกิน ' . $days . ' วัน ร้านตัดเป็นสูญหาย ไม่คืนมัดจำ', 'order.php?id=' . (int)$orderId);
    }
    return $marked;
}

// Days left before an overdue book is cut as lost (0 or less = today). $lateDays = days past due.
function days_to_lost($lateDays) { return max(0, (int)AUTO_LOST_DAYS - (int)$lateDays); }

// One 'due_soon' notification per shipped loan per day for books due within 2 days (not overdue yet).
function notify_due_soon($conn, $userId) {
    $uid = (int)$userId;
    $stmt = mysqli_prepare($conn, "SELECT l.order_id, b.title, DATEDIFF(l.due_date, CURDATE()) FROM lib_loans l JOIN lib_books b ON b.book_id = l.book_id WHERE l.user_id = ? AND l.status = 'shipped' AND l.due_date >= CURDATE() AND l.due_date <= CURDATE() + INTERVAL 2 DAY");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $orderId, $title, $left);
    $rows = [];
    while (mysqli_stmt_fetch($stmt)) $rows[] = [$orderId, $title, $left];
    mysqli_stmt_close($stmt);
    foreach ($rows as [$orderId, $title, $left]) {
        $link = 'order.php?id=' . (int)$orderId;
        $msg = 'หนังสือ "' . mb_substr($title, 0, 60) . '" ' . ($left > 0 ? 'ครบกำหนดคืนอีก ' . (int)$left . ' วัน' : 'ครบกำหนดคืนวันนี้');
        // the title is part of the match so two books of one order each get their own reminder
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM lib_notifications WHERE user_id = ? AND kind = 'due_soon' AND link = ? AND message LIKE ? AND DATE(created_at) = CURDATE() LIMIT 1");
        $like = addcslashes('หนังสือ "' . mb_substr($title, 0, 60) . '"', '%_\\') . '%'; // the title is data, not a pattern
        mysqli_stmt_bind_param($stmt, 'iss', $uid, $link, $like);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if (!$exists) notify($conn, $uid, 'due_soon', $msg, $link);
    }
}

// ---- stored orders ----

// Price breakdown of an order already in the database: rent_total is gross, discount_total = bundle + coupon.
// $count = books still on the order (a cancelled order has none, so its whole discount shows as one line).
function stored_pricing($rent, $discount, $deposit, $hasCoupon, $count) {
    $rent = round((float)$rent, 2); $discount = round((float)$discount, 2);
    $bundle = $discount; $coupon = 0.0; $label = ($hasCoupon && $count < 1) ? 'ส่วนลด' : 'ส่วนลดเช่าหลายเล่ม';
    if ($hasCoupon && $count > 0) { $bundle = min($discount, bundle_discount($rent, $count)); $coupon = round($discount - $bundle, 2); }
    return ['rent' => $rent, 'bundle' => $bundle, 'coupon' => $coupon, 'bundle_label' => $label, 'deposit' => round((float)$deposit, 2), 'total' => round($rent - $discount + (float)$deposit, 2)];
}
