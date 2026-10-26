-- ============================================================================
--  SecureVote - Direct Anonymous Secret Ballot Database Setup
--  Database: u184230832_securevote
--
--  KEY ARCHITECTURE & SECURITY:
--    1. NO-LOGIN DIRECT VOTING: Students/Voters do not need an account.
--       They simply enter their Student ID / Voter Passcode directly on the ballot.
--    2. TRUE SECRET BALLOT (DECOUPLED): The `ballots` and `votes` tables have NO
--       `voter_identifier` or `user_id`. Candidate choices cannot be linked back.
--    3. 1-PERSON-1-VOTE INTEGRITY: The `voter_participation` table enforces a
--       database-level UNIQUE constraint on (`election_id`, `voter_identifier`).
--    4. VERIFICATION RECEIPTS: Voters receive a cryptographic receipt reference
--       (e.g., SV-2026-XXXXXX) to confirm their ballot was cast and counted.
--
--  ADMINISTRATOR CREDENTIALS:
--    Username : admin
--    Password : Admin@12345
-- ============================================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `u184230832_securevote`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `u184230832_securevote`;

-- ----------------------------------------------------------------------------
-- Drop existing tables (child tables first)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `support_tickets`;
DROP TABLE IF EXISTS `feature_rate_limits`;
DROP TABLE IF EXISTS `dismissed_alerts`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `votes`;
DROP TABLE IF EXISTS `ballots`;
DROP TABLE IF EXISTS `voter_participation`;
DROP TABLE IF EXISTS `voter_eligibility`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `positions`;
DROP TABLE IF EXISTS `elections`;
DROP TABLE IF EXISTS `users`;

-- ============================================================================
-- 1. users - administrators
-- ============================================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
  `first_name`    VARCHAR(80)  NOT NULL,
  `last_name`     VARCHAR(80)  NOT NULL,
  `role`          ENUM('admin','voter')               NOT NULL DEFAULT 'admin',
  `status`        ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Administrator accounts. Passwords stored as bcrypt hashes.';

-- ============================================================================
-- 2. elections
-- ============================================================================
CREATE TABLE `elections` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`              VARCHAR(150) NOT NULL,
  `description`        TEXT         NULL,
  `start_datetime`     DATETIME     NOT NULL,
  `end_datetime`       DATETIME     NOT NULL,
  `status`             ENUM('draft','scheduled','open','closed','archived') NOT NULL DEFAULT 'open',
  `results_visibility` ENUM('hidden','after_vote','after_close')            NOT NULL DEFAULT 'after_vote',
  `created_by`         INT UNSIGNED NULL DEFAULT NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_elections_status` (`status`),
  KEY `idx_elections_window` (`start_datetime`,`end_datetime`),
  KEY `idx_elections_created_by` (`created_by`),
  CONSTRAINT `fk_elections_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. positions - contests inside an election
-- ============================================================================
CREATE TABLE `positions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id`    INT UNSIGNED  NOT NULL,
  `name`           VARCHAR(100)  NOT NULL,
  `description`    VARCHAR(255)  NULL DEFAULT NULL,
  `max_selections` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = single choice, >1 = choose up to N',
  `display_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_positions_election_name` (`election_id`,`name`),
  KEY `idx_positions_election_order` (`election_id`,`display_order`),
  CONSTRAINT `fk_positions_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. candidates
-- ============================================================================
CREATE TABLE `candidates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id` INT UNSIGNED NOT NULL,
  `position_id` INT UNSIGNED NOT NULL,
  `first_name`  VARCHAR(80)  NOT NULL,
  `last_name`   VARCHAR(80)  NOT NULL,
  `party`       VARCHAR(100) NULL DEFAULT NULL,
  `biography`   TEXT         NULL,
  `platform`    TEXT         NULL,
  `photo`       VARCHAR(255) NULL DEFAULT NULL,
  `status`      ENUM('active','inactive','disqualified') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_candidates_election_position` (`election_id`,`position_id`),
  KEY `idx_candidates_position_status` (`position_id`,`status`),
  CONSTRAINT `fk_candidates_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_candidates_position`
    FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. voter_participation - 1-Person-1-Vote Guard by Student ID / Passcode
-- ============================================================================
CREATE TABLE `voter_participation` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id`      INT UNSIGNED NOT NULL,
  `voter_identifier` VARCHAR(100) NOT NULL COMMENT 'Student ID / Passcode, e.g. 2024-00123',
  `voted_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ballot_reference` VARCHAR(30)  NULL DEFAULT NULL COMMENT 'Public receipt token, e.g. SV-2026-000001',
  `ip_hash`          VARCHAR(64)  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Prevents duplicate voting: exactly 1 participation record per Student ID per election
  UNIQUE KEY `uq_voter_participation` (`election_id`,`voter_identifier`),
  KEY `idx_participation_election` (`election_id`),
  KEY `idx_participation_identifier` (`voter_identifier`),
  CONSTRAINT `fk_participation_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Enforces 1-Person-1-Vote for Direct Secret Ballot voting using Student ID/Passcode.';

-- ============================================================================
-- 6. voter_eligibility - Optional registered voter tracking
-- ============================================================================
CREATE TABLE `voter_eligibility` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id`      INT UNSIGNED NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `has_voted`        TINYINT(1)   NOT NULL DEFAULT 0,
  `voted_at`         DATETIME     NULL DEFAULT NULL,
  `ballot_reference` VARCHAR(30)  NULL DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voter_eligibility_election_user` (`election_id`,`user_id`),
  CONSTRAINT `fk_eligibility_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_eligibility_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ballots - Anonymous Decoupled Ballot Box
--
--    CRITICAL: This table deliberately does NOT contain voter_id or voter_identifier.
--    This ensures that stored vote choices cannot be traced to any voter.
-- ============================================================================
CREATE TABLE `ballots` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id`      INT UNSIGNED NOT NULL,
  `ballot_reference` VARCHAR(30)  NOT NULL COMMENT 'anonymized ballot token, e.g. SV-2026-000001',
  `submitted_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`           ENUM('submitted','invalidated') NOT NULL DEFAULT 'submitted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ballots_reference` (`ballot_reference`),
  KEY `idx_ballots_election` (`election_id`),
  CONSTRAINT `fk_ballots_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Anonymous ballot box. Decoupled from user_id for complete ballot secrecy.';

-- ============================================================================
-- 8. votes - anonymous selections stored inside a ballot
-- ============================================================================
CREATE TABLE `votes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ballot_id`    INT UNSIGNED NOT NULL,
  `position_id`  INT UNSIGNED NOT NULL,
  `candidate_id` INT UNSIGNED NOT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_votes_ballot_position_candidate` (`ballot_id`,`position_id`,`candidate_id`),
  KEY `idx_votes_position` (`position_id`),
  KEY `idx_votes_candidate` (`candidate_id`),
  CONSTRAINT `fk_votes_ballot`
    FOREIGN KEY (`ballot_id`) REFERENCES `ballots` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_votes_position`
    FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_votes_candidate`
    FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. audit_logs - security audit trail
-- ============================================================================
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL DEFAULT NULL,
  `action`      VARCHAR(50)  NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `ip_address`  VARCHAR(45)  NULL DEFAULT NULL,
  `user_agent`  VARCHAR(255) NULL DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. dismissed_alerts - tracks acknowledged/resolved security alerts
-- ============================================================================
CREATE TABLE `dismissed_alerts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_key`    VARCHAR(100) NOT NULL,
  `alert_title`  VARCHAR(150) NOT NULL,
  `severity`     VARCHAR(30)  NOT NULL DEFAULT 'Medium',
  `reason`       VARCHAR(255) NULL DEFAULT NULL,
  `dismissed_by` INT UNSIGNED NULL DEFAULT NULL,
  `dismissed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`       ENUM('resolved','active') NOT NULL DEFAULT 'resolved',
  PRIMARY KEY (`id`),
  KEY `idx_dismissed_alert_key` (`alert_key`),
  KEY `idx_dismissed_by` (`dismissed_by`),
  CONSTRAINT `fk_dismissed_alerts_user_anon`
    FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks dismissed or resolved security alerts by administrators.';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  INITIAL DATA SEEDING
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Administrator Account
-- ----------------------------------------------------------------------------
INSERT INTO `users`
  (`id`,`username`,`email`,`password_hash`,`first_name`,`last_name`,`role`,`status`,`created_at`,`last_login`) VALUES
(1, 'admin', 'admin@securevote.local', '$2y$12$fsgfxiVIwqY4tADR3zyW9e4/rQWE.sS86MtXJc/VSPEcwJp34IN2i', 'System', 'Administrator', 'admin', 'active', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 1 HOUR);

-- ----------------------------------------------------------------------------
-- 2. Elections
-- ----------------------------------------------------------------------------
INSERT INTO `elections`
  (`id`,`title`,`description`,`start_datetime`,`end_datetime`,`status`,`results_visibility`,`created_by`,`created_at`) VALUES
(1, 'University Student Council General Election 2026', 'Official university-wide election for Supreme Student Council executive board and college representatives. Direct anonymous secret ballot.', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 7 DAY, 'open', 'after_vote', 1, NOW() - INTERVAL 5 DAY),
(2, 'College of Computer Studies Representative Election', 'College-level election for CCS representative seat and technical committee chair.', NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 5 DAY, 'open', 'after_vote', 1, NOW() - INTERVAL 4 DAY);

-- ----------------------------------------------------------------------------
-- 3. Positions
-- ----------------------------------------------------------------------------
INSERT INTO `positions`
  (`id`,`election_id`,`name`,`description`,`max_selections`,`display_order`) VALUES
(1, 1, 'President', 'Chief Executive of the Student Council; chairs the executive board.', 1, 1),
(2, 1, 'Vice President', 'Assists the President and oversees council standing committees.', 1, 2),
(3, 1, 'Secretary General', 'Maintains official records, resolutions, and student communications.', 1, 3),
(4, 1, 'Treasurer', 'Manages student activity funds, budget allocations, and financial reports.', 1, 4),
(5, 1, 'Auditor', 'Audits council expenditures and publishes transparency reports.', 1, 5),
(6, 1, 'College Representatives', 'Student council representatives across academic divisions. Choose up to 2.', 2, 6),
(7, 2, 'CCS Council Representative', 'Primary student voice for College of Computer Studies on university council.', 1, 1),
(8, 2, 'Technical Committee Chair', 'Leads university tech events, hackathons, and laboratory welfare.', 1, 2);

-- ----------------------------------------------------------------------------
-- 4. Candidates
-- ----------------------------------------------------------------------------
INSERT INTO `candidates`
  (`id`,`election_id`,`position_id`,`first_name`,`last_name`,`party`,`biography`,`platform`,`status`) VALUES
-- Election 1: President
(1, 1, 1, 'Alyssa', 'Mendoza', 'Progressive Student Alliance (PSA)', '4th Year Computer Science. Current CCS Representative with 3 years in student leadership.', 'Digital student services, free laboratory access expansion, and transparent council budget allocation.', 'active'),
(2, 1, 1, 'Gabriel', 'Santos', 'United Student Movement (USM)', '4th Year Information Technology. Vice President of IT Society and varsity debater.', 'Campus Wi-Fi modernization, industry internship tie-ups, and student mental health initiatives.', 'active'),
(3, 1, 1, 'Joshua', 'De Leon', 'Independent', '3rd Year Information Systems. Founder of Student Hackers Organization.', 'Open data governance, 24/7 campus library access, and zero student fee hikes.', 'active'),

-- Election 1: Vice President
(4, 1, 2, 'Bea', 'Reyes', 'Progressive Student Alliance (PSA)', '3rd Year IT. Secretary of Association of Computing Students.', 'Student organization funding assistance and expanded academic grievance support.', 'active'),
(5, 1, 2, 'Carlos', 'Navarro', 'United Student Movement (USM)', '3rd Year Computer Science. Peer tutor lead and Hackathon champion.', 'Inter-college tech symposiums and peer mentorship programs across all departments.', 'active'),

-- Election 1: Secretary General
(6, 1, 3, 'Patricia', 'Lim', 'Progressive Student Alliance (PSA)', '3rd Year Information Systems. Chief Editor of the College Newsletter.', 'Real-time council resolution publishing and automated student announcements.', 'active'),
(7, 1, 3, 'Dominic', 'Cruz', 'United Student Movement (USM)', '2nd Year Computer Science. Active student council secretariat officer.', 'Online document request portal and weekly student council open forums.', 'active'),

-- Election 1: Treasurer
(8, 1, 4, 'Hannah', 'Torres', 'Progressive Student Alliance (PSA)', '3rd Year Accountancy & Finance. Student Cooperative auditor.', 'Live public financial dashboard and transparent activity funding tracker.', 'active'),
(9, 1, 4, 'Mark', 'Villanueva', 'United Student Movement (USM)', '3rd Year IT. Business development lead for university incubator.', 'Micro-grants for student capstone projects and simplified reimbursement procedures.', 'active'),

-- Election 1: Auditor
(10, 1, 5, 'Chloe', 'Aquino', 'Progressive Student Alliance (PSA)', '3rd Year Information Systems. Internal audit intern.', 'Monthly financial audits published on the website for complete transparency.', 'active'),
(11, 1, 5, 'Ethan', 'Bautista', 'Independent', '3rd Year Computer Science. Open-source maintainer and council volunteer.', 'Independent audit committee with student observer seats in every council spending review.', 'active'),

-- Election 1: College Representatives (Pick up to 2)
(12, 1, 6, 'Lance', 'Castillo', 'Progressive Student Alliance (PSA)', '2nd Year Computer Science student leader.', 'More power outlets in study halls and updated software licenses.', 'active'),
(13, 1, 6, 'Sophia', 'Tan', 'United Student Movement (USM)', '2nd Year Information Technology honor student.', 'Inclusive tech workshops, women in STEM forums, and career mixers.', 'active'),
(14, 1, 6, 'Rafael', 'Soriano', 'Independent', '2nd Year Computer Engineering representative.', 'Laboratory hardware upgrades and faster technical support ticketing.', 'active'),

-- Election 2: CCS Candidates
(15, 2, 7, 'Mateo', 'Dizon', 'Tech Vanguard', '3rd Year Computer Science. Web developer and council tech head.', 'Upgraded server access for student developers and project deployment credits.', 'active'),
(16, 2, 7, 'Kaye', 'Morales', 'Inovasiya CCS', '3rd Year IT. Cybersecurity club president.', 'Cybersecurity training workshops and certification subsidy programs.', 'active'),
(17, 2, 8, 'Janine', 'Pascual', 'Tech Vanguard', '2nd Year IT. AI enthusiast and event organizer.', 'Annual university hackathon with cash prizes and startup seed mentorship.', 'active'),
(18, 2, 8, 'Leo', 'Valdez', 'Inovasiya CCS', '2nd Year CS. Robotics club lead engineer.', 'Hardware prototyping labs and student electronics borrowing center.', 'active');

-- ----------------------------------------------------------------------------
-- 5. Audit Log Seed
-- ----------------------------------------------------------------------------
INSERT INTO `audit_logs`
  (`user_id`,`action`,`description`,`ip_address`,`created_at`) VALUES
(1, 'system_initialized', 'Direct Secret Ballot system initialized. Admin account created.', '127.0.0.1', NOW() - INTERVAL 10 DAY),
(1, 'election_created', 'Election #1 "University Student Council General Election 2026" created.', '127.0.0.1', NOW() - INTERVAL 5 DAY),
(1, 'election_opened', 'Election #1 opened for direct voting.', '127.0.0.1', NOW() - INTERVAL 1 DAY),
(1, 'election_created', 'Election #2 "College of Computer Studies Representative Election" created.', '127.0.0.1', NOW() - INTERVAL 4 DAY),
(1, 'election_opened', 'Election #2 opened for direct voting.', '127.0.0.1', NOW() - INTERVAL 2 DAY);

-- Community features
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
