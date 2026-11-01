<?php
require 'auth.php'; require 'config/db.php';
require_login();
$me = (int)$_SESSION['user_id']; // always the logged-in member's own rows, never an id from the URL

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'clear') {
        $stmt = mysqli_prepare($conn, 'DELETE FROM lib_notifications WHERE user_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $me);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        flash('ok', 'ลบการแจ้งเตือนทั้งหมดแล้ว');
    }
    header('Location: notifications.php'); exit;
}

$items = [];
$stmt = mysqli_prepare($conn, 'SELECT notif_id, kind, message, link, created_at, read_at IS NULL, TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM lib_notifications WHERE user_id = ? ORDER BY notif_id DESC LIMIT 50');
mysqli_stmt_bind_param($stmt, 'i', $me);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $nid, $kind, $message, $link, $created, $isNew, $age);
while (mysqli_stmt_fetch($stmt)) $items[] = ['id' => $nid, 'kind' => $kind, 'message' => $message, 'link' => $link, 'created' => $created, 'new' => (bool)$isNew, 'age' => (int)$age];
mysqli_stmt_close($stmt);
$total = 0;
$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM lib_notifications WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $me);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Opening the page marks what is shown as read (only up to the newest id shown, so a row that arrives meanwhile stays unread)
if ($items) {
    $maxId = $items[0]['id'];
    $stmt = mysqli_prepare($conn, 'UPDATE lib_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL AND notif_id <= ?');
    mysqli_stmt_bind_param($stmt, 'ii', $me, $maxId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$kindIcon = ['paid' => 'receipt', 'shipped' => 'truck', 'due_soon' => 'clock', 'overdue' => 'flag', 'lost' => 'x', 'info' => 'info'];
$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
function when_text($age, $created, $months) {
    if ($age < 60) return 'เมื่อครู่';
    if ($age < 3600) return intdiv($age, 60) . ' นาทีที่แล้ว';
    if ($age < 86400) return intdiv($age, 3600) . ' ชั่วโมงที่แล้ว';
    if ($age < 7 * 86400) return intdiv($age, 86400) . ' วันที่แล้ว';
    $t = strtotime($created);
    return date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . ((int)date('Y', $t) + 543);
}
$pageTitle = 'การแจ้งเตือน'; $pageCss = ['platform'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>การแจ้งเตือน</h1><p>ความเคลื่อนไหวของการเช่าของคุณ</p></div></div>
<?php if (!$items): ?>
<div class="card empty"><h3>ยังไม่มีการแจ้งเตือน</h3><p>เมื่อชำระเงินได้รับการยืนยัน พัสดุถูกส่ง หรือใกล้ครบกำหนดคืน เราจะแจ้งที่นี่</p><a class="btn btn-primary" href="index.php">ไปเลือกหนังสือ</a></div>
<?php else: ?>
<ul class="notif-list">
<?php foreach ($items as $n):
    $k = isset($kindIcon[$n['kind']]) ? $n['kind'] : 'info';
    $okLink = $n['link'] !== '' && preg_match('/^[a-z0-9-]+\.php(\?[A-Za-z0-9_=&.-]*)?$/', $n['link']); ?>
  <li class="notif notif-<?= h($k) ?><?= $n['new'] ? ' unread' : '' ?>">
    <span class="notif-ic"><?= icon($kindIcon[$k], 22) ?></span>
    <div>
      <p class="notif-msg"><?= h($n['message']) ?></p>
      <div class="notif-meta"><?php if ($n['new']): ?><span class="badge b-warn">ใหม่</span><?php endif; ?><time datetime="<?= h(str_replace(' ', 'T', $n['created'])) ?>" title="<?= h($n['created']) ?>"><?= h(when_text($n['age'], $n['created'], $months)) ?></time></div>
    </div>
    <?php if ($okLink): ?><a class="btn btn-outline btn-sm notif-go" href="<?= h($n['link']) ?>">ดูรายละเอียด</a><?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<?php if ($total > count($items)): ?><p class="muted small center" style="margin-top:1rem">แสดง 50 รายการล่าสุดจากทั้งหมด <?= (int)$total ?> รายการ</p><?php endif; ?>
<form class="clear-form" action="notifications.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-danger btn-sm" data-confirm="ลบการแจ้งเตือนทั้งหมด?">ลบทั้งหมด</button></form>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
