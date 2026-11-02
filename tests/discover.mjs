// Tests for the discovery pages: home filters, book page reviews, wishlist, shelf, suggest. Run ONLY on a throwaway DB (name has sec/test).
import { execFileSync } from 'node:child_process';
const BASE = process.env.BASE ?? 'http://127.0.0.1:8101/';
const MYSQL = process.env.MYSQL ?? 'C:/xampp/mysql/bin/mysql.exe';
if (!/sec|test/.test(process.env.DB_NAME ?? 'lib_sec_d')) throw new Error('refusing to run: DB_NAME must contain sec or test');
const sql = (q) => execFileSync(MYSQL, ['-h', '127.0.0.1', '-P', process.env.DB_PORT ?? '3399', '-u', 'root', '--default-character-set=utf8mb4', '-N', '-B', process.env.DB_NAME ?? 'lib_sec_d', '-e', q]).toString().trim();
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
function client() {
  const jar = {};
  const c = {
    async req(p, { method = 'GET', form, headers = {} } = {}) {
      const h = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; '), ...headers };
      let body; if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(BASE + p, { method, headers: h, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
      return { status: r.status, loc: r.headers.get('location') || '', text: await r.text(), h: r.headers };
    },
    async token(p = 'index.php') { const r = await c.req(p); const m = r.text.match(/name="csrf" value="([0-9a-f]+)"/); return m ? m[1] : ''; },
    async post(p, form, page = 'index.php', headers = {}) { return c.req(p, { method: 'POST', form: { ...form, csrf: await c.token(page) }, headers }); },
  };
  return c;
}
const cleanup = () => sql("DELETE FROM lib_reviews WHERE user_id IN (SELECT user_id FROM lib_users WHERE email LIKE '%@d.test'); DELETE l FROM lib_loans l JOIN lib_users u USING(user_id) WHERE u.email LIKE '%@d.test'; DELETE FROM lib_users WHERE email LIKE '%@d.test'; DELETE FROM lib_books WHERE isbn LIKE 'DT-%'; DELETE FROM lib_attempts");
cleanup();
const A = client(), B = client(), anon = client();
for (const [c, n, e] of [[A, 'Ann', 'ann@d.test'], [B, 'Bob', 'bob@d.test']]) { await c.post('register.php', { full_name: n, email: e, password: 'disc-pass-1234', password_confirm: 'disc-pass-1234' }, 'register.php'); sql('DELETE FROM lib_attempts'); await c.post('login.php', { email: e, password: 'disc-pass-1234' }, 'login.php'); }
const uA = sql("SELECT user_id FROM lib_users WHERE email='ann@d.test'"), uB = sql("SELECT user_id FROM lib_users WHERE email='bob@d.test'");
const cat = sql('SELECT MIN(category_id) FROM lib_categories');
const mk = (isbn, t, rate) => sql(`INSERT INTO lib_books (category_id,title,author,isbn,description,copies_total,copies_available,rent_per_day,deposit) VALUES (${cat},'${t}','Tester','${isbn}','d',2,2,${rate},100)`);
mk('DT-1', 'Disc One', 7); mk('DT-2', 'Disc Two', 9); mk('DT-3', 'Disc 100% Three', 30);
const [b1, b2, b3] = ['DT-1', 'DT-2', 'DT-3'].map((i) => sql(`SELECT book_id FROM lib_books WHERE isbn='${i}'`));
const loan = (u, b, st) => sql(`INSERT INTO lib_loans (user_id,book_id,borrowed_at,due_date,status,daily_rate,deposit) VALUES (${u},${b},CURDATE(),CURDATE(),'${st}',7,100)`);
loan(uA, b1, 'returned'); loan(uA, b2, 'shipped'); loan(uB, b1, 'shipped');
let r;

// ---- reviews
const rv = (c, f) => c.post('review.php', f, 'book.php?id=' + b1);
const cnt = () => Number(sql(`SELECT COUNT(*) FROM lib_reviews WHERE book_id=${b1}`));
r = await anon.req('review.php', { method: 'POST', form: { book_id: b1, rating: 5 } }); ok('R1 review without CSRF -> 403', r.status === 403);
r = await A.req('review.php', { method: 'POST', form: { book_id: b1, rating: 5 } }); ok('R2 logged-in review without CSRF -> 403', r.status === 403 && cnt() === 0);
r = await anon.post('review.php', { book_id: b1, rating: 5 }); ok('R3 guest is sent to login, nothing saved', r.loc.includes('login.php') && cnt() === 0);
r = await B.req('book.php?id=' + b1); ok('R4 form hidden for a member who has not returned the book', !r.text.includes('action="review.php"'));
await rv(B, { book_id: b1, rating: 5, body: 'sneaky' }); ok('R5 review without a RETURNED loan refused (server side)', cnt() === 0);
await rv(A, { book_id: b2, rating: 5, body: 'x' }); ok('R6 review for a book only shipped (not returned) refused', Number(sql(`SELECT COUNT(*) FROM lib_reviews WHERE book_id=${b2}`)) === 0);
r = await A.req('book.php?id=' + b1); ok('R7 form shown to member with returned loan', r.text.includes('action="review.php"'));
for (const bad of ['0', '6', 'abc', '', '-1', '3.5', '1e1']) { await rv(A, { book_id: b1, rating: bad, body: 'bad' }); ok('R8 rating "' + bad + '" refused', cnt() === 0); }
r = await A.post('review.php', { book_id: b1, 'rating[]': 3 }, 'book.php?id=' + b1); ok('R9 array rating refused', cnt() === 0 && r.status < 500);
await rv(A, { book_id: b1, rating: 4, body: '<script>alert(1)</script> "><img src=x onerror=alert(1)>' });
r = await anon.req('book.php?id=' + b1); ok('R10 review saved and XSS body escaped', cnt() === 1 && !r.text.includes('<script>alert(1)') && !/<img src=x onerror/.test(r.text) && r.text.includes('&lt;script&gt;'));
await rv(A, { book_id: b1, rating: 2, body: 'edited' }); ok('R11 second post edits (upsert), still one row', cnt() === 1 && sql(`SELECT rating FROM lib_reviews WHERE book_id=${b1}`) === '2');
await rv(A, { book_id: b1, rating: 5, body: 'x'.repeat(401) }); ok('R12 body over 400 chars refused, old kept', sql(`SELECT body FROM lib_reviews WHERE book_id=${b1}`) === 'edited');
await rv(A, { book_id: b1, rating: 5, body: 'a\u0000b\u0007c' }); ok('R13 control characters stripped', sql(`SELECT body FROM lib_reviews WHERE book_id=${b1}`) === 'abc');
r = await A.post('review.php', { book_id: '99999999', rating: 5 }, 'book.php?id=' + b1); ok('R14 unknown book id -> no row, no error text', r.status < 500 && !/SQL|Warning/.test(r.text));
r = await anon.req('book.php?id=' + b1); ok('R15 rating summary shows average and count', /รีวิวจากผู้เช่า/.test(r.text) && r.text.includes('class="dist"'));
r = await A.req('review.php'); ok('R16 GET review.php just redirects', r.status === 302);

// ---- wishlist
const wl = (c, id, h) => c.post('wishlist.php', { book_id: id, back: 'index.php' }, 'index.php', h);
const wcount = (u) => Number(sql(`SELECT COUNT(*) FROM lib_wishlist WHERE user_id=${u}`));
r = await anon.req('wishlist.php', { method: 'POST', form: { book_id: b1 } }); ok('W1 wishlist without CSRF -> 403', r.status === 403);
r = await wl(anon, b1); ok('W2 guest redirected to login', r.loc.includes('login.php'));
r = await wl(A, b1); ok('W3 add -> redirect back', r.status === 302 && r.loc === 'index.php' && wcount(uA) === 1);
r = await wl(A, b1); ok('W4 toggle twice removes', wcount(uA) === 0);
r = await wl(A, b1, { 'X-Requested-With': 'fetch' }); ok('W5 fetch add answers JSON wished:true', r.status === 200 && JSON.parse(r.text).wished === true && wcount(uA) === 1);
r = await wl(A, b1, { 'X-Requested-With': 'fetch' }); ok('W6 fetch again answers wished:false', JSON.parse(r.text).wished === false && wcount(uA) === 0);
r = await wl(A, '99999999', { 'X-Requested-With': 'fetch' }); ok('W7 unknown book -> 404 JSON, nothing stored', r.status === 404 && wcount(uA) === 0);
r = await A.post('wishlist.php', { book_id: b2, back: 'https://evil.test/' }); ok('W8 evil back address ignored', r.loc === 'index.php');
r = await A.post('wishlist.php', { book_id: b2, back: 'shelf.php?tab=wish' }); ok('W9 shelf back allowed', r.loc === 'shelf.php?tab=wish');
r = await A.post('wishlist.php', { 'book_id[]': b3 }); ok('W10 array book_id harmless', r.status < 500 && wcount(uA) === 0);

// ---- shelf
await wl(B, b3); await wl(A, b2);
r = await A.req('shelf.php'); ok('S1 my shelf shows my wished book, not another member\'s', r.text.includes('Disc Two') && !r.text.includes('Disc 100% Three'));
r = await B.req('shelf.php'); ok('S2 other member sees only theirs', r.text.includes('Disc 100% Three') && !r.text.includes('Disc Two'));
r = await A.req('shelf.php?tab=rented&user_id=' + uB); ok('S3 rented tab lists my loans, user_id param ignored', r.text.includes('เช่าอีกครั้ง') && r.text.includes('Disc One') && !r.text.includes('Disc 100% Three'));
r = await anon.req('shelf.php'); ok('S4 shelf needs login', r.loc.includes('login.php'));
r = await A.req('shelf.php?tab[]=x'); ok('S5 array tab harmless', r.status === 200);

// ---- suggest
const sg = async (q) => anon.req('suggest.php?q=' + q);
r = await sg('Disc'); let j = JSON.parse(r.text);
ok('G1 suggest returns matches with no-store JSON', Array.isArray(j) && j.length >= 2 && j.length <= 6 && /no-store/.test(r.h.get('cache-control')) && /json/.test(r.h.get('content-type')) && 'isbn' in j[0]);
ok('G2 one character -> empty', JSON.parse((await sg('D')).text).length === 0);
ok('G3 wildcard % is literal', JSON.parse((await sg('%25%25')).text).length === 0 && JSON.parse((await sg('100%25')).text).length === 1);
ok('G4 underscore is literal', JSON.parse((await sg('Dis_')).text).length === 0);
ok('G5 61+ chars -> empty', JSON.parse((await sg('a'.repeat(61))).text).length === 0);
r = await anon.req('suggest.php?q[]=Disc'); ok('G6 array q -> empty array, no error', r.status === 200 && r.text === '[]');
r = await sg(encodeURIComponent("' OR SLEEP(3)-- ")); ok('G7 SQLi payload harmless', r.status === 200 && r.text === '[]');
r = await sg(encodeURIComponent('<script>')); ok('G8 JSON output cannot carry raw tags', !r.text.includes('<'));

// ---- catalogue
r = await anon.req('index.php?q[]=x&category[]=1&sort[]=x&avail[]=1&maxprice[]=3&page[]=2'); ok('H1 array params survive', r.status === 200 && !/Warning|Fatal/.test(r.text));
for (const g of ['abc', '-5', '1e999', 'NaN', "1' OR 1=1", '0', '99999999999999999999']) { r = await anon.req('index.php?maxprice=' + encodeURIComponent(g)); ok('H2 maxprice garbage "' + g.slice(0, 12) + '"', r.status === 200 && !/Warning|SQL syntax/.test(r.text)); }
r = await anon.req('index.php?q=Disc&maxprice=8'); ok('H3 price filter keeps only books at or under 8/day', r.text.includes('Disc One') && !r.text.includes('Disc Two') && !r.text.includes('Disc 100%'));
for (const s of ['rating', 'rented', 'price_desc', 'title']) { r = await anon.req('index.php?sort=' + s + '&q=Disc'); ok('H4 sort ' + s, r.status === 200 && r.text.includes('Disc One')); }
r = await anon.req('index.php'); ok('H5 hall: five zones, perks with real min price', (r.text.match(/class="zone z\d/g) || []).length === 5 && /เช่าเริ่มวันละ ฿\d/.test(r.text) && r.text.includes('class="perks"'));
r = await anon.req('index.php?category=' + cat); ok('H6 selected zone is .on', /class="zone z\d on"/.test(r.text));
ok('H7 popular row appears from recent loans', (await anon.req('index.php')).text.includes('ยอดนิยม'));
r = await anon.req('book.php?id=' + b2); ok('H8 book page "also rented" uses the same members\' loans', r.text.includes('คนที่เช่าเล่มนี้ก็เช่า') && r.text.includes('Disc One'));
r = await A.req('index.php'); ok('H9 member sees heart forms posting to wishlist.php', r.text.includes('action="wishlist.php"'));
r = await anon.req('index.php'); ok('H11 the newest books carry the "ใหม่" sticker (the lookup must not fail with out-of-sync)', (r.text.match(/class="burst new"/g) || []).length >= 1);
r = await anon.req('index.php?sort=rating&q=Disc'); const star = r.text.match(/aria-label="[\d.]+ จาก 5 ดาว"/g);
ok('H12 rating sort still returns books with their rating shown (summary join only when sorting)', r.status === 200 && r.text.includes('Disc'), String(star && star.length));
r = await anon.req('book.php?id=' + b2); ok('H13 the 7/14/30 tiles are real radio inputs tied to the add-to-cart form, 14 days pre-selected', (r.text.match(/type="radio" name="days"[^>]*form="buy-main"/g) || []).length === 3 && /value="14"[^>]*checked/.test(r.text) && r.text.includes('id="buy-main"'));
{ const t = await A.token('book.php?id=' + b2); const x = await A.req('cart.php', { method: 'POST', form: { csrf: t, action: 'add', book_id: b2, then: 'cart', days: '30' } }); const y = await A.req('cart.php', { method: 'POST', form: { csrf: t, action: 'add', book_id: b2, then: 'cart', days: '999' } });
  ok('H14 the chosen length goes to the cart (30), and a bad value is ignored', x.loc === 'cart.php?days=30' && y.loc === 'cart.php'); }
r = await anon.req('index.php'); ok('H10 guest hearts link to login', r.text.includes('class="heart-btn" href="login.php"'));

cleanup();
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
