// Staff back office tests: coupons admin, analytics dashboard, overdue, featured flag. Run ONLY against a throwaway DB (name has sec/test).
import { execFileSync } from 'node:child_process';
const BASE = process.env.BASE ?? 'http://127.0.0.1:8103/';
const MYSQL = process.env.MYSQL ?? 'C:/xampp/mysql/bin/mysql.exe';
const DB = process.env.DB_NAME ?? 'lib_sec_s';
if (!/sec|test/.test(DB)) throw new Error('refusing to run: DB_NAME must be a throwaway test database');
const sql = (q) => execFileSync(MYSQL, ['-h', '127.0.0.1', '-P', process.env.DB_PORT ?? '3399', '-u', 'root', '--default-character-set=utf8mb4', '-N', '-B', DB, '-e', q]).toString().trim();
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
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
  };
  return c;
}
const wipe = () => sql("DELETE FROM lib_coupons WHERE code LIKE 'ST%'; DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@st.test'; DELETE o FROM lib_orders o JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@st.test'; DELETE FROM lib_users WHERE email LIKE '%@st.test'; DELETE FROM lib_books WHERE isbn LIKE 'SF-%'; DELETE FROM lib_attempts");
wipe();
const A = client(), S = client(), anon = client();
for (const [c, n, e] of [[A, 'Mem', 'mem@st.test'], [S, 'Staffer', 'staff@st.test']]) {
  await c.post('register.php', { full_name: n, email: e, password: 'pass-word-123', password_confirm: 'pass-word-123' }, 'register.php');
}
sql("UPDATE lib_users SET role='staff' WHERE email='staff@st.test'; DELETE FROM lib_attempts");
await A.post('login.php', { email: 'mem@st.test', password: 'pass-word-123' }, 'login.php');
await S.post('login.php', { email: 'staff@st.test', password: 'pass-word-123' }, 'login.php');
let r;
const cnt = (code) => sql(`SELECT COUNT(*) FROM lib_coupons WHERE code='${code}'`);

// ===== access =====
for (const [who, cl] of [['member', A], ['guest', anon]]) for (const p of ['dashboard.php', 'rentals.php', 'overdue.php', 'books.php', 'book-form.php', 'coupons.php', 'users.php']) {
  r = await cl.req(p); ok(`S1 ${who} refused ${p}`, r.status === 403 || (r.status === 302 && r.loc.includes('login')));
}
r = await A.post('coupons.php', { action: 'create', code: 'STMEM1', kind: 'percent', value: '10' }); ok('S2 member cannot POST a coupon', r.status === 403 && cnt('STMEM1') === '0');
r = await anon.req('coupons.php', { method: 'POST', form: { action: 'create', code: 'STANON', kind: 'percent', value: '10' } }); ok('S3 guest cannot POST a coupon', cnt('STANON') === '0');
r = await S.req('coupons.php'); ok('S4 staff sees coupons page', r.status === 200 && r.text.includes('สร้างโค้ดใหม่'));

// ===== coupon create =====
const good = { action: 'create', code: 'STGOOD1', kind: 'percent', value: '15', min_rent: '50', max_uses: '5', expires_on: '2099-12-31' };
r = await S.req('coupons.php', { method: 'POST', form: good }); ok('C1 create without CSRF -> 403', r.status === 403 && cnt('STGOOD1') === '0');
r = await S.post('coupons.php', good, 'coupons.php'); ok('C2 staff creates a coupon', r.status === 302 && sql("SELECT CONCAT(kind,'|',value,'|',min_rent,'|',max_uses,'|',expires_on,'|',used_count,'|',active) FROM lib_coupons WHERE code='STGOOD1'") === 'percent|15.00|50.00|5|2099-12-31|0|1');
r = await S.post('coupons.php', good, 'coupons.php'); ok('C3 duplicate code refused', r.text.includes('โค้ดนี้มีอยู่แล้ว') && cnt('STGOOD1') === '1');
r = await S.post('coupons.php', { action: 'create', code: 'stlow2', kind: 'amount', value: '20' }, 'coupons.php'); ok('C4 lowercase code stored uppercase, optional fields empty', cnt('STLOW2') === '1' && sql("SELECT CONCAT(IFNULL(max_uses,'N'),'|',IFNULL(expires_on,'N'),'|',min_rent) FROM lib_coupons WHERE code='STLOW2'") === 'N|N|0.00');
const bad = {
  'short code': { code: 'AB' }, 'symbols': { code: 'ST BAD!' }, 'too long': { code: 'ST' + 'A'.repeat(30) }, 'xss code': { code: '<script>' }, 'sql code': { code: "ST'; DROP TABLE lib_coupons;--" },
  'bad kind': { kind: 'free' }, 'percent 0': { kind: 'percent', value: '0' }, 'percent 91': { kind: 'percent', value: '91' }, 'percent 1.5': { kind: 'percent', value: '1.5' },
  'amount 5001': { kind: 'amount', value: '5001' }, 'amount text': { kind: 'amount', value: 'abc' }, 'amount neg': { kind: 'amount', value: '-5' },
  'min_rent neg': { min_rent: '-1' }, 'min_rent text': { min_rent: 'x' }, 'max_uses 0': { max_uses: '0' }, 'max_uses text': { max_uses: 'many' },
  'expired': { expires_on: '2020-01-01' }, 'garbage date': { expires_on: '2099-13-45' },
};
for (const [n, o] of Object.entries(bad)) {
  const f = { action: 'create', code: 'STBAD9', kind: 'percent', value: '10', ...o };
  r = await S.post('coupons.php', f, 'coupons.php');
  ok('C5 ' + n + ' refused', r.status === 200 && /alert-err/.test(r.text) && sql("SELECT COUNT(*) FROM lib_coupons WHERE code LIKE 'ST%' AND code NOT IN ('STGOOD1','STLOW2')") === '0', 'status ' + r.status);
}
r = await S.post('coupons.php', { ...good, code: 'STXSS1', used_count: '99', active: '0', coupon_id: '1' }, 'coupons.php');
ok('C6 no mass assignment (used_count/active ignored)', sql("SELECT CONCAT(used_count,'|',active) FROM lib_coupons WHERE code='STXSS1'") === '0|1');
r = await S.req('coupons.php', { method: 'POST', form: { action: 'create', 'code[]': 'STARR1', csrf: await S.token('coupons.php') } }); ok('C7 array field does not crash', r.status === 200 && cnt('STARR1') === '0');

// ===== toggle / delete =====
const id = (c) => sql(`SELECT coupon_id FROM lib_coupons WHERE code='${c}'`);
r = await S.req('coupons.php', { method: 'POST', form: { action: 'toggle', coupon_id: id('STGOOD1'), to: '0' } }); ok('T1 toggle without CSRF -> 403', r.status === 403 && sql("SELECT active FROM lib_coupons WHERE code='STGOOD1'") === '1');
await S.post('coupons.php', { action: 'toggle', coupon_id: id('STGOOD1'), to: '0' }, 'coupons.php'); ok('T2 toggle off', sql("SELECT active FROM lib_coupons WHERE code='STGOOD1'") === '0');
await S.post('coupons.php', { action: 'toggle', coupon_id: id('STGOOD1'), to: '1' }, 'coupons.php'); ok('T3 toggle on', sql("SELECT active FROM lib_coupons WHERE code='STGOOD1'") === '1');
r = await S.post('coupons.php', { action: 'toggle', coupon_id: '0abc', to: '1' }, 'coupons.php'); ok('T4 bad id -> 400', r.status === 400);
r = await S.post('coupons.php', { action: 'nuke', coupon_id: id('STGOOD1') }, 'coupons.php'); ok('T5 unknown action -> 400', r.status === 400);
sql("UPDATE lib_coupons SET used_count=1 WHERE code='STGOOD1'");
r = await S.post('coupons.php', { action: 'delete', coupon_id: id('STGOOD1') }, 'coupons.php'); ok('T6 used coupon cannot be deleted', cnt('STGOOD1') === '1');
r = await S.req('coupons.php'); ok('T7 flash explains the refusal', r.text.includes('ถูกใช้แล้ว'));
r = await A.post('coupons.php', { action: 'delete', coupon_id: id('STLOW2') }); ok('T8 member cannot delete', r.status === 403 && cnt('STLOW2') === '1');
r = await S.req('coupons.php', { method: 'POST', form: { action: 'delete', coupon_id: id('STLOW2') } }); ok('T9 delete without CSRF refused', r.status === 403 && cnt('STLOW2') === '1');
await S.post('coupons.php', { action: 'delete', coupon_id: id('STLOW2') }, 'coupons.php'); ok('T10 unused coupon deleted', cnt('STLOW2') === '0');

// ===== dashboard: empty database =====
sql('DELETE FROM lib_reviews; DELETE FROM lib_coupon_uses; DELETE FROM lib_loans; DELETE FROM lib_orders');
r = await S.req('dashboard.php');
ok('E1 empty dashboard renders, no PHP errors', r.status === 200 && !/Warning|Fatal|Division|Notice|Deprecated/.test(r.text));
ok('E2 empty states and zero revenue shown', r.text.includes('ยังไม่มีรายได้ในช่วงนี้') && r.text.includes('฿0.00') && r.text.includes('รอตรวจสอบการโอน') && r.text.includes('หนังสือใกล้หมดชั้น'));
for (const q of ['days=abc', 'days[]=7', 'days=999', 'days=0', "days=7' OR 1=1--", 'days=-5', 'days=']) {
  r = await S.req('dashboard.php?' + encodeURI(q)); ok('P1 period garbage ' + q.slice(0, 18) + ' falls back to 30', r.status === 200 && !/Warning|Fatal|SQL syntax/.test(r.text) && r.text.includes('ผลงาน 30 วันล่าสุด'));
}
r = await S.req('dashboard.php?days=7'); ok('P2 days=7 honoured', r.text.includes('ผลงาน 7 วันล่าสุด'));
r = await S.req('dashboard.php?days=90'); ok('P3 days=90 honoured', r.text.includes('ผลงาน 90 วันล่าสุด'));

// ===== dashboard: revenue maths =====
const uid = sql("SELECT user_id FROM lib_users WHERE email='mem@st.test'");
const book = sql('SELECT book_id FROM lib_books ORDER BY book_id LIMIT 1');
const bTitle = sql(`SELECT title FROM lib_books WHERE book_id=${book}`);
const mkOrder = (rent, disc, dep, pay, st, conf) => sql(`INSERT INTO lib_orders (user_id, rent_days, rent_total, discount_total, deposit_total, pay_status, status, pay_confirmed_at, ordered_at) VALUES (${uid}, 10, ${rent}, ${disc}, ${dep}, '${pay}', '${st}', ${conf}, ${conf}); SELECT LAST_INSERT_ID()`);
const o1 = mkOrder(100, 30, 50, 'confirmed', 'open', 'NOW()');                     // counts: 70
mkOrder(500, 0, 50, 'confirmed', 'cancelled', 'NOW()');                            // cancelled: ignored
mkOrder(200, 20, 50, 'confirmed', 'open', 'NOW() - INTERVAL 40 DAY');              // 180, only in the 90 day view
const o4 = mkOrder(300, 0, 50, 'reported', 'open', 'NOW()');                       // not confirmed: ignored
sql(`INSERT INTO lib_loans (order_id, user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit) VALUES (${o1}, ${uid}, ${book}, CURDATE(), CURDATE() + INTERVAL 10 DAY, 'shipped', 10, 50)`);
r = await S.req('dashboard.php?days=7');
ok('R1 revenue = rent - discount of confirmed open orders (70.00)', r.text.includes('฿70.00'), (r.text.match(/฿[\d,]+\.\d\d/g) || []).join(' '));
r = await S.req('dashboard.php?days=30'); ok('R2 30 days still 70.00 (cancelled and unconfirmed ignored)', r.text.includes('฿70.00') && !r.text.includes('฿570.00'));
r = await S.req('dashboard.php?days=90'); ok('R3 90 days adds the older order: 250.00', r.text.includes('฿250.00'));
ok('R4 chart is accessible SVG with title, desc and data table', /<svg class="chart-svg"[^>]*role="img"/.test(r.text) && r.text.includes('<title id="rv-t">') && r.text.includes('<desc id="rv-d">') && /<table class="sr-only"><caption>รายได้รายวัน/.test(r.text));
ok('R5 top books lists the rented title and zone bar shows revenue', r.text.includes(bTitle.replace(/'/g, '&#039;')) && r.text.includes('class="hbar'));
ok('R6 awaiting-payment list shows the reported order', r.text.includes('#' + o4.padStart(5, '0')));
ok('R7 one member with 4 orders -> repeat 100%', /100%<\/b><span>ลูกค้าเช่าซ้ำ/.test(r.text));
sql(`UPDATE lib_loans SET status='returned', returned_at=CURDATE() WHERE order_id=${o1}`);
r = await S.req('dashboard.php?days=30'); ok('R8 on-time return rate 100%', /100%<\/b><span>คืนตรงเวลา/.test(r.text));

// ===== overdue =====
sql(`INSERT INTO lib_loans (order_id, user_id, book_id, borrowed_at, due_date, status, daily_rate, deposit) VALUES (${o1}, ${uid}, ${book}, CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 5 DAY, 'shipped', 4, 50)`);
r = await S.req('overdue.php'); ok('V1 overdue shows days late, fee 20.00, countdown to lost, order link', r.text.includes('5 วัน') && r.text.includes('20.00') && r.text.includes('ถูกตัดเป็นสูญหายในอีก 9 วัน') && r.text.includes('order.php?id=' + o1));
r = await S.req('dashboard.php'); ok('V2 dashboard overdue list links to the order', r.text.includes('order.php?id=' + o1));

// ===== rentals =====
r = await S.req('rentals.php?tab=all'); ok('L1 rentals shows net transfer (70+50=120.00) and zone chip', r.text.includes('฿120.00') && r.text.includes('class="zchip'));

// ===== featured =====
const cat = sql('SELECT MIN(category_id) FROM lib_categories');
const nb = { category_id: cat, title: 'Featured Test', author: 'T', isbn: 'SF-1', description: '', copies_total: '2', rent_per_day: '5', deposit: '50' };
r = await S.post('book-form.php', { ...nb, featured: '1' }, 'book-form.php'); ok('F1 create with featured on', sql("SELECT featured FROM lib_books WHERE isbn='SF-1'") === '1');
const bid = sql("SELECT book_id FROM lib_books WHERE isbn='SF-1'");
r = await S.req('book-form.php?id=' + bid); ok('F2 edit form shows the box checked', /name="featured" value="1" checked/.test(r.text));
r = await S.req('books.php'); ok('F3 list shows the featured badge', r.text.includes('คัดสรร'));
await S.post('book-form.php?id=' + bid, { ...nb, book_id: bid }, 'book-form.php?id=' + bid); ok('F4 edit without the box turns featured off', sql("SELECT featured FROM lib_books WHERE isbn='SF-1'") === '0');
r = await S.req('book-form.php?id=' + bid); ok('F5 box unchecked again', !/name="featured" value="1" checked/.test(r.text));
await S.post('book-form.php?id=' + bid, { ...nb, book_id: bid, featured: 'garbage' }, 'book-form.php?id=' + bid); ok('F6 any featured value means on, stored as 1', sql("SELECT featured FROM lib_books WHERE isbn='SF-1'") === '1');
sql(`INSERT INTO lib_reviews (book_id, user_id, rating, body) VALUES (${bid}, ${uid}, 4, 'x')`);
r = await S.req('books.php'); ok('F7 list shows rating 4.0 (1)', r.text.includes('4.0') && r.text.includes('(1)'));

sql('DELETE FROM lib_reviews');
wipe();
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
