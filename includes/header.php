<?php if (!function_exists('h')) { http_response_code(403); exit; } // never run on its own: only included by a page that loaded auth.php
// Shared page top: <head>, header with search + menu, staff bar, flash message, opens <main>. Set $pageTitle before including.
$navRole = $_SESSION['role'] ?? '';
$isStaff = $navRole === 'staff' || $navRole === 'admin';
$here = basename($_SERVER['SCRIPT_NAME']);
$cartCount = count(cart_ids());
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
function nav_current($files) { global $here; return in_array($here, (array)$files, true) ? ' aria-current="page"' : ''; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'Book Lending Shop') ?> | Book Lending Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<header class="topbar">
  <div class="topbar-in">
    <a class="logo" href="index.php"><span class="logo-mark" aria-hidden="true">B</span><span>Book Lending Shop<small>ร้านเช่าหนังสือออนไลน์</small></span></a>
    <form class="search" action="index.php" method="get" role="search">
      <label class="sr-only" for="hq">ค้นหาหนังสือ</label>
      <input type="text" id="hq" name="q" placeholder="ค้นหาชื่อหนังสือหรือผู้แต่ง" maxlength="100" value="<?= h($_GET['q'] ?? '') ?>">
      <button type="submit">ค้นหา</button>
    </form>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">เมนู</button>
    <nav class="nav" id="nav" aria-label="เมนูหลัก">
      <a href="index.php"<?= nav_current(['index.php', 'book.php']) ?>>หนังสือ</a>
      <a href="cart.php"<?= nav_current(['cart.php', 'checkout.php']) ?>>ตะกร้า<?php if ($cartCount): ?><span class="cart-count" aria-label="<?= (int)$cartCount ?> รายการ"><?= (int)$cartCount ?></span><?php endif; ?></a>
      <?php if ($navRole === ''): ?>
      <a href="login.php"<?= nav_current('login.php') ?>>เข้าสู่ระบบ</a>
      <a class="btn btn-primary btn-sm" href="register.php">สมัครสมาชิก</a>
      <?php else: ?>
      <a href="my-orders.php"<?= nav_current(['my-orders.php', 'order.php']) ?>>การเช่าของฉัน</a>
      <a href="profile.php"<?= nav_current('profile.php') ?>><?= h(mb_substr($_SESSION['full_name'] ?? 'โปรไฟล์', 0, 18)) ?></a>
      <form class="inline-form" action="logout.php" method="post"><?php csrf_field(); ?><button type="submit" class="linklike">ออกจากระบบ</button></form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($isStaff): ?>
<div class="staffbar"><div class="staffbar-in">
  <span>เจ้าหน้าที่</span>
  <a href="dashboard.php"<?= nav_current('dashboard.php') ?>>แดชบอร์ด</a>
  <a href="rentals.php"<?= nav_current('rentals.php') ?>>คำสั่งเช่า</a>
  <a href="books.php"<?= nav_current(['books.php', 'book-form.php']) ?>>หนังสือ</a>
  <a href="overdue.php"<?= nav_current('overdue.php') ?>>เกินกำหนด</a>
  <?php if ($navRole === 'admin'): ?><a href="users.php"<?= nav_current('users.php') ?>>ผู้ใช้</a><?php endif; ?>
</div></div>
<?php endif; ?>
<main id="main" class="container">
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'alert-ok' : 'alert-err' ?>" role="<?= $flash[0] === 'ok' ? 'status' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
