<?php
require 'auth.php'; require 'config/db.php';
// Changing a role changes data, so POST only, admin only, with a CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('<a href="users.php">กลับ</a>'); }
require_admin();
verify_csrf();
$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
$role = (string)($_POST['role'] ?? '');
// Only these three values are ever written to the database (the role never comes straight from the form into SQL).
if ($userId === false || $userId < 1 || !in_array($role, ['member', 'staff', 'admin'], true)) { http_response_code(400); exit('Invalid request'); }
// An admin cannot change their own role, so the system can never be left with no admin.
if ($userId === (int)$_SESSION['user_id']) { header('Location: users.php?error=1'); exit; }

$stmt = mysqli_prepare($conn, 'UPDATE lib_users SET role = ? WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'si', $role, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
header('Location: users.php?saved=1'); exit;
