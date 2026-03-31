-- Migration 007: print forms module
-- Extends organization table, adds print-specific tables

-- ── Extend organization ──────────────────────────────────────────────────────
ALTER TABLE `organization`
    ADD COLUMN `alias`           VARCHAR(32)  NULL DEFAULT NULL COMMENT 'Prefix for doc numbering: OFF, MFF, FOP'  AFTER `short_name`,
    ADD COLUMN `director_name`   VARCHAR(128) NULL DEFAULT NULL AFTER `email`,
    ADD COLUMN `director_title`  VARCHAR(128) NULL DEFAULT NULL COMMENT 'Директор / ФОП / ...' AFTER `director_name`,
    ADD COLUMN `logo_path`       VARCHAR(512) NULL DEFAULT NULL AFTER `director_title`,
    ADD COLUMN `stamp_path`      VARCHAR(512) NULL DEFAULT NULL AFTER `logo_path`,
    ADD COLUMN `signature_path`  VARCHAR(512) NULL DEFAULT NULL AFTER `stamp_path`;

-- ── Extend organization_bank_account ────────────────────────────────────────
ALTER TABLE `organization_bank_account`
    ADD COLUMN `bank_name`  VARCHAR(255) NULL DEFAULT NULL AFTER `account_name`,
    ADD COLUMN `mfo`        VARCHAR(16)  NULL DEFAULT NULL AFTER `bank_name`;

-- ── Print template types (справочник) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `print_template_types` (
    `id`     INT          NOT NULL AUTO_INCREMENT,
    `code`   VARCHAR(32)  NOT NULL COMMENT 'invoice, act, waybill, contract',
    `name`   VARCHAR(128) NOT NULL,
    `status` TINYINT      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `print_template_types` (`code`, `name`) VALUES
    ('invoice',  'Рахунок-фактура'),
    ('act',      'Акт виконаних робіт'),
    ('waybill',  'Видаткова накладна'),
    ('contract', 'Договір');

-- ── Print templates ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `print_templates` (
    `id`               INT           NOT NULL AUTO_INCREMENT,
    `type_id`          INT           NOT NULL,
    `parent_id`        INT           NULL DEFAULT NULL COMMENT 'Previous version',
    `code`             VARCHAR(64)   NOT NULL COMMENT 'invoice_v1, invoice_v2',
    `name`             VARCHAR(128)  NOT NULL,
    `html_body`        MEDIUMTEXT    NOT NULL,
    `variables_schema` JSON          NULL     COMMENT 'Variables documentation for editor',
    `page_settings`    JSON          NULL     COMMENT '{format, orientation, margins}',
    `status`           ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    `version`          INT           NOT NULL DEFAULT 1,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`),
    KEY `idx_type_status` (`type_id`, `status`),
    CONSTRAINT `fk_pt_type`   FOREIGN KEY (`type_id`)   REFERENCES `print_template_types` (`id`),
    CONSTRAINT `fk_pt_parent` FOREIGN KEY (`parent_id`) REFERENCES `print_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Print document counters (per org × doc_type × year) ──────────────────────
CREATE TABLE IF NOT EXISTS `print_doc_counters` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `org_id`      INT          NOT NULL,
    `doc_type`    VARCHAR(32)  NOT NULL,
    `year`        SMALLINT     NOT NULL,
    `last_number` INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_org_type_year` (`org_id`, `doc_type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Printed documents (immutable archive) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `print_documents` (
    `id`               INT          NOT NULL AUTO_INCREMENT,
    `template_id`      INT          NOT NULL,
    `org_id`           INT          NOT NULL,
    `entity_type`      VARCHAR(64)  NULL DEFAULT NULL COMMENT 'customerorder, manual, ...',
    `entity_id`        INT          NULL DEFAULT NULL,
    `serial_number`    VARCHAR(64)  NOT NULL COMMENT 'OFF-РАХ-2026-0042',
    `context_snapshot` JSON         NOT NULL COMMENT 'Data at generation time',
    `file_path`        VARCHAR(512) NULL DEFAULT NULL COMMENT 'Relative path to PDF',
    `created_by`       INT          NULL DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_org_date` (`org_id`, `created_at`),
    CONSTRAINT `fk_pd_template` FOREIGN KEY (`template_id`) REFERENCES `print_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;