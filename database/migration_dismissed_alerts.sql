-- ============================================================================
-- Migration: Add `dismissed_alerts` Table for Option B (Database-Persisted Dismiss)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `dismissed_alerts` (
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
  CONSTRAINT `fk_dismissed_alerts_user`
    FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks dismissed or resolved security alerts by administrators.';
