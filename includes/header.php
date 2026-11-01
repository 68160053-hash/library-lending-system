<?php if (!function_exists('h')) { http_response_code(403); exit; } // never run on its own: only included by a page that loaded auth.php
// Shared page top: <head>, bunting + fascia (logo, search, menu), staff bar, flash message, opens <main>.
// A page sets $pageTitle first, and may set $pageCss = ['discover'] to load assets/css/discover.css after app.css.
$navRole = $_SESSION['role'] ?? '';
$isStaff = $navRole === 'staff' || $navRole === 'admin';
$here = basename($_SERVER['SCRIPT_NAME']);
$cartCount = count(cart_ids());
$unread = ($navRole !== '' && isset($conn)) ? unread_notifications($conn, (int)$_SESSION['user_id']) : 0;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
function nav_current($files) { global $here; return in_array($here, (array)$files, true) ? ' aria-current="page"' : ''; }
$v = function ($file) { return (int)@filemtime(__DIR__ . '/../' . $file); };
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#12206e">
  <title><?= h($pageTitle ?? 'Book Lending Shop') ?> | Book Lending Shop</title>
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="assets/icons/icon-192.png" type="image/png" sizes="192x192">
  <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;800&amp;family=Sarabun:wght@400;600;700&amp;display=swap" rel="stylesheet">
  <script src="assets/js/theme.js?v=<?= $v('assets/js/theme.js') ?>"></script>
  <link rel="stylesheet" href="assets/css/app.css?v=<?= $v('assets/css/app.css') ?>">
<?php foreach ((array)($pageCss ?? []) as $css): $css = preg_replace('/[^a-z0-9-]/', '', $css); ?>
  <link rel="stylesheet" href="assets/css/<?= h($css) ?>.css?v=<?= $v('assets/css/' . $css . '.css') ?>">
<?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
<div class="bunting" aria-hidden="true"></div>
<header class="topbar">
  <div class="topbar-in">
    <a class="logo" href="index.php"><span class="logo-mark" aria-hidden="true"><?= icon('book', 24) ?></span><span>Book Lending Shop<small>ร้านเช่าหนังสือออนไลน์</small></span></a>
    <form class="search" action="index.php" method="get" role="search">
      <label class="sr-only" for="hq">ค้นหาหนังสือ</label>
      <?= icon('search', 18) ?>
      <input type="text" id="hq" name="q" placeholder="ค้นหาชื่อหนังสือหรือผู้แต่ง" maxlength="100" autocomplete="off" value="<?= h(is_string($_GET['q'] ?? '') ? $_GET['q'] : '') ?>" data-suggest>
      <button type="submit">ค้นหา</button>
    </form>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav"><?= icon('menu', 20) ?>เมนู</button>
    <nav class="nav" id="nav" aria-label="เมนูหลัก">
      <a href="index.php"<?= nav_current(['index.php', 'book.php']) ?>>หนังสือ</a>
      <a href="shelf.php"<?= nav_current('shelf.php') ?>><?= icon('heart', 18) ?>ชั้นหนังสือ</a>
      <a href="cart.php"<?= nav_current(['cart.php', 'checkout.php']) ?>><?= icon('cart', 18) ?>ตะกร้า<?php if ($cartCount): ?><span class="cart-count" aria-label="<?= (int)$cartCount ?> รายการ"><?= (int)$cartCount ?></span><?php endif; ?></a>
      <?php if ($navRole === ''): ?>
      <a href="login.php"<?= nav_current('login.php') ?>>เข้าสู่ระบบ</a>
      <a class="btn btn-accent btn-sm" href="register.php">สมัครสมาชิก</a>
      <?php else: ?>
      <a href="my-orders.php"<?= nav_current(['my-orders.php', 'order.php']) ?>><?= icon('receipt', 18) ?>การเช่าของฉัน</a>
      <a class="icon-btn" href="notifications.php" aria-label="การแจ้งเตือน<?= $unread ? ' ' . (int)$unread . ' รายการใหม่' : '' ?>"<?= nav_current('notifications.php') ?>><?= icon('bell', 22) ?><?php if ($unread): ?><span class="badge-dot"><?= $unread > 9 ? '9+' : (int)$unread ?></span><?php endif; ?></a>
      <a href="profile.php"<?= nav_current('profile.php') ?>><?= icon('user', 18) ?><?= h(mb_substr($_SESSION['full_name'] ?? 'โปรไฟล์', 0, 14)) ?></a>
      <form class="inline-form" action="logout.php" method="post"><?php csrf_field(); ?><button type="submit" class="linklike">ออกจากระบบ</button></form>
      <?php endif; ?>
      <button class="icon-btn" type="button" data-theme-toggle aria-pressed="false" aria-label="สลับโหมดมืด/สว่าง"><?= icon('moon', 20) ?></button>
    </nav>
  </div>
</header>
<?php if ($isStaff): ?>
<div class="staffbar"><div class="staffbar-in">
  <span>เจ้าหน้าที่</span>
  <a href="dashboard.php"<?= nav_current('dashboard.php') ?>>แดชบอร์ด</a>
  <a href="rentals.php"<?= nav_current('rentals.php') ?>>คำสั่งเช่า</a>
  <a href="books.php"<?= nav_current(['books.php', 'book-form.php']) ?>>หนังสือ</a>
  <a href="coupons.php"<?= nav_current('coupons.php') ?>>โค้ดส่วนลด</a>
  <a href="overdue.php"<?= nav_current('overdue.php') ?>>เกินกำหนด</a>
  <?php if ($navRole === 'admin'): ?><a href="users.php"<?= nav_current('users.php') ?>>ผู้ใช้</a><?php endif; ?>
</div></div>
<?php endif; ?>
<main id="main" class="container">
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'alert-ok' : 'alert-err' ?>" role="<?= $flash[0] === 'ok' ? 'status' : 'alert' ?>"><?= icon($flash[0] === 'ok' ? 'check' : 'info', 20) ?><span><?= h($flash[1]) ?></span></div><?php endif; ?>
