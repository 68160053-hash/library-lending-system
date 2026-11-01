-- Book Lending Shop: ร้านเช่าหนังสือออนไลน์ (MySQL 8, InnoDB, utf8mb4)
-- ปลอดภัยต่อตารางเดิม: ไม่มี DROP / CREATE DATABASE, ทุกตารางขึ้นต้น lib_ และรันซ้ำได้
-- Angsila ยืนยันแล้ว: MySQL 8.0.46 (CHECK ถูกบังคับใช้)
--
-- แนวคิด: ลูกค้าหยิบหนังสือใส่ตะกร้า (ตะกร้าอยู่ใน session ไม่มีตาราง) แล้วกดสั่งเช่า 1 ครั้ง
--   = 1 แถวใน lib_orders (ที่อยู่ ยอดเงิน การชำระเงิน เลขพัสดุ) + 1 แถวใน lib_loans ต่อหนังสือ 1 เล่ม

CREATE TABLE IF NOT EXISTS lib_users (
  user_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(120) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(100) NOT NULL,
  role            ENUM('member','staff','admin') NOT NULL DEFAULT 'member',
  google_sub      VARCHAR(40) NULL,                 -- รหัสผู้ใช้ของ Google (ถ้าสมัครด้วย Google) NULL = สมัครด้วยรหัสผ่าน
  phone           VARCHAR(20) NOT NULL DEFAULT '',  -- เบอร์โทร (กรอกในโปรไฟล์)
  default_address VARCHAR(255) NOT NULL DEFAULT '', -- ที่อยู่จัดส่งประจำ ใช้เติมให้อัตโนมัติตอนสั่งเช่า
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_lib_users_email (email),
  UNIQUE KEY uq_lib_users_google (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_categories (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80) NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_lib_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_books (
  book_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id      INT UNSIGNED NOT NULL,
  title            VARCHAR(200) NOT NULL,
  author           VARCHAR(120) NOT NULL,
  isbn             VARCHAR(20) NOT NULL,
  description      TEXT NULL,                          -- เรื่องย่อ (แสดงในหน้ารายละเอียด)
  copies_total     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  copies_available SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  rent_per_day     DECIMAL(8,2) NOT NULL DEFAULT 10,   -- ค่าเช่าต่อวัน (บาท)
  deposit          DECIMAL(8,2) NOT NULL DEFAULT 100,  -- ค่ามัดจำต่อเล่ม คืนตอนส่งหนังสือกลับ
  sale_price       DECIMAL(8,2) NULL,                  -- ไม่ใช้แล้ว (เหลือไว้ให้ฐานข้อมูลเดิมไม่พัง)
  sale_stock       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (book_id),
  UNIQUE KEY uq_lib_books_isbn (isbn),
  KEY idx_lib_books_category (category_id),
  CONSTRAINT fk_lib_books_category FOREIGN KEY (category_id)
    REFERENCES lib_categories (category_id) ON DELETE RESTRICT,
  CONSTRAINT chk_lib_books_copies CHECK (copies_available <= copies_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ใบสั่งเช่า 1 ใบ (หนังสือหลายเล่มอยู่ใน lib_loans ที่ชี้มาที่ใบนี้)
CREATE TABLE IF NOT EXISTS lib_orders (
  order_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  ordered_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  rent_days        SMALLINT UNSIGNED NOT NULL DEFAULT 7,        -- เช่ากี่วัน (7 / 14 / 30)
  rent_total       DECIMAL(10,2) NOT NULL DEFAULT 0,            -- ค่าเช่ารวมทุกเล่มตามจำนวนวัน
  deposit_total    DECIMAL(10,2) NOT NULL DEFAULT 0,            -- ค่ามัดจำรวม
  ship_name        VARCHAR(100) NOT NULL DEFAULT '',
  ship_phone       VARCHAR(20) NOT NULL DEFAULT '',
  ship_address     VARCHAR(255) NOT NULL DEFAULT '',
  pay_status       ENUM('unpaid','reported','confirmed') NOT NULL DEFAULT 'unpaid', -- ยังไม่จ่าย / ลูกค้าแจ้งโอนแล้ว / เจ้าหน้าที่ยืนยันแล้ว
  pay_note         VARCHAR(80) NOT NULL DEFAULT '',             -- ข้อความที่ลูกค้าแจ้งตอนโอน (เวลา/ธนาคาร)
  pay_reported_at  DATETIME NULL,
  pay_confirmed_at DATETIME NULL,
  courier          VARCHAR(30) NOT NULL DEFAULT '',             -- บริษัทขนส่ง
  tracking_no      VARCHAR(40) NOT NULL DEFAULT '',             -- เลขพัสดุ
  shipped_at       DATE NULL,                                   -- วันที่ส่ง (เริ่มนับวันเช่า)
  status           ENUM('open','cancelled') NOT NULL DEFAULT 'open',
  PRIMARY KEY (order_id),
  KEY idx_lib_orders_user (user_id),
  KEY idx_lib_orders_pay (pay_status, status),
  CONSTRAINT fk_lib_orders_user FOREIGN KEY (user_id)
    REFERENCES lib_users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- รายการเช่า 1 เล่ม = 1 แถว
CREATE TABLE IF NOT EXISTS lib_loans (
  loan_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT UNSIGNED NULL,                    -- ใบสั่งเช่าที่เล่มนี้อยู่
  user_id     INT UNSIGNED NOT NULL,                -- เจ้าของ (ใช้ใน WHERE ทุกครั้งที่สมาชิกแก้ข้อมูลตัวเอง)
  book_id     INT UNSIGNED NOT NULL,
  borrowed_at DATE NOT NULL,                        -- วันที่สั่งเช่า
  due_date    DATE NOT NULL,                        -- วันครบกำหนดคืน (ตั้งใหม่ตอนส่งพัสดุ = วันส่ง + จำนวนวันเช่า)
  returned_at DATE NULL,                            -- NULL = ยังไม่คืน
  renew_count TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- ต่อได้ 1 ครั้ง
  status      ENUM('pending','shipped','returned','lost') NOT NULL DEFAULT 'pending', -- รอส่ง / ส่งแล้ว (อยู่กับผู้เช่า) / คืนแล้ว / สูญหาย
  daily_rate  DECIMAL(8,2) NOT NULL DEFAULT 10,     -- ค่าเช่าต่อวัน ณ ตอนเช่า (ราคาเปลี่ยนทีหลังก็ไม่กระทบ)
  deposit     DECIMAL(8,2) NOT NULL DEFAULT 0,      -- ค่ามัดจำของเล่มนี้
  PRIMARY KEY (loan_id),
  KEY idx_lib_loans_user (user_id),
  KEY idx_lib_loans_book (book_id),
  KEY idx_lib_loans_order (order_id),
  KEY idx_lib_loans_due (due_date),
  CONSTRAINT fk_lib_loans_user FOREIGN KEY (user_id)
    REFERENCES lib_users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_lib_loans_book FOREIGN KEY (book_id)
    REFERENCES lib_books (book_id) ON DELETE RESTRICT,
  CONSTRAINT fk_lib_loans_order FOREIGN KEY (order_id)
    REFERENCES lib_orders (order_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- บันทึกการล็อกอินผิด/การสมัคร/การเปลี่ยนรหัสผ่านผิด ใช้จำกัดจำนวนครั้ง ลบแถวเก่าอัตโนมัติจากโค้ด
CREATE TABLE IF NOT EXISTS lib_attempts (
  attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind       ENUM('login','register','password') NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  email      VARCHAR(120) NOT NULL DEFAULT '',
  tried_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (attempt_id),
  KEY idx_lib_attempts_lookup (kind, ip, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ข้อมูลตั้งต้น (หมวดหนังสือ) รันซ้ำได้ =====
INSERT INTO lib_categories (name) VALUES
  ('นิยายแฟนตาซี'), ('วิทยาศาสตร์'), ('ประวัติศาสตร์'), ('วรรณกรรมคลาสสิก'), ('คอมพิวเตอร์')
ON DUPLICATE KEY UPDATE name = name;

-- ไม่มีผู้ใช้ตัวอย่างใน SQL (กันบัญชีที่ชื่อรู้กันทั้งโลก): สมัครที่ register.php
-- แล้วตั้งแอดมินคนแรกด้วย  UPDATE lib_users SET role = 'admin' WHERE email = 'อีเมลของคุณ';
-- หนังสือตัวอย่าง: นำเข้า sql/seed_books.sql (สร้างด้วย tools/fetch_books.mjs)
