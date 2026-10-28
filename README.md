# Book Lending Shop · ร้านยืมหนังสือ

โปรเจกต์ปลายภาค วิชาการจัดการฐานข้อมูล (มหาวิทยาลัยบูรพา)

> โครง README: เติมส่วนที่ยังเป็น TODO ตอน Milestone 2

## ปัญหาและเป้าหมาย
TODO: สรุป 2-3 บรรทัดจาก `docs/proposal.md`

## ฟีเจอร์
- สมาชิก: ดูหนังสือ, ยืม, ต่ออายุ (1 ครั้ง), ดูประวัติและค่าปรับของตัวเอง, ซื้อหนังสือ
- เจ้าหน้าที่: จัดการหนังสือ, รับคืน, ดูรายการเกินกำหนด, จัดการคำสั่งซื้อ
- แอดมิน: ทุกอย่างของเจ้าหน้าที่ + จัดการบทบาทผู้ใช้

## เทคโนโลยี
PHP 8 (procedural mysqli, session), MySQL 8, HTML/CSS/JS, โฮสต์ที่ Angsila

## ติดตั้ง
1. import `sql/schema.sql` ผ่าน phpMyAdmin
2. คัดลอก `secret/config.example.php` เป็น `secret/config.php` แล้วใส่ค่าฐานข้อมูลของตัวเอง (ไฟล์นี้ถูก git-ignore และ `secret/.htaccess` บล็อกการเข้าทางเว็บ)
3. สมัครสมาชิกที่ `register.php` (บัญชีใหม่ทุกบัญชีเป็น member) ไม่มีผู้ใช้ตัวอย่างหรือรหัสผ่านใน repo
4. ถ้าต้องการบัญชีเจ้าหน้าที่ ให้รันใน phpMyAdmin: `UPDATE lib_users SET role = 'staff' WHERE email = 'อีเมลของคุณ';` แล้วเข้าสู่ระบบใหม่

## ความปลอดภัย
- รหัสผ่านเก็บด้วย bcrypt (`password_hash` / `password_verify`)
- ทุกคำสั่ง SQL ที่มีค่าจากผู้ใช้ใช้ prepared statement
- `session_regenerate_id(true)` ตอนเข้าสู่ระบบ และคุกกี้ session เป็น HttpOnly / SameSite=Lax
- CSRF token ในทุกฟอร์ม POST รวมถึงออกจากระบบ (logout เป็น POST ไม่ใช่ลิงก์)
- `htmlspecialchars` (ฟังก์ชัน `h()`) ทุกค่าที่แสดงบนหน้า
- ตรวจบทบาทที่ฝั่งเซิร์ฟเวอร์ (`require_staff()`) ไม่ได้อาศัยแค่ซ่อนเมนู
- ใส่ `user_id` ของเจ้าของใน WHERE (เช่น ต่ออายุ) สมาชิกจึงแก้ `loan_id` เพื่อต่ออายุของคนอื่นไม่ได้
- ความลับ (`secret/config.php`, `.env`) ถูก git-ignore

- สิทธิ์เจ้าหน้าที่อ่านจากฐานข้อมูลทุกครั้งที่เปิดหน้า staff ลดบทบาทแล้วมีผลทันที
- จำกัดจำนวนครั้ง เก็บในตาราง `lib_attempts` (ล้างคุกกี้แล้วก็หนีไม่พ้น): ล็อกอินผิด 5 ครั้งต่ออีเมล หรือ 30 ครั้งต่อ IP ใน 15 นาทีโดนล็อก, สมัครได้ 10 ครั้งต่อ IP ต่อชั่วโมง
- ส่วนหัวความปลอดภัยทุกหน้า: CSP (รันเฉพาะสคริปต์ของเราเอง), X-Frame-Options DENY, nosniff, Referrer-Policy, ซ่อน X-Powered-By, `Cache-Control: no-store` เมื่อล็อกอิน
- session หมดอายุเมื่อไม่ใช้ 30 นาทีหรือครบ 8 ชั่วโมง, เปลี่ยน session id และ CSRF token ตอนล็อกอิน
- รหัสผ่านห้ามมีอักขระควบคุม (bcrypt อ่านถึงแค่ NUL)

## ทดสอบความปลอดภัย
`tests/sectest.mjs` (32 ข้อ ตรวจฟังก์ชันหลัก) และ `tests/pentest.mjs` (106 ข้อ จำลองการโจมตี: SQL injection, XSS, CSRF, session, IDOR, race condition, brute force ฯลฯ) รันกับฐานข้อมูลทดลองเท่านั้น: import `sql/schema.sql` เข้า DB `lib_sec` พอร์ต 3399 แล้วรัน
`DB_HOST=127.0.0.1 DB_USER=root DB_NAME=lib_sec php -d mysqli.default_port=3399 -S 127.0.0.1:8092` แล้ว `node tests/sectest.mjs` และ `node tests/pentest.mjs` (ตั้ง `SESS_DIR` ถ้าโฟลเดอร์ session ไม่ใช่ `C:/xampp/tmp`)
ก่อน merge PR ของเพื่อนให้รันซ้ำทุกครั้ง

## กติกาเขียนโค้ดสำหรับเพื่อน (ตอนทำ PR)
- ยืมหนังสือ: ตัดจำนวนเล่มด้วย UPDATE เดียว `UPDATE lib_books SET copies_available = copies_available - 1 WHERE book_id = ? AND copies_available > 0` แล้วดู `affected_rows` (ถ้า 0 = เล่มหมด) และทำ INSERT lib_loans ใน transaction เดียวกัน ห้าม SELECT ดูก่อนแล้วค่อย UPDATE
- ทุก UPDATE/DELETE ใส่ id ของเจ้าของใน WHERE และหน้า staff เรียก `require_staff()` (ต้อง include `config/db.php` ก่อน)

## เอกสาร
- `docs/milestone1.md` ขอบเขต, use case, ER, data dictionary, แผนงาน
- `docs/proposal.md` ข้อเสนอโครงการ
- `docs/design.md` ดีไซน์และ VFX
- `prototype/` หน้าเว็บต้นแบบ HTML/CSS/JS

## ลิงก์
- หน้าเว็บต้นแบบ (GitHub Pages): https://68160053-hash.github.io/library-lending-system/prototype/index.html
- GitHub: https://github.com/68160053-hash/library-lending-system
- Angsila: TODO (Milestone 2)

## ทีม
| รหัส | ชื่อ | หน้าที่ |
|---|---|---|
| 68160053 | นายวัณณุวรรธน์ นพสมบูรณ์ | หน้าเว็บ/UI, ระบบความปลอดภัย, ประสานงาน, deploy |
| 68160203 | นายภัทรพล เครือแตง | ฐานข้อมูล, จัดการหนังสือ |
| 68160382 | นายเตชินท์ หิมทอง | ระบบยืม-คืน, ร้านขาย |
