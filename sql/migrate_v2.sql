-- ย้ายฐานข้อมูลที่สร้างจาก schema.sql รุ่นแรก (ร้านเช่าแบบเล่มเดียว) ไปรุ่นใหม่ (ตะกร้า + ใบสั่งเช่า + การชำระเงิน + โปรไฟล์)
-- รันครั้งเดียวใน phpMyAdmin (แท็บ SQL) ก่อนอัปโหลดโค้ดใหม่ ตารางที่แตะมีแต่ lib_ ของโปรเจกต์นี้เท่านั้น
-- ข้อมูลเดิมในตารางเหล่านี้เป็นข้อมูลทดสอบ ถ้ามีรายการเช่าเก่าอยู่จะถูกเก็บไว้ (order_id = NULL) ไม่ถูกลบ

ALTER TABLE lib_users
  ADD COLUMN phone VARCHAR(20) NOT NULL DEFAULT '' AFTER google_sub,
  ADD COLUMN default_address VARCHAR(255) NOT NULL DEFAULT '' AFTER phone;

ALTER TABLE lib_books
  ADD COLUMN description TEXT NULL AFTER isbn;

ALTER TABLE lib_attempts
  MODIFY COLUMN kind ENUM('login','register','password') NOT NULL;

-- lib_orders รุ่นแรกยังไม่เคยถูกใช้งาน (ไม่มีแถว) จึงปรับโครงสร้างได้เลย
ALTER TABLE lib_orders
  DROP COLUMN total,
  DROP COLUMN status,
  ADD COLUMN rent_days SMALLINT UNSIGNED NOT NULL DEFAULT 7 AFTER ordered_at,
  ADD COLUMN rent_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER rent_days,
  ADD COLUMN deposit_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER rent_total,
  ADD COLUMN ship_name VARCHAR(100) NOT NULL DEFAULT '' AFTER deposit_total,
  ADD COLUMN ship_phone VARCHAR(20) NOT NULL DEFAULT '' AFTER ship_name,
  ADD COLUMN ship_address VARCHAR(255) NOT NULL DEFAULT '' AFTER ship_phone,
  ADD COLUMN pay_status ENUM('unpaid','reported','confirmed') NOT NULL DEFAULT 'unpaid' AFTER ship_address,
  ADD COLUMN pay_note VARCHAR(80) NOT NULL DEFAULT '' AFTER pay_status,
  ADD COLUMN pay_reported_at DATETIME NULL AFTER pay_note,
  ADD COLUMN pay_confirmed_at DATETIME NULL AFTER pay_reported_at,
  ADD COLUMN courier VARCHAR(30) NOT NULL DEFAULT '' AFTER pay_confirmed_at,
  ADD COLUMN tracking_no VARCHAR(40) NOT NULL DEFAULT '' AFTER courier,
  ADD COLUMN shipped_at DATE NULL AFTER tracking_no,
  ADD COLUMN status ENUM('open','cancelled') NOT NULL DEFAULT 'open' AFTER shipped_at,
  ADD KEY idx_lib_orders_pay (pay_status, status);

ALTER TABLE lib_loans
  ADD COLUMN order_id INT UNSIGNED NULL AFTER loan_id,
  DROP COLUMN ship_address,
  DROP COLUMN shipped_at,
  ADD KEY idx_lib_loans_order (order_id),
  ADD CONSTRAINT fk_lib_loans_order FOREIGN KEY (order_id) REFERENCES lib_orders (order_id) ON DELETE RESTRICT;
