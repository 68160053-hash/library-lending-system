-- v2 -> v3: รีวิว ชั้นหนังสือ ส่วนลด การแจ้งเตือน (รันครั้งเดียวใน phpMyAdmin หลังอัปโหลดโค้ดใหม่ ก่อนใช้งาน)
ALTER TABLE lib_orders
  ADD COLUMN coupon_code VARCHAR(30) NULL AFTER ship_address,
  ADD COLUMN discount_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER coupon_code;
ALTER TABLE lib_books
  ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER deposit;

-- ===== v3: รีวิว ชั้นหนังสือ ส่วนลด การแจ้งเตือน =====
CREATE TABLE IF NOT EXISTS lib_reviews (
  review_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL,                         -- 1-5 ดาว (ตรวจที่ฝั่งเซิร์ฟเวอร์)
  body       VARCHAR(400) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (review_id),
  UNIQUE KEY uq_lib_reviews_user_book (user_id, book_id),       -- รีวิวได้เล่มละ 1 ครั้งต่อคน (และต้องเคยเช่าและคืนแล้ว ตรวจในโค้ด)
  KEY idx_lib_reviews_book (book_id),
  CONSTRAINT fk_lib_reviews_book FOREIGN KEY (book_id) REFERENCES lib_books (book_id) ON DELETE CASCADE,
  CONSTRAINT fk_lib_reviews_user FOREIGN KEY (user_id) REFERENCES lib_users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_wishlist (
  user_id  INT UNSIGNED NOT NULL,
  book_id  INT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, book_id),
  CONSTRAINT fk_lib_wish_user FOREIGN KEY (user_id) REFERENCES lib_users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_lib_wish_book FOREIGN KEY (book_id) REFERENCES lib_books (book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_coupons (
  coupon_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(30) NOT NULL,                            -- ตัวพิมพ์ใหญ่ A-Z 0-9
  kind         ENUM('percent','amount') NOT NULL,               -- ลดเป็น % ของค่าเช่า หรือลดเป็นบาท
  value        DECIMAL(8,2) NOT NULL,
  min_rent     DECIMAL(8,2) NOT NULL DEFAULT 0,                 -- ค่าเช่าขั้นต่ำก่อนลด
  max_uses     INT UNSIGNED NULL,                               -- NULL = ไม่จำกัด
  used_count   INT UNSIGNED NOT NULL DEFAULT 0,
  expires_on   DATE NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (coupon_id),
  UNIQUE KEY uq_lib_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_coupon_uses (
  coupon_id INT UNSIGNED NOT NULL,
  user_id   INT UNSIGNED NOT NULL,
  order_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (coupon_id, user_id),                             -- ใช้โค้ดเดียวกันได้คนละครั้ง
  CONSTRAINT fk_lib_cu_coupon FOREIGN KEY (coupon_id) REFERENCES lib_coupons (coupon_id) ON DELETE CASCADE,
  CONSTRAINT fk_lib_cu_user FOREIGN KEY (user_id) REFERENCES lib_users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lib_notifications (
  notif_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  kind       VARCHAR(20) NOT NULL,                              -- paid / shipped / due_soon / overdue / lost / info
  message    VARCHAR(200) NOT NULL,
  link       VARCHAR(120) NOT NULL DEFAULT '',                  -- หน้าในเว็บ เช่น order.php?id=5
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at    DATETIME NULL,
  PRIMARY KEY (notif_id),
  KEY idx_lib_notif_user (user_id, read_at, notif_id),
  CONSTRAINT fk_lib_notif_user FOREIGN KEY (user_id) REFERENCES lib_users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
