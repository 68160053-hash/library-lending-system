// End-to-end security test for LibraryProject PHP pages (run against the throwaway DB only).
// Setup: see README "ทดสอบความปลอดภัย". Needs: MariaDB/MySQL on port 3399 with db lib_sec (import sql/schema.sql),
// and: DB_HOST=127.0.0.1 DB_USER=root DB_NAME=lib_sec php -d mysqli.default_port=3399 -S 127.0.0.1:8092
import { execFileSync } from 'node:child_process';
const BASE = 'http://127.0.0.1:8092/';
const MYSQL = 'C:/xampp/mysql/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-h', '127.0.0.1', '-P', '3399', '-u', 'root', '--default-character-set=utf8mb4', '-N', '-B', 'lib_sec', '-e', q]).toString().trim();

let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => { cond ? pass++ : fail++; console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  [' + extra + ']' : '')); };

function client() {
  let jar = {};
  const c = {
    jar,
    async req(path, { method = 'GET', form } = {}) {
      const headers = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; ') };
      let body;
      if (form) { headers['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(BASE + path, { method, headers, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); c.lastSetCookie = sc; }
      return { status: r.status, loc: r.headers.get('location') || '', text: await r.text() };
    },
    async token(path) { const r = await c.req(path); const m = r.text.match(/name="csrf" value="([0-9a-f]+)"/); return m ? m[1] : ''; },
  };
  return c;
}

// clean previous runs
sql("DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@t.test'; DELETE FROM lib_users WHERE email LIKE '%@t.test';");

const A = client(), B = client(), S = client();
let t = await A.token('register.php');

// --- Registration
let r = await A.req('register.php', { method: 'POST', form: { full_name: 'Short Pw', email: 'short@t.test', password: '1234567', password_confirm: '1234567', csrf: t } });
ok('T1 register rejects 7-char password', sql("SELECT COUNT(*) FROM lib_users WHERE email='short@t.test'") === '0' && r.status === 200);

r = await A.req('register.php', { method: 'POST', form: { full_name: 'No Token', email: 'notoken@t.test', password: 'password123', password_confirm: 'password123' } });
ok('T2 register without CSRF token -> 403', r.status === 403 && sql("SELECT COUNT(*) FROM lib_users WHERE email='notoken@t.test'") === '0', 'status ' + r.status);

r = await A.req('register.php', { method: 'POST', form: { full_name: '<script>alert(1)</script>', email: 'a@t.test', password: 'password123', password_confirm: 'password123', role: 'admin', csrf: t } });
ok('T3 register ok, role forced to member (role=admin in form ignored)', sql("SELECT role FROM lib_users WHERE email='a@t.test'") === 'member', r.loc);
ok('T4 password stored as bcrypt hash', sql("SELECT password_hash FROM lib_users WHERE email='a@t.test'").startsWith('$2y$'));

r = await A.req('register.php', { method: 'POST', form: { full_name: 'Dup', email: 'A@T.test', password: 'password123', password_confirm: 'password123', csrf: t } });
ok('T5 duplicate email (different case) rejected', sql("SELECT COUNT(*) FROM lib_users WHERE email IN ('a@t.test','A@T.test')") === '1' && r.text.includes('อีเมลนี้มีผู้ใช้แล้ว'));

r = await A.req('register.php', { method: 'POST', form: { full_name: 'Mismatch', email: 'mm@t.test', password: 'password123', password_confirm: 'password124', csrf: t } });
ok('T6 password confirm mismatch rejected', sql("SELECT COUNT(*) FROM lib_users WHERE email='mm@t.test'") === '0');

// second member and a staff member
t = await B.token('register.php');
await B.req('register.php', { method: 'POST', form: { full_name: 'Member B', email: 'b@t.test', password: 'password456', password_confirm: 'password456', csrf: t } });
t = await S.token('register.php');
await S.req('register.php', { method: 'POST', form: { full_name: 'Staff S', email: 's@t.test', password: 'password789', password_confirm: 'password789', csrf: t } });
sql("UPDATE lib_users SET role='staff' WHERE email='s@t.test'");

// --- Login
t = await A.token('login.php');
r = await A.req('login.php', { method: 'POST', form: { email: 'a@t.test', password: 'wrongpass1', csrf: t } });
const wrongMsg = r.text.includes('อีเมลหรือรหัสผ่านไม่ถูกต้อง');
r = await A.req('login.php', { method: 'POST', form: { email: 'nobody@t.test', password: 'wrongpass1', csrf: t } });
ok('T7 wrong password and unknown email give the same message', wrongMsg && r.text.includes('อีเมลหรือรหัสผ่านไม่ถูกต้อง') && !r.loc);

r = await A.req('login.php', { method: 'POST', form: { email: "' OR '1'='1' -- ", password: "' OR '1'='1", csrf: t } });
ok('T8 SQL injection in login does not log in', !r.loc.includes('my-loans') && !r.loc.includes('overdue'), 'loc=' + r.loc);

r = await A.req('login.php', { method: 'POST', form: { email: 'a@t.test', password: 'password123' } });
ok('T9 login without CSRF token -> 403', r.status === 403);

const sidBefore = A.jar.PHPSESSID;
r = await A.req('login.php', { method: 'POST', form: { email: 'a@t.test', password: 'password123', csrf: t } });
ok('T10 member login ok -> my-loans.php', r.status === 302 && r.loc.includes('my-loans.php'), r.loc);
ok('T11 session id changes after login (session_regenerate_id)', A.jar.PHPSESSID && A.jar.PHPSESSID !== sidBefore);
ok('T12 session cookie is HttpOnly + SameSite=Lax', /httponly/i.test(A.lastSetCookie) && /samesite=lax/i.test(A.lastSetCookie), A.lastSetCookie);

r = await A.req('my-loans.php');
ok('T13 XSS name is escaped on output', r.status === 200 && r.text.includes('&lt;script&gt;alert(1)&lt;/script&gt;') && !r.text.includes('<script>alert(1)</script>'));

// --- Authorization
const anon = client();
r = await anon.req('my-loans.php');
ok('T14 my-loans without login -> redirect to login', r.status === 302 && r.loc.includes('login.php'));
r = await A.req('overdue.php');
ok('T15 member opening staff page overdue.php -> 403', r.status === 403, 'status ' + r.status);

// loans: A has an active loan, B has an active loan and an overdue loan
const uid = (e) => sql(`SELECT user_id FROM lib_users WHERE email='${e}'`);
sql(`INSERT INTO lib_loans (user_id, book_id, borrowed_at, due_date) VALUES (${uid('a@t.test')}, 1, CURDATE(), CURDATE() + INTERVAL 7 DAY)`);
const loanA = sql('SELECT MAX(loan_id) FROM lib_loans');
sql(`INSERT INTO lib_loans (user_id, book_id, borrowed_at, due_date) VALUES (${uid('b@t.test')}, 2, CURDATE(), CURDATE() + INTERVAL 7 DAY)`);
const loanB = sql('SELECT MAX(loan_id) FROM lib_loans');
sql(`INSERT INTO lib_loans (user_id, book_id, borrowed_at, due_date) VALUES (${uid('b@t.test')}, 3, CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 3 DAY)`);
const loanBlate = sql('SELECT MAX(loan_id) FROM lib_loans');

r = await A.req('my-loans.php');
ok('T16 member sees only own loans', r.text.includes('Database System Concepts') && !r.text.includes('Sample Novel') && !r.text.includes('Sample History Book'));

t = await A.token('my-loans.php');
const dueB = sql(`SELECT due_date FROM lib_loans WHERE loan_id=${loanB}`);
r = await A.req('renew.php', { method: 'POST', form: { loan_id: loanB, csrf: t } });
ok('T17 member A cannot renew member B loan (owner id in WHERE)', sql(`SELECT due_date FROM lib_loans WHERE loan_id=${loanB}`) === dueB && r.loc.includes('renew_error'), r.loc);

r = await A.req('renew.php', { method: 'POST', form: { loan_id: loanA } });
ok('T18 renew without CSRF token -> 403', r.status === 403 && sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${loanA}`) === '0');

r = await A.req(`renew.php?loan_id=${loanA}`);
ok('T19 renew via GET does nothing', r.status === 405 && sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${loanA}`) === '0', 'status ' + r.status);

r = await A.req('renew.php', { method: 'POST', form: { loan_id: "1 OR 1=1", csrf: t } });
ok('T20 renew with injected loan_id -> 400', r.status === 400, 'status ' + r.status);

r = await A.req('renew.php', { method: 'POST', form: { loan_id: loanA, csrf: t } });
ok('T21 own renew works: +7 days, renew_count 1', r.loc.includes('renewed=1') && sql(`SELECT CONCAT(renew_count, ' ', DATEDIFF(due_date, CURDATE()))  FROM lib_loans WHERE loan_id=${loanA}`) === '1 14');
r = await A.req('renew.php', { method: 'POST', form: { loan_id: loanA, csrf: t } });
ok('T22 second renew refused (only once)', r.loc.includes('renew_error') && sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${loanA}`) === '1');

t = await B.token('login.php');
await B.req('login.php', { method: 'POST', form: { email: 'b@t.test', password: 'password456', csrf: t } });
t = await B.token('my-loans.php');
r = await B.req('renew.php', { method: 'POST', form: { loan_id: loanBlate, csrf: t } });
ok('T23 overdue loan cannot be renewed', r.loc.includes('renew_error') && sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${loanBlate}`) === '0');

t = await S.token('login.php');
r = await S.req('login.php', { method: 'POST', form: { email: 's@t.test', password: 'password789', csrf: t } });
ok('T24 staff login -> overdue.php', r.loc.includes('overdue.php'), r.loc);
r = await S.req('overdue.php');
ok('T25 staff sees overdue loan with fine 15 baht', r.status === 200 && r.text.includes('Member B') && r.text.includes('Sample History Book') && /15\s*บาท/.test(r.text));

// --- Logout
r = await A.req('logout.php');
r = await A.req('my-loans.php');
ok('T26 GET logout.php does NOT log out (needs POST + token)', r.status === 200);
r = await A.req('logout.php', { method: 'POST', form: {} });
ok('T27 POST logout without token -> 403', r.status === 403);
t = await A.token('my-loans.php');
r = await A.req('logout.php', { method: 'POST', form: { csrf: t } });
const after = await A.req('my-loans.php');
ok('T28 POST logout with token logs out', r.loc.includes('logged_out') && after.status === 302 && after.loc.includes('login.php'));

// --- Files that must not leak
r = await anon.req('secret/config.example.php');
ok('T29 secret example file returns no PHP source', !r.text.includes('<?php'), 'status ' + r.status + ' (php -S ignores .htaccess; Apache would 403)');

// --- Added after the audit: stale role + login lockout
r = await S.req('overdue.php');
ok('T30 staff can open overdue list', r.status === 200, 'status ' + r.status);
sql("UPDATE lib_users SET role='member' WHERE email='s@t.test'");
r = await S.req('overdue.php');
ok('T31 demoted staff loses access at once (no re-login)', r.status === 403, 'status ' + r.status);
const L = client();
t = await L.token('login.php');
for (let i = 0; i < 5; i++) await L.req('login.php', { method: 'POST', form: { email: 'b@t.test', password: 'wrongpass' + i, csrf: t } });
r = await L.req('login.php', { method: 'POST', form: { email: 'b@t.test', password: 'password456', csrf: t } });
ok('T32 5 wrong tries lock login even with the right password', r.status === 429 && !r.loc, 'status ' + r.status);

console.log(`\n${pass} passed, ${fail} failed`);
sql("DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@t.test'; DELETE FROM lib_users WHERE email LIKE '%@t.test';");
