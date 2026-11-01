// One-off helper: pull real book data from Open Library, save the cover pictures, and write sql/seed_books.sql.
// Run it on your own PC (not on the server): node tools/fetch_books.mjs
// Check the generated SQL by eye, then import it in phpMyAdmin after sql/schema.sql. Cover pictures go to assets/covers/<isbn>.jpg.
import fs from 'node:fs';

// category name in lib_categories -> Open Library search query
const topics = [
  ['นิยายแฟนตาซี', 'subject_key:fantasy'],
  ['วิทยาศาสตร์', 'subject_key:popular_works AND subject_key:science'],
  ['ประวัติศาสตร์', 'subject_key:world_history'],
  ['วรรณกรรมคลาสสิก', 'subject_key:classic_literature'],
  ['คอมพิวเตอร์', 'subject_key:computer_programming'],
];
const PER_TOPIC = 12;
const UA = { 'User-Agent': 'course-project-seed/1.0 (student project)' };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(new URL('../assets/covers/', import.meta.url), { recursive: true });

const esc = (s) => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "''") + "'";
const rows = [];
const seen = new Set();

// try the ISBNs of an edition until one has a real cover picture (Open Library answers 404 when there is none)
async function saveCover(isbns) {
  for (const isbn of isbns.slice(0, 4)) {
    const r = await fetch(`https://covers.openlibrary.org/b/isbn/${isbn}-M.jpg?default=false`, { headers: UA });
    if (!r.ok) continue;
    const buf = Buffer.from(await r.arrayBuffer());
    if (buf.length < 4000 || buf[0] !== 0xff) continue; // not a real JPEG picture
    fs.writeFileSync(new URL(`../assets/covers/${isbn}.jpg`, import.meta.url), buf);
    return isbn;
  }
  return null;
}
async function describe(workKey) {
  try {
    const r = await fetch(`https://openlibrary.org${workKey}.json`, { headers: UA });
    const j = await r.json();
    let d = typeof j.description === 'string' ? j.description : j.description?.value;
    if (!d) return '';
    d = d.split(/\r?\n\r?\n/)[0].replace(/\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/\s+/g, ' ').trim(); // first paragraph, links removed
    return d.length > 700 ? d.slice(0, 697) + '...' : d;
  } catch { return ''; }
}

for (const [cat, q] of topics) {
  const url = 'https://openlibrary.org/search.json?q=' + encodeURIComponent(q + ' language:eng') +
    '&sort=readinglog&limit=60&fields=key,title,author_name,isbn,first_publish_year';
  const data = await (await fetch(url, { headers: UA })).json();
  let n = 0;
  for (const d of data.docs ?? []) {
    if (n >= PER_TOPIC) break;
    const all = (d.isbn ?? []).filter((x) => /^97[89]\d{10}$/.test(x)); // ISBN-13 only
    if (!all.length || !d.title || !d.author_name || !d.key) continue;
    if (d.title.length > 200 || d.author_name[0].length > 120) continue;
    if (/[Ͱ-῿⺀-鿿]/.test(d.title) || /[⺀-鿿]/.test(d.author_name[0])) continue; // titles/authors the page font cannot show
    if (/Mein Kampf|Bachman|Running Man|Haunting Adeline/i.test(d.title)) continue; // not suitable for a shop demo
    const isbn = await saveCover(all);
    if (!isbn || seen.has(isbn)) continue;                                  // keep only books that have a real cover
    seen.add(isbn);
    const description = await describe(d.key);
    await sleep(150);
    // simple deterministic pricing so the data is repeatable: 5-14 baht/day, deposit = 20 days of rent
    const rent = 5 + (rows.length * 7) % 10;
    const total = 2 + (rows.length % 3);
    rows.push({ cat, title: d.title, author: d.author_name[0], isbn, description, rent, deposit: rent * 20, total });
    n++;
  }
  console.log(cat, n, 'books');
}

let sql = '-- สร้างอัตโนมัติจาก Open Library ด้วย tools/fetch_books.mjs (ข้อมูลหนังสือและเรื่องย่อจริง ราคาเช่าและจำนวนเล่มเป็นค่าสมมติ)\n';
sql += '-- รันหลัง schema.sql รันซ้ำได้ (ISBN ซ้ำจะถูกข้าม) รูปปกอยู่ที่ assets/covers/<isbn>.jpg\n';
sql += 'INSERT INTO lib_books (category_id, title, author, isbn, description, copies_total, copies_available, rent_per_day, deposit) VALUES\n';
sql += rows.map((r) => `  ((SELECT category_id FROM lib_categories WHERE name=${esc(r.cat)}), ${esc(r.title)}, ${esc(r.author)}, ${esc(r.isbn)}, ${r.description ? esc(r.description) : 'NULL'}, ${r.total}, ${r.total}, ${r.rent}, ${r.deposit})`).join(',\n');
sql += '\nON DUPLICATE KEY UPDATE isbn = isbn;\n';
fs.writeFileSync(new URL('../sql/seed_books.sql', import.meta.url), sql, 'utf8');
console.log('wrote', rows.length, 'rows to sql/seed_books.sql');
