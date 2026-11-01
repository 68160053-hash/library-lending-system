-- v3 -> v4: ให้ lib_attempts นับการเดาโค้ดส่วนลดได้ (รันครั้งเดียวใน phpMyAdmin หลังอัปโหลดโค้ดใหม่)
ALTER TABLE lib_attempts MODIFY COLUMN kind ENUM('login','register','password','coupon') NOT NULL;
