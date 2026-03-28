-- Поля для генерації контенту: що генерувати і скільки символів
-- per site + entity_type (product / category)

CREATE TABLE IF NOT EXISTS `ai_site_fields` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_id`     TINYINT UNSIGNED NOT NULL,
    `entity_type` ENUM('product','category') NOT NULL,
    `field_key`   VARCHAR(32) NOT NULL,
    `label`       VARCHAR(64) NOT NULL DEFAULT '',
    `max_chars`   INT UNSIGNED DEFAULT NULL,
    `is_enabled`  TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_site_entity_field` (`site_id`, `entity_type`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── site_id=1 (off / Офісторг, opencart2) ──────────────────────────────────

-- Товари
INSERT INTO `ai_site_fields` (site_id, entity_type, field_key, label, max_chars, sort_order) VALUES
(1, 'product', 'name',             'Назва товару',     100,  1),
(1, 'product', 'description',      'Опис (HTML)',      3000, 2),
(1, 'product', 'meta_title',       'Meta Title',        70,  3),
(1, 'product', 'meta_description', 'Meta Description', 160,  4);

-- Категорії
INSERT INTO `ai_site_fields` (site_id, entity_type, field_key, label, max_chars, sort_order) VALUES
(1, 'category', 'name',             'Назва категорії',  100,  1),
(1, 'category', 'description',      'Опис (HTML)',      2000, 2),
(1, 'category', 'meta_title',       'Meta Title',        70,  3),
(1, 'category', 'meta_description', 'Meta Description', 160,  4);

-- ─── site_id=2 (mff / Menu Folder, opencart2) ────────────────────────────────

-- Товари
INSERT INTO `ai_site_fields` (site_id, entity_type, field_key, label, max_chars, sort_order) VALUES
(2, 'product', 'name',             'Назва товару',     100,  1),
(2, 'product', 'description',      'Опис (HTML)',      3000, 2),
(2, 'product', 'meta_title',       'Meta Title',        70,  3),
(2, 'product', 'meta_description', 'Meta Description', 160,  4);

-- Категорії
INSERT INTO `ai_site_fields` (site_id, entity_type, field_key, label, max_chars, sort_order) VALUES
(2, 'category', 'name',             'Назва категорії',  100,  1),
(2, 'category', 'description',      'Опис (HTML)',      2000, 2),
(2, 'category', 'meta_title',       'Meta Title',        70,  3),
(2, 'category', 'meta_description', 'Meta Description', 160,  4);
