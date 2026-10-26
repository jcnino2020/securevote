CREATE TABLE IF NOT EXISTS election_milestones (
 election_id INT UNSIGNED PRIMARY KEY,
 registration_opens DATETIME NULL,
 registration_closes DATETIME NULL,
 campaign_opens DATETIME NULL,
 campaign_closes DATETIME NULL,
 results_at DATETIME NULL,
 FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS candidate_answers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 candidate_id INT UNSIGNED NOT NULL,
 question VARCHAR(300) NOT NULL,
 answer TEXT NOT NULL,
 status ENUM('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
 KEY idx_answers (candidate_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS reminder_dismissals (
 user_id INT UNSIGNED NOT NULL,
 reminder_key CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,reminder_key),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
