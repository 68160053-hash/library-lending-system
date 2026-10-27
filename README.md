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
PHP 8 (procedural mysqli), MySQL 8, HTML/CSS, โฮสต์ที่ Angsila

## ติดตั้ง
1. import `sql/schema.sql` ผ่าน phpMyAdmin
2. (Milestone 2) คัดลอก `config/config.example.php` เป็น `secret/config.php` แล้วใส่ค่าฐานข้อมูลของตัวเอง — ไฟล์ตัวอย่างนี้จะเพิ่มตอนเริ่มเขียน PHP; `secret/` ถูก git-ignore
3. สมัครสมาชิกผ่านหน้า register ไม่มีรหัสผ่านใน repo

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
