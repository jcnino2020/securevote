-- Additive migration. Safe to run again. Does not modify ballots or votes.
CREATE TABLE IF NOT EXISTS announcements (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 election_id INT UNSIGNED NULL,
 title VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 status ENUM('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
 created_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_announcement_feed (status, created_at),
 FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS support_tickets (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference CHAR(23) NOT NULL UNIQUE,
 user_id INT UNSIGNED NULL,
 email VARCHAR(150) NOT NULL,
 category ENUM('eligibility','login','ballot','other') NOT NULL,
 subject VARCHAR(150) NOT NULL,
 message TEXT NOT NULL,
 status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
 response TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_ticket_queue (status, created_at),
 KEY idx_ticket_user (user_id, created_at),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS feature_rate_limits (
 bucket CHAR(64) NOT NULL PRIMARY KEY,
 window_start BIGINT UNSIGNED NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 1,
 KEY idx_limit_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
