// Tests of the platform pages: notifications (own rows only, CSRF, link whitelist), PWA files, about/faq/404, auth pages.
// Run ONLY against your own throwaway database/server (see tests/shop.mjs for the pattern).
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';

const BASE = process.env.BASE ?? 'http://127.0.0.1:8104/';
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
      let body;
      if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(BASE + p, { method, headers: h, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
      return { status: r.status, loc: r.headers.get('location') || '', type: r.headers.get('content-type') || '', text: await r.text() };
    },
    async token(p = 'index.php') { const r = await c.req(p); const m = r.text.match(/name="csrf" value="([0-9a-f]+)"/); return m ? m[1] : ''; },
    async post(p, form, page = 'index.php') { return c.req(p, { method: 'POST', form: { ...form, csrf: await c.token(page) } }); },
  };
  return c;
}
const cleanup = () => sql("DELETE FROM lib_users WHERE email LIKE '%@pf.test'; DELETE FROM lib_attempts;");
cleanup();

const A = client(), B = client(), anon = client();
for (const [c, n, e] of [[A, 'Pim Platform', 'pim@pf.test'], [B, 'Bo Platform', 'bo@pf.test']]) {
  await c.post('register.php', { full_name: n, email: e, password: 'platform-pass-1', password_confirm: 'platform-pass-1' }, 'register.php');
  await c.post('login.php', { email: e, password: 'platform-pass-1' }, 'login.php');
}
const uid = (m) => sql(`SELECT user_id FROM lib_users WHERE email='${m}'`);
const ua = uid('pim@pf.test'), ub = uid('bo@pf.test');
const ins = (u, kind, msg, link) => sql(`INSERT INTO lib_notifications (user_id, kind, message, link) VALUES (${u}, '${kind}', '${msg}', '${link}')`);
ins(ua, 'paid', 'A-paid-msg', 'order.php?id=7');
ins(ua, 'shipped', 'A-evil-link-msg', 'javascript:alert(1)');
ins(ua, 'info', 'A-xss-<b>x</b>', 'https://evil.test/x.php');
ins(ua, 'weird', 'A-unknown-kind', '../secret.php');
ins(ub, 'info', 'B-private-msg', 'order.php?id=9');
let r;

// ===== notifications =====
r = await anon.req('notifications.php'); ok('N1 guest -> login', r.status === 302 && r.loc.includes('login'));
const unreadA = () => sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ua} AND read_at IS NULL`);
const unreadB = () => sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ub} AND read_at IS NULL`);
ok('N2 four unread before opening', unreadA() === '4');
r = await A.req('notifications.php');
ok('N3 own messages shown, other member\'s not', r.status === 200 && r.text.includes('A-paid-msg') && !r.text.includes('B-private-msg'));
ok('N4 unread rows are highlighted on first view', (r.text.match(/notif-[a-z_]+ unread/g) || []).length === 4);
ok('N5 opening marks own rows read, other member untouched', unreadA() === '0' && unreadB() === '1');
ok('N6 safe link rendered', r.text.includes('href="order.php?id=7"'));
ok('N7 javascript:, absolute and path-traversal links are NOT rendered', !r.text.includes('javascript:') && !r.text.includes('evil.test') && !r.text.includes('../secret'));
ok('N8 message is escaped (XSS)', !r.text.includes('<b>x</b>') && r.text.includes('&lt;b&gt;x&lt;/b&gt;'));
ok('N9 unknown kind still renders (info icon)', r.text.includes('A-unknown-kind'));
r = await A.req('notifications.php'); ok('N10 second view has nothing highlighted', !/notif-[a-z_]+ unread/.test(r.text));
r = await A.req('notifications.php?user_id=' + ub + '&id=' + ub); ok('N11 URL parameters cannot select another member', !r.text.includes('B-private-msg'));
r = await A.req('notifications.php', { method: 'POST', form: { action: 'clear' } }); ok('N12 delete without CSRF token refused', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ua}`) === '4');
r = await A.req('notifications.php', { method: 'POST', form: { action: 'clear', csrf: 'f'.repeat(64) } }); ok('N13 delete with a wrong token refused', r.status === 403 && sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ua}`) === '4');
r = await anon.req('notifications.php', { method: 'POST', form: { action: 'clear', csrf: await anon.token('login.php') } }); ok('N14 guest cannot delete', r.status === 302 && r.loc.includes('login') && sql('SELECT COUNT(*) FROM lib_notifications') === '5');
r = await A.post('notifications.php', { action: 'clear' }, 'notifications.php');
ok('N15 delete all removes only own rows', r.status === 302 && sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ua}`) === '0' && sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ub}`) === '1');
r = await A.req('notifications.php'); ok('N16 empty state', r.text.includes('ยังไม่มีการแจ้งเตือน'));
r = await A.post('notifications.php', { action: '1 OR 1=1' }, 'notifications.php'); ok('N17 unknown action deletes nothing', sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ub}`) === '1');
for (let i = 0; i < 55; i++) ins(ub, 'info', 'bulk-' + i, '');
r = await B.req('notifications.php'); ok('N18 at most 50 shown, newest first', (r.text.match(/class="notif /g) || []).length === 50 && r.text.includes('bulk-54') && !r.text.includes('bulk-0<'));
ok('N19 opening clears the bell even with more than 50 rows', sql(`SELECT COUNT(*) FROM lib_notifications WHERE user_id=${ub} AND read_at IS NULL`) === '0');

// ===== PWA =====
r = await anon.req('manifest.webmanifest');
let man = null; try { man = JSON.parse(r.text); } catch { /* stays null */ }
ok('W1 manifest is valid JSON', r.status === 200 && man !== null);
ok('W2 manifest keys', man && man.name === 'Book Lending Shop' && man.short_name === 'BLS' && man.lang === 'th' && man.display === 'standalone' && man.theme_color === '#12206e' && man.background_color === '#12206e' && man.start_url === 'index.php' && man.scope === './');
ok('W3 manifest uses relative URLs only', man && JSON.stringify(man).indexOf('"/') === -1 && !/https?:/.test(JSON.stringify(man)));
const sizes = (man?.icons ?? []).map((i) => i.sizes + (i.purpose ?? ''));
ok('W4 icons 192, 512 and maskable 512 declared', sizes.includes('192x192any') && sizes.includes('512x512any') && sizes.includes('512x512maskable'));
for (const i of man?.icons ?? []) { const f = await anon.req(i.src); ok('W5 icon reachable ' + i.src, existsSync(i.src) && f.status === 200); }
r = await anon.req('sw.js');
ok('W6 sw.js served as JavaScript', r.status === 200 && /javascript/.test(r.type) && r.text.includes("addEventListener('fetch'"));
ok('W7 sw.js never caches POSTs or HTML pages', /req\.method !== 'GET'/.test(r.text) && /mode === 'navigate'/.test(r.text) && !/c\.put\(req/.test(r.text.split("mode === 'navigate'")[1].split('STATIC.test')[0]));
ok('W8 sw.js has skipWaiting, claim and a versioned cache', /skipWaiting/.test(r.text) && /clients\.claim/.test(r.text) && /bls-static-v\d+/.test(r.text));
r = await anon.req('offline.html'); ok('W9 offline page served', r.status === 200 && r.text.includes('ออฟไลน์'));
r = await anon.req('assets/js/pwa.js'); ok('W10 pwa.js served, feature-detected, install button only on beforeinstallprompt', r.status === 200 && r.text.includes("'serviceWorker' in navigator") && r.text.includes('beforeinstallprompt'));
r = await anon.req('index.php'); ok('W11 header links the manifest', r.text.includes('href="manifest.webmanifest"'));

// ===== about / faq / 404 =====
const clean = (t) => !/(Warning|Notice|Deprecated|Fatal error|Parse error)\b.*(on line|in <b>)/.test(t) && !/<b>(Warning|Notice|Deprecated|Fatal error)<\/b>/.test(t);
for (const p of ['about.php', 'faq.php']) {
  r = await anon.req(p); ok('A1 ' + p + ' 200, no PHP warnings, Thai text', r.status === 200 && clean(r.text) && r.text.includes('มัดจำ'));
}
r = await anon.req('about.php'); ok('A2 about states the real rules', r.text.includes('14 วัน') && r.text.includes('10%') && r.text.includes('15%') && r.text.includes('+7 วัน') && r.text.includes('เลขพัสดุ'));
r = await anon.req('faq.php'); ok('A3 faq uses details/summary groups', (r.text.match(/<details class="qa">/g) || []).length >= 10 && r.text.includes('<summary>'));
ok('A4 no invented contact data or stats on about/faq', !/\d{2,3}-\d{3,4}-\d{4}|@[a-z]+\.(com|co\.th)|ลูกค้ากว่า|รีวิว/.test((await anon.req('about.php')).text + r.text));
r = await anon.req('this-page-does-not-exist-404.php'); ok('A5 unknown .php is a plain 404 (server default), not a crash', r.status === 404);
r = await anon.req('404.php'); ok('A6 404.php answers 404 with the branded page', r.status === 404 && r.text.includes('หาหน้านี้ไม่เจอ') && clean(r.text));

// ===== auth pages keep their contract =====
r = await anon.req('login.php');
ok('U1 login fields and autocomplete kept', /id="email" name="email" autocomplete="username"/.test(r.text) && /id="password" name="password" autocomplete="current-password"/.test(r.text) && r.text.includes('data-pw-toggle="password"') && !/remember|จำฉันไว้/i.test(r.text));
r = await anon.req('register.php');
ok('U2 register fields, hint and strength hook kept', r.text.includes('autocomplete="new-password" minlength="8" maxlength="72"') && r.text.includes('อย่างน้อย 8 ตัวอักษร') && r.text.includes('data-strength') && r.text.includes('name="password_confirm"'));
r = await A.req('profile.php'); ok('U3 profile keeps both forms and the strength hook', r.text.includes('name="action" value="profile"') && r.text.includes('name="action" value="password"') && r.text.includes('data-strength') && clean(r.text));
r = await A.req('logout.php'); ok('U4 GET logout only asks, does not log out', r.status === 200 && (await A.req('my-orders.php')).status === 200);

cleanup();
sql(`DELETE FROM lib_notifications WHERE user_id IN (${ua}, ${ub})`);
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
