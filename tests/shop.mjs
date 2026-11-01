// End-to-end tests of the shop: catalogue, book page, cart, checkout, orders, payment, shipping, returns,
// staff desk, book CRUD, roles and profile. Includes attacks on each step.
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
    async req(p, { method = 'GET', form, headers = {} } = {}) {
      const h = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; '), ...headers };
      let body;
      if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(BASE + p, { method, headers: h, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
      return { status: r.status, loc: r.headers.get('location') || '', text: await r.text() };
    },
    async token(p = 'index.php') { const r = await c.req(p); const m = r.text.match(/name="csrf" value="([0-9a-f]+)"/); return m ? m[1] : ''; },
    async post(p, form, page = 'index.php') { return c.req(p, { method: 'POST', form: { ...form, csrf: await c.token(page) } }); },
    async register(name, email, pw) { return c.post('register.php', { full_name: name, email, password: pw, password_confirm: pw }, 'register.php'); },
    async login(email, pw) { return c.post('login.php', { email, password: pw }, 'login.php'); },
  };
  return c;
}
const cleanup = () => sql("DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@s.test'; DELETE o FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@s.test'; DELETE FROM lib_users WHERE email LIKE '%@s.test'; DELETE FROM lib_books WHERE isbn LIKE 'ST-%'; DELETE FROM lib_attempts;");
cleanup();

const A = client(), B = client(), S = client(), AD = client(), anon = client();
await A.register('Rita Renter', 'rita@s.test', 'rita-pass-123'); await B.register('Ben Renter', 'ben@s.test', 'ben-pass-1234');
await S.register('Sam Staff', 'sam@s.test', 'sam-pass-12345'); await AD.register('Ada Admin', 'ada@s.test', 'ada-pass-12345');
sql("UPDATE lib_users SET role='staff' WHERE email='sam@s.test'; UPDATE lib_users SET role='admin' WHERE email='ada@s.test'; DELETE FROM lib_attempts");
await A.login('rita@s.test', 'rita-pass-123'); await B.login('ben@s.test', 'ben-pass-1234'); await S.login('sam@s.test', 'sam-pass-12345'); await AD.login('ada@s.test', 'ada-pass-12345');
const cat = sql('SELECT MIN(category_id) FROM lib_categories');
const mk = (isbn, title, total, rate, dep) => sql(`INSERT INTO lib_books (category_id, title, author, isbn, description, copies_total, copies_available, rent_per_day, deposit) VALUES (${cat}, '${title}', 'Tester', '${isbn}', 'desc <b>x</b>', ${total}, ${total}, ${rate}, ${dep})`);
mk('ST-1', 'Shop Book One', 3, 8, 160); mk('ST-2', 'Shop Book Two', 3, 5, 100); mk('ST-3', 'Shop Book Three', 1, 10, 200); mk('ST-4', 'Shop Book Four', 2, 6, 120);
mk('ST-5', 'Shop Book Five', 2, 6, 120); mk('ST-6', 'Shop Book Six', 2, 6, 120); mk('ST-7', 'Shop Book Seven', 5, 6, 120);
const bid = (isbn) => sql(`SELECT book_id FROM lib_books WHERE isbn='${isbn}'`);
const left = (isbn) => sql(`SELECT CONCAT(copies_available,'/',copies_total) FROM lib_books WHERE isbn='${isbn}'`);
const [b1, b2, b3, b4, b5, b6, b7] = ['ST-1', 'ST-2', 'ST-3', 'ST-4', 'ST-5', 'ST-6', 'ST-7'].map(bid);
const ADDR = '99 Test Road, Bang Saen, Chonburi 20131', PHONE = '0812345678';
const ship = { ship_name: 'Rita R', ship_phone: PHONE, ship_address: ADDR };
const lastOrder = (mail) => sql(`SELECT MAX(order_id) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='${mail}'`);
const ord = (id, col) => sql(`SELECT ${col} FROM lib_orders WHERE order_id=${id}`);
let r;

// ===== Catalogue and book page =====
r = await anon.req('index.php');
ok('P1 catalogue shows books with prices, 12 cards on page 1', r.status === 200 && (r.text.match(/class="book-card"/g) || []).length === 12 && /฿\d+<small>\/วัน/.test(r.text));
r = await anon.req('index.php?q=' + encodeURIComponent('Shop Book Three'));
ok('P2 search finds the book', r.text.includes('Shop Book Three') && !r.text.includes('Shop Book One'));
r = await anon.req('index.php?q=' + encodeURIComponent('%'));
ok('P3 a "%" typed in the search is escaped (does not match every book)', r.text.includes('ไม่พบหนังสือที่ตรงกัน'));
r = await anon.req('index.php?category=' + cat + '&avail=1&sort=price_asc&q=Shop+Book');
ok('P4 category + availability + sort filters work together', r.status === 200 && r.text.includes('Shop Book'));
r = await anon.req('index.php?sort=price_asc&q=Shop+Book'); const prices = [...r.text.matchAll(/class="price">฿(\d+)/g)].map((m) => +m[1]);
ok('P5 sort by price ascending is really ascending', prices.length > 3 && prices.every((v, i) => i === 0 || prices[i - 1] <= v), prices.join(','));
for (const evil of ["index.php?sort=b.book_id;DROP TABLE lib_books", "index.php?category=1%20OR%201=1", "index.php?page=-5", "index.php?page=999999", "index.php?q[]=x&category[]=1&sort[]=x", "index.php?q=" + encodeURIComponent("' OR SLEEP(3)-- ")]) {
  const t0 = performance.now(); r = await anon.req(evil);
  ok('P6 catalogue survives ' + evil.slice(10, 50), r.status === 200 && !/SQL|mysqli|Warning/i.test(r.text) && performance.now() - t0 < 2500);
}
ok('P7 books table still exists after the injection attempts', Number(sql('SELECT COUNT(*) FROM lib_books')) > 50);
r = await anon.req('index.php?q=' + encodeURIComponent('"><script>alert(1)</script>'));
ok('P8 the search text is escaped where it is printed back', !r.text.includes('<script>alert(1)') && r.text.includes('&lt;script&gt;'));
r = await anon.req('book.php?id=' + b1);
ok('P9 book page shows price table for 7/14/30 days and escapes the description', r.status === 200 && r.text.includes('฿56.00') && r.text.includes('฿112.00') && r.text.includes('฿240.00') && r.text.includes('&lt;b&gt;x&lt;/b&gt;') && !r.text.includes('<b>x</b>'));
for (const bad of ['book.php', 'book.php?id=0', 'book.php?id=abc', 'book.php?id=99999999', "book.php?id=1' OR '1'='1", 'book.php?id[]=1']) { r = await anon.req(bad); ok('P10 ' + bad + ' -> 404, no error text', r.status === 404 && !/SQL|Warning/i.test(r.text)); }

// ===== Cart =====
r = await A.post('cart.php', { action: 'add', book_id: b1, then: 'cart' });
ok('C1 add to cart (member)', r.loc.includes('cart.php'));
r = await A.req('cart.php'); ok('C2 cart lists the book with totals for 7 days (56 + 160)', r.text.includes('Shop Book One') && r.text.includes('฿216.00'));
r = await A.req('cart.php?days=30'); ok('C3 30 days: 240 + 160 = 400', r.text.includes('฿400.00'));
r = await A.req('cart.php?days=999'); ok('C4 invalid days falls back to 7', r.text.includes('฿216.00'));
r = await A.req('cart.php', { method: 'POST', form: { action: 'add', book_id: b2 } }); ok('C5 add without CSRF token -> 403, cart unchanged', r.status === 403 && !(await A.req('cart.php')).text.includes('Shop Book Two'));
r = await A.req('cart.php?action=add&book_id=' + b2); ok('C6 add via GET does nothing', !r.text.includes('Shop Book Two'));
await A.post('cart.php', { action: 'add', book_id: b1 }); r = await A.req('cart.php'); ok('C7 the same book twice stays one line', (r.text.match(/Shop Book One/g) || []).length >= 1 && (r.text.match(/ลบ|เอาออก/g) || []).length === 1);
for (const bad of ['0', '-3', 'abc', '99999999', '1 OR 1=1']) { r = await A.post('cart.php', { action: 'add', book_id: bad }); ok('C8 add book_id=' + bad + ' is refused without crash', r.status < 500); }
r = await A.post('cart.php', { action: 'add', book_id: b2, back: 'https://evil.example/' }); ok('C9 open redirect through "back" is ignored', r.status === 302 && !r.loc.includes('evil'));
r = await A.post('cart.php', { action: 'add', book_id: b2, back: 'index.php?q=a%20b&page=2' }); ok('C10 a safe catalogue address in "back" is used', r.loc === 'index.php?q=a%20b&page=2');
for (const b of [b4, b5, b6, b7]) await A.post('cart.php', { action: 'add', book_id: b });
r = await A.req('cart.php'); ok('C11 cart holds at most 5 books', (r.text.match(/aria-label="ปกหนังสือ|class="cover/g) || []).length > 0 && !r.text.includes('Shop Book Seven'));
r = await A.post('cart.php', { action: 'remove', book_id: b2 }); r = await A.req('cart.php'); ok('C12 remove takes one book out', !r.text.includes('Shop Book Two'));
r = await A.post('cart.php', { action: 'clear' }); r = await A.req('cart.php'); ok('C13 clear empties the cart', r.text.includes('ตะกร้ายังว่างอยู่'));
sql(`UPDATE lib_books SET copies_available=0 WHERE book_id=${b4}`); r = await A.post('cart.php', { action: 'add', book_id: b4 }); r = await A.req('cart.php');
ok('C14 a sold-out book cannot be added', !r.text.includes('Shop Book Four')); sql(`UPDATE lib_books SET copies_available=copies_total WHERE book_id=${b4}`);

// ===== Checkout =====
r = await anon.req('checkout.php'); ok('K1 guest at checkout -> login', r.status === 302 && r.loc.includes('login'));
r = await A.req('checkout.php'); ok('K2 empty cart at checkout -> back to cart', r.status === 302 && r.loc.includes('cart.php'));
await A.post('cart.php', { action: 'add', book_id: b1 }); await A.post('cart.php', { action: 'add', book_id: b2 });
const bad = [['short name', { ...ship, ship_name: 'R' }], ['letters in phone', { ...ship, ship_phone: 'call-me-maybe' }], ['short address', { ...ship, ship_address: 'near 7-11' }], ['255+ address', { ...ship, ship_address: 'x'.repeat(256) }], ['days 5', { ...ship, days: '5' }], ['days missing', { ...ship, days: '' }]];
for (const [n, f] of bad) { r = await A.post('checkout.php', { days: '7', ...f }, 'checkout.php'); ok('K3 ' + n + ' is refused, no order, stock untouched', r.status === 200 && !r.loc && lastOrder('rita@s.test') === 'NULL' && left('ST-1') === '3/3'); }
r = await A.post('checkout.php', { ...ship, days: '14' }, 'checkout.php');
const o1 = lastOrder('rita@s.test');
ok('K4 valid checkout creates order and goes to its page', r.status === 302 && r.loc === 'order.php?id=' + o1);
ok('K5 totals: (8+5) x 14 = 182 rent, 260 deposit', ord(o1, "CONCAT(rent_total,'|',deposit_total,'|',rent_days,'|',pay_status,'|',status)") === '182.00|260.00|14|unpaid|open');
ok('K6 one rental row per book, pending, rate+deposit copied, due = today + 14', sql(`SELECT COUNT(*), SUM(status='pending'), SUM(due_date=CURDATE()+INTERVAL 14 DAY), SUM(daily_rate>0) FROM lib_loans WHERE order_id=${o1}`) === '2\t2\t2\t2');
ok('K7 stock went down by one for each book', left('ST-1') === '2/3' && left('ST-2') === '2/3');
ok('K8 cart is empty after ordering', (await A.req('cart.php')).text.includes('ตะกร้ายังว่างอยู่'));
ok('K9 phone and address were saved to the profile', sql("SELECT CONCAT(phone,'|',default_address) FROM lib_users WHERE email='rita@s.test'") === PHONE + '|' + ADDR);
sql(`UPDATE lib_books SET rent_per_day=99, deposit=999 WHERE book_id=${b1}`);
ok('K10 a later price change does not touch the existing order', ord(o1, 'rent_total') === '182.00'); sql(`UPDATE lib_books SET rent_per_day=8, deposit=160 WHERE book_id=${b1}`);
// race for the last copy of Book Three
const racers = [A, B]; for (const c of racers) { await c.post('cart.php', { action: 'add', book_id: b3 }); }
const tk = [await A.token('checkout.php'), await B.token('checkout.php')];
const res = await Promise.all([A, B, A, B, A, B].map((c, i) => c.req('checkout.php', { method: 'POST', form: { ...ship, days: '7', csrf: tk[i % 2] } })));
ok('K11 race for the last copy: exactly one order contains it, stock 0 and never negative', sql(`SELECT COUNT(*) FROM lib_loans WHERE book_id=${b3}`) === '1' && left('ST-3') === '0/1', 'rows=' + sql(`SELECT COUNT(*) FROM lib_loans WHERE book_id=${b3}`));
// all-or-nothing: Ben has Book Three still in the cart; add a free book too and sell Three out
await B.post('cart.php', { action: 'clear' }); await B.post('cart.php', { action: 'add', book_id: b5 }); await B.post('cart.php', { action: 'add', book_id: b6 });
sql(`UPDATE lib_books SET copies_available=0 WHERE book_id=${b6}`);
r = await B.post('checkout.php', { ...ship, days: '7' }, 'checkout.php');
ok('K12 one sold-out book cancels the whole checkout: the other book is NOT taken from stock', r.status === 200 && left('ST-5') === '2/2'); sql(`UPDATE lib_books SET copies_available=copies_total WHERE book_id=${b6}`);
await A.post('cart.php', { action: 'add', book_id: b4 });
r = await A.req('checkout.php', { method: 'POST', form: { ...ship, days: '7' } }); await A.post('cart.php', { action: 'clear' }); ok('K13 checkout POST without CSRF token -> 403', r.status === 403);
// the unpaid limit
const L = client(); await L.register('Lim Limit', 'lim@s.test', 'lim-pass-12345'); sql('DELETE FROM lib_attempts'); await L.login('lim@s.test', 'lim-pass-12345');
for (let i = 0; i < 4; i++) { await L.post('cart.php', { action: 'add', book_id: b7 }); await L.post('checkout.php', { ...ship, days: '7' }, 'checkout.php'); }
ok('K14 at most 3 unpaid orders at the same time', sql("SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='lim@s.test'") === '3');

// parallel checkouts by one member must not get past the limit of 3 unpaid orders
const P = client(); await P.register('Par Allel', 'par@s.test', 'par-pass-12345'); sql('DELETE FROM lib_attempts'); await P.login('par@s.test', 'par-pass-12345');
const Ps = [P]; for (let i = 0; i < 7; i++) { const x = client(); await x.login('par@s.test', 'par-pass-12345'); Ps.push(x); }   // one member, 8 separate sessions
const tps = []; for (const x of Ps) { await x.post('cart.php', { action: 'add', book_id: b7 }); tps.push(await x.token('checkout.php')); }
await Promise.all(Ps.map((x, i) => x.req('checkout.php', { method: 'POST', form: { ...ship, days: '7', csrf: tps[i] } })));
ok('K15 8 parallel checkouts by one member -> at most 3 unpaid orders', Number(sql("SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='par@s.test'")) <= 3, sql("SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='par@s.test'"));

// ===== Orders: pages and access =====
r = await A.req('order.php?id=' + o1);
ok('O1 owner sees the order: stepper, payment box with bank info, report form', r.status === 200 && r.text.includes('stepper') && r.text.includes('โอนเงินมาที่') && r.text.includes('name="pay_note"') && r.text.includes('฿442.00'));
r = await B.req('order.php?id=' + o1); ok("O2 another member gets 404 for Rita's order", r.status === 404);
r = await anon.req('order.php?id=' + o1); ok('O3 guest -> login', r.status === 302 && r.loc.includes('login'));
r = await S.req('order.php?id=' + o1); ok('O4 staff can open any order and sees the confirm button', r.status === 200 && r.text.includes('ยืนยันได้รับเงินแล้ว'));
for (const badId of ['0', 'abc', '99999999', "1 OR 1=1"]) { r = await A.req('order.php?id=' + encodeURIComponent(badId)); ok('O5 order.php?id=' + badId + ' -> 404', r.status === 404); }
r = await A.req('my-orders.php'); ok('O6 my-orders lists own orders only', r.text.includes(order_no(o1)) && !r.text.includes(order_no(sql("SELECT MIN(order_id) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='lim@s.test'"))));
function order_no(id) { return '#' + String(id).padStart(5, '0'); }

// ===== Payment =====
const act = (c, form, page) => c.post('order-action.php', form, page ?? 'index.php');
r = await B.post('order-action.php', { order_id: o1, action: 'report_pay' }); ok("O7 another member cannot report payment on Rita's order", ord(o1, 'pay_status') === 'unpaid');
r = await A.req('order-action.php', { method: 'POST', form: { order_id: o1, action: 'report_pay' } }); ok('O8 report without CSRF -> 403', r.status === 403 && ord(o1, 'pay_status') === 'unpaid');
r = await A.req('order-action.php?order_id=' + o1 + '&action=report_pay'); ok('O9 report via GET does nothing (405)', r.status === 405);
r = await act(A, { order_id: o1, action: 'confirm_pay' }); ok('O10 a member cannot confirm their own payment (403)', r.status === 403 && ord(o1, 'pay_status') === 'unpaid');
r = await act(A, { order_id: o1, action: 'report_pay', pay_note: '<script>alert(1)</script> ' + 'x'.repeat(200), pay_status: 'confirmed', status: 'cancelled', rent_total: '0' });
ok('O11 payment report: note cut to 80 chars, extra fields (pay_status, rent_total) ignored', ord(o1, 'pay_status') === 'reported' && ord(o1, 'LENGTH(pay_note)') === '80' && ord(o1, 'rent_total') === '182.00' && ord(o1, 'status') === 'open');
r = await A.req('order.php?id=' + o1); ok('O12 the note is escaped, status says waiting for check', !r.text.includes('<script>alert(1)') && r.text.includes('รอตรวจสอบการโอน'));
r = await act(A, { order_id: o1, action: 'report_pay' }); ok('O13 reporting twice changes nothing', ord(o1, 'pay_status') === 'reported');
r = await act(S, { order_id: o1, action: 'ship', courier: 'Flash Express', tracking_no: 'TH123456789' }); ok('O14 cannot ship before payment is confirmed', ord(o1, "IFNULL(shipped_at,'none')") === 'none');
r = await S.req('order-action.php', { method: 'POST', form: { order_id: o1, action: 'confirm_pay' } }); ok('O15 staff confirm without CSRF -> 403', r.status === 403 && ord(o1, 'pay_status') === 'reported');
r = await act(S, { order_id: o1, action: 'confirm_pay' }); ok('O16 staff confirms the money: confirmed with a timestamp', ord(o1, "CONCAT(pay_status,'|',pay_confirmed_at IS NOT NULL)") === 'confirmed|1');

// ===== Shipping =====
for (const [n, f] of [['no tracking', { courier: 'Flash Express', tracking_no: '' }], ['short tracking', { courier: 'Flash Express', tracking_no: '123' }], ['tracking with spaces/quotes', { courier: 'Flash Express', tracking_no: "AB 12'; DROP" }], ['unknown courier', { courier: 'Pigeon Post', tracking_no: 'TH123456789' }]]) {
  r = await act(S, { order_id: o1, action: 'ship', ...f }); ok('O17 ship with ' + n + ' is refused', ord(o1, "IFNULL(shipped_at,'none')") === 'none');
}
r = await act(A, { order_id: o1, action: 'ship', courier: 'Flash Express', tracking_no: 'TH123456789' }); ok('O18 a member cannot ship (403)', r.status === 403 && ord(o1, "IFNULL(shipped_at,'none')") === 'none');
r = await act(S, { order_id: o1, action: 'ship', courier: 'Flash Express', tracking_no: 'TH123456789' });
ok('O19 staff ships: tracking saved, shipped today', ord(o1, "CONCAT(courier,'|',tracking_no,'|',shipped_at=CURDATE())") === 'Flash Express|TH123456789|1');
ok('O20 shipping starts the rental days: both books shipped, due = today + 14', sql(`SELECT SUM(status='shipped'), SUM(due_date=CURDATE()+INTERVAL 14 DAY) FROM lib_loans WHERE order_id=${o1}`) === '2\t2');
r = await act(S, { order_id: o1, action: 'ship', courier: 'Kerry Express', tracking_no: 'KERRY99999' }); ok('O21 shipping twice changes nothing (tracking stays)', ord(o1, 'tracking_no') === 'TH123456789');
r = await A.req('order.php?id=' + o1); ok('O22 the owner sees the tracking number and "อยู่ระหว่างเช่า"', r.text.includes('TH123456789') && r.text.includes('อยู่ระหว่างเช่า'));
r = await A.post('order-action.php', { order_id: o1, action: 'cancel' }); ok('O23 a shipped order cannot be cancelled', ord(o1, 'status') === 'open');

// ===== Renew, late fees, returns =====
const [l1, l2] = sql(`SELECT loan_id FROM lib_loans WHERE order_id=${o1} ORDER BY loan_id`).split('\n');
r = await B.post('renew.php', { loan_id: l1 }, 'index.php'); ok("O24 another member cannot renew Rita's book", sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${l1}`) === '0');
r = await A.post('renew.php', { loan_id: l1 });
ok('O25 owner renews once: +7 days, back on the order page', r.loc === 'order.php?id=' + o1 && sql(`SELECT renew_count, due_date = CURDATE() + INTERVAL 21 DAY FROM lib_loans WHERE loan_id=${l1}`) === '1\t1');
r = await A.post('renew.php', { loan_id: l1 }); ok('O26 a second renewal is refused', sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${l1}`) === '1');
r = await A.req('renew.php?loan_id=' + l2); ok('O27 renew via GET -> 405', r.status === 405);
sql(`UPDATE lib_loans SET due_date = CURDATE() - INTERVAL 4 DAY WHERE loan_id=${l2}`);
r = await A.post('renew.php', { loan_id: l2 }); ok('O28 an overdue book cannot be renewed', sql(`SELECT renew_count FROM lib_loans WHERE loan_id=${l2}`) === '0');
r = await S.req('overdue.php'); ok('O29 staff overdue list shows the late fee 4 days x 5 = 20.00 and a link to the order', /20\.00/.test(r.text) && r.text.includes('order.php?id=' + o1));
r = await A.req('order.php?id=' + o1); ok('O30 the owner sees "เกิน 4 วัน" on the late book', r.text.includes('เกิน 4 วัน'));
r = await act(A, { order_id: o1, loan_id: l2, action: 'receive' }); ok('O31 a member cannot mark a return (403)', r.status === 403 && sql(`SELECT status FROM lib_loans WHERE loan_id=${l2}`) === 'shipped');
r = await act(S, { order_id: o1, loan_id: l2, action: 'receive' });
ok('O32 staff receives the late book: returned, copy back on the shelf', sql(`SELECT status, returned_at=CURDATE() FROM lib_loans WHERE loan_id=${l2}`) === 'returned\t1' && left('ST-2') === '3/3');
r = await act(S, { order_id: o1, loan_id: l2, action: 'receive' }); ok('O33 receiving twice does not add a second copy', left('ST-2') === '3/3');
r = await A.req('order.php?id=' + o1); ok('O34 deposit refund = 100 - 20 = 80.00 is shown', r.text.includes('คืนให้ ฿80.00'));
r = await act(S, { order_id: Number(o1) + 1, loan_id: l1, action: 'receive' }); ok('O35 a loan id from a different order is not touched', sql(`SELECT status FROM lib_loans WHERE loan_id=${l1}`) === 'shipped');
r = await act(S, { order_id: o1, loan_id: l1, action: 'lost' }); ok('O36 lost: status lost, total copies -1, free copies unchanged', sql(`SELECT status FROM lib_loans WHERE loan_id=${l1}`) === 'lost' && left('ST-1') === '2/2');
r = await A.req('order.php?id=' + o1); ok('O37 lost book: deposit is not refunded; order is finished', r.text.includes('ไม่คืนมัดจำ') && r.text.includes('เสร็จสิ้น'));
for (const bad of ['delete', '', 'SHIP', "ship'; DROP TABLE lib_loans;--"]) { r = await act(S, { order_id: o1, action: bad }); ok('O38 unknown action "' + bad.slice(0, 12) + '" refused', r.status === 400); }

// ===== Cancel and expiry =====
await A.post('cart.php', { action: 'clear' }); await A.post('cart.php', { action: 'add', book_id: b5 }); await A.post('cart.php', { action: 'add', book_id: b6 });
await A.post('checkout.php', { ...ship, days: '7' }, 'checkout.php'); const o2 = lastOrder('rita@s.test');
ok('X1 second order took two copies', left('ST-5') === '1/2' && left('ST-6') === '1/2');
r = await B.post('order-action.php', { order_id: o2, action: 'cancel' }); ok("X2 another member cannot cancel Rita's order", ord(o2, 'status') === 'open');
r = await A.req('order-action.php', { method: 'POST', form: { order_id: o2, action: 'cancel' } }); ok('X3 cancel without CSRF -> 403', r.status === 403 && ord(o2, 'status') === 'open');
r = await A.post('order-action.php', { order_id: o2, action: 'cancel' });
ok('X4 owner cancels an unpaid order: books back on the shelf, rows removed', ord(o2, 'status') === 'cancelled' && left('ST-5') === '2/2' && left('ST-6') === '2/2' && sql(`SELECT COUNT(*) FROM lib_loans WHERE order_id=${o2}`) === '0');
r = await A.post('order-action.php', { order_id: o2, action: 'cancel' }); ok('X5 cancelling twice does not add copies again', left('ST-5') === '2/2');
r = await act(S, { order_id: o2, action: 'confirm_pay' }); ok('X6 a cancelled order cannot be confirmed or shipped', ord(o2, 'pay_status') === 'unpaid');
await A.post('cart.php', { action: 'add', book_id: b5 }); await A.post('checkout.php', { ...ship, days: '7' }, 'checkout.php'); const o3 = lastOrder('rita@s.test');
await act(A, { order_id: o3, action: 'report_pay' }); await act(S, { order_id: o3, action: 'confirm_pay' });
r = await act(S, { order_id: o3, action: 'cancel' }); ok('X7 staff can cancel a paid order before shipping', ord(o3, 'status') === 'cancelled' && left('ST-5') === '2/2');
await A.post('cart.php', { action: 'add', book_id: b5 }); await A.post('checkout.php', { ...ship, days: '7' }, 'checkout.php'); const o4 = lastOrder('rita@s.test');
sql(`UPDATE lib_orders SET ordered_at = NOW() - INTERVAL 3 DAY WHERE order_id=${o4}`); await A.req('my-orders.php');
ok('X8 an order unpaid for 48 hours is cancelled automatically and the copy returns', ord(o4, 'status') === 'cancelled' && left('ST-5') === '2/2');
await A.post('cart.php', { action: 'add', book_id: b5 }); await A.post('checkout.php', { ...ship, days: '7' }, 'checkout.php'); const o5 = lastOrder('rita@s.test');
await act(A, { order_id: o5, action: 'report_pay' }); sql(`UPDATE lib_orders SET ordered_at = NOW() - INTERVAL 5 DAY WHERE order_id=${o5}`); await A.req('my-orders.php');
ok('X9 an order with a payment report is NOT auto-cancelled', ord(o5, 'status') === 'open');

// after staff confirmed the money, the owner cannot cancel; staff can, and the confirming staff member is recorded
await A.post('cart.php', { action: 'add', book_id: b5 }); await A.post('checkout.php', { ...ship, days: '7' }, 'checkout.php'); const o6 = lastOrder('rita@s.test');
await act(S, { order_id: o6, action: 'confirm_pay' });
ok('X10 confirm_pay records which staff member confirmed', ord(o6, 'confirmed_by') === sql("SELECT user_id FROM lib_users WHERE email='sam@s.test'"));
r = await A.post('order-action.php', { order_id: o6, action: 'cancel' }); ok('X11 the owner cannot cancel after payment is confirmed', ord(o6, 'status') === 'open' && left('ST-5') === '0/2');
r = await A.req('order.php?id=' + o6); ok('X12 the owner no longer sees a cancel button on a paid order', !r.text.includes('value="cancel"'));
r = await act(S, { order_id: o6, action: 'cancel' }); ok('X13 staff can still cancel a paid order', ord(o6, 'status') === 'cancelled' && left('ST-5') === '1/2');

// ===== Staff desk, dashboard =====
for (const [who, cl] of [['member', A], ['guest', anon]]) for (const p of ['dashboard.php', 'rentals.php', 'books.php', 'book-form.php', 'overdue.php', 'users.php']) { r = await cl.req(p); ok('D1 ' + who + ' opening ' + p + ' is refused', r.status === 403 || (r.status === 302 && r.loc.includes('login'))); }
r = await S.req('dashboard.php'); ok('D2 staff dashboard shows the numbers and low stock list', r.status === 200 && r.text.includes('รอตรวจสอบการโอน') && r.text.includes('หนังสือใกล้หมดชั้น') && r.text.includes('Shop Book Three'));
r = await S.req('rentals.php?tab=review'); ok('D3 the "review" tab lists the reported order', r.text.includes(order_no(o5)));
r = await S.req('rentals.php?tab=all&q=' + encodeURIComponent('Rita')); ok('D4 search by member name works', r.text.includes(order_no(o5)));
r = await S.req('rentals.php?tab=all&q=' + o5); ok('D5 search by order number works', r.text.includes(order_no(o5)));
for (const evil of ["rentals.php?tab=review' OR 1=1--", "rentals.php?tab[]=x", "rentals.php?q=" + encodeURIComponent("'; DROP TABLE lib_orders;--")]) { r = await S.req(evil); ok('D6 rentals survives ' + evil.slice(0, 30), r.status === 200 && !/SQL|Warning/i.test(r.text)); }
r = await S.req('order.php?id=' + o5); ok('D7 staff sees the order of another member with member name', r.text.includes('Rita Renter'));
r = await S.req('users.php'); ok('D8 staff cannot open the users page (admin only)', r.status === 403);

// ===== Book CRUD =====
const nb = { title: 'Created In Test', author: 'Writer', isbn: 'ST-1001', description: 'About it', category_id: cat, copies_total: '4', rent_per_day: '12.5', deposit: '250' };
r = await S.post('book-form.php', nb, 'book-form.php');
ok('B1 staff adds a book (4 free copies, rate, deposit, description)', r.loc.includes('books.php') && sql("SELECT CONCAT(copies_total,'|',copies_available,'|',rent_per_day,'|',deposit,'|',description) FROM lib_books WHERE isbn='ST-1001'") === '4|4|12.50|250.00|About it');
r = await S.post('book-form.php', nb, 'book-form.php'); ok('B2 duplicate ISBN refused', r.text.includes('ISBN นี้มีอยู่แล้ว') && sql("SELECT COUNT(*) FROM lib_books WHERE isbn='ST-1001'") === '1');
for (const [n, f] of [['empty title', { ...nb, isbn: 'ST-1002', title: '' }], ['201-char title', { ...nb, isbn: 'ST-1002', title: 'T'.repeat(201) }], ['quote in isbn', { ...nb, isbn: "ST'1002" }], ['unknown category', { ...nb, isbn: 'ST-1002', category_id: '99999' }], ['zero copies', { ...nb, isbn: 'ST-1002', copies_total: '0' }], ['negative rent', { ...nb, isbn: 'ST-1002', rent_per_day: '-5' }], ['text price', { ...nb, isbn: 'ST-1002', deposit: 'free' }], ['huge description', { ...nb, isbn: 'ST-1002', description: 'd'.repeat(2001) }]]) {
  r = await S.post('book-form.php', f, 'book-form.php'); ok('B3 ' + n + ' refused, nothing saved', r.status === 200 && sql("SELECT COUNT(*) FROM lib_books WHERE isbn IN ('ST-1002',\"ST'1002\")") === '0');
}
r = await S.post('book-form.php', { ...nb, isbn: 'ST-1003', title: "x'),(1,'hack','h','ST-9999',1,1,0,0)-- ", author: '<img src=x onerror=alert(1)>' }, 'book-form.php');
ok('B4 SQL in the title is plain text; no extra book', sql("SELECT COUNT(*) FROM lib_books WHERE isbn='ST-9999'") === '0' && sql("SELECT COUNT(*) FROM lib_books WHERE isbn='ST-1003'") === '1');
r = await S.req('books.php'); ok('B5 stored XSS in author escaped on the book list', !r.text.includes('<img src=x onerror') && r.text.includes('&lt;img'));
const idNew = bid('ST-1001');
r = await S.post('book-form.php?id=' + idNew, { ...nb, book_id: idNew, copies_total: '6', title: 'Edited Title' }, 'book-form.php?id=' + idNew);
ok('B6 editing raises free copies with the total', sql(`SELECT CONCAT(title,'|',copies_total,'|',copies_available) FROM lib_books WHERE book_id=${idNew}`) === 'Edited Title|6|6');
await A.post('cart.php', { action: 'clear' }); await A.post('cart.php', { action: 'add', book_id: idNew }); await A.post('checkout.php', { ...ship, days: '7' }, 'checkout.php');
await B.post('cart.php', { action: 'clear' }); await B.post('cart.php', { action: 'add', book_id: idNew }); await B.post('checkout.php', { ...ship, days: '7' }, 'checkout.php');
r = await S.post('book-form.php?id=' + idNew, { ...nb, book_id: idNew, copies_total: '1' }, 'book-form.php?id=' + idNew);
ok('B7 cannot cut the total below the 2 copies reserved or rented', sql(`SELECT copies_total FROM lib_books WHERE book_id=${idNew}`) === '6' && r.text.includes('ไม่น้อยกว่า'));
r = await S.post('book-form.php?id=99999999', { ...nb, book_id: '99999999' }, 'book-form.php'); ok('B8 editing a missing book -> 404', r.status === 404);
r = await A.post('book-form.php', { ...nb, isbn: 'ST-2001' }); ok('B9 a member cannot add a book', r.status === 403 && sql("SELECT COUNT(*) FROM lib_books WHERE isbn='ST-2001'") === '0');
r = await S.req('book-form.php', { method: 'POST', form: { ...nb, isbn: 'ST-2002' } }); ok('B10 add without CSRF token -> 403', r.status === 403);
r = await S.post('book-delete.php', { book_id: idNew }, 'books.php'); ok('B11 a book with rental history cannot be deleted', sql(`SELECT COUNT(*) FROM lib_books WHERE book_id=${idNew}`) === '1');
const idFree = bid('ST-1003');
r = await A.post('book-delete.php', { book_id: idFree }); ok('B12 a member cannot delete', r.status === 403);
r = await S.req('book-delete.php?book_id=' + idFree); ok('B13 delete via GET -> 405', r.status === 405);
r = await S.post('book-delete.php', { book_id: idFree }, 'books.php'); ok('B14 staff deletes a book with no history', sql(`SELECT COUNT(*) FROM lib_books WHERE book_id=${idFree}`) === '0');

// ===== Admin: users and roles =====
r = await AD.req('users.php'); ok('U1 admin sees the user list', r.status === 200 && r.text.includes('Rita Renter') && !r.text.includes('$2y$'));
const uid = (m) => sql(`SELECT user_id FROM lib_users WHERE email='${m}'`), role = (m) => sql(`SELECT role FROM lib_users WHERE email='${m}'`);
r = await S.post('user-role.php', { user_id: uid('ben@s.test'), role: 'admin' }); ok('U2 staff cannot make someone admin', r.status === 403 && role('ben@s.test') === 'member');
r = await A.post('user-role.php', { user_id: uid('rita@s.test'), role: 'admin' }); ok('U3 a member cannot make themselves admin', r.status === 403 && role('rita@s.test') === 'member');
r = await AD.req('user-role.php', { method: 'POST', form: { user_id: uid('ben@s.test'), role: 'staff' } }); ok('U4 role change without CSRF -> 403', r.status === 403);
r = await AD.post('user-role.php', { user_id: uid('ben@s.test'), role: 'staff' }, 'users.php'); ok('U5 admin promotes a member to staff; effective at once', role('ben@s.test') === 'staff' && (await B.req('dashboard.php')).status === 200);
for (const badRole of ['owner', '', 'ADMIN', "admin'; DROP TABLE lib_users;--"]) { r = await AD.post('user-role.php', { user_id: uid('rita@s.test'), role: badRole }, 'users.php'); ok('U6 unknown role "' + badRole.slice(0, 10) + '" refused', r.status === 400 && role('rita@s.test') === 'member'); }
r = await AD.post('user-role.php', { user_id: uid('ada@s.test'), role: 'member' }, 'users.php'); ok('U7 an admin cannot demote themselves', role('ada@s.test') === 'admin');
r = await AD.post('user-role.php', { user_id: uid('ben@s.test'), role: 'member' }, 'users.php'); ok('U8 demoted staff loses the staff pages at once', (await B.req('dashboard.php')).status === 403);

// ===== Profile =====
r = await anon.req('profile.php'); ok('F1 guest -> login', r.status === 302 && r.loc.includes('login'));
r = await A.post('profile.php', { action: 'profile', full_name: 'Rita <b>New</b>', phone: '+66 81-234-5678', default_address: 'New address 123 Soi Test, Mueang, Chonburi' }, 'profile.php');
ok('F2 profile saved (name, phone, address)', sql("SELECT CONCAT(full_name,'|',phone) FROM lib_users WHERE email='rita@s.test'") === 'Rita <b>New</b>|+66 81-234-5678');
r = await A.req('profile.php'); ok('F3 profile page escapes the saved name', !r.text.includes('<b>New</b>') && r.text.includes('&lt;b&gt;New&lt;/b&gt;'));
for (const [n, f] of [['bad phone', { full_name: 'Ok Name', phone: 'abc', default_address: '' }], ['short name', { full_name: 'R', phone: '', default_address: '' }], ['long address', { full_name: 'Ok Name', phone: '', default_address: 'x'.repeat(300) }]]) { r = await A.post('profile.php', { action: 'profile', ...f }, 'profile.php'); ok('F4 ' + n + ' refused', r.status === 200 && sql("SELECT full_name FROM lib_users WHERE email='rita@s.test'") === 'Rita <b>New</b>'); }
r = await A.post('profile.php', { action: 'profile', full_name: 'X Y Z', user_id: uid('ben@s.test'), email: 'hacked@s.test', role: 'admin' }, 'profile.php');
ok('F5 the profile form only edits the logged-in user (user_id/email/role fields ignored)', sql("SELECT full_name FROM lib_users WHERE email='ben@s.test'") === 'Ben Renter' && role('rita@s.test') === 'member' && sql("SELECT COUNT(*) FROM lib_users WHERE email='hacked@s.test'") === '0');
r = await A.post('profile.php', { action: 'password', old_password: 'wrong-old-pass', new_password: 'new-pass-12345', new_password_confirm: 'new-pass-12345' }, 'profile.php'); ok('F6 wrong old password refused', r.text.includes('รหัสผ่านเดิมไม่ถูกต้อง'));
r = await A.post('profile.php', { action: 'password', old_password: 'rita-pass-123', new_password: 'short', new_password_confirm: 'short' }, 'profile.php'); ok('F7 short new password refused', r.text.includes('8-72'));
r = await A.post('profile.php', { action: 'password', old_password: 'rita-pass-123', new_password: 'new-pass-12345', new_password_confirm: 'different-12345' }, 'profile.php'); ok('F8 mismatch refused', r.text.includes('ไม่ตรงกัน'));
r = await A.post('profile.php', { action: 'password', old_password: 'rita-pass-123', new_password: 'rita-pass-123', new_password_confirm: 'rita-pass-123' }, 'profile.php'); ok('F9 same password refused', r.text.includes('ไม่เหมือนรหัสเดิม'));
r = await A.req('profile.php', { method: 'POST', form: { action: 'password', old_password: 'rita-pass-123', new_password: 'new-pass-12345', new_password_confirm: 'new-pass-12345' } }); ok('F10 password change without CSRF -> 403', r.status === 403);
for (let i = 0; i < 4; i++) await A.post('profile.php', { action: 'password', old_password: 'wrong' + i, new_password: 'new-pass-12345', new_password_confirm: 'new-pass-12345' }, 'profile.php');
r = await A.post('profile.php', { action: 'password', old_password: 'rita-pass-123', new_password: 'new-pass-12345', new_password_confirm: 'new-pass-12345' }, 'profile.php');
ok('F11 after 5 wrong old passwords the form is locked (even the right one is refused)', r.status === 429 && r.text.includes('รอ 15 นาที'));
sql('DELETE FROM lib_attempts');
r = await A.post('profile.php', { action: 'password', old_password: 'rita-pass-123', new_password: 'new-pass-12345', new_password_confirm: 'new-pass-12345' }, 'profile.php');
const A2 = client(); const okNew = (await A2.login('rita@s.test', 'new-pass-12345')).loc; const A3 = client(); const noOld = (await A3.login('rita@s.test', 'rita-pass-123')).loc;
ok('F12 the password changes: new one logs in, old one does not', r.loc === 'profile.php' && okNew.includes('index.php') && noOld === '');
r = await S.req('login.php'); ok('F13 staff visiting login is sent to the dashboard', r.loc.includes('dashboard.php'));
r = await A.req('logout.php', { method: 'POST', form: {} }); ok('F14 logout without CSRF -> 403', r.status === 403);
r = await A.post('logout.php', {}); ok('F15 logout with token works, old pages need login again', r.loc.includes('logged_out') && (await A.req('my-orders.php')).loc.includes('login'));

console.log(`\n${pass} passed, ${fail} failed`);
cleanup();
process.exit(fail ? 1 : 0);
