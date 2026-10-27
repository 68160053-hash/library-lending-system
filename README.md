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
3. สมัครสมาชิกที่ `register.php` (บัญชีใหม่ทุกบัญชีเป็น member) ไม่มีรหัสผ่านใน repo
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
TODO: ชื่อ-หน้าที่
