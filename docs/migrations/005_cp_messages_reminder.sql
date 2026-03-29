-- Migration 005: Add scheduled_at + assigned_to to cp_messages (reminder system)
-- 2026-03-29

ALTER TABLE `cp_messages`
    ADD COLUMN `scheduled_at` DATETIME NULL DEFAULT NULL AFTER `body`,
    ADD COLUMN `assigned_to`  INT      NULL DEFAULT NULL AFTER `scheduled_at`,
    ADD INDEX  `idx_scheduled` (`scheduled_at`);
