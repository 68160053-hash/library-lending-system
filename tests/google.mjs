// Tests for "Sign in with Google": account rules (PHP) and the OAuth guards of the two pages (HTTP).
// Needs TWO local servers (see README): BASE (no Google settings) and BASE_G (started with fake GOOGLE_* settings).
// Real Google is never logged into: the callback is only given wrong/fake answers and must refuse them.
import { execFileSync } from 'node:child_process';

const BASE = process.env.BASE ?? 'http://127.0.0.1:8092/';
const BASE_G = process.env.BASE_G ?? 'http://127.0.0.1:8093/';
const PHP = process.env.PHP ?? 'C:/xampp/php/php.exe';
const MYSQL = process.env.MYSQL ?? 'C:/xampp/mysql/bin/mysql.exe';
const DBNAME = process.env.DB_NAME ?? 'lib_sec';
if (!/sec|test/.test(DBNAME)) throw new Error('refusing to run: DB_NAME must be a throwaway test database (name contains sec or test)');
const sql = (q) => execFileSync(MYSQL, ['-h', '127.0.0.1', '-P', process.env.DB_PORT ?? '3399', '-u', 'root', '--default-character-set=utf8mb4', '-N', '-B', DBNAME, '-e', q]).toString().trim();

let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => { cond ? pass++ : fail++; console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  [' + extra + ']' : '')); };

// ----- Part 1: account rules, run in PHP against the test database -----
let out = '';
try {
  out = execFileSync(PHP, ['-d', 'mysqli.default_port=' + (process.env.DB_PORT ?? '3399'), 'tests/google_logic.php'], { env: { ...process.env, DB_HOST: '127.0.0.1', DB_USER: 'root', DB_NAME: DBNAME } }).toString();
} catch (e) { out = (e.stdout ?? '').toString() + 'FAIL php script crashed: ' + e.message; }
for (const line of out.split(/\r?\n/)) {
  if (line.startsWith('PASS ')) { pass++; console.log(line); }
  else if (line.startsWith('FAIL ')) { fail++; console.log(line); }
}

// ----- Part 2: HTTP guards -----
function client(base) {
  const jar = {};
  return {
    jar,
    async req(p, { method = 'GET', form } = {}) {
      const h = { Cookie: Object.entries(jar).map(([k, v]) => k + '=' + v).join('; ') };
      let body;
      if (form) { h['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
      const r = await fetch(base + p, { method, headers: h, body, redirect: 'manual' });
      for (const sc of r.headers.getSetCookie()) { const [kv] = sc.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); }
      return { status: r.status, loc: r.headers.get('location') || '', text: await r.text() };
    },
  };
}

// no Google settings: everything is switched off cleanly
let c = client(BASE);
let r = await c.req('login.php');
ok('G1 without Google settings the login page has no Google button', r.status === 200 && !r.text.includes('google-login.php'));
r = await c.req('google-login.php');
ok('G2 google-login.php without settings goes back to login with an "off" message', r.status === 302 && r.loc.includes('google_error=off'));
r = await c.req('google-callback.php?state=abc&code=abc');
ok('G3 google-callback.php without settings refuses', r.status === 302 && r.loc.includes('google_error=off'));
r = await c.req('login.php?google_error=off');
ok('G4 the "off" message is shown as fixed text', r.text.includes('ยังไม่เปิดใช้การเข้าสู่ระบบด้วย Google'));

// with (fake) Google settings
c = client(BASE_G);
r = await c.req('login.php');
ok('G5 with settings, login and register pages show the Google button', r.text.includes('google-login.php') && (await c.req('register.php')).text.includes('google-login.php'));
r = await c.req('google-login.php');
const u = new URL(r.loc || 'http://none/');
const state1 = u.searchParams.get('state'), nonce1 = u.searchParams.get('nonce');
ok('G6 google-login.php redirects to Google with client_id, scope, code flow, our redirect URL', r.status === 302 && u.host === 'accounts.google.com' && u.searchParams.get('client_id') === 'test-client-id.apps.googleusercontent.com' && u.searchParams.get('response_type') === 'code' && /openid/.test(u.searchParams.get('scope')) && u.searchParams.get('redirect_uri') === 'http://127.0.0.1:8093/google-callback.php');
ok('G7 state and nonce are random 32-hex values, different every time', /^[0-9a-f]{32}$/.test(state1) && /^[0-9a-f]{32}$/.test(nonce1) && state1 !== nonce1);
const c2 = client(BASE_G); const r2 = await c2.req('google-login.php');
ok('G8 another visitor gets a different state', new URL(r2.loc).searchParams.get('state') !== state1);

// callback guards
const cb = client(BASE_G);
r = await cb.req('google-callback.php?state=' + state1 + '&code=abc');
ok('G9 callback with no session state at all is refused (login CSRF)', r.loc.includes('google_error=state'));
let v = client(BASE_G); await v.req('google-login.php');
r = await v.req('google-callback.php?state=' + 'f'.repeat(32) + '&code=abc');
ok('G10 callback with a wrong state is refused', r.loc.includes('google_error=state'));
const ls = new URL((await v.req('google-login.php')).loc).searchParams.get('state');
await v.req('google-callback.php?state=wrong&code=x');
r = await v.req('google-callback.php?state=' + ls + '&code=x');
ok('G11 a wrong try burns the state: the right state no longer works afterwards (single use)', r.loc.includes('google_error=state'));
v = client(BASE_G); let st = new URL((await v.req('google-login.php')).loc).searchParams.get('state');
r = await v.req('google-callback.php?state=' + st);
ok('G12 right state but no code is refused', r.loc.includes('google_error=code'));
v = client(BASE_G); st = new URL((await v.req('google-login.php')).loc).searchParams.get('state');
r = await v.req('google-callback.php?state=' + st + '&error=access_denied');
ok('G13 the person pressing Cancel at Google ends back on login with a message', r.loc.includes('google_error=denied'));
v = client(BASE_G); st = new URL((await v.req('google-login.php')).loc).searchParams.get('state');
r = await v.req('google-callback.php?state=' + st + '&code=' + 'a'.repeat(3000));
ok('G14 an absurdly long code is refused before any call to Google', r.loc.includes('google_error=code'));
v = client(BASE_G); st = new URL((await v.req('google-login.php')).loc).searchParams.get('state');
r = await v.req('google-callback.php?state=' + st + '&code=not-a-real-code');
ok('G15 a fake code is rejected by Google and nobody is logged in', r.loc.includes('google_error=token') && (await v.req('my-loans.php')).loc.includes('login.php'));
r = await v.req('login.php?google_error=token');
ok('G16 the token error is shown as a fixed message', r.text.includes('เข้าสู่ระบบด้วย Google ไม่สำเร็จ'));
r = await v.req('login.php?google_error=' + encodeURIComponent('<script>alert(1)</script>'));
ok('G17 an unknown google_error value is never printed', !r.text.includes('<script>alert(1)') && !r.text.includes('alert(1)'));
for (const bad of ['state[]=1', 'state=' + '%00', 'code[]=x&state=y']) {
  r = await client(BASE_G).req('google-callback.php?' + bad);
  ok('G18 odd parameters (' + bad + ') do not crash the callback', r.status < 500, 'status ' + r.status);
}
// a logged-in visitor is not sent to Google
const L = client(BASE_G);
const tok = (await L.req('login.php')).text.match(/name="csrf" value="([0-9a-f]+)"/)[1];
sql("DELETE FROM lib_users WHERE email='gl@g.test'");
const t2 = (await L.req('register.php')).text.match(/name="csrf" value="([0-9a-f]+)"/)[1];
await L.req('register.php', { method: 'POST', form: { full_name: 'Gee El', email: 'gl@g.test', password: 'gee-el-pass-1', password_confirm: 'gee-el-pass-1', csrf: t2 } });
const t3 = (await L.req('login.php')).text.match(/name="csrf" value="([0-9a-f]+)"/)[1];
await L.req('login.php', { method: 'POST', form: { email: 'gl@g.test', password: 'gee-el-pass-1', csrf: t3 } });
r = await L.req('google-login.php');
ok('G19 a logged-in visitor going to google-login.php is sent home, not to Google', r.status === 302 && !r.loc.includes('accounts.google.com'));
sql("DELETE FROM lib_users WHERE email='gl@g.test'; DELETE FROM lib_attempts");

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
