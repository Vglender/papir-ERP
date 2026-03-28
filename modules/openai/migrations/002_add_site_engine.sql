-- Додаємо тип движка до sites
-- OpenCart 2 — стандартний для off та mff
ALTER TABLE sites
    ADD COLUMN engine VARCHAR(32) NOT NULL DEFAULT 'opencart2' AFTER url;
