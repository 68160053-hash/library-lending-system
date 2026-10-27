# Milestone 1 — ระบบห้องสมุดยืม-คืน + ร้านขายหนังสือ

สถานะ: ร่าง (ข้อที่ไม่แน่ใจมีป้าย **[ยังไม่ยืนยัน]**)

## 1. ปัญหา กลุ่มผู้ใช้ และทางแก้

**Problem statement:** ห้องสมุด/ชั้นหนังสือของมหาวิทยาลัยขนาดเล็กยังจดการยืม-คืนด้วยสมุดหรือ Excel ทำให้ไม่รู้ว่าเล่มไหนว่าง ใครถืออยู่ และใครเกินกำหนด ระบบนี้เก็บข้อมูลไว้ที่เดียวและคำนวณวันครบกำหนดกับค่าปรับให้อัตโนมัติ

**กลุ่มผู้ใช้:**
| บทบาท | คือใคร |
|---|---|
| member | นักศึกษา/บุคลากรที่ยืมหรือซื้อหนังสือ |
| staff | เจ้าหน้าที่ที่จัดการหนังสือและรับคืน |
| admin | ผู้ดูแลระบบ (ทำแทน staff ได้ + จัดการบทบาทผู้ใช้) |

**Pain point (ตัวอย่างของเดิม):** นักศึกษายืม "Database System Concepts" ไป เจ้าหน้าที่จดชื่อลงสมุด 7 วันต่อมาไม่มีใครเปิดสมุดดู เล่มเกินกำหนดไป 12 วัน คนอื่นมาถามหาก็ไม่รู้ว่าเล่มอยู่ที่ใคร และค่าปรับต้องนับวันด้วยมือซึ่งนับผิดบ่อย

**ทางแก้:** เว็บ PHP + MySQL ที่ยืม/คืน/ต่ออายุผ่านระบบ แสดงรายการเกินกำหนดและค่าปรับทันที สมาชิกเห็นประวัติของตัวเอง

**กฎธุรกิจที่ตกลงแล้ว:** ยืมฟรี 7 วัน ต่ออายุได้ 1 ครั้ง (+7 วัน และต่อไม่ได้ถ้าเกินกำหนดแล้ว) คืนช้าปรับ 5 บาท/วัน (คำนวณตอนแสดงผล ไม่เก็บ ไม่มีการจ่ายออนไลน์) **[อัตรา 5 บาท และข้อห้ามต่ออายุเมื่อเกินกำหนดเป็นข้อสมมติของผม ยังไม่ได้ยืนยัน]** การยืมให้สมาชิกกดเอง ส่วนการรับคืนให้ staff เป็นคนกด **[ข้อสมมติ]**

## 2. ขอบเขตฟีเจอร์

| ฟีเจอร์ | ระดับ | ทำได้จริงใน 14 วัน? |
|---|---|---|
| Login / Logout / สมัครสมาชิก | Must | ✅ |
| CRUD หนังสือ + หมวด (staff) | Must | ✅ |
| ยืม / รับคืน / ต่ออายุ | Must | ✅ |
| รายการเกินกำหนด + ค่าปรับ | Must | ✅ |
| สมาชิกดูประวัติของตัวเอง | Must | ✅ |
| ซื้อหนังสือ + ประวัติคำสั่งซื้อ | Nice | ⚠️ เริ่มหลัง 4 พ.ย. ถ้าแกนหลักเสร็จ |
| ค้นหาหนังสือ | Nice | ✅ (แค่ `LIKE`) |
| จัดการบทบาทผู้ใช้ (admin) | Nice | ⚠️ |
| จำกัดจำนวนเล่มที่ยืม | Nice | ⚠️ |
| ชำระเงินออนไลน์, ส่งอีเมลเตือน, แยกเลขเล่มรายเล่ม, ใบเสร็จ PDF | Out of scope | ❌ |

## 3. Use case

| บทบาท | Use case |
|---|---|
| Guest | ดูรายการหนังสือ, ค้นหา, สมัครสมาชิก, login |
| Member | logout, ยืม, ต่ออายุ, ดูประวัติและค่าปรับของตัวเอง, ซื้อหนังสือ, ดูคำสั่งซื้อของตัวเอง |
| Staff | เพิ่ม/แก้/ลบหนังสือและหมวด, รับคืน, ดูรายการเกินกำหนด, จัดการสถานะคำสั่งซื้อ |
| Admin | ทุกอย่างของ staff + เปลี่ยนบทบาทผู้ใช้ |

```mermaid
flowchart LR
  G([Guest]) --- U1(ดู/ค้นหาหนังสือ)
  G --- U2(สมัคร / Login)
  M([Member]) --- U3(ยืม / ต่ออายุ)
  M --- U4(ดูประวัติ + ค่าปรับตัวเอง)
  M --- U5(ซื้อหนังสือ)
  S([Staff]) --- U6(จัดการหนังสือ)
  S --- U7(รับคืน + รายการเกินกำหนด)
  S --- U8(จัดการคำสั่งซื้อ)
  A([Admin]) --- U9(จัดการบทบาทผู้ใช้)
```
(ไดอะแกรมนี้เป็นร่างจาก flowchart เพราะ Mermaid ไม่มี use case diagram โดยตรง ตอนส่งจริงควรวาดใหม่ใน draw.io)

## 4. ER diagram (ร่าง)

```mermaid
erDiagram
  lib_users ||--o{ lib_loans : "ยืม"
  lib_books ||--o{ lib_loans : "ถูกยืม"
  lib_users ||--o{ lib_orders : "สั่งซื้อ"
  lib_orders ||--|{ lib_order_items : "มีรายการ"
  lib_books ||--o{ lib_order_items : "ถูกขาย"
  lib_categories ||--o{ lib_books : "จัดกลุ่ม"

  lib_users { int user_id PK
    varchar email UK
    varchar password_hash
    varchar full_name
    enum role
    datetime created_at }
  lib_categories { int category_id PK
    varchar name UK }
  lib_books { int book_id PK
    int category_id FK
    varchar title
    varchar author
    varchar isbn UK
    int copies_total
    int copies_available
    decimal sale_price
    int sale_stock }
  lib_loans { int loan_id PK
    int user_id FK
    int book_id FK
    date borrowed_at
    date due_date
    date returned_at
    int renew_count }
  lib_orders { int order_id PK
    int user_id FK
    datetime ordered_at
    decimal total
    enum status }
  lib_order_items { int item_id PK
    int order_id FK
    int book_id FK
    int qty
    decimal unit_price }
```

| ตาราง | ทำไมต้องมี |
|---|---|
| lib_users | เก็บบัญชีและบทบาท ใช้ทั้ง login และ authorization |
| lib_categories | จัดหมวดหนังสือ ไม่ต้องพิมพ์ชื่อหมวดซ้ำ |
| lib_books | รายการหนังสือ + จำนวนเล่มให้ยืม + ราคา/สต็อกขาย |
| lib_loans | หนึ่งแถวต่อการยืมหนึ่งครั้ง ใช้ตามเกินกำหนดและประวัติ |
| lib_orders | หัวคำสั่งซื้อ (ใคร เมื่อไร ยอดรวม สถานะ) |
| lib_order_items | รายการในคำสั่งซื้อ เก็บราคาตอนซื้อไว้ |

## 5. Data dictionary

**lib_users**
| คอลัมน์ | ชนิด | Key | Null | คำอธิบาย |
|---|---|---|---|---|
| user_id | INT UNSIGNED | PK | No | รหัสผู้ใช้ (auto) |
| email | VARCHAR(120) | UNIQUE | No | ใช้ login ห้ามซ้ำ |
| password_hash | VARCHAR(255) | | No | bcrypt จาก `password_hash` |
| full_name | VARCHAR(100) | | No | ชื่อ |
| role | ENUM | | No | member / staff / admin (ค่าเริ่ม member) |
| created_at | DATETIME | | No | วันที่สมัคร |

**lib_categories**
| คอลัมน์ | ชนิด | Key | Null | คำอธิบาย |
|---|---|---|---|---|
| category_id | INT UNSIGNED | PK | No | รหัสหมวด |
| name | VARCHAR(80) | UNIQUE | No | ชื่อหมวด |

**lib_books**
| คอลัมน์ | ชนิด | Key | Null | คำอธิบาย |
|---|---|---|---|---|
| book_id | INT UNSIGNED | PK | No | รหัสหนังสือ |
| category_id | INT UNSIGNED | FK→lib_categories | No | หมวด |
| title | VARCHAR(200) | | No | ชื่อเรื่อง |
| author | VARCHAR(120) | | No | ผู้แต่ง |
| isbn | VARCHAR(20) | UNIQUE | No | ISBN |
| copies_total | SMALLINT UNSIGNED | | No | จำนวนเล่มทั้งหมด |
| copies_available | SMALLINT UNSIGNED | | No | เล่มที่ว่างให้ยืม (ต้อง ≤ total) |
| sale_price | DECIMAL(8,2) | | Yes | ราคาขาย (NULL = ไม่ขาย) |
| sale_stock | SMALLINT UNSIGNED | | No | จำนวนที่ขายได้ |

**lib_loans**
| คอลัมน์ | ชนิด | Key | Null | คำอธิบาย |
|---|---|---|---|---|
| loan_id | INT UNSIGNED | PK | No | รหัสการยืม |
| user_id | INT UNSIGNED | FK→lib_users | No | ผู้ยืม (ใช้เป็น owner id ใน WHERE) |
| book_id | INT UNSIGNED | FK→lib_books | No | หนังสือที่ยืม |
| borrowed_at | DATE | | No | วันยืม |
| due_date | DATE | | No | วันครบกำหนด (ยืม+7, ต่อ +7) |
| returned_at | DATE | | Yes | วันคืน (NULL = ยังไม่คืน) |
| renew_count | TINYINT UNSIGNED | | No | จำนวนครั้งที่ต่อ (สูงสุด 1) |

ค่าปรับ = `GREATEST(0, DATEDIFF(IFNULL(returned_at, CURDATE()), due_date)) * 5` ไม่เก็บในตาราง

**lib_orders**
| คอลัมน์ | ชนิด | Key | Null | คำอธิบาย |
|---|---|---|---|---|
| order_id | INT UNSIGNED | PK | No | รหัสคำสั่งซื้อ |
| user_id | INT UNSIGNED | FK→lib_users | No | ผู้ซื้อ (owner id) |
| ordered_at | DATETIME | | No | เวลาสั่ง |
| total | DECIMAL(10,2) | | No | ยอดรวม |
| status | ENUM | | No | pending / completed / cancelled |

**lib_order_items**
| คอลัมน์ | ชนิด | Key | Null | คำอธิบาย |
|---|---|---|---|---|
| item_id | INT UNSIGNED | PK | No | รหัสรายการ |
| order_id | INT UNSIGNED | FK→lib_orders | No | คำสั่งซื้อ |
| book_id | INT UNSIGNED | FK→lib_books | No | หนังสือ |
| qty | SMALLINT UNSIGNED | | No | จำนวน |
| unit_price | DECIMAL(8,2) | | No | ราคา ณ ตอนซื้อ |

SQL สร้างตาราง: `sql/schema.sql`

## 6. แผนงานรายวัน (27 ต.ค. – 10 พ.ย.)

**การแบ่งงาน (ข้อสมมติ 3 คน — ยังไม่ยืนยันจำนวนคนจริง):**
| คน | หน้าที่ |
|---|---|
| P1 | หน้าเว็บ + UI/VFX (`includes/header.php`, `footer.php`, `assets/` CSS/JS, เลย์เอาต์ทุกหน้า) + ความปลอดภัย (`includes/auth.php`, CSRF, ตรวจสิทธิ์) + repo/deploy |
| A | ฐานข้อมูล (ER, schema, `config/db.php`) + ตรรกะ CRUD หนังสือ |
| B | ตรรกะยืม/คืน/ต่ออายุ/ค่าปรับ + ร้านขาย |

| วัน | งาน | ผู้รับผิดชอบ (ข้อสมมติ 3 คน) |
|---|---|---|
| 27 ต.ค. | สร้าง repo + `.gitignore`, ล็อกขอบเขต | P1 |
| 28 | problem statement, use case; ร่าง ER | P1 / A |
| 29 | **schema สุดท้ายต้องเสร็จ**; use case diagram + แผน | A / B |
| 30 | รวม proposal; เขียน `includes/auth.php` (login guard, role guard, CSRF) | P1 |
| 31 | **ส่ง Milestone 1** | P1 |
| 1 พ.ย. | register/login/logout; หน้า CRUD หนังสือ; โครงหน้ายืม | P1 / A / B |
| 2 | ต่อ: ปิดช่อง CSRF ทุก form; CRUD หมวด; ยืมหนังสือ | P1 / A / B |
| 3 | รับคืน + ต่ออายุ; ตรวจสิทธิ์ทุกหน้า | B / P1 |
| 4 | รายการเกินกำหนด + ค่าปรับ; ประวัติสมาชิก; ค้นหา | B / A |
| 5 | **ตัดสินใจเรื่องร้านขาย** (แกนหลักต้องเสร็จก่อน); ถ้าไม่ทัน ตัดออก | ทุกคน |
| 6 | คำสั่งซื้อ (ถ้าทำ); security review ทุก query | B / P1 |
| 7 | deploy ขึ้น Angsila; ทดสอบบนเครื่องจริง | P1 |
| 8 | แก้บั๊ก; ถ่าย screenshot; ร่างรายงาน | ทุกคน |
| 9 | รายงานฉบับเต็ม + README + ตารางผลทดสอบ | ทุกคน |
| 10 พ.ย. | เช็กขั้นสุดท้าย (ลิงก์ Angsila ใช้ได้, repo ไม่มีความลับ) แล้วส่ง | P1 |

## 7. โครง repo

```
LibraryProject/
├─ .gitignore          (ต้องมาก่อน git add ครั้งแรก)
├─ README.md
├─ sql/schema.sql
├─ config/db.php, config.example.php   (ค่าจริงอยู่ secret/config.php)
├─ includes/auth.php, header.php, footer.php
├─ assets/css/style.css, assets/js/app.js   (UI/VFX)
├─ index.php, login.php, register.php, logout.php
├─ books/   (list, add, edit, delete)
├─ loans/   (borrow, return, renew, my, overdue)
├─ shop/    (buy, orders)
└─ docs/    (milestone1.md, proposal.md, screenshots/)
```
**[ยังไม่ยืนยัน]** โครงโฟลเดอร์บน Angsila (เช่น ต้องอยู่ใน `public_html`) ต้องเช็กจากเอกสารแล็บ

## 8. Git workflow

- Branch: `main` มีแต่ของที่ใช้ได้ แล้วแตก `feature/auth`, `feature/books`, `feature/loans`, `feature/shop`, `docs/report` merge เข้า `main` เมื่อเสร็จ
- ตัวอย่าง commit message:
  - `chore: add .gitignore for .env and secret/`
  - `docs: add draft ER diagram and data dictionary`
  - `feat(db): add lib_ schema with sample data`
  - `feat(auth): register with bcrypt and duplicate email check`
  - `feat(auth): login with session_regenerate_id and CSRF token`
  - `feat(books): staff can add and edit books`
  - `fix(loans): restrict return query by loan_id and user_id`
  - `docs: add README setup steps`
- Push โดยไม่รั่ว: (1) `.gitignore` มาก่อน (2) `git add` เป็นรายไฟล์ ไม่ใช้ `git add .` จนกว่าจะเช็ก (3) ก่อน commit รัน `git status` และ `git diff --cached --stat` (4) `git ls-files` ต้องไม่มี `.env` หรือ `secret/` (5) ถ้าเผลอ push ความลับ **ต้องเปลี่ยนรหัสผ่านฐานข้อมูลทันที** การลบประวัติ git ไม่พอเพราะอาจถูกคัดลอกไปแล้ว
