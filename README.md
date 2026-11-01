# Book Lending Shop · ร้านยืมหนังสือ

โปรเจกต์ปลายภาค วิชาการจัดการฐานข้อมูล (มหาวิทยาลัยบูรพา)

> โครง README: เติมส่วนที่ยังเป็น TODO ตอน Milestone 2

## ปัญหาและเป้าหมาย
TODO: สรุป 2-3 บรรทัดจาก `docs/proposal.md`

## ฟีเจอร์ (ร้านเช่าหนังสือออนไลน์)
- สมาชิก: ดูและค้นหาหนังสือ, เช่าพร้อมกรอกที่อยู่จัดส่ง, ต่ออายุ (1 ครั้ง), ดูประวัติ ค่าปรับ และสถานะพัสดุของตัวเอง
- เจ้าหน้าที่: จัดการหนังสือ (เพิ่ม/แก้/ลบ ตั้งราคาเช่าต่อวันและค่ามัดจำ), จัดการการเช่า (ส่งพัสดุ → รับคืน หรือแจ้งสูญหาย), ดูรายการเกินกำหนด
- แอดมิน: ทุกอย่างของเจ้าหน้าที่ + หน้า "จัดการผู้ใช้" เปลี่ยนบทบาทสมาชิก/เจ้าหน้าที่/แอดมิน (เปลี่ยนบทบาทตัวเองไม่ได้ จะได้ไม่เหลือระบบไม่มีแอดมิน)
- สมาชิกยกเลิกการเช่าของตัวเองได้ตราบที่ยังเป็น "รอส่งพัสดุ" (เล่มกลับเข้าชั้น)

### กติกาการเช่า
- เช่าได้ 7 วัน นับจากวันที่เจ้าหน้าที่ส่งพัสดุ ต่ออายุได้ 1 ครั้ง (+7 วัน) ถ้ายังไม่เกินกำหนด
- ค่าเช่าคิดรายวัน ราคาของเล่มนั้น ณ ตอนเช่า (เก็บสำเนาไว้ในรายการเช่า ราคาเปลี่ยนทีหลังก็ไม่กระทบ)
- เก็บค่ามัดจำตอนเช่า ถ้าคืนล่าช้า ค่าปรับ = จำนวนวันที่เกิน x ราคาเช่าต่อวัน หักจากมัดจำ ที่เหลือคืนให้ ถ้าหนังสือสูญหายไม่คืนมัดจำ
- ค่าปรับและเงินมัดจำที่คืนคำนวณจากวันที่ตอนแสดงผล ไม่เก็บซ้ำในตาราง
- สถานะรายการเช่า: รอส่งพัสดุ → ส่งแล้ว → คืนแล้ว หรือ สูญหาย (แต่ละปุ่มเปลี่ยนได้เฉพาะจากสถานะก่อนหน้า กดซ้ำไม่มีผล)
- เช่าเล่มสุดท้ายพร้อมกันสองคน ได้คนเดียวเสมอ (UPDATE เดียวตรวจและลดจำนวนเล่ม)

## เทคโนโลยี
PHP 8 (procedural mysqli, session), MySQL 8, HTML/CSS/JS, โฮสต์ที่ Angsila

## ติดตั้ง
1. import `sql/schema.sql` แล้วตามด้วย `sql/seed_books.sql` (หนังสือจริง 40 เล่มจาก Open Library, สร้างด้วย `node tools/fetch_books.mjs` บนเครื่องตัวเอง) ผ่าน phpMyAdmin บน Angsila: MySQL 8.0.46, PHP 8.4, ชื่อฐานข้อมูลคือ `s` + รหัสนักศึกษา
2. คัดลอก `secret/config.example.php` เป็น `secret/config.php` แล้วใส่ค่าฐานข้อมูลของตัวเอง (host `localhost`, user และ name เป็น `s68160053`) (ไฟล์นี้ถูก git-ignore และ `secret/.htaccess` บล็อกการเข้าทางเว็บ)
3. สมัครสมาชิกที่ `register.php` (บัญชีใหม่ทุกบัญชีเป็น member) ไม่มีผู้ใช้ตัวอย่างหรือรหัสผ่านใน repo
4. ถ้าต้องการบัญชีเจ้าหน้าที่ ให้รันใน phpMyAdmin: `UPDATE lib_users SET role = 'admin' WHERE email = 'อีเมลของคุณ';` (แอดมินคนแรกต้องตั้งผ่าน SQL ครั้งเดียว) แล้วเข้าสู่ระบบใหม่ จากนั้นตั้งบทบาทคนอื่นได้ที่หน้า "จัดการผู้ใช้"

## เข้าสู่ระบบด้วย Google (ไม่บังคับ)
ถ้าไม่ตั้งค่า ปุ่ม Google จะซ่อนอยู่และเว็บใช้ได้ตามปกติด้วยอีเมลกับรหัสผ่าน วิธีเปิดใช้:
1. ไป https://console.cloud.google.com สร้างโปรเจกต์ แล้วเมนู APIs & Services > OAuth consent screen ตั้งชื่อแอป (ประเภท External แล้วใส่อีเมลตัวเองเป็น Test user ถ้ายังไม่ publish)
2. Credentials > Create credentials > OAuth client ID > Web application ใส่ Authorized redirect URI เป็น `https://angsila.informatics.buu.ac.th/~68160053/library/google-callback.php` (ต้องตรงทุกตัวอักษร)
3. เอา Client ID กับ Client secret ไปใส่ใน `secret/config.php` บนเซิร์ฟเวอร์ (บรรทัด `google_client_id`, `google_client_secret`, `google_redirect_uri` ที่คอมเมนต์ไว้ใน `config.example.php`) ห้ามใส่ลง git
4. ฐานข้อมูลที่สร้างไว้แล้วต้องเพิ่มคอลัมน์ครั้งเดียว: `ALTER TABLE lib_users ADD COLUMN google_sub VARCHAR(40) NULL, ADD UNIQUE KEY uq_lib_users_google (google_sub);`

กติกา: Google ใช้ยืนยันตัวตนเท่านั้น คนที่เข้าด้วย Google ครั้งแรกจะได้บัญชีสมาชิก (member) ถ้าอีเมลนั้นสมัครด้วยรหัสผ่านไว้ก่อน ระบบไม่รวมบัญชีให้ ต้องเข้าด้วยรหัสผ่านตามเดิม (กันคนสมัครอีเมลของคนอื่นไว้ก่อนแล้วเข้าไปถือบัญชีหลังเจ้าของมาใช้ Google) ตัวป้องกัน: `state` สุ่มใช้ครั้งเดียว, `nonce`, ตรวจ `aud`/`iss`/`exp`/`email_verified` และเรียก Google จากฝั่งเซิร์ฟเวอร์ด้วย curl เท่านั้น (curl กับ json_decode ไม่ได้เรียนในวิชา จึงใช้เฉพาะ 2 ไฟล์นี้)

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
- จำกัดจำนวนครั้ง เก็บในตาราง `lib_attempts` (ล้างคุกกี้แล้วก็หนีไม่พ้น): ล็อกอินผิด 10 ครั้งต่ออีเมล หรือ 100 ครั้งต่อ IP (Wi-Fi มหาวิทยาลัยใช้ IP ร่วมกันหลายคน จึงตั้งไม่ตึงเกินไป) ใน 15 นาทีโดนล็อก, สมัครได้ 10 ครั้งต่อ IP ต่อชั่วโมง
- ส่วนหัวความปลอดภัยทุกหน้า: CSP (รันเฉพาะสคริปต์ของเราเอง), X-Frame-Options DENY, nosniff, Referrer-Policy, ซ่อน X-Powered-By, `Cache-Control: no-store` เมื่อล็อกอิน
- session หมดอายุเมื่อไม่ใช้ 30 นาทีหรือครบ 8 ชั่วโมง, เปลี่ยน session id และ CSRF token ตอนล็อกอิน
- รหัสผ่านห้ามมีอักขระควบคุม (bcrypt อ่านถึงแค่ NUL)

## ทดสอบความปลอดภัย
`tests/sectest.mjs` (32 ข้อ ตรวจฟังก์ชันหลัก), `tests/rental.mjs` (100 ข้อ ระบบเช่าและหลังบ้าน), `tests/google.mjs` (34 ข้อ ล็อกอินด้วย Google ต้องเปิดเซิร์ฟเวอร์ที่สอง พอร์ต 8093 พร้อมค่า `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` ปลอม) และ `tests/pentest.mjs` (106 ข้อ จำลองการโจมตี: SQL injection, XSS, CSRF, session, IDOR, race condition, brute force ฯลฯ) รันกับฐานข้อมูลทดลองเท่านั้น: import `sql/schema.sql` เข้า DB `lib_sec` พอร์ต 3399 แล้วรัน
`DB_HOST=127.0.0.1 DB_USER=root DB_NAME=lib_sec php -d mysqli.default_port=3399 -S 127.0.0.1:8092` แล้ว `node tests/sectest.mjs`, `node tests/rental.mjs` และ `node tests/pentest.mjs` (ตั้ง `SESS_DIR` ถ้าโฟลเดอร์ session ไม่ใช่ `C:/xampp/tmp`)
ก่อน merge PR ของเพื่อนให้รันซ้ำทุกครั้ง

## กติกาเขียนโค้ดสำหรับเพื่อน (ตอนทำ PR)
- เช่าหนังสือ (ทำแล้วใน `rent.php` ใช้เป็นแบบ): ตัดจำนวนเล่มด้วย UPDATE เดียว `UPDATE lib_books SET copies_available = copies_available - 1 WHERE book_id = ? AND copies_available > 0` แล้วดู `affected_rows` (ถ้า 0 = เล่มหมด) และทำ INSERT lib_loans ใน transaction เดียวกัน ห้าม SELECT ดูก่อนแล้วค่อย UPDATE
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
