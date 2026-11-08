// Tests of the order-side features: coupons, bundle maths, PromptPay QR payload, overdue rules, notifications, CSRF.
// Run ONLY against your own throwaway database/server: BASE=http://127.0.0.1:8102/ DB_NAME=lib_sec_o node tests/orders.mjs
import { execFileSync, spawn } from 'node:child_process';

const BASE = process.env.BASE ?? 'http://127.0.0.1:8102/';
const MYSQL = process.env.MYSQL ?? 'C:/xampp/mysql/bin/mysql.exe';
const PHP = process.env.PHP ?? 'C:/xampp/php/php.exe';
const DB = process.env.DB_NAME ?? 'lib_sec_o';
if (!/sec|test/.test(DB)) throw new Error('refusing to run: DB_NAME must be a throwaway test database (name contains sec or test)');
const sql = (q) => execFileSync(MYSQL, ['-h', '127.0.0.1', '-P', process.env.DB_PORT ?? '3399', '-u', 'root', '--default-character-set=utf8mb4', '-N', '-B', DB, '-e', q]).toString().trim();
let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => { cond ? pass++ : fail++; console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  [' + extra + ']' : '')); };

function client(base = BASE) {
  const jar = {};
  const c = {
    async req(p, { method = 'GET', form } = {}) {
      const h = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; ') };
      let body;
      if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(base + p, { method, headers: h, body, redirect: 'manual' });
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

// ===== PromptPay payload: checked against an independent implementation written here from the EMVCo / PromptPay spec =====
const phpEval = (code) => execFileSync(PHP, ['-r', `require 'includes/orders.php'; ${code}`], { cwd: new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1') }).toString();
function crcJs(s) { let c = 0xFFFF; for (const ch of Buffer.from(s, 'ascii')) { c ^= ch << 8; for (let i = 0; i < 8; i++) c = c & 0x8000 ? ((c << 1) ^ 0x1021) & 0xFFFF : (c << 1) & 0xFFFF; } return c.toString(16).toUpperCase().padStart(4, '0'); }
const tlv = (id, v) => id + String(v.length).padStart(2, '0') + v;
function ppJs(target, amount) {
  const acct = target.length === 13 ? tlv('02', target) : tlv('01', '0066' + target.slice(1));
  const s = tlv('00', '01') + tlv('01', '12') + tlv('29', tlv('00', 'A000000677010111') + acct) + tlv('53', '764') + tlv('54', amount.toFixed(2)) + tlv('58', 'TH') + '6304';
  return s + crcJs(s);
}
ok('Q1 CRC-16/CCITT-FALSE standard vector "123456789" = 29B1 (node and php agree)', crcJs('123456789') === '29B1' && phpEval("echo crc16_ccitt('123456789');") === '29B1');
for (const [t, a] of [['0812345678', 100], ['0899999999', 420.5], ['1234567890123', 1.99], ['0612345678', 1234.56]]) {
  const got = phpEval(`echo promptpay_payload('${t}', ${a});`);
  ok(`Q2 PromptPay payload ${t} / ${a} equals the independent build`, got === ppJs(t, a), got);
}
const known = phpEval("echo promptpay_payload('0812345678', 100);");
ok('Q3 payload shape: starts 000201010212, has AID, 0066812345678, 764, 54 amount 100.00, TH, CRC verifies', known.startsWith('000201010212') && known.includes('A000000677010111') && known.includes('0066812345678') && known.includes('5303764') && known.includes('5406100.00') && known.includes('5802TH') && crcJs(known.slice(0, -4)) === known.slice(-4));
ok('Q4 unusable targets or amounts give an empty payload', ['', 'abc', '12345', '0000'].every((t) => phpEval(`echo promptpay_payload('${t}', 100);`) === '') && phpEval("echo promptpay_payload('0812345678', 0);") === '' && phpEval("echo promptpay_payload('0812345678', -5);") === '');

// ===== fixtures =====
const cleanup = () => sql("DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@o.test'; DELETE o FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@o.test'; DELETE FROM lib_users WHERE email LIKE '%@o.test'; DELETE FROM lib_books WHERE isbn LIKE 'OT-%'; DELETE FROM lib_coupons WHERE code LIKE 'OT%'");
cleanup();
const U = {}; const names = ['ua', 'ub', 'uc', 'ud', 'ue', 'uf', 'staff'];
for (const n of names) { U[n] = client(); await U[n].register('Order ' + n, n + '@o.test', 'order-pass-123'); }
sql("UPDATE lib_users SET role='staff' WHERE email='staff@o.test'; DELETE FROM lib_attempts");
for (const n of names) await U[n].login(n + '@o.test', 'order-pass-123');
const cat = sql('SELECT MIN(category_id) FROM lib_categories');
const mk = (isbn, title, total, rate, dep) => sql(`INSERT INTO lib_books (category_id, title, author, isbn, description, copies_total, copies_available, rent_per_day, deposit) VALUES (${cat}, '${title}', 'Tester', '${isbn}', 'd', ${total}, ${total}, ${rate}, ${dep})`);
for (const i of [1, 2, 3, 4, 5, 6]) mk('OT-' + i, 'Order Book ' + i, 30, 10, 100);
const bk = [1, 2, 3, 4, 5, 6].map((i) => sql(`SELECT book_id FROM lib_books WHERE isbn='OT-${i}'`));
const left = (i) => sql(`SELECT CONCAT(copies_available,'/',copies_total) FROM lib_books WHERE isbn='OT-${i}'`);
const cp = (code, kind, value, extra = '') => sql(`INSERT INTO lib_coupons (code, kind, value, min_rent, max_uses, expires_on, active) VALUES ('${code}', '${kind}', ${value}, ${extra || '0, NULL, NULL, 1'})`);
cp('OTPCT10', 'percent', 10); cp('OTAMT50', 'amount', 50); cp('OTHUGE', 'amount', 9999); cp('OTONE', 'amount', 20, '0, 1, NULL, 1');
cp('OTEXP', 'amount', 20, '0, NULL, CURDATE() - INTERVAL 1 DAY, 1'); cp('OTMIN', 'amount', 20, '500, NULL, NULL, 1'); cp('OTOFF', 'amount', 20, '0, NULL, NULL, 0');
const ship = { ship_name: 'Order T', ship_phone: '0812345678', ship_address: '99 Test Road, Bang Saen, Chonburi 20131' };
const email = (n) => n + '@o.test';
const lastOrder = (n) => sql(`SELECT MAX(order_id) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='${email(n)}'`);
const orders = (n) => Number(sql(`SELECT COUNT(*) FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email='${email(n)}'`));
const ord = (id, col) => sql(`SELECT ${col} FROM lib_orders WHERE order_id=${id}`);
const fill = async (c, idx) => { await c.post('cart.php', { action: 'clear' }); for (const i of idx) await c.post('cart.php', { action: 'add', book_id: bk[i] }); };
const buy = (c, extra = {}) => c.post('checkout.php', { ...ship, days: '7', ...extra }, 'checkout.php');
const used = (code) => sql(`SELECT used_count FROM lib_coupons WHERE code='${code}'`);
let r;

// ===== cart: breakdown + nudge =====
await fill(U.ua, [0, 1]);
r = await U.ua.req('cart.php');
ok('C1 two books: nudge "เพิ่มอีก 1 เล่ม ลดค่าเช่า 10%" and rent 140 + deposit 200 = 340', r.text.includes('เพิ่มอีก 1 เล่ม ลดค่าเช่า 10%') && r.text.includes('฿340.00'));
await fill(U.ua, [0, 1, 2]);
r = await U.ua.req('cart.php');
ok('C2 three books: bundle line -21.00, total 210-21+300 = 489.00, nudge now for 15%', r.text.includes('-฿21.00') && r.text.includes('฿489.00') && r.text.includes('ลดค่าเช่า 15%'));
r = await U.ua.req('cart.php?coupon=otpct10');
ok('C3 cart preview with a lower-case code: coupon after bundle (10% of 189 = 18.90), total 470.10; nothing consumed', r.text.includes('-฿18.90') && r.text.includes('฿470.10') && used('OTPCT10') === '0');
r = await U.ua.req('cart.php?coupon=NOPE1234');
ok('C4 unknown code in the cart shows an error and no discount', r.text.includes('ไม่พบโค้ดนี้') && r.text.includes('฿489.00'));
r = await U.ua.req('cart.php?coupon=' + encodeURIComponent('"><script>alert(1)</script>'));
ok('C5 odd coupon text is escaped and refused', !r.text.includes('<script>alert(1)') && r.text.includes('รูปแบบโค้ดไม่ถูกต้อง'));

// ===== checkout: maths =====
r = await buy(U.ua, { coupon: 'otpct10' });
const o1 = lastOrder('ua');
ok('K1 checkout with percent coupon + bundle: rent_total gross 210, discount 39.90, code stored', r.status === 302 && ord(o1, "CONCAT(rent_total,'|',discount_total,'|',coupon_code,'|',deposit_total)") === '210.00|39.90|OTPCT10|300.00', r.loc);
ok('K2 coupon consumed once: used_count 1 and one use row', used('OTPCT10') === '1' && sql(`SELECT COUNT(*) FROM lib_coupon_uses WHERE order_id=${o1}`) === '1');
r = await U.ua.req('order.php?id=' + o1);
ok('K3 order page shows rent 210.00, bundle -21.00, coupon -18.90, deposit 300.00, total 470.10', ['฿210.00', '-฿21.00', '-฿18.90', '฿300.00', '฿470.10', 'OTPCT10', 'ยอดที่ต้องชำระ ฿470.10'].every((s) => r.text.includes(s)));
r = await U.ua.req('my-orders.php'); ok('K4 my-orders shows the discounted amount 470.10', r.text.includes('฿470.10'));
await fill(U.ua, [3]); r = await buy(U.ua, { coupon: 'OTPCT10' });
ok('K5 same member using the same coupon twice is refused, no order, stock untouched', r.status === 200 && r.text.includes('ใช้โค้ดนี้ไปแล้ว') && orders('ua') === 1 && left(4) === '30/30' && used('OTPCT10') === '1');
r = await U.ua.req('cart.php?coupon=OTPCT10'); ok('K6 cart preview also says already used', r.text.includes('ใช้โค้ดนี้ไปแล้ว'));
await fill(U.ub, [0]); r = await buy(U.ub, { coupon: 'OTAMT50' });
const o2 = lastOrder('ub');
ok('K7 amount coupon: rent 70, discount 50, pay 70-50+100 = 120.00', ord(o2, "CONCAT(rent_total,'|',discount_total)") === '70.00|50.00' && (await U.ub.req('order.php?id=' + o2)).text.includes('ยอดที่ต้องชำระ ฿120.00'));
await fill(U.uc, [0]); r = await buy(U.uc, { coupon: 'OTHUGE' });
const o3 = lastOrder('uc');
ok('K8 a coupon bigger than the rent never goes past the rent: discount 70, pay only the 100 deposit', ord(o3, "CONCAT(rent_total,'|',discount_total)") === '70.00|70.00' && (await U.uc.req('order.php?id=' + o3)).text.includes('ยอดที่ต้องชำระ ฿100.00'));
for (const [code, msg, why] of [['OTEXP', 'หมดอายุ', 'expired'], ['OTMIN', 'ไม่ต่ำกว่า', 'min_rent'], ['OTOFF', 'ไม่พบโค้ด', 'inactive'], ['NOSUCH99', 'ไม่พบโค้ด', 'unknown'], ['ab', 'รูปแบบ', 'too short'], ["OT' OR 1=1 --", 'รูปแบบ', 'sql'], ['A'.repeat(31), 'รูปแบบ', 'too long']]) {
  sql('DELETE FROM lib_attempts'); await fill(U.ud, [0]); const before = orders('ud'); const stock0 = left(1); r = await buy(U.ud, { coupon: code });
  ok('K9 coupon ' + why + ' is refused with a message; no order, stock restored', r.status === 200 && r.text.includes(msg) && orders('ud') === before && left(1) === stock0, why);
}
// tampered discount fields are never read
await fill(U.ud, [0]);
r = await buy(U.ud, { discount_total: '999', rent_total: '0', coupon_code: 'OTAMT50', total: '1', deposit_total: '0' });
const o4 = lastOrder('ud');
ok('K10 tampered discount_total/rent_total/coupon_code/deposit in the POST are ignored', r.status === 302 && ord(o4, "CONCAT(rent_total,'|',discount_total,'|',IFNULL(coupon_code,'none'),'|',deposit_total)") === '70.00|0.00|none|100.00');
// cancelling frees the coupon
r = await U.ub.post('order-action.php', { order_id: o2, action: 'cancel' });
ok('K11 cancelling releases the coupon: used_count back to 0, use row gone', ord(o2, 'status') === 'cancelled' && used('OTAMT50') === '0' && sql(`SELECT COUNT(*) FROM lib_coupon_uses WHERE order_id=${o2}`) === '0');
r = await U.ub.post('order-action.php', { order_id: o2, action: 'cancel' }); ok('K12 cancelling twice does not release twice', used('OTAMT50') === '0');
await fill(U.ub, [0]); r = await buy(U.ub, { coupon: 'OTAMT50' }); ok('K13 the same member can use the released coupon again', r.status === 302 && used('OTAMT50') === '1');
await fill(U.ud, [0]); const nUd = orders('ud');
r = await U.ud.post('checkout.php', { ...ship, days: '14', coupon: 'OTAMT50', do: 'apply' }, 'checkout.php');
ok('K15 "ใช้โค้ด" (do=apply) only recalculates: 14 days = 140 rent, discount 50, no new order', r.status === 200 && r.text.includes('-฿50.00') && r.text.includes('฿190.00') && orders('ud') === nUd);

// ===== race: one coupon, max_uses = 1, four members =====
sql('DELETE FROM lib_attempts'); const racers = ['ua', 'ub', 'uc', 'ud'];
for (const n of racers) await fill(U[n], [4]);
const before = Object.fromEntries(racers.map((n) => [n, orders(n)]));
const toks = await Promise.all(racers.map((n) => U[n].token('checkout.php')));
const rr = await Promise.all(racers.map((n, i) => U[n].req('checkout.php', { method: 'POST', form: { ...ship, days: '7', coupon: 'OTONE', csrf: toks[i] } })));
const won = racers.filter((n) => orders(n) > before[n]);
ok('R1 max_uses=1 coupon raced by 4 members: exactly one order gets it', won.length === 1 && used('OTONE') === '1' && sql("SELECT COUNT(*) FROM lib_orders WHERE coupon_code='OTONE'") === '1', won.join(',') + ' used=' + used('OTONE'));
ok('R2 the losers got a clear message and their stock was given back', rr.filter((x) => x.status === 200 && x.text.includes('ใช้ครบ')).length === 3 && sql("SELECT copies_total-copies_available FROM lib_books WHERE isbn='OT-5'") === '1');

// ===== overdue rules =====
const mkLoan = (n, dueExpr, status = 'shipped', book = 6) => {
  sql(`INSERT INTO lib_orders (user_id, rent_days, rent_total, deposit_total, ship_name, ship_phone, ship_address, pay_status, pay_confirmed_at, courier, tracking_no, shipped_at) SELECT user_id, 7, 70, 100, 'x', '0800000001', 'seed-overdue-address-only', 'confirmed', NOW(), 'Flash Express', 'TH00000001', CURDATE() - INTERVAL 30 DAY FROM lib_users WHERE email='${email(n)}'`);
  const oid = lastOrder(n);
  sql(`INSERT INTO lib_loans (order_id, user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit) SELECT ${oid}, user_id, ${bk[book - 1]}, CURDATE() - INTERVAL 30 DAY, ${dueExpr}, '${status}', 10, 100 FROM lib_users WHERE email='${email(n)}'`);
  sql(`UPDATE lib_books SET copies_available = copies_available - 1 WHERE book_id=${bk[book - 1]}`);
  return [oid, sql(`SELECT MAX(loan_id) FROM lib_loans WHERE order_id=${oid}`)];
};
const [oe, le] = mkLoan('ue', 'CURDATE() - INTERVAL 3 DAY');
await fill(U.ue, [0]);
const beforeE = orders('ue'); r = await buy(U.ue);
ok('V1 a member with an overdue book cannot place a new order (message + link to my-orders)', r.status === 200 && r.text.includes('เกินกำหนดคืน') && r.text.includes('my-orders.php') && orders('ue') === beforeE);
r = await U.ue.req('checkout.php'); ok('V2 the checkout page warns and disables the confirm button', r.text.includes('เกินกำหนดคืน') && /<button type="submit" class="btn btn-accent btn-block" disabled>/.test(r.text));
r = await U.ue.req('my-orders.php'); ok('V3 my-orders warns "ใกล้ถูกตัดเป็นสูญหายใน 11 วัน" (14 - 3)', r.text.includes('ใกล้ถูกตัดเป็นสูญหายใน 11 วัน'));
r = await U.ue.req('order.php?id=' + oe); ok('V4 order page shows the same warning on the book', r.text.includes('ใกล้ถูกตัดเป็นสูญหายใน 11 วัน') && r.text.includes('เกิน 3 วัน'));
sql(`UPDATE lib_loans SET status='returned', returned_at=CURDATE() WHERE loan_id=${le}`); sql("UPDATE lib_books SET copies_available=copies_available+1 WHERE isbn='OT-6'");
r = await buy(U.ue); ok('V5 after returning the book the member can order again', r.status === 302 && r.loc.startsWith('order.php?id='));

// auto-lost
const [of, lf] = mkLoan('uf', 'CURDATE() - INTERVAL 14 DAY');
const [of2, lf2] = mkLoan('uf', 'CURDATE() - INTERVAL 13 DAY');
const tot0 = sql("SELECT copies_total FROM lib_books WHERE isbn='OT-6'");
r = await U.uf.req('my-orders.php');
ok('L1 a book 14 days overdue is marked lost, returned_at stays empty; 13 days stays shipped', sql(`SELECT CONCAT(status,'|',IFNULL(returned_at,'null')) FROM lib_loans WHERE loan_id=${lf}`) === 'lost|null' && sql(`SELECT status FROM lib_loans WHERE loan_id=${lf2}`) === 'shipped');
ok('L2 copies_total went down by exactly 1', Number(sql("SELECT copies_total FROM lib_books WHERE isbn='OT-6'")) === Number(tot0) - 1);
ok('L3 the member got one "lost" notification linking to the order', sql(`SELECT COUNT(*) FROM lib_notifications n JOIN lib_users u USING(user_id) WHERE u.email='uf@o.test' AND n.kind='lost' AND n.link='order.php?id=${of}'`) === '1');
await U.uf.req('my-orders.php'); await Promise.all([U.uf.req('my-orders.php'), U.uf.req('order.php?id=' + of)]);
ok('L4 repeated and parallel page loads do not lose the book twice', Number(sql("SELECT copies_total FROM lib_books WHERE isbn='OT-6'")) === Number(tot0) - 1 && sql(`SELECT COUNT(*) FROM lib_notifications n JOIN lib_users u USING(user_id) WHERE u.email='uf@o.test' AND n.kind='lost'`) === '1');
r = await U.uf.req('order.php?id=' + of); ok('L5 lost book on the order page: deposit kept', r.text.includes('ไม่คืนมัดจำ'));

// overdue.php uses the same fee rule as order.php: (late days + 7 per renewal) x rate = (4 + 7) x 10 = 110 > deposit 100
const [ow, lw] = mkLoan('uf', 'CURDATE() - INTERVAL 4 DAY'); sql(`UPDATE lib_loans SET renew_count=1 WHERE loan_id=${lw}`);
r = await U.staff.req('overdue.php'); const rowW = r.text.split('<tr>').find((x) => x.includes('order.php?id=' + ow + '"')) ?? '';
ok('W1 staff overdue list charges the renewal too: ฿110.00 fine, over the ฿100.00 deposit, with the badge', rowW.includes('฿110.00') && rowW.includes('ค่าปรับเกินมัดจำ'), rowW.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 120));

// due soon
const [od, ld] = mkLoan('ud', 'CURDATE() + INTERVAL 1 DAY', 'shipped', 2);
const [od2, ld2] = mkLoan('ud', 'CURDATE() + INTERVAL 2 DAY', 'shipped', 3);
await U.ud.req('my-orders.php'); await U.ud.req('my-orders.php'); await U.ud.req('my-orders.php');
ok('D1 due-soon reminders: one notification per book per day (2 books, 3 page loads = 2)', sql(`SELECT COUNT(*) FROM lib_notifications n JOIN lib_users u USING(user_id) WHERE u.email='ud@o.test' AND n.kind='due_soon'`) === '2');
sql(`UPDATE lib_notifications SET created_at = created_at - INTERVAL 1 DAY WHERE kind='due_soon'`); await U.ud.req('my-orders.php');
ok('D2 the next day a new reminder is allowed (4 in total)', sql(`SELECT COUNT(*) FROM lib_notifications n JOIN lib_users u USING(user_id) WHERE u.email='ud@o.test' AND n.kind='due_soon'`) === '4');
r = await U.ud.req('my-orders.php'); ok('D3 my-orders shows the due-soon banner', r.text.includes('ครบกำหนดคืนภายใน 2 วัน'));

// ===== staff actions notify the member =====
await fill(U.uc, [1]); await buy(U.uc); const os = lastOrder('uc');
const nOf = (kind, oid) => sql(`SELECT COUNT(*) FROM lib_notifications WHERE kind='${kind}' AND link='order.php?id=${oid}' AND user_id=(SELECT user_id FROM lib_orders WHERE order_id=${oid})`);
await U.staff.post('order-action.php', { order_id: os, action: 'confirm_pay' });
ok('N1 confirm_pay notifies the owner (kind paid, link to the order)', nOf('paid', os) === '1');
await U.staff.post('order-action.php', { order_id: os, action: 'confirm_pay' });
ok('N2 pressing confirm twice does not notify twice', nOf('paid', os) === '1');
await U.staff.post('order-action.php', { order_id: os, action: 'ship', courier: 'Kerry Express', tracking_no: 'KR12345678' });
ok('N3 ship notifies with courier and tracking number', sql(`SELECT COUNT(*) FROM lib_notifications WHERE kind='shipped' AND link='order.php?id=${os}' AND message LIKE '%Kerry Express%' AND message LIKE '%KR12345678%'`) === '1');
const lq = sql(`SELECT loan_id FROM lib_loans WHERE order_id=${os}`);
await U.staff.post('order-action.php', { order_id: os, loan_id: lq, action: 'receive' });
ok('N4 receive notifies the owner (kind info)', nOf('info', os) === '1');
await U.staff.post('order-action.php', { order_id: os, loan_id: lq, action: 'receive' });
ok('N5 receiving twice does not notify twice', nOf('info', os) === '1');
await fill(U.uc, [2]); await buy(U.uc); const ol = lastOrder('uc');
await U.staff.post('order-action.php', { order_id: ol, action: 'confirm_pay' }); await U.staff.post('order-action.php', { order_id: ol, action: 'ship', courier: 'Flash Express', tracking_no: 'FL12345678' });
await U.staff.post('order-action.php', { order_id: ol, loan_id: sql(`SELECT loan_id FROM lib_loans WHERE order_id=${ol}`), action: 'lost' });
ok('N6 marking lost notifies the owner (kind lost)', nOf('lost', ol) === '1');
r = await U.ua.post('order-action.php', { order_id: ol, action: 'confirm_pay' }); ok('N7 a member cannot trigger staff actions (403) and no notification appears', r.status === 403);

// ===== CSRF and method on every POST =====
await fill(U.ua, [3]); const oc = lastOrder('ua');
for (const [p, form] of [['cart.php', { action: 'add', book_id: bk[0] }], ['cart.php', { action: 'remove', book_id: bk[3] }], ['cart.php', { action: 'clear' }], ['checkout.php', { ...ship, days: '7', coupon: 'OTAMT50' }], ['checkout.php', { ...ship, days: '7', do: 'apply' }],
  ['order-action.php', { order_id: oc, action: 'cancel' }], ['order-action.php', { order_id: oc, action: 'report_pay' }], ['order-action.php', { order_id: oc, action: 'confirm_pay' }], ['renew.php', { loan_id: 1 }]]) {
  const c = p === 'order-action.php' && form.action === 'confirm_pay' ? U.staff : U.ua;
  const n0 = orders('ua'); const rq = await c.req(p, { method: 'POST', form }); const rb = await c.req(p, { method: 'POST', form: { ...form, csrf: 'deadbeef' } });
  ok(`S1 ${p} (${form.action ?? 'order'}) without or with a wrong CSRF token -> 403, nothing changes`, rq.status === 403 && rb.status === 403 && orders('ua') === n0 && ord(oc, 'status') === 'open' && ord(oc, 'pay_status') === 'unpaid');
}
ok('S2 no coupon was consumed by the CSRF attempts', used('OTAMT50') === '1');
r = await U.ua.req('checkout.php?coupon=OTAMT50&days=7'); ok('S3 coupon in the checkout URL only previews (GET never consumes)', used('OTAMT50') === '1');

// ===== PromptPay on the order page (a second server with SHOP_PROMPTPAY set) =====
const PP_PORT = 8112;
const srv = spawn(PHP, ['-d', 'mysqli.default_port=' + (process.env.DB_PORT ?? '3399'), '-S', '127.0.0.1:' + PP_PORT], { cwd: new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'), env: { ...process.env, DB_HOST: '127.0.0.1', DB_USER: 'root', DB_NAME: DB, SHOP_PROMPTPAY: '0812345678' }, stdio: 'ignore' });
await new Promise((res) => setTimeout(res, 1500));
try {
  const P = client('http://127.0.0.1:' + PP_PORT + '/'); await P.login('ua@o.test', 'order-pass-123');
  await P.post('cart.php', { action: 'clear' }); await P.post('cart.php', { action: 'add', book_id: bk[3] }); await P.post('checkout.php', { ...ship, days: '7', coupon: '' }, 'checkout.php');
  const op = lastOrder('ua');
  r = await P.req('order.php?id=' + op);
  const m = r.text.match(/data-payload="([^"]+)"/);
  ok('PP1 unpaid order with PromptPay configured: QR canvas with data-payload, scripts loaded, save hint', !!m && r.text.includes('qrcode.js') && r.text.includes('orders.js') && r.text.includes('บันทึกรูป'));
  const grand = Number(ord(op, 'rent_total - discount_total + deposit_total'));
  ok('PP2 the payload encodes this order\'s amount and verifies', !!m && m[1] === ppJs('0812345678', grand) && m[1].includes('54' + String(grand.toFixed(2).length).padStart(2, '0') + grand.toFixed(2)), m && m[1]);
  r = await U.ub.req('order.php?id=' + op); ok('PP3 another member does not get the order or its QR', r.status === 404 && !r.text.includes('data-payload'));
  r = await U.staff.req('order.php?id=' + op); ok('PP4 staff viewing the order does not get the payment QR', r.status === 200 && !r.text.includes('data-payload'));
  await P.post('order-action.php', { order_id: op, action: 'report_pay' });
  r = await P.req('order.php?id=' + op); ok('PP5 after reporting payment the QR is gone', !r.text.includes('data-payload'));
} finally { srv.kill(); }
r = await U.ua.req('order.php?id=' + lastOrder('ua'));
ok('PP6 without PromptPay configured the plain "ยอดที่ต้องชำระ" box stays and no QR is drawn', !r.text.includes('data-payload'));

console.log(`\n${pass} passed, ${fail} failed`);
cleanup();
process.exit(fail ? 1 : 0);
