# Design Spec: Arcanum Library (ห้องสมุดมนตรา)

## 1. Mood
ห้องสมุดเวทมนตร์ยามค่ำคืน: พื้นหลังสีหมึกเข้ม มีดาว/ฝุ่นเวทกะพริบ การ์ดและฟอร์มเป็นกระดาษ parchment ตัวหนังสือสีทองอบอุ่น ทุกอย่างเป็นงานออกแบบต้นฉบับ ไม่ใช้ชื่อ ตรา ฟอนต์ หรือสีประจำบ้านของแฟรนไชส์ใดๆ

## 2. Palette และค่า contrast
ตัวแปรทั้งหมดอยู่ใน `:root` ของ `assets/css/style.css` (เกณฑ์ WCAG AA: ข้อความปกติ >= 4.5:1, ขอบช่องกรอก >= 3:1)

| คู่สี (ตัวอักษร / พื้น) | ใช้ที่ไหน | อัตราส่วน |
|---|---|---|
| text-light #efe6d2 / ink-900 #14111f | เนื้อหาหน้า | 14.98 |
| text-light / ink-800 #1e1a2e | nav, note, ป้าย "ถูกยืมหมด" | 13.62 |
| text-light / ink-700 #2a2440 | hover เมนู | 11.87 |
| gold-soft #e8cf7a / ink-900 | หัวข้อบนพื้นมืด | 12.07 |
| gold-soft / ink-800 | โลโก้, หัวตาราง | 10.98 |
| gold #d4af37 / ink-900 | ลิงก์ | 8.84 |
| gold / ink-800 | ลิงก์ใน nav/note | 8.04 |
| ink-900 / gold (ปุ่มหลัก) | ปุ่ม .btn-primary, role-badge | 8.84 |
| ink-900 / gold-soft (ปุ่ม hover) | ปุ่ม hover | 12.07 |
| text-ink #2b1d0e / parchment #f3e9d2 | ข้อความในการ์ด/ฟอร์ม/ตาราง | 13.54 |
| text-ink / parchment-dark #e4d5b0 | แถวสลับสีตาราง | 11.24 |
| text-muted #5a4630 / parchment | ข้อความรอง, hint, caption | 7.39 |
| text-muted / parchment-dark | ข้อความรองบนแถวสลับสี | 6.14 |
| text-muted-dark #b9afc9 / ink-900 | ข้อความรองบนพื้นมืด | 8.89 |
| burgundy #7a1f2b / parchment | หัวข้อฟอร์ม, error | 8.45 |
| burgundy / #fbe9e7 (พื้น alert) | กล่อง alert | 8.70 |
| white / burgundy | ป้าย "เกินกำหนด", ปุ่ม danger | 10.20 |
| white / emerald #1f6b4f | ป้าย "ว่าง" | 6.41 |
| ink-900 / amber-warn #b7791f | ป้าย "ใกล้ครบกำหนด" | 5.11 |
| muted-dark / ink-700 | ปุ่ม disabled | 7.05 |
| gold-soft / ปกเล่ม 1,2,3,4 (สว่างสุดของ gradient) | ชื่อบนปก | 6.63 / 5.26 / 6.48 / 6.63 |
| field-border #7d6743 / #fffaf0 | ขอบช่องกรอก (ไม่ใช่ข้อความ ต้อง >= 3) | 5.18 |

หมายเหตุ: **ตัวอักษรขาวบน amber-warn ได้แค่ 3.64 (ไม่ผ่าน)** จึงใช้ตัวอักษร ink-900 บนป้ายสีเหลืองอำพันแทน

## 3. ฟอนต์
- หัวข้อ: Cinzel (500/700) แล้วตกไป Trirong แล้ว serif
- เนื้อหา: Crimson Pro (400/600) แล้ว Trirong แล้ว serif
- Cinzel และ Crimson Pro ไม่มีอักษรไทย ตัวอักษรไทยจะถูกเรนเดอร์ด้วย Trirong อัตโนมัติ
- โหลดผ่าน `<link>` เดียวจาก Google Fonts (`display=swap`) ขนาดตัวอักษรเนื้อหา 17px, line-height 1.6

## 4. VFX และตำแหน่งในโค้ด

| เอฟเฟกต์ | อยู่ที่ |
|---|---|
| ดาว/ฝุ่นเวท + กะพริบ | `style.css` หมวด vfx: `body::before` + `@keyframes twinkle` |
| แสงเทียนไหว | `.logo, .hero h1` + `@keyframes flicker` (text-shadow) |
| หมึกค่อยๆ ปรากฏ | `main`, `.grid > .card:nth-child(1..6)` + `@keyframes ink-reveal` |
| การ์ดลอย + เรืองแสงทอง | `.grid > .card:hover` (transition 220ms) |
| ประกายตอนกดปุ่ม | `app.js` (สร้าง `span.spark` 12 อัน) + `.spark` / `@keyframes spark-out` |
| ตราขี้ผึ้งสถานะ | `.badge`, `.badge--ok/out/late/soon` (inset shadow) |
| ปกหนังสือ CSS | `.cover` (aspect-ratio 2/3) + `.cover--1..4` |

ทุกอย่างถูกปิดใน `@media (prefers-reduced-motion: reduce)` ท้ายไฟล์ CSS และ `app.js` ตรวจ `matchMedia` ก่อนสร้างประกาย

## 5. Accessibility
- ทุก input มี `<label for>`; ข้อความ error ผูกด้วย `aria-describedby` + `aria-invalid`; alert ใช้ `role="alert"`
- `:focus-visible` วงทอง 2px offset 2px; ปุ่ม/ลิงก์/ช่องกรอกสูงอย่างน้อย 44px; ปุ่มทุกอันมี `cursor:pointer` (disabled เป็น `not-allowed`)
- ปุ่มแฮมเบอร์เกอร์ใช้ `aria-expanded` + `aria-controls`; ลิงก์ "ข้ามไปยังเนื้อหา"; หน้าปัจจุบันใช้ `aria-current="page"`
- ไอคอน SVG ทุกอันเป็น `aria-hidden="true"`; ปกหนังสือ aria-hidden เพราะชื่อเรื่องซ้ำอยู่ใน `<h3>`
- ตารางห่อด้วย `.table-wrap` (`overflow-x:auto`, `tabindex="0"`, `role="region"`) เพื่อเลื่อนด้วยคีย์บอร์ดได้
- สถานะไม่ใช้สีอย่างเดียว มีข้อความกำกับในป้ายเสมอ

## 6. แปลงเป็น PHP
1. ตัดส่วน `<!-- header start --> ... <!-- header end -->` ไปไว้ `includes/header.php` และ `<!-- footer start --> ... <!-- footer end -->` ไปไว้ `includes/footer.php` (ส่วน `<head>` และ `<main>` เปิด/ปิด อยู่ในสองไฟล์นี้ตามความสะดวก) แต่ละหน้าเรียก `require __DIR__ . '/../includes/header.php';`
2. `aria-current="page"` ในโปรโตไทป์ต่างกันตามหน้า ใน PHP ให้เช็คจากชื่อไฟล์ปัจจุบัน (`basename($_SERVER['PHP_SELF'])`)
3. ข้อความที่มาจากฐานข้อมูลหรือผู้ใช้ทุกตัว ต้องครอบด้วย `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` ก่อน echo (ชื่อหนังสือ ผู้แต่ง ชื่อสมาชิก ข้อความ error)
4. เปลี่ยน `CSRF_TOKEN_HERE` เป็น `<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>` แล้วตรวจฝั่งเซิร์ฟเวอร์ทุกครั้งที่รับ POST
5. ลูป `foreach` สร้างการ์ดหนังสือและแถวตาราง; ป้ายสถานะเลือก class ด้วย if/else; ปุ่มที่ disabled ให้ตัดสินจากข้อมูลจริง (เล่มว่าง = 0, ต่ออายุแล้ว, เกินกำหนด) และต้องตรวจซ้ำฝั่ง PHP ด้วย เพราะ disabled ฝั่งเบราว์เซอร์แก้ได้
6. ปุ่มใน prototype เป็น `type="button"` ให้เปลี่ยนเป็น `<form method="post">` พร้อม csrf_token และ hidden id ของรายการ
7. ลิงก์ "ออกจากระบบ" ใน prototype เป็นลิงก์ธรรมดา ของจริงควรเป็นฟอร์ม POST + CSRF
8. ปุ่ม/ฟอร์มที่ใช้ SQL ต้องใช้ prepared statement เสมอ (ไม่เกี่ยวกับหน้าตา แต่ห้ามลืม)
