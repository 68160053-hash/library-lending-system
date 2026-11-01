# Book Lending Shop · ร้านยืมหนังสือ

โปรเจกต์ปลายภาค วิชาการจัดการฐานข้อมูล (มหาวิทยาลัยบูรพา)

> โครง README: เติมส่วนที่ยังเป็น TODO ตอน Milestone 2

## ปัญหาและเป้าหมาย
TODO: สรุป 2-3 บรรทัดจาก `docs/proposal.md`

## ฟีเจอร์ (ร้านเช่าหนังสือออนไลน์ ธีมงานสัปดาห์หนังสือ)
- ลูกค้า: หน้าแรกเป็นแผนผังงาน 5 โซนสี ค้นหาอัจฉริยะ (แนะนำคำค้น กรองราคา/ว่าง/หมวด เรียงตามคะแนนหรือยอดนิยม) หน้าหนังสือพร้อมรีวิวและคะแนน (รีวิวได้เฉพาะเล่มที่เคยเช่าและคืนแล้ว) "คนที่เช่าเล่มนี้ก็เช่า" ชั้นหนังสือของฉัน (อยากอ่าน/เคยเช่า) ตะกร้า ส่วนลดชุดเช่า (3 เล่มลด 10%, 4 เล่มขึ้นไปลด 15% ของค่าเช่า) โค้ดส่วนลด PromptPay QR (เมื่อตั้งเบอร์ไว้) ไทม์ไลน์ติดตามพัสดุ ต่ออายุ ยกเลิก แจ้งเตือนในเว็บ โปรไฟล์ โหมดมืด ติดตั้งเป็นแอป (PWA)
- เจ้าหน้าที่: แดชบอร์ดวิเคราะห์ (รายได้รายวัน แยกตามโซน หนังสือขายดี อัตราคืนตรงเวลา ลูกค้าประจำ มัดจำที่ถือ) ตรวจและยืนยันรับเงิน ส่งพัสดุพร้อมเลขพัสดุ รับคืน/สูญหาย รายการเกินกำหนด จัดการหนังสือและหนังสือคัดสรร จัดการโค้ดส่วนลด
- แอดมิน: ทุกอย่างของเจ้าหน้าที่ + จัดการผู้ใช้ (เปลี่ยนบทบาทตัวเองไม่ได้)
- กันคนไม่คืน: ค้างหนังสือเกินกำหนดสั่งใหม่ไม่ได้ และเกิน 14 วันระบบตัดเป็นสูญหาย (ไม่คืนมัดจำ) อัตโนมัติ

### กติกาการเช่า
- ลูกค้าเลือกเช่า 7, 14 หรือ 30 วัน จ่ายค่าเช่า + มัดจำครั้งเดียวด้วยการโอน แล้วกด "แจ้งโอนเงิน" เจ้าหน้าที่ตรวจแล้วกดยืนยัน จึงส่งพัสดุ (ไม่มีอัปโหลดสลิป)
- วันเช่านับจากวันที่ร้านส่งพัสดุ ต่ออายุได้ 1 ครั้ง (+7 วัน) ถ้ายังไม่เกินกำหนด
- ราคาเช่าและมัดจำของแต่ละเล่มถูกคัดลอกเก็บในรายการเช่า ราคาเปลี่ยนทีหลังไม่กระทบ
- ค่าปรับ = จำนวนวันที่เกิน x ราคาเช่าต่อวัน หักจากมัดจำ ที่เหลือคืนให้ ถ้าสูญหายไม่คืนมัดจำ (คำนวณตอนแสดงผล ไม่เก็บซ้ำ)
- ใบที่ยังไม่แจ้งโอนเกิน 48 ชั่วโมงถูกยกเลิกอัตโนมัติ เล่มกลับเข้าชั้น; ค้างจ่ายได้พร้อมกันไม่เกิน 3 ใบ
- สั่งเช่าเล่มสุดท้ายพร้อมกันสองคน ได้คนเดียวเสมอ (UPDATE เดียวตรวจและลดจำนวนเล่ม) และถ้ามีเล่มใดหมด ทั้งใบถูกยกเลิก (transaction)

## เทคโนโลยี
PHP 8 (procedural mysqli, session), MySQL 8, HTML/CSS/JS, โฮสต์ที่ Angsila

## ติดตั้ง
1. import `sql/schema.sql` (ฐานข้อมูลเก่ารัน `sql/migrate_v3.sql` แทน) แล้วตามด้วย `sql/seed_books.sql` (หนังสือจริง 60 เล่มพร้อมปกและคำอธิบาย (ปกอยู่ใน `assets/covers/`)จาก Open Library, สร้างด้วย `node tools/fetch_books.mjs` บนเครื่องตัวเอง) ผ่าน phpMyAdmin บน Angsila: MySQL 8.0.46, PHP 8.4, ชื่อฐานข้อมูลคือ `s` + รหัสนักศึกษา
2. คัดลอก `secret/config.example.php` เป็น `secret/config.php` แล้วใส่ค่าฐานข้อมูลของตัวเอง (host `localhost`, user และ name เป็น `s68160053`) (ไฟล์นี้ถูก git-ignore และ `secret/.htaccess` บล็อกการเข้าทางเว็บ)
3. สมัครสมาชิกที่ `register.php` (บัญชีใหม่ทุกบัญชีเป็น member) ไม่มีผู้ใช้ตัวอย่างหรือรหัสผ่านใน repo
3b. ใส่ข้อมูลบัญชีธนาคารร้านใน `secret/config.php` (`shop_bank`, `shop_account_no`, `shop_account_name`, `shop_promptpay`) หน้าใบสั่งเช่าจะแสดงให้ลูกค้าโอน
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

## ทดสอบ
`tests/shop.mjs` (188 ข้อ ระบบร้านทั้งหมด: แคตตาล็อก ตะกร้า สั่งเช่า ชำระเงิน จัดส่ง คืน/ปรับ หลังบ้าน บทบาท โปรไฟล์ พร้อมการโจมตีทุกขั้น), `tests/discover.mjs` (64), `tests/orders.mjs` (71), `tests/staff.mjs` (80), `tests/platform.mjs` (43) และ `tests/pentest.mjs` (106 ข้อ SQL injection, XSS, CSRF, session, IDOR, race condition, brute force) และ `tests/google.mjs` (34 ข้อ ต้องเปิดเซิร์ฟเวอร์ที่สอง พอร์ต 8093 พร้อมค่า `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` ปลอม) รันกับฐานข้อมูลทดลองเท่านั้น (ชื่อ DB ต้องมี sec หรือ test): import `sql/schema.sql` เข้า DB `lib_sec` พอร์ต 3399 แล้วรัน
`DB_HOST=127.0.0.1 DB_USER=root DB_NAME=lib_sec php -d mysqli.default_port=3399 -S 127.0.0.1:8092` แล้ว `node tests/shop.mjs`, `node tests/pentest.mjs` (ตั้ง `SESS_DIR` ถ้าโฟลเดอร์ session ไม่ใช่ `C:/xampp/tmp`)

## กติกาเขียนโค้ดสำหรับเพื่อน (ตอนทำ PR)
- เช่าหนังสือ (ทำแล้วใน `checkout.php` ใช้เป็นแบบ): ตัดจำนวนเล่มด้วย UPDATE เดียว `UPDATE lib_books SET copies_available = copies_available - 1 WHERE book_id = ? AND copies_available > 0` แล้วดู `affected_rows` (ถ้า 0 = เล่มหมด) และทำ INSERT lib_loans ใน transaction เดียวกัน ห้าม SELECT ดูก่อนแล้วค่อย UPDATE
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
