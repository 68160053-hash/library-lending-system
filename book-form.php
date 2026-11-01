<?php
require 'auth.php'; require 'config/db.php';
require_staff();

// ?id=N = edit that book, no id = add a new one. The same form and the same checks serve both.
$bookId = filter_var($_GET['id'] ?? $_POST['book_id'] ?? 0, FILTER_VALIDATE_INT);
if ($bookId === false || $bookId < 0) { http_response_code(400); exit('Invalid book ID'); }
$error = '';
$f = ['title' => '', 'author' => '', 'isbn' => '', 'category_id' => 0, 'copies_total' => 1, 'rent_per_day' => '10', 'deposit' => '100'];

// Categories for the drop-down
$cats = [];
$res = mysqli_query($conn, 'SELECT category_id, name FROM lib_categories ORDER BY category_id');
while ($res && ($row = mysqli_fetch_assoc($res))) $cats[$row['category_id']] = $row['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Step 1: read and clean the form values
    $f['title'] = trim((string)($_POST['title'] ?? ''));
    $f['author'] = trim((string)($_POST['author'] ?? ''));
    $f['isbn'] = trim((string)($_POST['isbn'] ?? ''));
    $f['category_id'] = (int)($_POST['category_id'] ?? 0);
    $f['copies_total'] = filter_var($_POST['copies_total'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
    $f['rent_per_day'] = filter_var($_POST['rent_per_day'] ?? null, FILTER_VALIDATE_FLOAT);
    $f['deposit'] = filter_var($_POST['deposit'] ?? null, FILTER_VALIDATE_FLOAT);
    // Step 2: validate on the server (the browser's own checks can be bypassed)
    if (mb_strlen($f['title']) < 1 || mb_strlen($f['title']) > 200) $error = 'ชื่อเรื่องต้องยาว 1-200 ตัวอักษร';
    elseif (mb_strlen($f['author']) < 1 || mb_strlen($f['author']) > 120) $error = 'ชื่อผู้แต่งต้องยาว 1-120 ตัวอักษร';
    elseif (!preg_match('/^[0-9A-Za-z-]{3,20}$/', $f['isbn'])) $error = 'ISBN ใช้ได้เฉพาะตัวเลข ตัวอักษร และขีด (3-20 ตัว)';
    elseif (!isset($cats[$f['category_id']])) $error = 'กรุณาเลือกหมวด';
    elseif ($f['copies_total'] === false) $error = 'จำนวนเล่มต้องเป็นเลข 1-999';
    elseif ($f['rent_per_day'] === false || $f['rent_per_day'] < 0 || $f['rent_per_day'] > 9999) $error = 'ค่าเช่าต่อวันต้องเป็นตัวเลข 0-9999';
    elseif ($f['deposit'] === false || $f['deposit'] < 0 || $f['deposit'] > 99999) $error = 'ค่ามัดจำต้องเป็นตัวเลข 0-99999';
    else {
        $outNow = 0; // copies currently out with renters
        if ($bookId > 0) {
            $stmt = mysqli_prepare($conn, 'SELECT copies_total - copies_available FROM lib_books WHERE book_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $bookId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $outNow);
            $exists = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            if (!$exists) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้'); }
        }
        if ($f['copies_total'] < $outNow) {
            $error = 'จำนวนเล่มทั้งหมดต้องไม่น้อยกว่าจำนวนเล่มที่ถูกเช่าอยู่ (' . (int)$outNow . ' เล่ม)';
        } else {
            if ($bookId === 0) {
                // Step 3a: add. All values are bound as parameters, never glued into the SQL text.
                $sql = 'INSERT INTO lib_books (category_id, title, author, isbn, copies_total, copies_available, rent_per_day, deposit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'isssiidd', $f['category_id'], $f['title'], $f['author'], $f['isbn'], $f['copies_total'], $f['copies_total'], $f['rent_per_day'], $f['deposit']);
            } else {
                // Step 3b: edit. Free copies = new total - copies out, so renters' copies are never lost.
                $sql = 'UPDATE lib_books SET category_id = ?, title = ?, author = ?, isbn = ?, rent_per_day = ?, deposit = ?,
                               copies_available = ? - (copies_total - copies_available), copies_total = ?
                        WHERE book_id = ?';
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'isssddiii', $f['category_id'], $f['title'], $f['author'], $f['isbn'], $f['rent_per_day'], $f['deposit'], $f['copies_total'], $f['copies_total'], $bookId);
            }
            $ok = mysqli_stmt_execute($stmt);
            $errno = mysqli_stmt_errno($stmt);
            mysqli_stmt_close($stmt);
            if ($ok) { header('Location: books.php?saved=1'); exit; }
            $error = $errno === 1062 ? 'ISBN นี้มีอยู่แล้ว' : 'บันทึกไม่ได้ ลองใหม่อีกครั้ง';
        }
    }
} elseif ($bookId > 0) {
    // Show the current values of the book being edited
    $stmt = mysqli_prepare($conn, 'SELECT title, author, isbn, category_id, copies_total, rent_per_day, deposit FROM lib_books WHERE book_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $bookId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $f['title'], $f['author'], $f['isbn'], $f['category_id'], $f['copies_total'], $f['rent_per_day'], $f['deposit']);
    if (!mysqli_stmt_fetch($stmt)) { mysqli_stmt_close($stmt); http_response_code(404); exit('ไม่พบหนังสือเล่มนี้'); }
    mysqli_stmt_close($stmt);
}

$pageTitle = $bookId ? 'แก้ไขหนังสือ' : 'เพิ่มหนังสือ';
require 'includes/header.php';
?>
<main id="main">
  <div class="container">
    <div class="letter-wrap">
      <section class="letter paper frame" aria-labelledby="bf-title">
        <h1 id="bf-title"><?= h($pageTitle) ?></h1>
        <?php if ($error): ?>
        <div class="alert" role="alert"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span><?= h($error) ?></span></div>
        <?php endif; ?>
        <form action="book-form.php<?= $bookId ? '?id=' . (int)$bookId : '' ?>" method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="book_id" value="<?= (int)$bookId ?>">
          <div class="field"><label for="title">ชื่อเรื่อง</label><input type="text" id="title" name="title" maxlength="200" value="<?= h($f['title']) ?>" required></div>
          <div class="field"><label for="author">ผู้แต่ง</label><input type="text" id="author" name="author" maxlength="120" value="<?= h($f['author']) ?>" required></div>
          <div class="field"><label for="isbn">ISBN</label><input type="text" id="isbn" name="isbn" maxlength="20" value="<?= h($f['isbn']) ?>" required></div>
          <div class="field"><label for="category_id">หมวด</label>
            <select id="category_id" name="category_id" required>
              <option value="">เลือกหมวด</option>
              <?php foreach ($cats as $cid => $cname): ?><option value="<?= (int)$cid ?>"<?= (int)$f['category_id'] === (int)$cid ? ' selected' : '' ?>><?= h($cname) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><label for="copies_total">จำนวนเล่มทั้งหมด</label><input type="number" id="copies_total" name="copies_total" min="1" max="999" value="<?= h($f['copies_total']) ?>" required></div>
          <div class="field"><label for="rent_per_day">ค่าเช่าต่อวัน (บาท)</label><input type="number" id="rent_per_day" name="rent_per_day" min="0" max="9999" step="0.01" value="<?= h($f['rent_per_day']) ?>" required></div>
          <div class="field"><label for="deposit">ค่ามัดจำ (บาท)</label><input type="number" id="deposit" name="deposit" min="0" max="99999" step="0.01" value="<?= h($f['deposit']) ?>" required></div>
          <button type="submit" class="btn btn-primary btn-magic btn-block">บันทึก</button>
        </form>
        <p class="form-foot"><a href="books.php">กลับไปรายการหนังสือ</a></p>
      </section>
    </div>
  </div>
</main>
<?php require 'includes/footer.php'; ?>
