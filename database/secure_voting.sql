-- ============================================================================
--  SecureVote - Secure Online Voting System
--  Database schema & initial configuration
--
--  Target   : MySQL 5.7+ / MariaDB 10.2+ (developed and tested on MariaDB 12.3)
--  Engine   : InnoDB (transactions + foreign keys)
--  Charset  : utf8mb4 / utf8mb4_unicode_ci
--
--  HOW TO IMPORT (phpMyAdmin)
--    1. Open phpMyAdmin and select your database: u184230832_securevote
--    2. Click the "Import" tab at the top
--    3. Choose file: database/secure_voting.sql
--    4. Leave all defaults, then click "Import"
--    5. All eight tables, the default admin account, and active elections,
--       positions, and candidates are created in one step.
--
--  VOTER REGISTRATION:
--    Instead of hardcoded fictional voter accounts, any visitor can register
--    a real voter account directly on the website (auth/register.php) or be
--    added by the administrator in the admin control panel (admin/voters.php).
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


-- ============================================================================
--  INITIAL DATA SEEDING
--
--  Default Administrator account:
--    Username : admin
--    Password : Admin@12345
--
--  Voter Accounts:
--    Anyone can register directly through the registration portal (auth/register.php).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. System Administrator
-- ----------------------------------------------------------------------------
INSERT INTO `users`
  (`id`,`username`,`email`,`password_hash`,`first_name`,`last_name`,`role`,`status`,`created_at`,`last_login`) VALUES
(1, 'admin', 'admin@securevote.local', '$2y$12$fsgfxiVIwqY4tADR3zyW9e4/rQWE.sS86MtXJc/VSPEcwJp34IN2i', 'System', 'Administrator', 'admin', 'active', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 1 HOUR);


-- ----------------------------------------------------------------------------
-- 2. Elections
--   #1 Student Council Election 2026      -> OPEN (active, ready for voting)
--   #2 College Department Election 2026   -> SCHEDULED (upcoming)
--   #3 Student Organization Election 2026 -> CLOSED (historical/completed)
-- ----------------------------------------------------------------------------
INSERT INTO `elections`
  (`id`,`title`,`description`,`start_datetime`,`end_datetime`,`status`,`results_visibility`,`created_by`,`created_at`) VALUES
(1, 'Student Council Election 2026',
    'Annual election of the Supreme Student Council officers for academic year 2026-2027. Each registered voter may submit exactly one ballot. Positions that allow more than one selection are marked on the ballot.',
    NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 7 DAY, 'open', 'after_vote', 1, NOW() - INTERVAL 7 DAY),
(2, 'College Department Election 2026',
    'Election of the College Department officers. Voter registration is already open, but no ballot can be submitted before the published start date.',
    NOW() + INTERVAL 3 DAY, NOW() + INTERVAL 10 DAY, 'scheduled', 'hidden', 1, NOW() - INTERVAL 7 DAY),
(3, 'Student Organization Election 2026',
    'Election of the officers of the recognised student organizations. This election has finished and voting is closed.',
    NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 10 DAY, 'closed', 'after_close', 1, NOW() - INTERVAL 35 DAY);


-- ----------------------------------------------------------------------------
-- 3. Positions
--   Election 1: 7 positions (President, VP, Secretary, Treasurer, Auditor, PIO, Representative)
--   Election 2: 2 positions (President, VP)
--   Election 3: 2 positions (President, VP)
-- ----------------------------------------------------------------------------
INSERT INTO `positions`
  (`id`,`election_id`,`name`,`description`,`max_selections`,`display_order`,`created_at`) VALUES
(1,  1, 'President',                  'Highest executive officer of the Supreme Student Council.', 1, 1, NOW() - INTERVAL 7 DAY),
(2,  1, 'Vice President',             'Assists the President and leads the internal committees.',  1, 2, NOW() - INTERVAL 7 DAY),
(3,  1, 'Secretary',                  'Keeps the minutes and the official records.',               1, 3, NOW() - INTERVAL 7 DAY),
(4,  1, 'Treasurer',                  'Manages the council funds and the financial reports.',       1, 4, NOW() - INTERVAL 7 DAY),
(5,  1, 'Auditor',                    'Audits the council finances and assets.',                    1, 5, NOW() - INTERVAL 7 DAY),
(6,  1, 'Public Information Officer', 'Publishes announcements and handles public relations.',      1, 6, NOW() - INTERVAL 7 DAY),
(7,  1, 'Representative',             'Represents the student body. Choose up to 2.',               2, 7, NOW() - INTERVAL 7 DAY),
(8,  2, 'President',                  'President of the College Department officers.',              1, 1, NOW() - INTERVAL 7 DAY),
(9,  2, 'Vice President',             'Vice President of the College Department officers.',         1, 2, NOW() - INTERVAL 7 DAY),
(10, 3, 'President',                  'President of the recognised student organizations.',         1, 1, NOW() - INTERVAL 35 DAY),
(11, 3, 'Vice President',             'Vice President of the recognised student organizations.',    1, 2, NOW() - INTERVAL 35 DAY);


-- ----------------------------------------------------------------------------
-- 4. Candidates - Standing candidates for the active elections
-- ----------------------------------------------------------------------------
INSERT INTO `candidates`
  (`id`,`election_id`,`position_id`,`first_name`,`last_name`,`party`,`biography`,`platform`,`status`,`created_at`) VALUES
-- Election 1 - Position 1: President
(1,  1, 1, 'Alex',     'Santos',     'Unity Alliance',
 'Third year BSIT student and current council officer. Experienced in student governance.',
 'Transparent budget reporting, faster student document processing, and regular student forums.', 'active', NOW() - INTERVAL 7 DAY),
(2,  1, 1, 'Miguel',   'Reyes',      'Student First',
 'Fourth year BS Accountancy student and former class president.',
 'Free printing quota for graduating students, more scholarship slots, and student welfare desks.', 'active', NOW() - INTERVAL 7 DAY),
(3,  1, 1, 'Daniel',   'Cruz',       'Forward Movement',
 'Fourth year BS Education student and campus radio host.',
 'Student representation in policy committees, upgraded campus Wi-Fi, and peer mentorship.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 1 - Position 2: Vice President
(4,  1, 2, 'Sofia',    'Garcia',     'Unity Alliance',
 'Second year BS Psychology student and peer counsellor.',
 'Campus wellness program, mental health awareness drives, and department liaisons.', 'active', NOW() - INTERVAL 7 DAY),
(5,  1, 2, 'Andrea',   'Ramos',      'Student First',
 'Third year BS Civil Engineering student and engineering society officer.',
 'Coordinated campus events calendar and streamlined inter-club funding.', 'active', NOW() - INTERVAL 7 DAY),
(6,  1, 2, 'Carlo',    'Mendoza',    'Forward Movement',
 'Third year BS Accountancy student and assistant treasurer.',
 'Quarterly financial statements published for student transparency.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 1 - Position 3: Secretary
(7,  1, 3, 'Bianca',   'Flores',     'Unity Alliance',
 'Second year BSIT student and documentation head.',
 'Publish council assembly minutes within 48 hours of every meeting.', 'active', NOW() - INTERVAL 7 DAY),
(8,  1, 3, 'Rafael',   'Torres',     'Student First',
 'Third year BS Accountancy student and records officer.',
 'Searchable online archive of all council resolutions and student guidelines.', 'active', NOW() - INTERVAL 7 DAY),
(9,  1, 3, 'Nicole',   'Navarro',    'Forward Movement',
 'Second year BS Education student and class secretary.',
 'Digital attendance tracking and clear announcement broadcasts.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 1 - Position 4: Treasurer
(10, 1, 4, 'Ethan',    'Castillo',   'Student First',
 'Third year BS Accountancy student with bookkeeping experience.',
 'Online itemised expense tracker and quick request form for student clubs.', 'active', NOW() - INTERVAL 7 DAY),
(11, 1, 4, 'Hannah',   'Rivera',     'Unity Alliance',
 'Third year BS Business Administration student.',
 'Published monthly cash-flow summaries and open procurement logs.', 'active', NOW() - INTERVAL 7 DAY),
(12, 1, 4, 'Joshua',   'Fernandez',  'Forward Movement',
 'Second year BS Accountancy student and finance committee volunteer.',
 'End-of-semester financial audit digests made easy to read for students.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 1 - Position 5: Auditor
(13, 1, 5, 'Maria',    'Lopez',      'Forward Movement',
 'Fourth year BS Accountancy student and internal audit intern.',
 'Independent spot audits of all council funded initiatives.', 'active', NOW() - INTERVAL 7 DAY),
(14, 1, 5, 'Kevin',    'Bautista',   'Student First',
 'Third year BS Accountancy student.',
 'Semester-end published audit reports and asset inventories.', 'active', NOW() - INTERVAL 7 DAY),
(15, 1, 5, 'Angela',   'Villanueva', 'Unity Alliance',
 'Second year BS Accountancy student.',
 'Public receipt repository for all council expenditures.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 1 - Position 6: Public Information Officer
(16, 1, 6, 'Patrick',  'Gonzales',   'Unity Alliance',
 'Third year BS Development Communication student and student journalist.',
 'Weekly newsletter bulletins and real-time social media updates.', 'active', NOW() - INTERVAL 7 DAY),
(17, 1, 6, 'Samantha', 'Aquino',     'Student First',
 'Second year BSIT student and creative media lead.',
 'Centralized student portal for all campus announcements.', 'active', NOW() - INTERVAL 7 DAY),
(18, 1, 6, 'Adrian',   'Mercado',    'Forward Movement',
 'Third year BS Education student.',
 'Campus podcast news updates and physical noticeboard roundups.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 1 - Position 7: Representative (choose up to 2)
(19, 1, 7, 'Chloe',    'Ramirez',    'Unity Alliance',
 'Second year BSIT student and student programmer.',
 'Extended 24-hour study spaces during exam periods and improved computer labs.', 'active', NOW() - INTERVAL 7 DAY),
(20, 1, 7, 'Nathan',   'Delgado',    'Student First',
 'Third year BS Mechanical Engineering student and varsity player.',
 'Better athletic equipment and campus sports tournament support.', 'active', NOW() - INTERVAL 7 DAY),
(21, 1, 7, 'Isabel',   'Domingo',    'Independent',
 'Second year BS Architecture student and art guild member.',
 'More creative exhibition spaces and campus cultural showcases.', 'active', NOW() - INTERVAL 7 DAY),
(22, 1, 7, 'Gabriel',  'Salazar',    'Forward Movement',
 'Third year BS Information Systems student and tech club lead.',
 'Student-led tech help desk and digital literacy workshops.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 2 - Position 8: President (College Department)
(23, 2, 8, 'Bianca',   'Flores',     'Unity Alliance',
 'Fourth year BS Accountancy student and department representative.',
 'Department consultation sessions before major curriculum shifts.', 'active', NOW() - INTERVAL 7 DAY),
(24, 2, 8, 'Rafael',   'Torres',     'Student First',
 'Fourth year BS Education student.',
 'Extended laboratory opening hours and study groups.', 'active', NOW() - INTERVAL 7 DAY),
(25, 2, 8, 'Nicole',   'Navarro',    'Forward Movement',
 'Third year BS Education student.',
 'Peer tutoring exchange and academic assistance desks.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 2 - Position 9: Vice President (College Department)
(26, 2, 9, 'Ethan',    'Castillo',   'Unity Alliance',
 'Third year BS Accountancy student.',
 'Shared activities calendar and clear fee breakdowns.', 'active', NOW() - INTERVAL 7 DAY),
(27, 2, 9, 'Hannah',   'Rivera',     'Student First',
 'Second year BS Business Administration student.',
 'Regular department assemblies and feedback surveys.', 'active', NOW() - INTERVAL 7 DAY),

-- Election 3 - Position 10: President (Student Organization)
(28, 3, 10, 'Joshua',  'Fernandez',  'Student First',
 'Third year BS Accountancy student.',
 'Transparent cooperative finances.', 'active', NOW() - INTERVAL 35 DAY),
(29, 3, 10, 'Maria',   'Lopez',      'Forward Movement',
 'Fourth year BS Accountancy student.',
 'Annual organization financial reports.', 'active', NOW() - INTERVAL 35 DAY),
(30, 3, 10, 'Kevin',   'Bautista',   'Unity Alliance',
 'Third year BS Business Administration student.',
 'Joint organization initiatives.', 'active', NOW() - INTERVAL 35 DAY),

-- Election 3 - Position 11: Vice President (Student Organization)
(31, 3, 11, 'Angela',  'Villanueva', 'Unity Alliance',
 'Second year BS Accountancy student.',
 'Monthly open club meetings.', 'active', NOW() - INTERVAL 35 DAY),
(32, 3, 11, 'Patrick', 'Gonzales',   'Student First',
 'Third year BS Development Communication student.',
 'Shared communications network for student orgs.', 'active', NOW() - INTERVAL 35 DAY),
(33, 3, 11, 'Samantha','Aquino',     'Forward Movement',
 'Second year BSIT student.',
 'Annual organizational timeline alignment.', 'active', NOW() - INTERVAL 35 DAY);


-- ----------------------------------------------------------------------------
-- 5. Audit Trail - Initial system configuration events
-- ----------------------------------------------------------------------------
INSERT INTO `audit_logs` (`user_id`,`action`,`description`,`ip_address`,`user_agent`,`created_at`) VALUES
(1, 'election_created',   'Created election #1 "Student Council Election 2026".',                        '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #1 "President" in election #1.',                             '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #2 "Vice President" in election #1.',                        '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #3 "Secretary" in election #1.',                             '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #4 "Treasurer" in election #1.',                             '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #5 "Auditor" in election #1.',                               '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #6 "Public Information Officer" in election #1.',            '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #7 "Representative" in election #1 (up to 2 selections).',   '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'election_activated', 'Election #1 "Student Council Election 2026" set to open.',                    '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 1 DAY),
(1, 'election_created',   'Created election #2 "College Department Election 2026".',                     '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #8 "President" in election #2.',                             '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'position_created',   'Created position #9 "Vice President" in election #2.',                        '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 7 DAY),
(1, 'election_created',   'Created election #3 "Student Organization Election 2026".',                   '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 35 DAY),
(1, 'position_created',   'Created position #10 "President" in election #3.',                            '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 35 DAY),
(1, 'position_created',   'Created position #11 "Vice President" in election #3.',                       '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 35 DAY),
(1, 'election_closed',    'Election #3 "Student Organization Election 2026" set to closed.',            '127.0.0.1', 'SecureVote System Seed', NOW() - INTERVAL 10 DAY);


-- ============================================================================
--  DATABASE SUMMARY & HEALTH CHECKS
-- ============================================================================
--
--   SELECT COUNT(*) FROM users WHERE role = 'admin';   -- 1
--   SELECT COUNT(*) FROM elections;                    -- 3
--   SELECT COUNT(*) FROM positions;                    -- 11
--   SELECT COUNT(*) FROM candidates;                   -- 33
--
--   -- When voters register via auth/register.php, rows will populate in:
--   --   users (role = 'voter')
--   --   voter_eligibility
--   --   ballots (upon casting vote)
--   --   votes (individual ballot selections)
--   --   audit_logs (registration and voting security logs)
--
-- ============================================================================
