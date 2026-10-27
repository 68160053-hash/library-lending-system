-- ระบบห้องสมุดยืม-คืน + ร้านขายหนังสือ (MySQL 8, InnoDB, utf8mb4)
-- ปลอดภัยต่อตารางเดิม: ไม่มี DROP / CREATE DATABASE, ทุกตารางขึ้นต้น lib_ และรันซ้ำได้
-- ยังไม่ยืนยัน: เวอร์ชัน MySQL บน Angsila (CHECK ถูกบังคับใช้ตั้งแต่ 8.0.16)

CREATE TABLE IF NOT EXISTS lib_users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  role          ENUM('member','staff','admin') NOT NULL DEFAULT 'member',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_lib_users_email (email)
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
  copies_total     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  copies_available SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  sale_price       DECIMAL(8,2) NULL,              -- NULL = ไม่ขาย
  sale_stock       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (book_id),
  UNIQUE KEY uq_lib_books_isbn (isbn),
  KEY idx_lib_books_category (category_id),
  CONSTRAINT fk_lib_books_category FOREIGN KEY (category_id)
    REFERENCES lib_categories (category_id) ON DELETE RESTRICT,
  CONSTRAINT chk_lib_books_copies CHECK (copies_available <= copies_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_loans (
  loan_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  book_id     INT UNSIGNED NOT NULL,
  borrowed_at DATE NOT NULL,
  due_date    DATE NOT NULL,
  returned_at DATE NULL,                           -- NULL = ยังไม่คืน
  renew_count TINYINT UNSIGNED NOT NULL DEFAULT 0, -- ต่อได้ 1 ครั้ง
  PRIMARY KEY (loan_id),
  KEY idx_lib_loans_user (user_id),
  KEY idx_lib_loans_book (book_id),
  KEY idx_lib_loans_due (due_date),
  CONSTRAINT fk_lib_loans_user FOREIGN KEY (user_id)
    REFERENCES lib_users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_lib_loans_book FOREIGN KEY (book_id)
    REFERENCES lib_books (book_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_orders (
  order_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  ordered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total      DECIMAL(10,2) NOT NULL DEFAULT 0,
  status     ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (order_id),
  KEY idx_lib_orders_user (user_id),
  CONSTRAINT fk_lib_orders_user FOREIGN KEY (user_id)
    REFERENCES lib_users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_order_items (
  item_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  book_id    INT UNSIGNED NOT NULL,
  qty        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(8,2) NOT NULL,                -- เก็บราคาตอนซื้อ ราคาหนังสือเปลี่ยนทีหลังก็ไม่กระทบ
  PRIMARY KEY (item_id),
  KEY idx_lib_items_order (order_id),
  KEY idx_lib_items_book (book_id),
  CONSTRAINT fk_lib_items_order FOREIGN KEY (order_id)
    REFERENCES lib_orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_lib_items_book FOREIGN KEY (book_id)
    REFERENCES lib_books (book_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ข้อมูลตัวอย่าง (รันซ้ำได้: ข้ามเฉพาะแถวที่ซ้ำ แต่ error อื่นยังแจ้งตามปกติ) =====
INSERT INTO lib_categories (name) VALUES
  ('นิยายแฟนตาซี'), ('วิทยาศาสตร์'), ('ประวัติศาสตร์'), ('วรรณกรรมคลาสสิก'), ('คอมพิวเตอร์')
ON DUPLICATE KEY UPDATE name = name;

-- ISBN ด้านล่างเป็นค่าสมมติสำหรับทดสอบ
INSERT INTO lib_books
  (category_id, title, author, isbn, copies_total, copies_available, sale_price, sale_stock) VALUES
  ((SELECT category_id FROM lib_categories WHERE name='คอมพิวเตอร์'),
   'Database System Concepts', 'Silberschatz', 'SAMPLE-0001', 3, 3, 450.00, 5),
  ((SELECT category_id FROM lib_categories WHERE name='นิยายแฟนตาซี'),
   'Sample Novel', 'Sample Author', 'SAMPLE-0002', 2, 2, NULL, 0),
  ((SELECT category_id FROM lib_categories WHERE name='ประวัติศาสตร์'),
   'Sample History Book', 'Sample Author', 'SAMPLE-0003', 1, 1, 220.00, 2)
ON DUPLICATE KEY UPDATE isbn = isbn;

-- placeholder ไม่ใช่ hash จริง -> password_verify เป็น false จึง login ไม่ได้จนกว่าจะแทนที่
-- สร้าง hash ในเครื่องตัวเอง (อย่า commit รหัสผ่านจริง):
--   php -r "echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT);"
--   แล้วพิมพ์รหัสตอนโปรแกรมรอ (รหัสจะไม่ค้างในประวัติคำสั่ง)
INSERT INTO lib_users (email, password_hash, full_name, role) VALUES
  ('member@example.com', 'REPLACE_WITH_BCRYPT_HASH', 'Sample Member', 'member'),
  ('admin@example.com',  'REPLACE_WITH_BCRYPT_HASH', 'Sample Admin',  'admin')
ON DUPLICATE KEY UPDATE email = email;
