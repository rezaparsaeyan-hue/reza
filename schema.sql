CREATE TABLE bookings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(32) NOT NULL UNIQUE,
  therapist_id VARCHAR(40) NOT NULL,
  therapist_name VARCHAR(120) NOT NULL,
  mode VARCHAR(20) NOT NULL,
  session_date VARCHAR(120) NOT NULL,
  session_time VARCHAR(30) NOT NULL,
  client_name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  note VARCHAR(200) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_therapist_slot (therapist_id, session_date, session_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workshops (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  instructor VARCHAR(120) NOT NULL,
  start_date VARCHAR(120) NOT NULL,
  start_time VARCHAR(80) NOT NULL,
  price INT UNSIGNED NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  reserved INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO workshops
(id,title,instructor,start_date,start_time,price,capacity,reserved) VALUES
('w1','بازی‌درمانی کودک','زینب صادقیان','۲۵ آبان ۱۴۰۵','۱۶:۰۰ تا ۱۸:۰۰',1200000,20,14),
('w2','تربیت جنسی کودک','ارزو سمسار','۲ آذر ۱۴۰۵','۱۷:۰۰ تا ۱۹:۰۰',980000,25,21),
('w3','آمادگی پیش از ازدواج','مهناز همتی','۵ آذر ۱۴۰۵','۱۸:۰۰ تا ۲۰:۰۰',1500000,16,16),
('w4','مدیریت اضطراب نوجوان','لیلا همتی','۱۰ آذر ۱۴۰۵','۱۶:۳۰ تا ۱۸:۳۰',1100000,22,9),
('w5','استعدادسنجی و انتخاب رشته','ریحانه رحیمیان','۱۵ آذر ۱۴۰۵','۱۵:۰۰ تا ۱۷:۰۰',850000,30,11),
('w6','مسیر شغلی و آینده حرفه‌ای','هادی فخار','۲۰ آذر ۱۴۰۵','۱۷:۳۰ تا ۱۹:۳۰',1050000,24,6);

CREATE TABLE workshop_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(32) NOT NULL UNIQUE,
  workshop_id VARCHAR(20) NOT NULL,
  workshop_title VARCHAR(160) NOT NULL,
  instructor VARCHAR(120) NOT NULL,
  start_date VARCHAR(120) NOT NULL,
  price INT UNSIGNED NOT NULL,
  client_name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(workshop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_key VARCHAR(128) NOT NULL,
  entry_text VARCHAR(140) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(session_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wall_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  note_text VARCHAR(120) NOT NULL,
  author_label VARCHAR(80) NOT NULL DEFAULT 'ناشناس',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE future_letters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_key VARCHAR(128) NOT NULL,
  client_name VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  letter_text VARCHAR(600) NOT NULL,
  deliver_after DATETIME NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(status, deliver_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE visitor_state (
  session_key VARCHAR(128) NOT NULL PRIMARY KEY,
  mood VARCHAR(30) NOT NULL DEFAULT '',
  mood_seen TINYINT(1) NOT NULL DEFAULT 0,
  mood_skipped TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;