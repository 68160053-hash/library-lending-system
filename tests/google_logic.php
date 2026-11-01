<?php
// Tests the account rules behind "Sign in with Google" (google_account() in auth.php) against the TEST database.
// Run by tests/google.mjs:  DB_NAME=lib_sec php tests/google_logic.php
chdir(__DIR__ . '/..');
require 'auth.php'; require 'config/db.php';
if (!preg_match('/sec|test/', getenv('DB_NAME') ?: '')) { echo "refusing: DB_NAME must be a throwaway test database\n"; exit(1); }
$pass = 0; $fail = 0;
function ok($name, $cond) { global $pass, $fail; $cond ? $pass++ : $fail++; echo ($cond ? 'PASS ' : 'FAIL ') . $name . "\n"; }
function one($conn, $sql) { $r = mysqli_query($conn, $sql); $row = $r ? mysqli_fetch_row($r) : null; return $row ? $row[0] : null; }
function wipe($conn) { mysqli_query($conn, "DELETE FROM lib_users WHERE email LIKE '%@g.test'"); }
wipe($conn);

// L1 new person
$a = google_account($conn, 'sub-new-1', 'new1@g.test', 'Nina New');
ok('L1 a new Google identity creates a member account', is_array($a) && $a[2] === 'member' && $a[1] === 'Nina New' && one($conn, "SELECT google_sub FROM lib_users WHERE email='new1@g.test'") === 'sub-new-1');
$hash = one($conn, "SELECT password_hash FROM lib_users WHERE email='new1@g.test'");
ok('L2 the account has a random password nobody knows (password login is not possible)', strlen($hash) > 50 && !password_verify('password', $hash) && !password_verify('', $hash) && !password_verify('new1@g.test', $hash));
// L3 same person again
$b = google_account($conn, 'sub-new-1', 'new1@g.test', 'Nina New');
ok('L3 the same Google id comes back to the same account (no duplicate)', $b[0] === $a[0] && one($conn, "SELECT COUNT(*) FROM lib_users WHERE email='new1@g.test'") === '1');
// L4 password account with the same email (pre-hijacking)
mysqli_query($conn, "INSERT INTO lib_users (email, password_hash, full_name) VALUES ('victim@g.test', '" . password_hash('attacker-chose-this', PASSWORD_DEFAULT) . "', 'Attacker')");
$c = google_account($conn, 'sub-victim', 'victim@g.test', 'Real Victim');
ok('L4 Google refuses to merge into an existing password account (stops pre-hijacking)', $c === false && one($conn, "SELECT google_sub IS NULL FROM lib_users WHERE email='victim@g.test'") === '1' && one($conn, "SELECT COUNT(*) FROM lib_users WHERE email='victim@g.test'") === '1');
// L5 changed email on Google side
$d = google_account($conn, 'sub-new-1', 'renamed@g.test', 'Nina New');
ok('L5 a linked Google id still finds its account after the Google email changed', $d[0] === $a[0] && one($conn, "SELECT COUNT(*) FROM lib_users WHERE email='renamed@g.test'") === '0');
// L6 role comes from the database
mysqli_query($conn, "UPDATE lib_users SET role='admin' WHERE user_id=" . (int)$a[0]);
$e = google_account($conn, 'sub-new-1', 'new1@g.test', 'Nina New');
ok('L6 role is read from the database (admin stays admin, a new Google user never starts as staff/admin)', $e[2] === 'admin');
$f = google_account($conn, 'sub-new-2', 'new2@g.test', 'Second');
ok('L7 a second new Google user is a plain member', $f[2] === 'member');
// L8 name cleaning
$g = google_account($conn, 'sub-new-3', 'new3@g.test', "  Ev\x00il\x1f Name  ");
ok('L8 control characters are stripped from the name', $g[1] === 'Evil Name');
$h = google_account($conn, 'sub-new-4', 'new4@g.test', str_repeat('N', 300));
ok('L9 a 300-character name is cut to 100', mb_strlen($h[1]) === 100);
$i = google_account($conn, 'sub-new-5', 'localpart5@g.test', '   ');
ok('L10 an empty name falls back to the email name', $i[1] === 'localpart5');
// L11 injection strings
$j = google_account($conn, "x'); DROP TABLE lib_users;--", "inj'--@g.test", "Robert'); DROP TABLE lib_users;--");
ok('L11 SQL in sub/email/name is stored as plain text, table survives', is_array($j) && one($conn, 'SELECT COUNT(*) FROM lib_users') !== null && $j[1] === "Robert'); DROP TABLE lib_users;--");
// L12 two different Google ids, same email
$k = google_account($conn, 'sub-other', 'new1@g.test', 'Impostor');
ok('L12 a different Google id with an already used email is refused', $k === false);
// L13 duplicate sub cannot be inserted twice by hand
ok('L13 google_sub is UNIQUE in the database', !mysqli_query($conn, "INSERT INTO lib_users (email, password_hash, full_name, google_sub) VALUES ('dup@g.test', 'x', 'Dup', 'sub-new-2')"));
wipe($conn);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
