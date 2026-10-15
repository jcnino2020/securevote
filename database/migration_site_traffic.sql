-- Site-wide, first-party page views and link clicks.
-- Import once for existing installations. No query strings or ballot values are stored.
CREATE TABLE IF NOT EXISTS `site_traffic_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_key` CHAR(32) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `event_type` ENUM('page_view','link_click') NOT NULL,
  `page_path` VARCHAR(255) NOT NULL,
  `target_path` VARCHAR(255) NULL,
  `referrer_path` VARCHAR(255) NULL,
  `ip_address` VARCHAR(45) NULL,
  `device_type` VARCHAR(16) NOT NULL,
  `browser` VARCHAR(32) NOT NULL,
  `operating_system` VARCHAR(32) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_traffic_created` (`created_at`),
  KEY `idx_traffic_visitor` (`visitor_key`, `created_at`),
  KEY `idx_traffic_user` (`user_id`, `created_at`),
  KEY `idx_traffic_page` (`page_path`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
