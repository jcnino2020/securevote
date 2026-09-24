-- ============================================================================
--  SecureVote - Secure Online Voting System
--  Database schema + complete fictional sample data
--
--  Target   : MySQL 5.7+ / MariaDB 10.2+ (developed and tested on MariaDB 12.3)
--  Engine   : InnoDB (transactions + foreign keys)
--  Charset  : utf8mb4 / utf8mb4_unicode_ci
--
--  HOW TO IMPORT (phpMyAdmin)
--    1. Open phpMyAdmin
--    2. Click the "Import" tab at the top
--    3. Choose file: database/secure_voting.sql
--    4. Leave all defaults, then click "Import"
--    5. The database `secure_voting`, all eight tables and the complete sample
--       dataset are created in one step. Nothing is left empty.
--
--  If your hosting already created an empty database for you, delete/comment
--  the CREATE DATABASE and USE lines below and import into that database.
--
--  WHAT THE SAMPLE DATA CONTAINS
--    1 administrator, 20 voters, 3 elections (open / scheduled / closed),
--    11 positions, 33 candidates, 60 eligibility rows, 11 submitted ballots
--    with 67 vote records, and a populated audit trail. Every figure on every
--    screen is calculated from these rows: no number is hard-coded in PHP.
--
--  ALL NAMES, ORGANISATIONS AND ELECTIONS BELOW ARE FICTIONAL. They do not
--  represent real politicians, real political parties, real schools or any
--  actual election.
-- ============================================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `secure_voting`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `secure_voting`;

-- ----------------------------------------------------------------------------
-- Drop existing tables (child tables first so foreign keys never block us)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `votes`;
DROP TABLE IF EXISTS `ballots`;
DROP TABLE IF EXISTS `voter_eligibility`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `positions`;
DROP TABLE IF EXISTS `elections`;
DROP TABLE IF EXISTS `users`;


-- ============================================================================
-- 1. users - administrators and voters (single table, role-based access)
-- ============================================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
  `first_name`    VARCHAR(80)  NOT NULL,
  `last_name`     VARCHAR(80)  NOT NULL,
  `role`          ENUM('admin','voter')               NOT NULL DEFAULT 'voter',
  `status`        ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Accounts. Passwords are bcrypt hashes - never plaintext.';


-- ============================================================================
-- 2. elections
-- ============================================================================
CREATE TABLE `elections` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`              VARCHAR(150) NOT NULL,
  `description`        TEXT         NULL,
  `start_datetime`     DATETIME     NOT NULL,
  `end_datetime`       DATETIME     NOT NULL,
  `status`             ENUM('draft','scheduled','open','closed','archived') NOT NULL DEFAULT 'draft',
  `results_visibility` ENUM('hidden','after_vote','after_close')            NOT NULL DEFAULT 'after_close',
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
-- 3. positions - one row per contest inside an election
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
  `photo`       VARCHAR(255) NULL DEFAULT NULL COMMENT 'filename inside assets/images/candidates/',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='The application additionally verifies candidate.position_id belongs to the same election_id.';


-- ============================================================================
-- 5. voter_eligibility - who may vote in which election (authorization list)
-- ============================================================================
CREATE TABLE `voter_eligibility` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id` INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `has_voted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `voted_at`    DATETIME     NULL DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Core duplicate-voting guard: one eligibility row per voter per election.
  UNIQUE KEY `uq_voter_eligibility_election_user` (`election_id`,`user_id`),
  KEY `idx_eligibility_user` (`user_id`),
  KEY `idx_eligibility_election_voted` (`election_id`,`has_voted`),
  CONSTRAINT `fk_eligibility_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_eligibility_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 6. ballots - one submitted ballot per voter per election
-- ============================================================================
CREATE TABLE `ballots` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `election_id`      INT UNSIGNED NOT NULL,
  `voter_id`         INT UNSIGNED NOT NULL,
  `ballot_reference` VARCHAR(30)  NOT NULL COMMENT 'public receipt code, e.g. SV-2026-000001',
  `submitted_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`           ENUM('submitted','invalidated') NOT NULL DEFAULT 'submitted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ballots_reference` (`ballot_reference`),
  -- Second duplicate-voting guard at the ballot level.
  UNIQUE KEY `uq_ballots_election_voter` (`election_id`,`voter_id`),
  KEY `idx_ballots_election` (`election_id`),
  CONSTRAINT `fk_ballots_election`
    FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ballots_voter`
    FOREIGN KEY (`voter_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 7. votes - the individual selections stored inside a ballot
--
--    No page ever joins a ballot back to the voter's name. Results are
--    produced from aggregate counts only, which is what keeps the ballot
--    secret while still producing a tally.
-- ============================================================================
CREATE TABLE `votes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ballot_id`    INT UNSIGNED NOT NULL,
  `position_id`  INT UNSIGNED NOT NULL,
  `candidate_id` INT UNSIGNED NOT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Blocks the same candidate being stored twice for the same position.
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
-- 8. audit_logs - security and administrative event trail
-- ============================================================================
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL for anonymous events (e.g. failed login)',
  `action`      VARCHAR(50)  NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `ip_address`  VARCHAR(45)  NULL DEFAULT NULL COMMENT 'IPv4 or IPv6',
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


SET FOREIGN_KEY_CHECKS = 1;
-- Foreign key checks stay ON for the sample data below, so any wrong id fails
-- loudly during the import instead of quietly landing in the database.


-- ============================================================================
--  SAMPLE DATA
--
--  Demonstration credentials (documented in the README as well):
--    Administrator : admin     / Admin@12345
--    Voter         : voter001  / Voter@12345   (voter001 to voter020 all share it)
--    Blocked voter : voter020  / Voter@12345   (sign-in refused on purpose)
--
--  The password hashes below were produced with PHP's
--  password_hash($password, PASSWORD_BCRYPT). Bcrypt is one way, so the
--  plaintext password is stored nowhere in this file and cannot be recovered
--  from the hash: it can only be verified with password_verify().
--
--  Timestamps are written relative to the import moment (NOW()), so the
--  demonstration looks freshly prepared no matter when it is imported: the
--  open election always has two days behind it and five days ahead.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1 administrator + 20 voters
--
-- Some candidate names also appear in the voter list. That is deliberate and
-- realistic for a student election: a candidate is also a student voter. Users
-- and candidates are separate tables, so there is no conflict of any kind.
-- The two bcrypt hashes are Admin@12345 (admin) and Voter@12345 (every voter).
-- ----------------------------------------------------------------------------
INSERT INTO `users`
  (`id`,`username`,`email`,`password_hash`,`first_name`,`last_name`,`role`,`status`,`created_at`,`last_login`) VALUES
(1,  'admin',    'admin@securevote.local', '$2y$12$fsgfxiVIwqY4tADR3zyW9e4/rQWE.sS86MtXJc/VSPEcwJp34IN2i', 'System',   'Administrator', 'admin', 'active',  NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 5 HOUR),
(2,  'voter001', 'voter001@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Alex',     'Santos',        'voter', 'active',  NOW() - INTERVAL 55 DAY, NOW() - INTERVAL 1 HOUR),
(3,  'voter002', 'voter002@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Miguel',   'Reyes',         'voter', 'active',  NOW() - INTERVAL 54 DAY, NOW() - INTERVAL 2 DAY),
(4,  'voter003', 'voter003@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Sofia',    'Garcia',        'voter', 'active',  NOW() - INTERVAL 53 DAY, NOW() - INTERVAL 44 HOUR),
(5,  'voter004', 'voter004@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Andrea',   'Ramos',         'voter', 'active',  NOW() - INTERVAL 52 DAY, NOW() - INTERVAL 30 HOUR),
(6,  'voter005', 'voter005@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Carlo',    'Mendoza',       'voter', 'active',  NOW() - INTERVAL 51 DAY, NOW() - INTERVAL 26 HOUR),
(7,  'voter006', 'voter006@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Daniel',   'Cruz',          'voter', 'active',  NOW() - INTERVAL 50 DAY, NOW() - INTERVAL 22 HOUR),
(8,  'voter007', 'voter007@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Bianca',   'Flores',        'voter', 'active',  NOW() - INTERVAL 49 DAY, NOW() - INTERVAL 12 HOUR),
(9,  'voter008', 'voter008@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Rafael',   'Torres',        'voter', 'active',  NOW() - INTERVAL 48 DAY, NOW() - INTERVAL 9 HOUR),
(10, 'voter009', 'voter009@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Nicole',   'Navarro',       'voter', 'active',  NOW() - INTERVAL 47 DAY, NOW() - INTERVAL 4 DAY),
(11, 'voter010', 'voter010@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Ethan',    'Castillo',      'voter', 'active',  NOW() - INTERVAL 46 DAY, NOW() - INTERVAL 5 DAY),
(12, 'voter011', 'voter011@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Hannah',   'Rivera',        'voter', 'active',  NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 6 DAY),
(13, 'voter012', 'voter012@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Joshua',   'Fernandez',     'voter', 'active',  NOW() - INTERVAL 44 DAY, NOW() - INTERVAL 7 HOUR),
(14, 'voter013', 'voter013@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Maria',    'Lopez',         'voter', 'active',  NOW() - INTERVAL 43 DAY, NOW() - INTERVAL 8 DAY),
(15, 'voter014', 'voter014@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Kevin',    'Bautista',      'voter', 'active',  NOW() - INTERVAL 42 DAY, NOW() - INTERVAL 9 DAY),
(16, 'voter015', 'voter015@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Angela',   'Villanueva',    'voter', 'active',  NOW() - INTERVAL 41 DAY, NOW() - INTERVAL 11 DAY),
(17, 'voter016', 'voter016@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Patrick',  'Gonzales',      'voter', 'active',  NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 12 DAY),
(18, 'voter017', 'voter017@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Samantha', 'Aquino',        'voter', 'active',  NOW() - INTERVAL 39 DAY, NOW() - INTERVAL 13 DAY),
(19, 'voter018', 'voter018@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Adrian',   'Mercado',       'voter', 'active',  NOW() - INTERVAL 38 DAY, NOW() - INTERVAL 14 DAY),
(20, 'voter019', 'voter019@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Chloe',    'Ramirez',       'voter', 'active',  NOW() - INTERVAL 37 DAY, NOW() - INTERVAL 15 DAY),
(21, 'voter020', 'voter020@student.local', '$2y$12$uWefsCvqf4m9pRlmzavsjOd1L2pxeCIp01/.bXmcqGlf37MaTekMK', 'Nathan',   'Delgado',       'voter', 'blocked', NOW() - INTERVAL 36 DAY, NOW() - INTERVAL 6 DAY);


-- ----------------------------------------------------------------------------
-- 3 elections, one in each state the system has to handle
--   #1 Student Council Election 2026      -> OPEN      (window covers right now)
--   #2 College Department Election 2026   -> SCHEDULED (window starts next week)
--   #3 Student Organization Election 2026 -> CLOSED    (window already ended)
--
-- The three elections also carry the three possible results policies, so the
-- per-election publication rule can be shown: after_vote (#1), hidden (#2) and
-- after_close (#3).
-- ----------------------------------------------------------------------------
INSERT INTO `elections`
  (`id`,`title`,`description`,`start_datetime`,`end_datetime`,`status`,`results_visibility`,`created_by`,`created_at`) VALUES
(1, 'Student Council Election 2026',
    'Annual election of the Supreme Student Council officers for academic year 2026-2027. Each registered voter may submit exactly one ballot. Positions that allow more than one selection are marked on the ballot.',
    NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 5 DAY, 'open', 'after_vote', 1, NOW() - INTERVAL 21 DAY),
(2, 'College Department Election 2026',
    'Election of the College Department officers. Voter registration is already open, but no ballot can be submitted before the published start date.',
    NOW() + INTERVAL 7 DAY, NOW() + INTERVAL 9 DAY, 'scheduled', 'hidden', 1, NOW() - INTERVAL 14 DAY),
(3, 'Student Organization Election 2026',
    'Election of the officers of the recognised student organizations. This election has already finished, so voting is closed and its results are published.',
    NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 33 DAY, 'closed', 'after_close', 1, NOW() - INTERVAL 45 DAY);


-- ----------------------------------------------------------------------------
-- Positions
--   Election 1: 7 positions. Representative allows 2 selections, the rest 1.
--   Election 2: 2 positions (not votable yet).
--   Election 3: 2 positions (already finished).
-- ----------------------------------------------------------------------------
INSERT INTO `positions`
  (`id`,`election_id`,`name`,`description`,`max_selections`,`display_order`,`created_at`) VALUES
(1,  1, 'President',                  'Highest executive officer of the Supreme Student Council.', 1, 1, NOW() - INTERVAL 21 DAY),
(2,  1, 'Vice President',             'Assists the President and leads the internal committees.',  1, 2, NOW() - INTERVAL 21 DAY),
(3,  1, 'Secretary',                  'Keeps the minutes and the official records.',               1, 3, NOW() - INTERVAL 21 DAY),
(4,  1, 'Treasurer',                  'Manages the council funds and the financial reports.',       1, 4, NOW() - INTERVAL 20 DAY),
(5,  1, 'Auditor',                    'Audits the council finances and assets.',                    1, 5, NOW() - INTERVAL 20 DAY),
(6,  1, 'Public Information Officer', 'Publishes announcements and handles public relations.',      1, 6, NOW() - INTERVAL 19 DAY),
(7,  1, 'Representative',             'Represents the student body. Choose up to 2.',               2, 7, NOW() - INTERVAL 19 DAY),
(8,  2, 'President',                  'President of the College Department officers.',              1, 1, NOW() - INTERVAL 14 DAY),
(9,  2, 'Vice President',             'Vice President of the College Department officers.',         1, 2, NOW() - INTERVAL 14 DAY),
(10, 3, 'President',                  'President of the recognised student organizations.',         1, 1, NOW() - INTERVAL 45 DAY),
(11, 3, 'Vice President',             'Vice President of the recognised student organizations.',    1, 2, NOW() - INTERVAL 45 DAY);


-- ----------------------------------------------------------------------------
-- Candidates - 33 fictional students, 3 per position (4 for Representative)
-- Fictional organizations: Unity Alliance, Student First, Forward Movement,
-- plus one Independent candidate.
-- ----------------------------------------------------------------------------
INSERT INTO `candidates`
  (`id`,`election_id`,`position_id`,`first_name`,`last_name`,`party`,`biography`,`platform`,`status`,`created_at`) VALUES
-- Position 1 - President (election 1)
(1,  1, 1, 'Alex',     'Santos',     'Unity Alliance',
 'Third year BSIT student and current council sergeant-at-arms. Handled logistics for three campus-wide events.',
 'Transparent budget reporting, faster student document processing, and a monthly open forum.', 'active', NOW() - INTERVAL 20 DAY),
(2,  1, 1, 'Miguel',   'Reyes',      'Student First',
 'Fourth year BS Accountancy student and former class president. Two terms in the finance committee.',
 'Free printing quota for thesis students, more scholarship slots, and a welfare desk in every building.', 'active', NOW() - INTERVAL 20 DAY),
(3,  1, 1, 'Daniel',   'Cruz',       'Forward Movement',
 'Fourth year BS Education student and campus radio host. Advocates for student representation in policy discussions.',
 'A student seat in the academic council, better library internet, and an anti-bullying referral program.', 'active', NOW() - INTERVAL 19 DAY),
-- Position 2 - Vice President
(4,  1, 2, 'Sofia',    'Garcia',     'Unity Alliance',
 'Second year BS Psychology student and peer counsellor for two years.',
 'A permanent student wellness room and a peer support program in every college.', 'active', NOW() - INTERVAL 19 DAY),
(5,  1, 2, 'Andrea',   'Ramos',      'Student First',
 'Third year BS Civil Engineering student and officer of the engineering society.',
 'Clearer coordination between student organizations and a shared events calendar.', 'active', NOW() - INTERVAL 19 DAY),
(6,  1, 2, 'Carlo',    'Mendoza',    'Forward Movement',
 'Third year BS Accountancy student and current assistant treasurer.',
 'Quarterly financial statements posted publicly for every student to review.', 'active', NOW() - INTERVAL 18 DAY),
-- Position 3 - Secretary
(7,  1, 3, 'Bianca',   'Flores',     'Unity Alliance',
 'Second year BSIT student and documentation head of the campus publication.',
 'Publish the council minutes within 48 hours of every meeting.', 'active', NOW() - INTERVAL 18 DAY),
(8,  1, 3, 'Rafael',   'Torres',     'Student First',
 'Third year BS Accountancy student and records officer of the debating society.',
 'A searchable digital archive of every council resolution.', 'active', NOW() - INTERVAL 18 DAY),
(9,  1, 3, 'Nicole',   'Navarro',    'Forward Movement',
 'Second year BS Education student and class secretary since first year.',
 'An online attendance system for council assemblies and clearly published minutes.', 'active', NOW() - INTERVAL 17 DAY),
-- Position 4 - Treasurer
(10, 1, 4, 'Ethan',    'Castillo',   'Student First',
 'Third year BS Accountancy student and small business owner.',
 'Itemised spending reports and a simple online request form for organization funds.', 'active', NOW() - INTERVAL 17 DAY),
(11, 1, 4, 'Hannah',   'Rivera',     'Unity Alliance',
 'Third year BS Business Administration student and finance committee member.',
 'A published cash-flow summary every month and a public procurement checklist.', 'active', NOW() - INTERVAL 17 DAY),
(12, 1, 4, 'Joshua',   'Fernandez',  'Forward Movement',
 'Second year BS Accountancy student and treasurer of the accounting society.',
 'End-of-term audit summaries and a student-friendly guide to the council budget.', 'active', NOW() - INTERVAL 16 DAY),
-- Position 5 - Auditor
(13, 1, 5, 'Maria',    'Lopez',      'Forward Movement',
 'Fourth year BS Accountancy student and internal audit intern.',
 'Independent spot audits of every council-funded project.', 'active', NOW() - INTERVAL 16 DAY),
(14, 1, 5, 'Kevin',    'Bautista',   'Student First',
 'Third year BS Accountancy student and treasurer of the business society.',
 'A published audit report at the end of each semester.', 'active', NOW() - INTERVAL 16 DAY),
(15, 1, 5, 'Angela',   'Villanueva', 'Unity Alliance',
 'Second year BS Accountancy student and campus cooperative volunteer.',
 'A public receipt log for every expense above one thousand pesos.', 'active', NOW() - INTERVAL 15 DAY),
-- Position 6 - Public Information Officer
(16, 1, 6, 'Patrick',  'Gonzales',   'Unity Alliance',
 'Third year BS Development Communication student and campus journalist.',
 'A weekly council bulletin and same-day announcements on the official page.', 'active', NOW() - INTERVAL 15 DAY),
(17, 1, 6, 'Samantha', 'Aquino',     'Student First',
 'Second year BSIT student and social media manager of the IT society.',
 'A single verified announcement channel so that no notice is missed.', 'active', NOW() - INTERVAL 15 DAY),
(18, 1, 6, 'Adrian',   'Mercado',    'Forward Movement',
 'Third year BS Education student and campus radio production head.',
 'A daily five-minute campus news segment and a printed bulletin board digest.', 'active', NOW() - INTERVAL 14 DAY),
-- Position 7 - Representative (up to 2 selections)
(19, 1, 7, 'Chloe',    'Ramirez',    'Unity Alliance',
 'Second year BSIT student and competitive programmer.',
 'A 24-hour study area during finals week and additional computer laboratory units.', 'active', NOW() - INTERVAL 14 DAY),
(20, 1, 7, 'Nathan',   'Delgado',    'Student First',
 'Third year BS Mechanical Engineering student and varsity athlete.',
 'Better sports facilities and a free shuttle service during intramurals.', 'active', NOW() - INTERVAL 14 DAY),
(21, 1, 7, 'Isabel',   'Domingo',    'Independent',
 'Second year BS Architecture student and member of the campus art collective.',
 'More student exhibition spaces and a small budget for creative projects.', 'active', NOW() - INTERVAL 13 DAY),
(22, 1, 7, 'Gabriel',  'Salazar',    'Forward Movement',
 'Third year BS Information Systems student and cybersecurity club president.',
 'A student-run tech help desk and digital literacy workshops.', 'active', NOW() - INTERVAL 13 DAY),
-- Position 8 - President (election 2, not votable yet)
(23, 2, 8, 'Bianca',   'Flores',     'Unity Alliance',
 'Fourth year BS Accountancy student and department representative.',
 'A department-wide consultation before every policy change.', 'active', NOW() - INTERVAL 13 DAY),
(24, 2, 8, 'Rafael',   'Torres',     'Student First',
 'Fourth year BS Education student and former intramural coordinator.',
 'More laboratory hours and a formal review of the department curriculum.', 'active', NOW() - INTERVAL 12 DAY),
(25, 2, 8, 'Nicole',   'Navarro',    'Forward Movement',
 'Third year BS Education student and student teacher.',
 'A peer tutoring exchange and clearer grading rubrics.', 'active', NOW() - INTERVAL 12 DAY),
-- Position 9 - Vice President (election 2)
(26, 2, 9, 'Ethan',    'Castillo',   'Unity Alliance',
 'Third year BS Accountancy student and department event coordinator.',
 'A shared calendar for department activities and clearer fee breakdowns.', 'active', NOW() - INTERVAL 12 DAY),
(27, 2, 9, 'Hannah',   'Rivera',     'Student First',
 'Second year BS Business Administration student and class officer.',
 'Regular department assemblies and a feedback form after every event.', 'active', NOW() - INTERVAL 11 DAY),
-- Position 10 - President (election 3, already finished)
(28, 3, 10, 'Joshua',  'Fernandez',  'Student First',
 'Third year BS Accountancy student and founder of the student cooperative.',
 'Transparent cooperative finances and a member benefit programme.', 'active', NOW() - INTERVAL 45 DAY),
(29, 3, 10, 'Maria',   'Lopez',      'Forward Movement',
 'Fourth year BS Accountancy student and volunteer auditor.',
 'Independent audits and a published annual report for every organization.', 'active', NOW() - INTERVAL 45 DAY),
(30, 3, 10, 'Kevin',   'Bautista',   'Unity Alliance',
 'Third year BS Business Administration student and events head.',
 'Joint organization projects and a shared equipment inventory.', 'active', NOW() - INTERVAL 44 DAY),
-- Position 11 - Vice President (election 3)
(31, 3, 11, 'Angela',  'Villanueva', 'Unity Alliance',
 'Second year BS Accountancy student and cooperative volunteer.',
 'Monthly open meetings and a written summary of every resolution.', 'active', NOW() - INTERVAL 44 DAY),
(32, 3, 11, 'Patrick', 'Gonzales',   'Student First',
 'Third year BS Development Communication student and campus journalist.',
 'A shared announcement channel for all recognized organizations.', 'active', NOW() - INTERVAL 44 DAY),
(33, 3, 11, 'Samantha','Aquino',     'Forward Movement',
 'Second year BSIT student and social media manager.',
 'A single calendar of organization activities and deadlines.', 'active', NOW() - INTERVAL 43 DAY);


-- ----------------------------------------------------------------------------
-- Voter eligibility
--
-- All 20 voters are registered for all 3 elections (60 rows), starting with
-- has_voted = 0. The flags are synchronised from the ballots further down, so
-- an eligibility row can never claim a vote that has no ballot behind it.
-- ----------------------------------------------------------------------------
INSERT INTO `voter_eligibility` (`election_id`,`user_id`,`has_voted`,`voted_at`,`created_at`)
SELECT e.`id`, u.`id`, 0, NULL, e.`created_at`
  FROM `elections` e
  CROSS JOIN `users` u
 WHERE u.`role` = 'voter';


-- ----------------------------------------------------------------------------
-- Submitted ballots
--   Election 1 (open):      8 ballots, SV-2026-000001 .. SV-2026-000008
--   Election 3 (closed):    3 ballots, SV-2026-000009 .. SV-2026-000011
--   Election 2 (scheduled): none, because voting has not started
--
-- Ballot ids 1..8 belong to user ids 2..9 (voter001..voter008).
-- Ballot ids 9..11 belong to user ids 2, 3, 4 (voter001..voter003).
-- ----------------------------------------------------------------------------
INSERT INTO `ballots`
  (`id`,`election_id`,`voter_id`,`ballot_reference`,`submitted_at`,`status`) VALUES
(1,  1, 2, 'SV-2026-000001', NOW() - INTERVAL 2 DAY,   'submitted'),
(2,  1, 3, 'SV-2026-000002', NOW() - INTERVAL 44 HOUR, 'submitted'),
(3,  1, 4, 'SV-2026-000003', NOW() - INTERVAL 30 HOUR, 'submitted'),
(4,  1, 5, 'SV-2026-000004', NOW() - INTERVAL 26 HOUR, 'submitted'),
(5,  1, 6, 'SV-2026-000005', NOW() - INTERVAL 22 HOUR, 'submitted'),
(6,  1, 7, 'SV-2026-000006', NOW() - INTERVAL 20 HOUR, 'submitted'),
(7,  1, 8, 'SV-2026-000007', NOW() - INTERVAL 12 HOUR, 'submitted'),
(8,  1, 9, 'SV-2026-000008', NOW() - INTERVAL 9 HOUR,  'submitted'),
(9,  3, 2, 'SV-2026-000009', NOW() - INTERVAL 36 DAY,  'submitted'),
(10, 3, 3, 'SV-2026-000010', NOW() - INTERVAL 35 DAY,  'submitted'),
(11, 3, 4, 'SV-2026-000011', NOW() - INTERVAL 34 DAY,  'submitted');


-- ----------------------------------------------------------------------------
-- Vote records - 61 rows for election 1, 6 rows for election 3.
--
-- Every row satisfies all of the integrity rules the application also
-- enforces: the candidate belongs to that position, the position belongs to
-- that election, the ballot belongs to that election, and no position exceeds
-- its max_selections.
--
-- Election 1 results produced by these rows (one candidate is missing from
-- some ballots because those selections were left blank, which is allowed):
--   President   Alex Santos 4, Miguel Reyes 3, Daniel Cruz 1
--   Vice Pres.  Sofia Garcia 3, Andrea Ramos 3, Carlo Mendoza 2
--   Secretary   Bianca Flores 2, Rafael Torres 3, Nicole Navarro 3
--   Treasurer   Ethan Castillo 4, Hannah Rivera 3, Joshua Fernandez 1
--   Auditor     Maria Lopez 3, Kevin Bautista 3, Angela Villanueva 2
--   Public Info Patrick Gonzales 4, Samantha Aquino 2, Adrian Mercado 2
--   Rep.        Chloe Ramirez 4, Nathan Delgado 3, Isabel Domingo 3,
--               Gabriel Salazar 3    (a three-way tie for the second seat,
--               which the results page reports as a tie instead of deciding
--               the winner silently)
--
-- Election 3 results:
--   President   Joshua Fernandez 2, Maria Lopez 1
--   Vice Pres.  Patrick Gonzales 2, Angela Villanueva 1
-- ----------------------------------------------------------------------------
INSERT INTO `votes` (`ballot_id`,`position_id`,`candidate_id`,`created_at`) VALUES
-- Ballot SV-2026-000001 (voter001)
(1, 1, 1,  NOW() - INTERVAL 2 DAY),  (1, 2, 4,  NOW() - INTERVAL 2 DAY),  (1, 3, 7,  NOW() - INTERVAL 2 DAY),
(1, 4, 10, NOW() - INTERVAL 2 DAY),  (1, 5, 13, NOW() - INTERVAL 2 DAY),  (1, 6, 16, NOW() - INTERVAL 2 DAY),
(1, 7, 19, NOW() - INTERVAL 2 DAY),  (1, 7, 20, NOW() - INTERVAL 2 DAY),
-- Ballot SV-2026-000002 (voter002)
(2, 1, 1,  NOW() - INTERVAL 44 HOUR), (2, 2, 4,  NOW() - INTERVAL 44 HOUR), (2, 3, 7,  NOW() - INTERVAL 44 HOUR),
(2, 4, 10, NOW() - INTERVAL 44 HOUR), (2, 5, 13, NOW() - INTERVAL 44 HOUR), (2, 6, 16, NOW() - INTERVAL 44 HOUR),
(2, 7, 19, NOW() - INTERVAL 44 HOUR),
-- Ballot SV-2026-000003 (voter003)
(3, 1, 1,  NOW() - INTERVAL 30 HOUR), (3, 2, 4,  NOW() - INTERVAL 30 HOUR), (3, 3, 8,  NOW() - INTERVAL 30 HOUR),
(3, 4, 10, NOW() - INTERVAL 30 HOUR), (3, 5, 14, NOW() - INTERVAL 30 HOUR), (3, 6, 16, NOW() - INTERVAL 30 HOUR),
(3, 7, 19, NOW() - INTERVAL 30 HOUR), (3, 7, 21, NOW() - INTERVAL 30 HOUR),
-- Ballot SV-2026-000004 (voter004)
(4, 1, 1,  NOW() - INTERVAL 26 HOUR), (4, 2, 5,  NOW() - INTERVAL 26 HOUR), (4, 3, 8,  NOW() - INTERVAL 26 HOUR),
(4, 4, 10, NOW() - INTERVAL 26 HOUR), (4, 5, 14, NOW() - INTERVAL 26 HOUR), (4, 6, 17, NOW() - INTERVAL 26 HOUR),
(4, 7, 20, NOW() - INTERVAL 26 HOUR), (4, 7, 22, NOW() - INTERVAL 26 HOUR),
-- Ballot SV-2026-000005 (voter005)
(5, 1, 2,  NOW() - INTERVAL 22 HOUR), (5, 2, 5,  NOW() - INTERVAL 22 HOUR), (5, 3, 8,  NOW() - INTERVAL 22 HOUR),
(5, 4, 11, NOW() - INTERVAL 22 HOUR), (5, 5, 14, NOW() - INTERVAL 22 HOUR), (5, 6, 17, NOW() - INTERVAL 22 HOUR),
(5, 7, 20, NOW() - INTERVAL 22 HOUR),
-- Ballot SV-2026-000006 (voter006)
(6, 1, 2,  NOW() - INTERVAL 20 HOUR), (6, 2, 5,  NOW() - INTERVAL 20 HOUR), (6, 3, 9,  NOW() - INTERVAL 20 HOUR),
(6, 4, 11, NOW() - INTERVAL 20 HOUR), (6, 5, 15, NOW() - INTERVAL 20 HOUR), (6, 6, 18, NOW() - INTERVAL 20 HOUR),
(6, 7, 21, NOW() - INTERVAL 20 HOUR),
-- Ballot SV-2026-000007 (voter007)
(7, 1, 2,  NOW() - INTERVAL 12 HOUR), (7, 2, 6,  NOW() - INTERVAL 12 HOUR), (7, 3, 9,  NOW() - INTERVAL 12 HOUR),
(7, 4, 12, NOW() - INTERVAL 12 HOUR), (7, 5, 15, NOW() - INTERVAL 12 HOUR), (7, 6, 16, NOW() - INTERVAL 12 HOUR),
(7, 7, 19, NOW() - INTERVAL 12 HOUR), (7, 7, 22, NOW() - INTERVAL 12 HOUR),
-- Ballot SV-2026-000008 (voter008)
(8, 1, 3,  NOW() - INTERVAL 9 HOUR), (8, 2, 6,  NOW() - INTERVAL 9 HOUR), (8, 3, 9,  NOW() - INTERVAL 9 HOUR),
(8, 4, 11, NOW() - INTERVAL 9 HOUR), (8, 5, 13, NOW() - INTERVAL 9 HOUR), (8, 6, 18, NOW() - INTERVAL 9 HOUR),
(8, 7, 21, NOW() - INTERVAL 9 HOUR), (8, 7, 22, NOW() - INTERVAL 9 HOUR),
-- Ballot SV-2026-000009 (voter001, Student Organization Election 2026)
(9, 10, 28, NOW() - INTERVAL 36 DAY), (9, 11, 31, NOW() - INTERVAL 36 DAY),
-- Ballot SV-2026-000010 (voter002)
(10, 10, 28, NOW() - INTERVAL 35 DAY), (10, 11, 32, NOW() - INTERVAL 35 DAY),
-- Ballot SV-2026-000011 (voter003)
(11, 10, 29, NOW() - INTERVAL 34 DAY), (11, 11, 32, NOW() - INTERVAL 34 DAY);


-- ----------------------------------------------------------------------------
-- Synchronise the eligibility flags with the ballots that now exist.
--
-- This is the only place the sample data sets has_voted, and it derives the
-- value from the ballots themselves, so the two can never disagree. A voter
-- with no ballot stays at has_voted = 0, which is what leaves 12 voters still
-- able to vote in the open election.
--
-- Result: election 1 has 8 voters who have voted and 12 who have not (40%
-- turnout); election 3 has the 3 voters who took part before it closed.
-- ----------------------------------------------------------------------------
UPDATE `voter_eligibility` AS ve
   SET `has_voted` = 1,
       `voted_at`  = (SELECT b.`submitted_at`
                        FROM `ballots` b
                       WHERE b.`election_id` = ve.`election_id`
                         AND b.`voter_id`    = ve.`user_id`
                       LIMIT 1)
 WHERE `has_voted` = 0
   AND EXISTS (SELECT 1
                 FROM `ballots` b
                WHERE b.`election_id` = ve.`election_id`
                  AND b.`voter_id`    = ve.`user_id`);


-- ----------------------------------------------------------------------------
-- Audit trail - 57 entries telling one continuous story: the administrator
-- built the elections, added candidates, created and registered voters, opened
-- the elections, voters submitted ballots over the last two days, and a few
-- sign-ins failed or were refused. No passwords, tokens or other secret values
-- are recorded, only what happened, who did it, from where and when.
-- ----------------------------------------------------------------------------
INSERT INTO `audit_logs` (`user_id`,`action`,`description`,`ip_address`,`user_agent`,`created_at`) VALUES
-- Student Organization Election 2026 was set up, voted in, and closed
(1,  'election_created',   'Created election #3 "Student Organization Election 2026".',                   '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 45 DAY),
(1,  'position_created',   'Created position #10 "President" in election #3.',                            '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 45 DAY),
(1,  'position_created',   'Created position #11 "Vice President" in election #3.',                       '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 45 DAY),
(1,  'candidate_created',  'Created candidate #28 "Joshua Fernandez" in position #10 of election #3.',    '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 44 DAY),
(1,  'candidate_created',  'Created candidate #29 "Maria Lopez" in position #10 of election #3.',         '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 44 DAY),
(1,  'candidate_created',  'Created candidate #30 "Kevin Bautista" in position #10 of election #3.',      '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 44 DAY),
(1,  'candidate_created',  'Created candidate #31 "Angela Villanueva" in position #11 of election #3.',   '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 44 DAY),
(1,  'candidate_created',  'Created candidate #32 "Patrick Gonzales" in position #11 of election #3.',    '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 43 DAY),
(1,  'candidate_created',  'Created candidate #33 "Samantha Aquino" in position #11 of election #3.',     '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 43 DAY),
(1,  'eligibility_bulk_added', 'Registered 20 active voter(s) for election #3 "Student Organization Election 2026".', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW() - INTERVAL 43 DAY),
(1,  'election_activated', 'Election #3 "Student Organization Election 2026" set to open.',               '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 43 DAY),
(2,  'login_success',      'Signed in as "voter001".',                                                    '192.168.1.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 36 DAY),
(2,  'ballot_submitted',   'Ballot SV-2026-000009 submitted for election #3 (2 position(s) presented).',  '192.168.1.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 36 DAY),
(3,  'ballot_submitted',   'Ballot SV-2026-000010 submitted for election #3 (2 position(s) presented).',  '192.168.1.22', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 35 DAY),
(4,  'ballot_submitted',   'Ballot SV-2026-000011 submitted for election #3 (2 position(s) presented).',  '192.168.1.23', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', NOW() - INTERVAL 34 DAY),
(1,  'election_closed',    'Election #3 "Student Organization Election 2026" set to closed.',             '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 33 DAY),
-- Student Council Election 2026 was built, opened, and is running now
(1,  'election_created',   'Created election #1 "Student Council Election 2026".',                        '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 21 DAY),
(1,  'position_created',   'Created position #1 "President" in election #1.',                             '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 21 DAY),
(1,  'position_created',   'Created position #2 "Vice President" in election #1.',                        '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 21 DAY),
(1,  'position_created',   'Created position #7 "Representative" in election #1 (up to 2 selections).',    '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 19 DAY),
(1,  'candidate_created',  'Created candidate #1 "Alex Santos" in position #1 of election #1.',            '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 20 DAY),
(1,  'candidate_created',  'Created candidate #2 "Miguel Reyes" in position #1 of election #1.',           '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 20 DAY),
(1,  'candidate_created',  'Created candidate #3 "Daniel Cruz" in position #1 of election #1.',            '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 19 DAY),
(1,  'candidate_updated',  'Updated candidate #3 "Daniel Cruz" in position #1 (status active).',           '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 19 DAY),
(1,  'voter_created',      'Created voter #2 "voter001".',                                                '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 18 DAY),
(1,  'voter_created',      'Created voter #3 "voter002".',                                                '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 18 DAY),
(1,  'voter_created',      'Created voter #4 "voter003".',                                                '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 18 DAY),
(1,  'eligibility_bulk_added', 'Registered 20 active voter(s) for election #1 "Student Council Election 2026".', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW() - INTERVAL 18 DAY),
(1,  'election_activated', 'Election #1 "Student Council Election 2026" set to open.',                    '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 17 DAY),
-- College Department Election 2026 was created and scheduled
(1,  'election_created',   'Created election #2 "College Department Election 2026".',                      '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 14 DAY),
(1,  'position_created',   'Created position #8 "President" in election #2.',                              '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 14 DAY),
(1,  'candidate_created',  'Created candidate #23 "Bianca Flores" in position #8 of election #2.',         '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 13 DAY),
(1,  'eligibility_bulk_added', 'Registered 20 active voter(s) for election #2 "College Department Election 2026".', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW() - INTERVAL 13 DAY),
(1,  'election_updated',   'Updated election #2 "College Department Election 2026" (schedule confirmed, status scheduled).', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW() - INTERVAL 3 DAY),
-- Account administration
(1,  'voter_status_changed', 'Voter #21 "voter020" status changed from active to blocked.',                '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 5 DAY),
-- The last two days of voting in the open election
(2,  'login_success',      'Signed in as "voter001".',                                                    '192.168.1.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 2 DAY),
(2,  'ballot_reviewed',    'Ballot for election #1 prepared for review.',                                  '192.168.1.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 2 DAY),
(2,  'ballot_submitted',   'Ballot SV-2026-000001 submitted for election #1 (7 position(s) presented).',  '192.168.1.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 2 DAY),
(3,  'login_success',      'Signed in as "voter002".',                                                    '192.168.1.22', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 45 HOUR),
(3,  'ballot_submitted',   'Ballot SV-2026-000002 submitted for election #1 (7 position(s) presented).',  '192.168.1.22', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 44 HOUR),
(4,  'ballot_submitted',   'Ballot SV-2026-000003 submitted for election #1 (7 position(s) presented).',  '192.168.1.23', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', NOW() - INTERVAL 30 HOUR),
(5,  'ballot_submitted',   'Ballot SV-2026-000004 submitted for election #1 (7 position(s) presented).',  '192.168.1.24', 'Mozilla/5.0 (Linux; Android 14)', NOW() - INTERVAL 26 HOUR),
(6,  'login_success',      'Signed in as "voter005".',                                                    '192.168.1.25', 'Mozilla/5.0 (Linux; Android 14)', NOW() - INTERVAL 23 HOUR),
(6,  'ballot_submitted',   'Ballot SV-2026-000005 submitted for election #1 (7 position(s) presented).',  '192.168.1.25', 'Mozilla/5.0 (Linux; Android 14)', NOW() - INTERVAL 22 HOUR),
(7,  'ballot_submitted',   'Ballot SV-2026-000006 submitted for election #1 (7 position(s) presented).',  '192.168.1.26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 20 HOUR),
(8,  'logout',             'Signed out "voter006".',                                                      '192.168.1.26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 18 HOUR),
(8,  'ballot_submitted',   'Ballot SV-2026-000007 submitted for election #1 (7 position(s) presented).',  '192.168.1.26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 12 HOUR),
(9,  'ballot_duplicate_attempt', 'Reached the ballot for election #1 but a ballot already exists for this account.', '192.168.1.27', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW() - INTERVAL 11 HOUR),
(16, 'login_failure',      'Failed sign-in for "voter015": wrong password.',                              '192.168.1.34', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 10 HOUR),
(9,  'ballot_submitted',   'Ballot SV-2026-000008 submitted for election #1 (7 position(s) presented).',  '192.168.1.27', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 9 HOUR),
(13, 'unauthorized_access', 'User "voter012" (voter) was denied dashboard.php.',                          '192.168.1.31', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 8 HOUR),
(21, 'login_blocked',      'Blocked sign-in for "voter020": account status is blocked.',                  '192.168.1.39', 'Mozilla/5.0 (Linux; Android 14)', NOW() - INTERVAL 6 HOUR),
(1,  'login_success',      'Signed in as "admin".',                                                       '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 5 HOUR),
(1,  'login_failure',      'Failed sign-in for "admin": wrong password.',                                 '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',  NOW() - INTERVAL 3 HOUR),
(NULL,'login_failure',     'Failed sign-in for "unknown.user": no such account.',                         '192.168.1.77', 'Mozilla/5.0 (X11; Linux x86_64)', NOW() - INTERVAL 2 HOUR),
(2,  'login_success',      'Signed in as "voter001".',                                                    '192.168.1.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 1 HOUR),
(1,  'election_updated',   'Updated election #1 "Student Council Election 2026" (results policy after_vote confirmed).', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW() - INTERVAL 30 MINUTE);


-- ============================================================================
--  SAMPLE DATA SUMMARY
--
--  Run these to see exactly what the import produced. The expected value is in
--  each comment, so a mismatch is immediately visible.
-- ============================================================================
--
--   SELECT COUNT(*) FROM users WHERE role = 'admin';   -- 1
--   SELECT COUNT(*) FROM users WHERE role = 'voter';   -- 20
--   SELECT COUNT(*) FROM users WHERE status = 'blocked'; -- 1
--   SELECT COUNT(*) FROM elections;                    -- 3
--   SELECT COUNT(*) FROM positions;                    -- 11
--   SELECT COUNT(*) FROM candidates;                   -- 33
--   SELECT COUNT(*) FROM voter_eligibility;            -- 60
--   SELECT COUNT(*) FROM ballots;                      -- 11
--   SELECT COUNT(*) FROM votes;                        -- 67
--   SELECT COUNT(*) FROM audit_logs;                   -- 57
--
--   -- All three election states are present
--   SELECT id, title, status FROM elections ORDER BY id;
--
--   -- Turnout in the open election: 8 of 20 eligible voters = 40%
--   SELECT COUNT(*) AS eligible, SUM(has_voted) AS cast
--     FROM voter_eligibility WHERE election_id = 1;
--
--   -- How many voters can still vote in the open election: 12
--   SELECT COUNT(*) FROM voter_eligibility WHERE election_id = 1 AND has_voted = 0;


-- ============================================================================
--  DATA INTEGRITY CHECKS
--
--  Paste these into the phpMyAdmin "SQL" tab. Every one of them must return
--  ZERO rows. They are the same rules the application enforces in PHP, so a
--  row coming back means the sample data and the code disagree.
-- ============================================================================
--
-- 1. An eligibility row claims a vote with no ballot behind it
--      SELECT ve.election_id, ve.user_id
--        FROM voter_eligibility ve
--       WHERE ve.has_voted = 1
--         AND NOT EXISTS (SELECT 1 FROM ballots b
--                          WHERE b.election_id = ve.election_id
--                            AND b.voter_id    = ve.user_id);
--
-- 2. A ballot exists but the voter is still marked as not having voted
--      SELECT b.id FROM ballots b
--       WHERE NOT EXISTS (SELECT 1 FROM voter_eligibility ve
--                          WHERE ve.election_id = b.election_id
--                            AND ve.user_id     = b.voter_id
--                            AND ve.has_voted   = 1);
--
-- 3. A ballot has no selections at all
--      SELECT b.id FROM ballots b
--       WHERE NOT EXISTS (SELECT 1 FROM votes v WHERE v.ballot_id = b.id);
--
-- 4. A vote whose position belongs to a different election than its ballot
--      SELECT v.id FROM votes v
--        JOIN ballots   b ON b.id = v.ballot_id
--        JOIN positions p ON p.id = v.position_id
--       WHERE p.election_id <> b.election_id;
--
-- 5. A vote whose candidate does not belong to the position voted for
--      SELECT v.id FROM votes v
--        JOIN candidates c ON c.id = v.candidate_id
--       WHERE c.position_id <> v.position_id;
--
-- 6. A vote whose candidate belongs to another election than its ballot
--      SELECT v.id FROM votes v
--        JOIN candidates c ON c.id = v.candidate_id
--        JOIN ballots    b ON b.id = v.ballot_id
--       WHERE c.election_id <> b.election_id;
--
-- 7. More selections for a position than that position allows
--      SELECT v.ballot_id, v.position_id, COUNT(*) AS selected
--        FROM votes v
--        JOIN positions p ON p.id = v.position_id
--       GROUP BY v.ballot_id, v.position_id, p.max_selections
--      HAVING selected > p.max_selections;
--
-- 8. A ballot submitted outside its election's own window
--      SELECT b.id FROM ballots b JOIN elections e ON e.id = b.election_id
--       WHERE b.submitted_at < e.start_datetime
--          OR b.submitted_at > e.end_datetime;
--
-- 9. A candidate pointing at a position that belongs to another election
--      SELECT c.id FROM candidates c JOIN positions p ON p.id = c.position_id
--       WHERE c.election_id <> p.election_id;
--
-- 10. Duplicate usernames or emails
--      SELECT username, COUNT(*) FROM users GROUP BY username HAVING COUNT(*) > 1;
--      SELECT email,    COUNT(*) FROM users GROUP BY email    HAVING COUNT(*) > 1;
--
-- 11. An inactive or disqualified candidate holding a vote
--      SELECT v.id FROM votes v JOIN candidates c ON c.id = v.candidate_id
--       WHERE c.status <> 'active';


-- ============================================================================
--  CONSTRAINT DEMONSTRATION QUERIES
--
--  Run these one at a time during the defence to show that the database itself
--  refuses bad data, not only the PHP layer. Every INSERT below is EXPECTED TO
--  FAIL with the error in the comment.
-- ============================================================================
--
-- A. A voter can only be registered once per election
--      INSERT INTO voter_eligibility (election_id, user_id) VALUES (1, 2);
--    -> ERROR 1062 Duplicate entry '1-2' for key 'uq_voter_eligibility_election_user'
--
-- B. A voter can only submit one ballot per election
--      INSERT INTO ballots (election_id, voter_id, ballot_reference)
--        VALUES (1, 2, 'SV-2026-900001');
--    -> ERROR 1062 Duplicate entry '1-2' for key 'uq_ballots_election_voter'
--
-- C. Ballot references are unique across the whole system
--      INSERT INTO ballots (election_id, voter_id, ballot_reference)
--        VALUES (1, 10, 'SV-2026-000001');
--    -> ERROR 1062 Duplicate entry 'SV-2026-000001' for key 'uq_ballots_reference'
--
-- D. A vote cannot point at a candidate that does not exist
--      INSERT INTO votes (ballot_id, position_id, candidate_id) VALUES (1, 1, 9999);
--    -> ERROR 1452 Foreign key constraint fails (fk_votes_candidate)
--
-- E. The same candidate cannot be stored twice for one position in one ballot
--      INSERT INTO votes (ballot_id, position_id, candidate_id) VALUES (1, 1, 1);
--    -> ERROR 1062 Duplicate entry '1-1-1' for key 'uq_votes_ballot_position_candidate'
--
-- F. A candidate cannot be attached to a position that does not exist
--      INSERT INTO candidates (election_id, position_id, first_name, last_name)
--        VALUES (1, 999, 'Ghost', 'Candidate');
--    -> ERROR 1452 Foreign key constraint fails (fk_candidates_position)
--
-- Clean up anything you inserted while testing:
--    DELETE FROM votes;
--    DELETE FROM ballots;
--    UPDATE voter_eligibility SET has_voted = 0, voted_at = NULL;
--    DELETE FROM audit_logs;
-- ============================================================================
