<?php
// Home: send people to the right page. (Catalogue / borrow pages are teammates' work.)
require 'auth.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
header('Location: ' . (is_staff() ? 'overdue.php' : 'my-loans.php')); exit;
