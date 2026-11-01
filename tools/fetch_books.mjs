// One-off helper: pull real book data from Open Library and write sql/seed_books.sql.
// Run it on your own PC (not on the server): node tools/fetch_books.mjs
// Check the generated SQL by eye, then import it in phpMyAdmin after sql/schema.sql.
import fs from 'node:fs';

// category name in lib_categories -> Open Library search query
const topics = [
  ['นิยายแฟนตาซี', 'subject_key:fantasy'],
  ['วิทยาศาสตร์', 'subject_key:popular_works AND subject_key:science'],
  ['ประวัติศาสตร์', 'subject_key:world_history'],
  ['วรรณกรรมคลาสสิก', 'subject_key:classic_literature'],
  ['คอมพิวเตอร์', 'subject_key:computer_programming'],
];
const PER_TOPIC = 8;

const esc = (s) => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "''") + "'";
const rows = [];
const seen = new Set();

for (const [cat, q] of topics) {
  const url = 'https://openlibrary.org/search.json?q=' + encodeURIComponent(q + ' language:eng') +
    '&sort=readinglog&limit=40&fields=title,author_name,isbn,first_publish_year';
  const res = await fetch(url, { headers: { 'User-Agent': 'course-project-seed/1.0' } });
  const data = await res.json();
  let n = 0;
  for (const d of data.docs ?? []) {
    if (n >= PER_TOPIC) break;
    const isbn = (d.isbn ?? []).find((x) => /^97[89]\d{10}$/.test(x)); // ISBN-13 only
    if (!isbn || seen.has(isbn) || !d.title || !d.author_name) continue;
    if (d.title.length > 200 || d.author_name[0].length > 120) continue;
    if (/[Ͱ-῿⺀-鿿]/.test(d.title)) continue; // skip titles the page fonts cannot show (Cyrillic, Devanagari, ...)
    if (/[⺀-鿿]/.test(d.author_name[0])) continue; // author written in Chinese/Japanese characters
    if (/Mein Kampf|Bachman|Running Man|Haunting Adeline/i.test(d.title)) continue; // not suitable for a shop demo
    seen.add(isbn);
    // simple deterministic pricing so the data is repeatable: 5-14 baht/day, deposit = 20 days of rent
    const rent = 5 + (rows.length * 7) % 10;
    const total = 2 + (rows.length % 3);
    rows.push({ cat, title: d.title, author: d.author_name[0], isbn, rent, deposit: rent * 20, total });
    n++;
  }
  console.log(cat, n, 'books');
}

let sql = '-- สร้างอัตโนมัติจาก Open Library ด้วย tools/fetch_books.mjs (ข้อมูลหนังสือจริง ราคาเช่าและจำนวนเล่มเป็นค่าสมมติ)\n';
sql += '-- รันหลัง schema.sql รันซ้ำได้ (ISBN ซ้ำจะถูกข้าม)\n';
sql += 'INSERT INTO lib_books (category_id, title, author, isbn, copies_total, copies_available, rent_per_day, deposit) VALUES\n';
sql += rows.map((r) => `  ((SELECT category_id FROM lib_categories WHERE name=${esc(r.cat)}), ${esc(r.title)}, ${esc(r.author)}, ${esc(r.isbn)}, ${r.total}, ${r.total}, ${r.rent}, ${r.deposit})`).join(',\n');
sql += '\nON DUPLICATE KEY UPDATE isbn = isbn;\n';
fs.writeFileSync(new URL('../sql/seed_books.sql', import.meta.url), sql, 'utf8');
console.log('wrote', rows.length, 'rows to sql/seed_books.sql');
