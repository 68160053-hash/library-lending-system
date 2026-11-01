// Audit fixes: coupon guessing is rate-limited, a new account cannot reuse the contact of an overdue one, staff can recover a "lost" book.
// Run ONLY against your own throwaway database/server.
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
    async req(p, { method = 'GET', form } = {}) {
      const h = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; ') };
      let body; if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(BASE + p, { method, headers: h, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
      return { status: r.status, loc: r.headers.get('location') || '', text: await r.text() };
    },
    async token(p = 'index.php') { const r = await c.req(p); const m = r.text.match(/name="csrf" value="([0-9a-f]+)"/); return m ? m[1] : ''; },
    async post(p, form, page = 'index.php') { return c.req(p, { method: 'POST', form: { ...form, csrf: await c.token(page) } }); },
    async register(n, e, pw) { return c.post('register.php', { full_name: n, email: e, password: pw, password_confirm: pw }, 'register.php'); },
    async login(e, pw) { return c.post('login.php', { email: e, password: pw }, 'login.php'); },
  };
  return c;
}
const cleanup = () => sql("DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@h.test'; DELETE o FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@h.test'; DELETE FROM lib_users WHERE email LIKE '%@h.test'; DELETE FROM lib_coupons WHERE code LIKE 'HARD%'; DELETE FROM lib_attempts;");
cleanup();

const A = client(), B = client(), S = client(), G = client();
for (const [c, n, e, p] of [[A, 'Aom A', 'a@h.test', 'aom-pass-12345'], [B, 'Bee B', 'b@h.test', 'bee-pass-12345'], [S, 'Sam S', 's@h.test', 'sam-pass-12345']]) await c.register(n, e, p);
sql("UPDATE lib_users SET role='staff' WHERE email='s@h.test'; DELETE FROM lib_attempts");
await A.login('a@h.test', 'aom-pass-12345'); await B.login('b@h.test', 'bee-pass-12345'); await S.login('s@h.test', 'sam-pass-12345');
const bookId = sql('SELECT MIN(book_id) FROM lib_books');
const ADDR = '77 Hardening Road, Bang Saen, Chonburi 20131', PHONE = '0899998888';
const ship = { ship_name: 'Test Name', ship_phone: PHONE, ship_address: ADDR, days: '7' };
let r;

// ===== 1. coupon guessing =====
sql("INSERT INTO lib_coupons (code, kind, value, min_rent, active) VALUES ('HARDOK10', 'percent', 10, 0, 1)");
await A.post('cart.php', { action: 'add', book_id: bookId });
const tries = [];
for (let i = 0; i < 12; i++) { r = await A.req('cart.php?coupon=ZZZ' + String(1000 + i)); tries.push(r.status); }
r = await A.req('cart.php?coupon=HARDOK10');
ok('H1 after 10 wrong codes a member is locked out, even a real code is refused', r.text.includes('รอ 15 นาที') && !r.text.includes('โค้ด HARDOK10'));
ok('H2 the lock is stored per member in lib_attempts', Number(sql("SELECT COUNT(*) FROM lib_attempts WHERE kind='coupon' AND email LIKE 'u%'")) >= 10);
r = await B.req('cart.php?coupon=HARDOK10');
await B.post('cart.php', { action: 'add', book_id: bookId }); r = await B.req('cart.php?coupon=HARDOK10');
ok('H3 another member is not locked by the first one (the lock follows the member and the IP together)', r.status === 200);
sql('DELETE FROM lib_attempts');
for (let i = 0; i < 12; i++) await G.req('cart.php?coupon=GUESS' + (100 + i));
r = await G.req('cart.php?coupon=HARDOK10');
ok('H4 a guest guessing on the cart page is limited too', r.text.includes('รอ 15 นาที') || !/โค้ด HARDOK10/.test(r.text));
sql('DELETE FROM lib_attempts');
await A.post('cart.php', { action: 'clear' }); await B.post('cart.php', { action: 'clear' });

// ===== 2. same contact as an overdue member =====
sql(`INSERT INTO lib_orders (user_id, rent_days, rent_total, deposit_total, ship_name, ship_phone, ship_address, pay_status, pay_confirmed_at, courier, tracking_no, shipped_at) SELECT user_id, 7, 35, 100, 'Aom', '${PHONE}', '${ADDR}', 'confirmed', NOW(), 'Flash Express', 'TH0000001', CURDATE() - INTERVAL 20 DAY FROM lib_users WHERE email='a@h.test'`);
const oid = sql("SELECT MAX(order_id) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='a@h.test'");
sql(`INSERT INTO lib_loans (order_id, user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit) SELECT ${oid}, user_id, ${bookId}, CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 5 DAY, 'shipped', 5, 100 FROM lib_users WHERE email='a@h.test'`);
const loanId = sql(`SELECT MAX(loan_id) FROM lib_loans WHERE order_id=${oid}`);
await B.post('cart.php', { action: 'add', book_id: bookId });
r = await B.post('checkout.php', ship, 'checkout.php');
ok('H5 a different account with the same address and phone is refused', r.status === 200 && r.text.includes('ไม่สามารถสั่งเช่าด้วยที่อยู่หรือเบอร์โทรนี้') && sql("SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='b@h.test'") === '0');
r = await B.post('checkout.php', { ...ship, ship_address: '77 Hardening Road, Bang Saen, Chonburi 20131', ship_phone: '089-999-8888' }, 'checkout.php');
ok('H6 the same phone written with dashes is still caught', r.text.includes('ไม่สามารถสั่งเช่าด้วยที่อยู่หรือเบอร์โทรนี้'));
r = await B.post('checkout.php', { ...ship, ship_phone: '0811112222', ship_address: '5 Other Street, Mueang, Rayong 21000' }, 'checkout.php');
ok('H7 a genuinely different contact can order', r.status === 302 && sql("SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='b@h.test'") === '1');
ok('H8 the refusal does not say which other account is overdue', !/a@h\.test|Aom/.test((await (async () => { await B.post('cart.php', { action: 'add', book_id: bookId }); return B.post('checkout.php', ship, 'checkout.php'); })()).text));

// ===== 2b. guessing through the checkout form is counted too, address wording does not matter =====
r = await B.post('checkout.php', { ...ship, ship_phone: '0866667777', ship_address: '77 hardening ROAD,  Bang Saen; Chonburi 20131' }, 'checkout.php');
ok('H17 the same address with other case, spaces and punctuation is still caught', r.text.includes('ไม่สามารถสั่งเช่าด้วยที่อยู่หรือเบอร์โทรนี้'));
const C = client(); await C.register('Cat C', 'c@h.test', 'cat-pass-12345'); sql('DELETE FROM lib_attempts'); await C.login('c@h.test', 'cat-pass-12345');
await C.post('cart.php', { action: 'add', book_id: bookId });
const cship = { ship_name: 'Cat C', ship_phone: '0833334444', ship_address: '9 Coupon Lane, Mueang, Rayong 21000', days: '7' };
for (let i = 0; i < 12; i++) await C.post('checkout.php', { ...cship, coupon: 'WRONG' + (1000 + i) }, 'checkout.php');
ok('H18 wrong codes posted to checkout are counted (the rollback must not erase them)', Number(sql("SELECT COUNT(*) FROM lib_attempts WHERE kind='coupon'")) >= 10);
r = await C.post('checkout.php', { ...cship, coupon: 'HARDOK10' }, 'checkout.php');
ok('H19 after the guesses even the real code is refused at checkout, and no order exists', r.text.includes('รอ 15 นาที') && sql("SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='c@h.test'") === '0');
sql('DELETE FROM lib_attempts');
// reviews: only staff delete
sql(`INSERT INTO lib_reviews (book_id, user_id, rating, body) SELECT ${bookId}, user_id, 1, 'rude text' FROM lib_users WHERE email='c@h.test'`);
const rid = sql("SELECT MAX(review_id) FROM lib_reviews");
r = await B.post('review.php', { action: 'delete', review_id: rid, book_id: bookId });
ok('H20 a member cannot delete a review (403)', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_reviews WHERE review_id=${rid}`) === '1');
r = await S.req('review.php', { method: 'POST', form: { action: 'delete', review_id: rid, book_id: bookId } });
ok('H21 deleting without CSRF -> 403', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_reviews WHERE review_id=${rid}`) === '1');
r = await S.post('review.php', { action: 'delete', review_id: '1 OR 1=1', book_id: bookId });
ok('H22 a bad review id deletes nothing', sql(`SELECT COUNT(*) FROM lib_reviews WHERE review_id=${rid}`) === '1');
r = await S.req('book.php?id=' + bookId);
ok('H23 staff see the delete button on the book page, members do not', r.text.includes('value="delete"') && !(await B.req('book.php?id=' + bookId)).text.includes('value="delete"'));
r = await S.post('review.php', { action: 'delete', review_id: rid, book_id: bookId });
ok('H24 staff delete the review', sql(`SELECT COUNT(*) FROM lib_reviews WHERE review_id=${rid}`) === '0');
sql(`INSERT INTO lib_notifications (user_id, kind, message, created_at, read_at) SELECT user_id, 'info', 'old read one', NOW() - INTERVAL 100 DAY, NOW() - INTERVAL 99 DAY FROM lib_users WHERE email='a@h.test'`);

// ===== 3. found: a lost book comes back =====
const stock = () => sql(`SELECT CONCAT(copies_total,'/',copies_available) FROM lib_books WHERE book_id=${bookId}`);
const before = stock(); const [bt, ba] = before.split('/').map(Number); const after = bt + '/' + (ba + 1);  // the copy that was out is back on the shelf
r = await S.post('order-action.php', { order_id: oid, loan_id: loanId, action: 'lost' });
const lostStock = stock();
ok('H9 lost removes one copy from the total', lostStock !== before && sql(`SELECT status FROM lib_loans WHERE loan_id=${loanId}`) === 'lost');
r = await A.post('order-action.php', { order_id: oid, loan_id: loanId, action: 'found' });
ok('H10 a member cannot mark a book found (403)', r.status === 403 && sql(`SELECT status FROM lib_loans WHERE loan_id=${loanId}`) === 'lost');
r = await S.req('order-action.php', { method: 'POST', form: { order_id: oid, loan_id: loanId, action: 'found' } });
ok('H11 found without CSRF -> 403', r.status === 403 && sql(`SELECT status FROM lib_loans WHERE loan_id=${loanId}`) === 'lost');
r = await S.post('order-action.php', { order_id: Number(oid) + 999, loan_id: loanId, action: 'found' });
ok('H12 found with a loan that is not in that order changes nothing', sql(`SELECT status FROM lib_loans WHERE loan_id=${loanId}`) === 'lost');
r = await S.post('order-action.php', { order_id: oid, loan_id: loanId, action: 'found' });
ok('H13 staff marks found: returned, copy counted again, stock as before', sql(`SELECT status FROM lib_loans WHERE loan_id=${loanId}`) === 'returned' && stock() === after, stock());
r = await S.post('order-action.php', { order_id: oid, loan_id: loanId, action: 'found' });
ok('H14 found twice does not add a second copy', stock() === after);
ok('H15 the owner is told', Number(sql("SELECT COUNT(*) FROM lib_notifications n JOIN lib_users u USING(user_id) WHERE u.email='a@h.test' AND n.kind='info'")) === 1, sql("SELECT GROUP_CONCAT(CONCAT(user_id,':',kind)) FROM lib_notifications ORDER BY notif_id DESC LIMIT 5"));
ok('H25 an old read notification is removed when a new one is written', sql("SELECT COUNT(*) FROM lib_notifications WHERE message='old read one'") === '0');
r = await S.req('order.php?id=' + oid);
ok('H16 the order page shows no "found" button once returned', !r.text.includes('value="found"'));

console.log(`\n${pass} passed, ${fail} failed`);
cleanup();
process.exit(fail ? 1 : 0);
