-- SecurityGuard module - install.sql
-- PrestaShop 1.7.8.11 compatible

CREATE TABLE IF NOT EXISTS `PREFIX_securityguard_logs` (
  `id_log`       INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45)      NOT NULL DEFAULT '',
  `country`      CHAR(2)          NOT NULL DEFAULT '',
  `attack_type`  VARCHAR(64)      NOT NULL DEFAULT '',
  `form_type`    VARCHAR(64)      NOT NULL DEFAULT '',
  `threat_score` TINYINT(3)       NOT NULL DEFAULT 0,
  `date_add`     DATETIME         NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `idx_ip`       (`ip`),
  KEY `idx_date_add` (`date_add`),
  KEY `idx_attack`   (`attack_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_securityguard_blocklist` (
  `id_block`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`               VARCHAR(45)      NOT NULL DEFAULT '',
  `reason`           VARCHAR(255)     NOT NULL DEFAULT '',
  `reputation_score` SMALLINT(5)      NOT NULL DEFAULT 0,
  `attack_count`     INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_blocked`       TINYINT(1)       NOT NULL DEFAULT 1,
  `expires_at`       DATETIME         NULL,
  `date_add`         DATETIME         NOT NULL,
  PRIMARY KEY (`id_block`),
  UNIQUE KEY `uniq_ip`     (`ip`),
  KEY `idx_is_blocked`     (`is_blocked`),
  KEY `idx_expires_at`     (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_securityguard_patterns` (
  `id_pattern` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pattern`    VARCHAR(512)     NOT NULL DEFAULT '',
  `active`     TINYINT(1)       NOT NULL DEFAULT 1,
  `date_add`   DATETIME         NOT NULL,
  PRIMARY KEY (`id_pattern`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
