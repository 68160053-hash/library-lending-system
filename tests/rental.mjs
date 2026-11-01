// Tests for the rental backend: renting, staff shipping/returning, book CRUD, and attacks on them.
// Run ONLY against your own throwaway database/server (see README, "ทดสอบความปลอดภัย").
import { execFileSync } from 'node:child_process';

const BASE = process.env.BASE ?? 'http://127.0.0.1:8092/';
const MYSQL = process.env.MYSQL ?? 'C:/xampp/mysql/bin/mysql.exe';
if (!/sec|test/.test(process.env.DB_NAME ?? 'lib_sec')) throw new Error('refusing to run: DB_NAME must be a throwaway test database (name contains sec or test)');
const sql = (q) => execFileSync(MYSQL, ['-h', '127.0.0.1', '-P', process.env.DB_PORT ?? '3399', '-u', 'root', '--default-character-set=utf8mb4', '-N', '-B', process.env.DB_NAME ?? 'lib_sec', '-e', q]).toString().trim();

let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => { cond ? pass++ : fail++; console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  [' + extra + ']' : '')); };

function client() {
  const jar = {};
  const c = {
    async req(p, { method = 'GET', form, raw, headers = {} } = {}) {
      const h = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; '), ...headers };
      let body = raw;
      if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(BASE + p, { method, headers: h, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
      return { status: r.status, loc: r.headers.get('location') || '', text: await r.text() };
    },
    async token(p) { const r = await c.req(p); const m = r.text.match(/name="csrf" value="([0-9a-f]+)"/); return m ? m[1] : ''; },
    async register(name, email, pw) { const t = await c.token('register.php'); return c.req('register.php', { method: 'POST', form: { full_name: name, email, password: pw, password_confirm: pw, csrf: t } }); },
    async login(email, pw) { const t = await c.token('login.php'); return c.req('login.php', { method: 'POST', form: { email, password: pw, csrf: t } }); },
    async post(p, form, tokenPage = 'index.php') { const t = await c.token(tokenPage); return c.req(p, { method: 'POST', form: { ...form, csrf: t } }); },
  };
  return c;
}
const cleanup = () => sql("DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@r.test'; DELETE FROM lib_users WHERE email LIKE '%@r.test'; DELETE FROM lib_books WHERE isbn LIKE 'RT-%'; DELETE FROM lib_attempts;");
cleanup();

const A = client(), B = client(), S = client(), anon = client();
await A.register('Rita Renter', 'rita@r.test', 'rita-pass-123'); await B.register('Ben Renter', 'ben@r.test', 'ben-pass-1234'); await S.register('Sam Staff', 'sam@r.test', 'sam-pass-12345');
sql("UPDATE lib_users SET role='staff' WHERE email='sam@r.test'");
sql("DELETE FROM lib_attempts");
await A.login('rita@r.test', 'rita-pass-123'); await B.login('ben@r.test', 'ben-pass-1234'); await S.login('sam@r.test', 'sam-pass-12345');
const cat = sql("SELECT MIN(category_id) FROM lib_categories");
sql(`INSERT INTO lib_books (category_id, title, author, isbn, copies_total, copies_available, rent_per_day, deposit) VALUES (${cat}, 'Rental Test Book', 'Tester', 'RT-0001', 1, 1, 8, 160), (${cat}, 'Second Test Book', 'Tester', 'RT-0002', 3, 3, 5, 100)`);
const bookId = (isbn) => sql(`SELECT book_id FROM lib_books WHERE isbn='${isbn}'`);
const stock = (isbn) => sql(`SELECT copies_available, copies_total FROM lib_books WHERE isbn='${isbn}'`);
const b1 = bookId('RT-0001'), b2 = bookId('RT-0002');
const ADDR = '99 Test Road, Bang Saen, Chonburi 20131';

// ===== Renting =====
let r = await A.post('rent.php', { book_id: b1, ship_address: ADDR });
const loan1 = sql(`SELECT MAX(loan_id) FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email='rita@r.test'`);
ok('R1 member rents a book: rental saved as pending with rate, deposit and address copied from the book',
  r.loc.includes('rented=1') && sql(`SELECT CONCAT(status,'|',daily_rate,'|',deposit,'|',ship_address) FROM lib_loans WHERE loan_id=${loan1}`) === 'pending|8.00|160.00|' + ADDR);
ok('R2 renting takes one copy out of stock', stock('RT-0001') === '0\t1');
r = await B.post('rent.php', { book_id: b1, ship_address: ADDR });
ok('R3 last copy is gone: second renter is refused and stock stays 0', r.loc.includes('soldout') && stock('RT-0001') === '0\t1');
ok('R4 no rental row was created for the refused renter', sql(`SELECT COUNT(*) FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email='ben@r.test'`) === '0');

// race: 12 renters fight for the last copy of a fresh 1-copy book
sql(`INSERT INTO lib_books (category_id, title, author, isbn, copies_total, copies_available) VALUES (${cat}, 'Race Book', 'Tester', 'RT-0003', 1, 1)`);
const b3 = bookId('RT-0003');
const tk = await A.token('index.php');
await Promise.all(Array.from({ length: 12 }, () => A.req('rent.php', { method: 'POST', form: { book_id: b3, ship_address: ADDR, csrf: tk } })));
ok('R5 12 parallel rent requests for 1 copy -> exactly one rental, stock 0, never negative',
  sql(`SELECT COUNT(*) FROM lib_loans WHERE book_id=${b3}`) === '1' && stock('RT-0003') === '0\t1', 'rentals=' + sql(`SELECT COUNT(*) FROM lib_loans WHERE book_id=${b3}`));

for (const [n, form] of [['too short address', { book_id: b2, ship_address: 'short' }], ['empty address', { book_id: b2, ship_address: '' }], ['256-char address', { book_id: b2, ship_address: 'x'.repeat(256) }]]) {
  r = await A.post('rent.php', form);
  ok('R6 ' + n + ' is refused and stock untouched', r.loc.includes('rent_error') && stock('RT-0002') === '3\t3');
}
for (const bad of ['-1', '0', 'abc', '1.5', '99999999999', "1 OR 1=1"]) {
  r = await A.post('rent.php', { book_id: bad, ship_address: ADDR });
  ok('R7 rent with book_id=' + bad + ' is refused cleanly', (r.status === 400 || r.loc.includes('rent_error')) && stock('RT-0002') === '3\t3', 'status ' + r.status);
}
r = await A.post('rent.php', { book_id: '999999', ship_address: ADDR });
ok('R8 unknown book id is refused and nothing is created', r.loc.includes('rent_error') || r.status === 400);
r = await A.req('rent.php', { method: 'POST', form: { book_id: b2, ship_address: ADDR } });
ok('R9 rent without CSRF token -> 403', r.status === 403 && stock('RT-0002') === '3\t3');
r = await A.req('rent.php?book_id=' + b2 + '&ship_address=' + encodeURIComponent(ADDR));
ok('R10 rent via GET does nothing (405)', r.status === 405 && stock('RT-0002') === '3\t3');
r = await anon.req('rent.php', { method: 'POST', form: { book_id: b2, ship_address: ADDR, csrf: 'x' } });
ok('R11 anonymous rent does nothing', stock('RT-0002') === '3\t3');
r = await A.post('rent.php', { book_id: b2, ship_address: '<script>alert(1)</script> 12345 road', user_id: '1', status: 'returned', daily_rate: '0', deposit: '0' });
const loan2 = sql(`SELECT MAX(loan_id) FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email='rita@r.test'`);
ok('R12 mass assignment: user_id/status/daily_rate/deposit from the form are ignored', sql(`SELECT CONCAT(status,'|',daily_rate,'|',deposit,'|',(SELECT email FROM lib_users WHERE user_id=l.user_id)) FROM lib_loans l WHERE loan_id=${loan2}`) === 'pending|5.00|100.00|rita@r.test');
r = await A.req('my-loans.php');
ok('R13 stored XSS in the address is escaped where it is shown', !r.text.includes('<script>alert(1)') && r.text.includes('Rental Test Book'));

// ===== Staff desk =====
r = await S.req('rentals.php');
ok('S1 staff sees the pending list with the escaped address', r.status === 200 && r.text.includes('Rita Renter') && !r.text.includes('<script>alert(1)') && r.text.includes('&lt;script&gt;'));
for (const p of ['rentals.php', 'books.php', 'book-form.php']) { r = await A.req(p); ok('S2 member opening ' + p + ' -> 403', r.status === 403); r = await anon.req(p); ok('S3 anonymous opening ' + p + ' -> redirect to login', r.status === 302 && r.loc.includes('login')); }
r = await A.post('rental-action.php', { loan_id: loan1, action: 'ship' });
ok('S4 member cannot ship a rental (403, still pending)', r.status === 403 && sql(`SELECT status FROM lib_loans WHERE loan_id=${loan1}`) === 'pending');
r = await S.req('rental-action.php', { method: 'POST', form: { loan_id: loan1, action: 'ship' } });
ok('S5 staff action without CSRF token -> 403', r.status === 403 && sql(`SELECT status FROM lib_loans WHERE loan_id=${loan1}`) === 'pending');
r = await S.post('rental-action.php', { loan_id: loan1, action: 'receive' }, 'rentals.php');
ok('S6 cannot receive a rental that was never shipped (state machine)', r.loc.includes('error=1') && sql(`SELECT status FROM lib_loans WHERE loan_id=${loan1}`) === 'pending' && stock('RT-0001') === '0\t1');
for (const bad of ['delete', '', 'SHIP', "ship'; DROP TABLE lib_loans;--"]) { r = await S.post('rental-action.php', { loan_id: loan1, action: bad }, 'rentals.php'); ok('S7 unknown action "' + bad.slice(0, 14) + '" is refused', r.status === 400 && sql(`SELECT status FROM lib_loans WHERE loan_id=${loan1}`) === 'pending'); }
r = await S.post('rental-action.php', { loan_id: loan1, action: 'ship' }, 'rentals.php');
ok('S8 staff ships: status shipped, due date = today + 7', r.loc.includes('done=1') && sql(`SELECT status, due_date = CURDATE() + INTERVAL 7 DAY, shipped_at = CURDATE() FROM lib_loans WHERE loan_id=${loan1}`) === 'shipped\t1\t1');
r = await S.post('rental-action.php', { loan_id: loan1, action: 'ship' }, 'rentals.php');
ok('S9 shipping twice changes nothing', r.loc.includes('error=1'));

// member renew only after shipping
r = await A.post('renew.php', { loan_id: loan2 }, 'my-loans.php');
ok('S10 member cannot renew a rental that is still pending', r.loc.includes('renew_error') && sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${loan2}`) === '0');
r = await A.post('renew.php', { loan_id: loan1 }, 'my-loans.php');
ok('S11 member renews a shipped rental (+7 days)', r.loc.includes('renewed=1') && sql(`SELECT due_date = CURDATE() + INTERVAL 14 DAY FROM lib_loans WHERE loan_id=${loan1}`) === '1');

// overdue + deposit refund maths
sql(`UPDATE lib_loans SET due_date = CURDATE() - INTERVAL 4 DAY WHERE loan_id=${loan1}`);
r = await S.req('overdue.php');
ok('S12 overdue list shows the late fee = 4 days x 8 baht = 32.00', /32\.00\s*บาท/.test(r.text) && r.text.includes('Rita Renter'));
r = await S.post('rental-action.php', { loan_id: loan1, action: 'receive' }, 'rentals.php');
ok('S13 receive: returned, copy back on the shelf', r.loc.includes('done=1') && sql(`SELECT status, returned_at = CURDATE() FROM lib_loans WHERE loan_id=${loan1}`) === 'returned\t1' && stock('RT-0001') === '1\t1');
r = await S.req('rentals.php?status=returned');
ok('S14 deposit refund = 160 - 32 = 128.00 shown on the returned list', /128\.00/.test(r.text));
r = await S.post('rental-action.php', { loan_id: loan1, action: 'receive' }, 'rentals.php');
ok('S15 receiving twice does not add a second copy to the shelf', r.loc.includes('error=1') && stock('RT-0001') === '1\t1');
r = await S.req('overdue.php');
ok('S16 returned rental leaves the overdue list', !r.text.includes('Rental Test Book'));

// lost
r = await S.post('rental-action.php', { loan_id: loan2, action: 'ship' }, 'rentals.php');
r = await S.post('rental-action.php', { loan_id: loan2, action: 'lost' }, 'rentals.php');
ok('S17 lost book: status lost, total copies -1, free copies unchanged', r.loc.includes('done=1') && sql(`SELECT status FROM lib_loans WHERE loan_id=${loan2}`) === 'lost' && stock('RT-0002') === '2\t2');
r = await S.req('rentals.php?status=' + encodeURIComponent("pending' OR '1'='1"));
ok('S18 SQL injection in the status filter falls back to a safe default', r.status === 200 && !/SQL|mysqli/i.test(r.text));

// IDOR: Ben tries to act on Rita's rentals
const tB = await B.token('my-loans.php');
r = await B.req('renew.php', { method: 'POST', form: { loan_id: loan1, csrf: tB } });
ok("S19 another member cannot renew Rita's rental", sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${loan1}`) === '1');
r = await B.req('my-loans.php');
ok("S20 Ben's page shows none of Rita's rentals or address", !r.text.includes('Rental Test Book') && !r.text.includes(ADDR));

// ===== Book CRUD (staff) =====
const newBook = { title: 'Created In Test', author: 'Writer', isbn: 'RT-1001', category_id: cat, copies_total: '4', rent_per_day: '12.5', deposit: '250' };
r = await S.post('book-form.php', newBook, 'book-form.php');
ok('B1 staff adds a book: saved with 4 free copies, rate and deposit', r.loc.includes('saved=1') && sql(`SELECT CONCAT(copies_total,'|',copies_available,'|',rent_per_day,'|',deposit) FROM lib_books WHERE isbn='RT-1001'`) === '4|4|12.50|250.00');
r = await S.post('book-form.php', newBook, 'book-form.php');
ok('B2 duplicate ISBN is refused with a message', r.status === 200 && r.text.includes('ISBN นี้มีอยู่แล้ว') && sql(`SELECT COUNT(*) FROM lib_books WHERE isbn='RT-1001'`) === '1');
const bad = [
  ['empty title', { ...newBook, isbn: 'RT-1002', title: '' }], ['title 201 chars', { ...newBook, isbn: 'RT-1002', title: 'T'.repeat(201) }],
  ['isbn with quote', { ...newBook, isbn: "RT'1002" }], ['unknown category', { ...newBook, isbn: 'RT-1002', category_id: '99999' }],
  ['zero copies', { ...newBook, isbn: 'RT-1002', copies_total: '0' }], ['negative rent', { ...newBook, isbn: 'RT-1002', rent_per_day: '-5' }],
  ['text as price', { ...newBook, isbn: 'RT-1002', deposit: 'free' }], ['huge deposit', { ...newBook, isbn: 'RT-1002', deposit: '9999999' }],
];
for (const [n, form] of bad) { r = await S.post('book-form.php', form, 'book-form.php'); ok('B3 ' + n + ' is refused and nothing saved', r.status === 200 && !r.loc && sql(`SELECT COUNT(*) FROM lib_books WHERE isbn IN ('RT-1002',"RT'1002")`) === '0'); }
r = await S.post('book-form.php', { ...newBook, isbn: 'RT-1003', title: "x'),(1,'hack','h','RT-9999',1,1,0,0)-- ", author: '<img src=x onerror=alert(1)>' }, 'book-form.php');
ok('B4 SQL injection in the title is stored as plain text, no extra book created', sql(`SELECT COUNT(*) FROM lib_books WHERE isbn='RT-9999'`) === '0' && sql(`SELECT COUNT(*) FROM lib_books WHERE isbn='RT-1003'`) === '1');
r = await S.req('books.php');
ok('B5 stored XSS in the author is escaped on the book list', !r.text.includes('<img src=x onerror') && r.text.includes('&lt;img'));
const idNew = bookId('RT-1001');
r = await S.post('book-form.php?id=' + idNew, { ...newBook, book_id: idNew, copies_total: '6', title: 'Edited Title' }, 'book-form.php?id=' + idNew);
ok('B6 staff edits a book: raising the total raises the free copies by the same amount', r.loc.includes('saved=1') && sql(`SELECT CONCAT(title,'|',copies_total,'|',copies_available) FROM lib_books WHERE book_id=${idNew}`) === 'Edited Title|6|6');
await A.post('rent.php', { book_id: idNew, ship_address: ADDR });
await A.post('rent.php', { book_id: idNew, ship_address: ADDR });
r = await S.post('book-form.php?id=' + idNew, { ...newBook, book_id: idNew, copies_total: '1' }, 'book-form.php?id=' + idNew);
ok('B7 cannot cut the total below the copies currently rented out', r.status === 200 && r.text.includes('ไม่น้อยกว่า') && sql(`SELECT CONCAT(copies_total,'|',copies_available) FROM lib_books WHERE book_id=${idNew}`) === '6|4');
r = await S.post('book-form.php?id=99999999', { ...newBook, book_id: '99999999' }, 'book-form.php');
ok('B8 editing a book that does not exist -> 404', r.status === 404);
r = await A.post('book-form.php', { ...newBook, isbn: 'RT-2001' });
ok('B9 member cannot add a book', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_books WHERE isbn='RT-2001'`) === '0');
r = await S.req('book-form.php', { method: 'POST', form: { ...newBook, isbn: 'RT-2002' } });
ok('B10 staff add without CSRF token -> 403', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_books WHERE isbn='RT-2002'`) === '0');
r = await S.post('book-delete.php', { book_id: idNew }, 'books.php');
ok('B11 a book with rental history cannot be deleted', r.loc.includes('delete_error') && sql(`SELECT COUNT(*) FROM lib_books WHERE book_id=${idNew}`) === '1');
const idFree = bookId('RT-1003');
r = await A.post('book-delete.php', { book_id: idFree });
ok('B12 member cannot delete a book', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_books WHERE book_id=${idFree}`) === '1');
r = await S.req('book-delete.php?book_id=' + idFree);
ok('B13 delete via GET does nothing (405)', r.status === 405 && sql(`SELECT COUNT(*) FROM lib_books WHERE book_id=${idFree}`) === '1');
r = await S.post('book-delete.php', { book_id: idFree }, 'books.php');
ok('B14 staff deletes a book without history', r.loc.includes('deleted=1') && sql(`SELECT COUNT(*) FROM lib_books WHERE book_id=${idFree}`) === '0');


// ===== Cancel a pending rental (member) =====
sql(`INSERT INTO lib_books (category_id, title, author, isbn, copies_total, copies_available) VALUES (${cat}, 'Cancel Book', 'Tester', 'RT-3001', 2, 2)`);
const b4 = bookId('RT-3001');
await A.post('rent.php', { book_id: b4, ship_address: ADDR });
const loanC = sql(`SELECT MAX(loan_id) FROM lib_loans WHERE book_id=${b4}`);
ok('C1 rent took a copy (1 left)', stock('RT-3001') === '1	2');
r = await B.post('cancel.php', { loan_id: loanC }, 'my-loans.php');
ok("C2 another member cannot cancel Rita's rental", r.loc.includes('cancel_error') && sql(`SELECT COUNT(*) FROM lib_loans WHERE loan_id=${loanC}`) === '1' && stock('RT-3001') === '1	2');
r = await A.req('cancel.php', { method: 'POST', form: { loan_id: loanC } });
ok('C3 cancel without CSRF token -> 403', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_loans WHERE loan_id=${loanC}`) === '1');
r = await A.req('cancel.php?loan_id=' + loanC);
ok('C4 cancel via GET does nothing (405)', r.status === 405 && sql(`SELECT COUNT(*) FROM lib_loans WHERE loan_id=${loanC}`) === '1');
r = await A.post('cancel.php', { loan_id: loanC }, 'my-loans.php');
ok('C5 owner cancels a pending rental: row gone, copy back on the shelf', r.loc.includes('cancelled=1') && sql(`SELECT COUNT(*) FROM lib_loans WHERE loan_id=${loanC}`) === '0' && stock('RT-3001') === '2	2');
r = await A.post('cancel.php', { loan_id: loanC }, 'my-loans.php');
ok('C6 cancelling twice does not add a second copy to the shelf', r.loc.includes('cancel_error') && stock('RT-3001') === '2	2');
r = await A.post('cancel.php', { loan_id: loan1 }, 'my-loans.php');
ok('C7 a shipped/returned rental cannot be cancelled', r.loc.includes('cancel_error') && sql(`SELECT COUNT(*) FROM lib_loans WHERE loan_id=${loan1}`) === '1');
for (const bad of ['-1', 'abc', '1 OR 1=1']) { r = await A.post('cancel.php', { loan_id: bad }, 'my-loans.php'); ok('C8 cancel with loan_id=' + bad + ' is refused', r.status === 400); }

// ===== Admin: change roles =====
const AD = client(); await AD.register('Ada Admin', 'ada@r.test', 'ada-pass-12345');
sql("UPDATE lib_users SET role='admin' WHERE email='ada@r.test'"); sql('DELETE FROM lib_attempts');
await AD.login('ada@r.test', 'ada-pass-12345');
const uidOf = (mail) => sql(`SELECT user_id FROM lib_users WHERE email='${mail}'`);
const roleOf = (mail) => sql(`SELECT role FROM lib_users WHERE email='${mail}'`);
for (const [who, cl] of [['member', A], ['staff', S], ['anonymous', anon]]) { r = await cl.req('users.php'); ok('U1 ' + who + ' opening users.php is refused', r.status === 403 || (r.status === 302 && r.loc.includes('login'))); }
r = await AD.req('users.php');
ok('U2 admin sees the user list with escaped names', r.status === 200 && r.text.includes('Rita Renter') && !r.text.includes('$2y$'));
r = await S.post('user-role.php', { user_id: uidOf('ben@r.test'), role: 'admin' }, 'my-loans.php');
ok('U3 staff cannot make someone admin', r.status === 403 && roleOf('ben@r.test') === 'member');
r = await A.post('user-role.php', { user_id: uidOf('rita@r.test'), role: 'admin' }, 'my-loans.php');
ok('U4 a member cannot make themselves admin', r.status === 403 && roleOf('rita@r.test') === 'member');
r = await AD.req('user-role.php', { method: 'POST', form: { user_id: uidOf('ben@r.test'), role: 'staff' } });
ok('U5 role change without CSRF token -> 403', r.status === 403 && roleOf('ben@r.test') === 'member');
r = await AD.req('user-role.php?user_id=' + uidOf('ben@r.test') + '&role=staff');
ok('U6 role change via GET does nothing (405)', r.status === 405 && roleOf('ben@r.test') === 'member');
r = await AD.post('user-role.php', { user_id: uidOf('ben@r.test'), role: 'staff' }, 'users.php');
ok('U7 admin promotes a member to staff', r.loc.includes('saved=1') && roleOf('ben@r.test') === 'staff');
r = await B.req('rentals.php');
ok('U8 the promoted user gets the staff desk without logging in again', r.status === 200);
for (const bad of ['owner', '', 'ADMIN', "admin'; DROP TABLE lib_users;--", 'root']) { r = await AD.post('user-role.php', { user_id: uidOf('rita@r.test'), role: bad }, 'users.php'); ok('U9 unknown role "' + bad.slice(0, 12) + '" is refused', r.status === 400 && roleOf('rita@r.test') === 'member'); }
r = await AD.post('user-role.php', { user_id: uidOf('ada@r.test'), role: 'member' }, 'users.php');
ok('U10 an admin cannot demote themselves (never zero admins)', r.loc.includes('error=1') && roleOf('ada@r.test') === 'admin');
r = await AD.post('user-role.php', { user_id: '99999999', role: 'staff' }, 'users.php');
ok('U11 unknown user id changes nothing and does not crash', r.status < 500);
r = await AD.post('user-role.php', { user_id: uidOf('ben@r.test'), role: 'member' }, 'users.php');
r = await B.req('rentals.php');
ok('U12 demoted staff loses the staff desk at once', r.status === 403);
sql("UPDATE lib_users SET role='member' WHERE email='ada@r.test'");
r = await AD.req('users.php');
ok('U13 an admin demoted in the database loses admin pages at once', r.status === 403);

// ===== Shop page =====
r = await anon.req('index.php');
ok('P1 guest sees a login link instead of the rent form', r.text.includes('เข้าสู่ระบบเพื่อเช่า') && !r.text.includes('name="ship_address"'));
r = await A.req('index.php');
ok('P2 member sees the rent form with CSRF token and rent price on the shelf', r.text.includes('name="ship_address"') && r.text.includes('name="csrf"') && /เช่า ฿/.test(r.text));
r = await A.req('index.php?rent_error=' + encodeURIComponent('<script>alert(1)</script>'));
ok('P3 reflected rent_error value is never printed raw', !r.text.includes('<script>alert(1)'));

console.log(`\n${pass} passed, ${fail} failed`);
cleanup();
process.exit(fail ? 1 : 0);
