CREATE TABLE IF NOT EXISTS schema_versions (
 version INT PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 email VARCHAR(254) NOT NULL UNIQUE,
 student_id VARCHAR(20) UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
 password_ready BOOLEAN NOT NULL DEFAULT TRUE,
 auth_version INT NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS admin_keys (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS events (
 id INT AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150) NOT NULL,
 category VARCHAR(40) NOT NULL,
 description TEXT NOT NULL,
 venue VARCHAR(120) NOT NULL,
 starts_at DATETIME NOT NULL,
 price DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK(price=0),
 booking_enabled BOOLEAN NOT NULL DEFAULT TRUE,
 checkin_enabled BOOLEAN NOT NULL DEFAULT FALSE,
 deleted_at DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS gates (
 id INT AUTO_INCREMENT PRIMARY KEY,
 event_id INT NOT NULL,
 name VARCHAR(40) NOT NULL,
 is_open BOOLEAN NOT NULL DEFAULT FALSE,
 UNIQUE(event_id,name),
 UNIQUE(id,event_id),
 FOREIGN KEY(event_id) REFERENCES events(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS seats (
 id INT AUTO_INCREMENT PRIMARY KEY,
 event_id INT NOT NULL,
 gate_id INT NOT NULL,
 section VARCHAR(30) NOT NULL,
 row_label VARCHAR(10) NOT NULL,
 number INT NOT NULL CHECK(number>0),
 is_blocked BOOLEAN NOT NULL DEFAULT FALSE,
 UNIQUE(event_id,section,row_label,number),
 FOREIGN KEY(event_id) REFERENCES events(id),
 FOREIGN KEY(gate_id,event_id) REFERENCES gates(id,event_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bookings (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 seat_id INT NOT NULL,
 status ENUM('reserved','cancelled') NOT NULL DEFAULT 'reserved',
 price_at_booking DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK(price_at_booking=0),
 active_seat INT GENERATED ALWAYS AS (CASE WHEN status='reserved' THEN seat_id ELSE NULL END) PERSISTENT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(active_seat),
 FOREIGN KEY(user_id) REFERENCES users(id),
 FOREIGN KEY(seat_id) REFERENCES seats(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tickets (
 id INT AUTO_INCREMENT PRIMARY KEY,
 booking_id INT NOT NULL UNIQUE,
 event_id INT NOT NULL,
 attendee_user_id INT,
 attendee_name VARCHAR(120) NOT NULL,
 attendee_student_id VARCHAR(20),
 nonce CHAR(48) NOT NULL UNIQUE,
 status ENUM('active','checked_in','cancelled','unassigned') NOT NULL DEFAULT 'active',
 live_attendee INT GENERATED ALWAYS AS (CASE WHEN status IN ('active','checked_in') THEN attendee_user_id ELSE NULL END) PERSISTENT,
 checked_in_at DATETIME,
 checked_in_by_id INT,
 checked_in_gate_id INT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(event_id,live_attendee),
 FOREIGN KEY(booking_id) REFERENCES bookings(id),
 FOREIGN KEY(event_id) REFERENCES events(id),
 FOREIGN KEY(attendee_user_id) REFERENCES users(id),
 FOREIGN KEY(checked_in_by_id) REFERENCES users(id),
 FOREIGN KEY(checked_in_gate_id,event_id) REFERENCES gates(id,event_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rate_limits (
 bucket CHAR(64) PRIMARY KEY, hits INT NOT NULL, resets_at INT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mail_outbox (
 id INT AUTO_INCREMENT PRIMARY KEY,
 recipient VARCHAR(254) NOT NULL,
 subject VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 sent_at DATETIME,
 attempts INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_versions(version) VALUES(1);
