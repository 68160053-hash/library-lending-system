<?php
require 'auth.php'; require 'config/db.php';
require_staff(); // server-side role check from the database
require_once __DIR__ . '/includes/staff.php';

$today = (string)staff_row($conn, 'SELECT CURDATE() AS d')['d'];
$error = '';
$f = ['code' => '', 'kind' => 'percent', 'value' => '', 'min_rent' => '0', 'max_uses' => '', 'expires_on' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        // Read only the fields we know (no mass assignment), then validate each on the server
        foreach ($f as $k => $_) $f[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $f['code'] = strtoupper($f['code']);
        $value = $minRent = $maxUses = $expires = null;
        if (!preg_match('/^[A-Z0-9]{4,30}$/', $f['code'])) $error = 'โค้ดใช้ได้เฉพาะ A-Z และ 0-9 ยาว 4-30 ตัว';
        elseif (!in_array($f['kind'], ['percent', 'amount'], true)) $error = 'เลือกชนิดส่วนลดไม่ถูกต้อง';
        elseif (($value = filter_var($f['value'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $f['kind'] === 'percent' ? 90 : 5000]])) === false)
            $error = $f['kind'] === 'percent' ? 'ส่วนลดเป็นเปอร์เซ็นต์ต้องเป็นเลข 1-90' : 'ส่วนลดเป็นบาทต้องเป็นเลข 1-5000';
        elseif (($minRent = $f['min_rent'] === '' ? 0.0 : filter_var($f['min_rent'], FILTER_VALIDATE_FLOAT)) === false || $minRent < 0 || $minRent > 99999) $error = 'ค่าเช่าขั้นต่ำต้องเป็นตัวเลข 0-99999';
        elseif ($f['max_uses'] !== '' && ($maxUses = filter_var($f['max_uses'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]])) === false) $error = 'จำนวนครั้งที่ใช้ได้ต้องเป็นเลข 1 ขึ้นไป หรือเว้นว่างถ้าไม่จำกัด';
        elseif ($f['expires_on'] !== '') {
            $dt = DateTime::createFromFormat('!Y-m-d', $f['expires_on']);
            if (!$dt || $dt->format('Y-m-d') !== $f['expires_on']) $error = 'วันหมดอายุไม่ถูกต้อง';
            elseif ($f['expires_on'] < $today) $error = 'วันหมดอายุต้องไม่ใช่วันที่ผ่านมาแล้ว';
            else $expires = $f['expires_on'];
        }
        if ($error === '') {
            $minRent = round((float)$minRent, 2); $v = (float)$value;
            $stmt = mysqli_prepare($conn, 'INSERT INTO lib_coupons (code, kind, value, min_rent, max_uses, expires_on) VALUES (?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssddis', $f['code'], $f['kind'], $v, $minRent, $maxUses, $expires);
            $ok = mysqli_stmt_execute($stmt); $errno = mysqli_stmt_errno($stmt); mysqli_stmt_close($stmt);
            if ($ok) { flash('ok', 'สร้างโค้ด ' . $f['code'] . ' แล้ว'); header('Location: coupons.php'); exit; }
            $error = $errno === 1062 ? 'โค้ดนี้มีอยู่แล้ว' : 'บันทึกไม่ได้ ลองใหม่อีกครั้ง';
        }
    } else {
        $id = filter_var($_POST['coupon_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) { http_response_code(400); exit('Invalid request'); }
        if ($action === 'toggle') {
            $to = ($_POST['to'] ?? '') === '1' ? 1 : 0; // previous state is in the WHERE, so a double click cannot flip it back
            $stmt = mysqli_prepare($conn, 'UPDATE lib_coupons SET active = ? WHERE coupon_id = ? AND active <> ?');
            mysqli_stmt_bind_param($stmt, 'iii', $to, $id, $to);
            mysqli_stmt_execute($stmt); $n = mysqli_stmt_affected_rows($stmt); mysqli_stmt_close($stmt);
            flash($n > 0 ? 'ok' : 'err', $n > 0 ? ($to ? 'เปิดใช้โค้ดแล้ว' : 'ปิดใช้โค้ดแล้ว') : 'ไม่มีอะไรเปลี่ยน');
        } elseif ($action === 'delete') {
            $stmt = mysqli_prepare($conn, 'DELETE FROM lib_coupons WHERE coupon_id = ? AND used_count = 0');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt); $n = mysqli_stmt_affected_rows($stmt); mysqli_stmt_close($stmt);
            flash($n > 0 ? 'ok' : 'err', $n > 0 ? 'ลบโค้ดแล้ว' : 'ลบไม่ได้ เพราะโค้ดนี้ถูกใช้แล้ว (ปิดใช้งานแทนได้)');
        } else { http_response_code(400); exit('Invalid request'); }
        header('Location: coupons.php'); exit;
    }
}

$coupons = staff_rows($conn, 'SELECT coupon_id, code, kind, value, min_rent, max_uses, used_count, expires_on, active, (expires_on IS NOT NULL AND expires_on < CURDATE()) AS expired FROM lib_coupons ORDER BY coupon_id DESC LIMIT 300');

$pageTitle = 'โค้ดส่วนลด'; $pageCss = ['staff'];
require 'includes/header.php';
?>
<div class="page-head"><div><h1>โค้ดส่วนลด</h1><p>ลูกค้าใช้ได้คนละครั้งต่อโค้ด ส่วนลดหักจากค่าเช่าเท่านั้น</p></div></div>
<div class="cols-coupon">
  <section class="card"><h3>สร้างโค้ดใหม่</h3>
    <?php if ($error): ?><div class="alert alert-err" role="alert"><?= icon('info', 20) ?><span><?= h($error) ?></span></div><?php endif; ?>
    <form action="coupons.php" method="post" autocomplete="off">
      <?php csrf_field(); ?><input type="hidden" name="action" value="create">
      <div class="field"><label for="code">โค้ด</label><input type="text" id="code" name="code" maxlength="30" value="<?= h($f['code']) ?>" required pattern="[A-Za-z0-9]{4,30}" class="mono"><p class="hint">A-Z และ 0-9 ยาว 4-30 ตัว (ระบบแปลงเป็นตัวพิมพ์ใหญ่ให้)</p></div>
      <div class="field"><label for="kind">ชนิดส่วนลด</label><select id="kind" name="kind"><option value="percent"<?= $f['kind'] === 'percent' ? ' selected' : '' ?>>เปอร์เซ็นต์ของค่าเช่า (1-90%)</option><option value="amount"<?= $f['kind'] === 'amount' ? ' selected' : '' ?>>จำนวนเงิน (1-5,000 บาท)</option></select></div>
      <div class="two">
        <div class="field"><label for="value">ส่วนลด</label><input type="number" id="value" name="value" min="1" max="5000" step="1" value="<?= h($f['value']) ?>" required></div>
        <div class="field"><label for="min_rent">ค่าเช่าขั้นต่ำ (บาท)</label><input type="number" id="min_rent" name="min_rent" min="0" max="99999" step="0.01" value="<?= h($f['min_rent']) ?>"></div>
      </div>
      <div class="two">
        <div class="field"><label for="max_uses">ใช้ได้กี่ครั้ง</label><input type="number" id="max_uses" name="max_uses" min="1" max="1000000" step="1" value="<?= h($f['max_uses']) ?>"><p class="hint">เว้นว่าง = ไม่จำกัด</p></div>
        <div class="field"><label for="expires_on">หมดอายุวันที่</label><input type="date" id="expires_on" name="expires_on" min="<?= h($today) ?>" value="<?= h($f['expires_on']) ?>"><p class="hint">เว้นว่าง = ไม่หมดอายุ</p></div>
      </div>
      <button type="submit" class="btn btn-primary"><?= icon('plus', 18) ?>สร้างโค้ด</button>
    </form></section>
  <section class="card"><h3>โค้ดทั้งหมด (<?= count($coupons) ?>)</h3>
    <div class="tbl-wrap"><table class="tbl stack">
      <caption class="sr-only">รายการโค้ดส่วนลด</caption>
      <thead><tr><th>โค้ด</th><th>ส่วนลด</th><th class="num">ใช้แล้ว</th><th>หมดอายุ</th><th>สถานะ</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($coupons as $c): $live = $c['active'] && !$c['expired'] && ($c['max_uses'] === null || $c['used_count'] < $c['max_uses']); ?>
      <tr>
        <td class="full"><span class="code"><?= h($c['code']) ?></span></td>
        <td data-label="ส่วนลด"><?= $c['kind'] === 'percent' ? h(rtrim(rtrim(number_format((float)$c['value'], 2), '0'), '.')) . '%' : '฿' . h(number_format((float)$c['value'])) ?><?php if ((float)$c['min_rent'] > 0): ?><br><span class="muted small">เมื่อค่าเช่าถึง ฿<?= h(number_format((float)$c['min_rent'])) ?></span><?php endif; ?></td>
        <td class="num" data-label="ใช้แล้ว"><?= (int)$c['used_count'] ?>/<?= $c['max_uses'] === null ? 'ไม่จำกัด' : (int)$c['max_uses'] ?></td>
        <td data-label="หมดอายุ"><?= $c['expires_on'] ? h(date('d/m/Y', strtotime($c['expires_on']))) : '<span class="muted">ไม่หมดอายุ</span>' ?></td>
        <td data-label="สถานะ"><span class="badge <?= $live ? 'b-ok' : 'b-warn' ?>"><?= !$c['active'] ? 'ปิดอยู่' : ($c['expired'] ? 'หมดอายุ' : ($live ? 'ใช้งานได้' : 'ครบจำนวน')) ?></span></td>
        <td class="actions full">
          <form action="coupons.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>"><input type="hidden" name="to" value="<?= $c['active'] ? 0 : 1 ?>"><button type="submit" class="btn btn-outline btn-sm"><?= $c['active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button></form>
          <?php if ((int)$c['used_count'] === 0): ?><form action="coupons.php" method="post" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="coupon_id" value="<?= (int)$c['coupon_id'] ?>"><button type="submit" class="btn btn-danger btn-sm" data-confirm="ลบโค้ด <?= h($c['code']) ?> ?"><?= icon('trash', 16) ?>ลบ</button></form><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$coupons): ?><tr><td colspan="6" class="center empty">ยังไม่มีโค้ดส่วนลด สร้างโค้ดแรกได้จากฟอร์ม</td></tr><?php endif; ?>
      </tbody>
    </table></div></section>
</div>
<?php require 'includes/footer.php'; ?>
