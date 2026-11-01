<?php
require 'auth.php'; require 'config/db.php';
// Every change to an order goes through here: POST only, CSRF token, logged in. What each action needs:
//   report_pay  the OWNER tells the shop the money was sent           (unpaid  -> reported)
//   cancel      the OWNER or STAFF cancels before shipping            (books go back on the shelf)
//   confirm_pay STAFF confirms the money arrived                      (unpaid/reported -> confirmed)
//   ship        STAFF enters the tracking number                      (needs confirmed payment)
//   receive     STAFF got one book back                               (shipped -> returned, copy back on the shelf)
//   lost        STAFF marks one book lost                             (shipped -> lost, one copy less in total)
// Every UPDATE has the step before in its WHERE, so pressing a button twice or on the wrong step changes nothing.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('<a href="my-orders.php">กลับ</a>'); }
require_login();
verify_csrf();
if (is_staff()) role_now(); // a demoted staff member must not keep staff powers until the session ends
$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
$loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string)($_POST['action'] ?? '');
$me = (int)$_SESSION['user_id'];
if ($orderId === false || $orderId < 1 || !in_array($action, ['report_pay', 'cancel', 'confirm_pay', 'ship', 'receive', 'lost', 'found'], true)) { http_response_code(400); exit('Invalid request'); }
if (in_array($action, ['confirm_pay', 'ship', 'receive', 'lost', 'found'], true)) require_staff(); // staff rights from the database
$back = 'Location: order.php?id=' . $orderId;
$done = 0;
// Who gets the notification: the member who owns the order (staff actions only)
function order_owner($conn, $orderId) {
    $stmt = mysqli_prepare($conn, 'SELECT user_id FROM lib_orders WHERE order_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $orderId); mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt, $uid);
    $ok = mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    return $ok ? (int)$uid : 0;
}
$ordLink = 'order.php?id=' . $orderId;

if ($action === 'report_pay') {
    $note = mb_substr(trim((string)($_POST['pay_note'] ?? '')), 0, 80);
    $stmt = mysqli_prepare($conn, "UPDATE lib_orders SET pay_status = 'reported', pay_note = ?, pay_reported_at = NOW() WHERE order_id = ? AND user_id = ? AND pay_status = 'unpaid' AND status = 'open'");
    mysqli_stmt_bind_param($stmt, 'sii', $note, $orderId, $me);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    flash($done === 1 ? 'ok' : 'err', $done === 1 ? 'แจ้งโอนเรียบร้อย ร้านจะตรวจสอบและจัดส่งหนังสือให้' : 'แจ้งโอนไม่ได้ ใบสั่งเช่านี้อยู่ในขั้นตอนอื่นแล้ว');
} elseif ($action === 'cancel') {
    // the owner can cancel only their own order; staff (id 0 = no owner check) can cancel any order that is not shipped yet
    $done = cancel_order($conn, $orderId, is_staff() ? 0 : $me) ? 1 : 0;
    flash($done ? 'ok' : 'err', $done ? 'ยกเลิกใบสั่งเช่าแล้ว หนังสือกลับเข้าชั้นแล้ว' : 'ยกเลิกไม่ได้ ใบสั่งเช่านี้ส่งพัสดุแล้ว ชำระเงินแล้ว (ติดต่อร้านเพื่อขอยกเลิก/รับเงินคืน) หรือไม่ใช่ของคุณ');
    if ($done && !is_staff()) { header('Location: my-orders.php'); exit; }
} elseif ($action === 'confirm_pay') {
    $stmt = mysqli_prepare($conn, "UPDATE lib_orders SET pay_status = 'confirmed', pay_confirmed_at = NOW(), confirmed_by = ? WHERE order_id = ? AND pay_status IN ('unpaid', 'reported') AND status = 'open'");
    mysqli_stmt_bind_param($stmt, 'ii', $me, $orderId);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($done === 1) notify($conn, order_owner($conn, $orderId), 'paid', 'ร้านยืนยันการชำระเงินแล้ว กำลังเตรียมจัดส่งหนังสือ ใบสั่งเช่า ' . order_no($orderId), $ordLink);
    flash($done === 1 ? 'ok' : 'err', $done === 1 ? 'ยืนยันการชำระเงินแล้ว พร้อมจัดส่ง' : 'ยืนยันไม่ได้ ใบสั่งเช่านี้อยู่ในขั้นตอนอื่นแล้ว');
} elseif ($action === 'ship') {
    $couriers = ['ไปรษณีย์ไทย', 'Kerry Express', 'Flash Express', 'J&T Express', 'อื่นๆ'];
    $courier = (string)($_POST['courier'] ?? '');
    $tracking = trim((string)($_POST['tracking_no'] ?? ''));
    if (!in_array($courier, $couriers, true) || !preg_match('/^[A-Za-z0-9-]{6,40}$/', $tracking)) { flash('err', 'กรุณาเลือกขนส่งและกรอกเลขพัสดุ (ตัวอักษร/ตัวเลข 6-40 ตัว)'); header($back); exit; }
    // Shipping starts the rental days: due date = today + the days the customer chose. Only a paid, unshipped, open order can ship.
    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "UPDATE lib_orders SET courier = ?, tracking_no = ?, shipped_at = CURDATE() WHERE order_id = ? AND pay_status = 'confirmed' AND shipped_at IS NULL AND status = 'open'");
    mysqli_stmt_bind_param($stmt, 'ssi', $courier, $tracking, $orderId);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($done === 1) {
        $stmt = mysqli_prepare($conn, "UPDATE lib_loans l JOIN lib_orders o ON o.order_id = l.order_id SET l.status = 'shipped', l.due_date = CURDATE() + INTERVAL o.rent_days DAY WHERE l.order_id = ? AND l.status = 'pending'");
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
    } else mysqli_rollback($conn);
    if ($done === 1) notify($conn, order_owner($conn, $orderId), 'shipped', 'ร้านส่งพัสดุแล้ว ขนส่ง ' . $courier . ' เลขพัสดุ ' . $tracking . ' ใบสั่งเช่า ' . order_no($orderId), $ordLink);
    flash($done === 1 ? 'ok' : 'err', $done === 1 ? 'บันทึกการจัดส่งแล้ว เริ่มนับวันเช่าตั้งแต่วันนี้' : 'จัดส่งไม่ได้ ต้องยืนยันการชำระเงินก่อน หรือส่งไปแล้ว');
} elseif ($action === 'found') { // a book marked lost arrives after all: back on the shelf, its copy counted again
    if ($loanId === false || $loanId < 1) { http_response_code(400); exit('Invalid request'); }
    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "UPDATE lib_loans SET status = 'returned', returned_at = CURDATE() WHERE loan_id = ? AND order_id = ? AND status = 'lost'");
    mysqli_stmt_bind_param($stmt, 'ii', $loanId, $orderId);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($done === 1) {
        $stmt = mysqli_prepare($conn, 'UPDATE lib_books SET copies_total = copies_total + 1, copies_available = copies_available + 1 WHERE book_id = (SELECT book_id FROM lib_loans WHERE loan_id = ?)');
        mysqli_stmt_bind_param($stmt, 'i', $loanId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        notify($conn, order_owner($conn, $orderId), 'info', 'ร้านพบหนังสือที่บันทึกว่าสูญหายแล้ว รับคืนเรียบร้อย คืนมัดจำตามยอดในใบสั่งเช่า', $ordLink);
    } else mysqli_rollback($conn);
    flash($done === 1 ? 'ok' : 'err', $done === 1 ? 'บันทึกว่าพบหนังสือและรับคืนแล้ว' : 'ทำรายการไม่ได้ เล่มนี้ไม่ได้อยู่ในสถานะสูญหาย');
} else { // receive or lost: one book of the order
    if ($loanId === false || $loanId < 1) { http_response_code(400); exit('Invalid request'); }
    mysqli_begin_transaction($conn);
    $newStatus = $action === 'receive' ? 'returned' : 'lost';
    $stmt = mysqli_prepare($conn, "UPDATE lib_loans SET status = ?, returned_at = CURDATE() WHERE loan_id = ? AND order_id = ? AND status = 'shipped'");
    mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $loanId, $orderId);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($done === 1) {
        // returned book goes back on the shelf; a lost book leaves the shelf for good (total copies - 1)
        $col = $action === 'receive' ? 'copies_available = copies_available + 1' : 'copies_total = copies_total - 1';
        $stmt = mysqli_prepare($conn, "UPDATE lib_books SET $col WHERE book_id = (SELECT book_id FROM lib_loans WHERE loan_id = ?)");
        mysqli_stmt_bind_param($stmt, 'i', $loanId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        $stmt = mysqli_prepare($conn, 'SELECT b.title FROM lib_loans l JOIN lib_books b ON b.book_id = l.book_id WHERE l.loan_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $loanId); mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt, $bTitle); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
        $t = mb_substr((string)$bTitle, 0, 60);
        if ($action === 'receive') notify($conn, order_owner($conn, $orderId), 'info', 'ร้านรับหนังสือ "' . $t . '" คืนแล้ว คืนมัดจำตามยอดในใบสั่งเช่า', $ordLink);
        else notify($conn, order_owner($conn, $orderId), 'lost', 'หนังสือ "' . $t . '" ถูกบันทึกว่าสูญหาย ไม่คืนมัดจำ', $ordLink);
    } else mysqli_rollback($conn);
    flash($done === 1 ? 'ok' : 'err', $done === 1 ? ($action === 'receive' ? 'บันทึกรับคืนแล้ว คืนมัดจำตามยอดที่แสดงในตาราง' : 'บันทึกว่าหนังสือสูญหายแล้ว') : 'ทำรายการไม่ได้ เล่มนี้อยู่ในขั้นตอนอื่นแล้ว');
}
header($back); exit;
