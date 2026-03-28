-- Додаємо site_id в ai_instructions
-- site_id=0 означає "застосовується до всіх сайтів" (fallback)
-- Для entity_type='site' — site_id = entity_id (інструкція конкретного сайту)

ALTER TABLE ai_instructions
    ADD COLUMN site_id TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER entity_id;

-- Переносимо існуючі site-level записи: site_id = entity_id
UPDATE ai_instructions SET site_id = entity_id WHERE entity_type = 'site';

-- Оновлюємо унікальний ключ
ALTER TABLE ai_instructions
    DROP KEY uq_entity_usecase,
    ADD UNIQUE KEY uq_entity_site_usecase (entity_type, entity_id, site_id, use_case);
