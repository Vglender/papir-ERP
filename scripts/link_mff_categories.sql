-- Link Papir categories to mff (menufold_mff) categories
-- Papir.categoria.category_mf = mff.oc_category.category_id

-- ── Top-level ────────────────────────────────────────────────────────────────
UPDATE categoria SET category_mf = 141 WHERE category_id = 189;  -- АРХІВНІ КОРОБА, ПАПКИ       → mff: Короба архівні
UPDATE categoria SET category_mf = 162 WHERE category_id = 624;  -- ПАПІР, КАРТОН, МАТ. ДЛЯ БІЗ → mff: Папір
UPDATE categoria SET category_mf = 105 WHERE category_id = 626;  -- ПАПКИ МЕНЮ, ЛІЧНИЦІ         → mff: Папка МЕНЮ
UPDATE categoria SET category_mf = 171 WHERE category_id = 689;  -- ПАКУВАЛЬНІ МАТЕРІАЛИ        → mff: Пакувальні матеріали
UPDATE categoria SET category_mf = 176 WHERE category_id = 792;  -- ЗНАКИ, ТАБЛИЧКИ, СТЕНДИ     → mff: Таблички інформаційні

-- ── Папір і картон ───────────────────────────────────────────────────────────
UPDATE categoria SET category_mf = 163 WHERE category_id = 89;   -- Папір офсетний              → mff: Папір офсетний
UPDATE categoria SET category_mf = 164 WHERE category_id = 88;   -- Папір газетний              → mff: Папір газетний
UPDATE categoria SET category_mf = 170 WHERE category_id = 631;  -- Самокопіювальний папір      → mff: Папір самокопіювальний
UPDATE categoria SET category_mf = 165 WHERE category_id = 630;  -- Ватман                      → mff: Ватман
UPDATE categoria SET category_mf = 119 WHERE category_id = 75;   -- Картон макулатурний         → mff: Картон макулатурний
UPDATE categoria SET category_mf = 120 WHERE category_id = 103;  -- Картон палітурний           → mff: Картон для палітурки
UPDATE categoria SET category_mf = 151 WHERE category_id = 135;  -- Картон пивний               → mff: Пивний картон
UPDATE categoria SET category_mf = 144 WHERE category_id = 106;  -- Папір крафтовий             → mff: Крафт папір
UPDATE categoria SET category_mf = 121 WHERE category_id = 625;  -- Палітурні матеріали         → mff: Матеріали для поліграфічних робіт
UPDATE categoria SET category_mf = 145 WHERE category_id = 655;  -- Фурнітура для поліграфії   → mff: Фурнітура для виготовлення папок

-- ── Картон палітурний по товщині ─────────────────────────────────────────────
UPDATE categoria SET category_mf = 132 WHERE category_id = 659;  -- 0.80-1.00 мм → mff 132
UPDATE categoria SET category_mf = 128 WHERE category_id = 660;  -- 1.20 мм      → mff 128
UPDATE categoria SET category_mf = 127 WHERE category_id = 661;  -- 1.50 мм      → mff 127
UPDATE categoria SET category_mf = 140 WHERE category_id = 662;  -- 1.75 мм      → mff 140
UPDATE categoria SET category_mf = 130 WHERE category_id = 663;  -- 2.00 мм      → mff 130
UPDATE categoria SET category_mf = 131 WHERE category_id = 664;  -- 2.50-3.00 мм → mff 131 (3мм, ближчий)

-- ── Папки МЕНЮ та лічниці ────────────────────────────────────────────────────
UPDATE categoria SET category_mf = 105 WHERE category_id = 98;   -- Папки МЕНЮ (sub)            → mff: Папка МЕНЮ
UPDATE categoria SET category_mf = 106 WHERE category_id = 656;  -- Папки РАХУНОК               → mff: Папки рахунок (лічниці)
UPDATE categoria SET category_mf = 107 WHERE category_id = 802;  -- Настільний аксесуар для кафе → mff: Аксесуари для кафе

-- ── Пакувальні матеріали ─────────────────────────────────────────────────────
UPDATE categoria SET category_mf = 174 WHERE category_id = 690;  -- Стрічки клейкі пакувальні   → mff 174
UPDATE categoria SET category_mf = 173 WHERE category_id = 691;  -- Стрічки клейкі спеціальні   → mff 173
UPDATE categoria SET category_mf = 172 WHERE category_id = 692;  -- Диспенсери                  → mff 172
UPDATE categoria SET category_mf = 175 WHERE category_id = 693;  -- Стретч плівка               → mff 175

-- ── Таблички ─────────────────────────────────────────────────────────────────
UPDATE categoria SET category_mf = 176 WHERE category_id = 799;  -- Таблички інформаційні (sub) → mff 176

-- ── Килимки ──────────────────────────────────────────────────────────────────
UPDATE categoria SET category_mf = 177 WHERE category_id = 1104; -- Килимки                    → mff: Килимки для різання

-- ── Перевірка ────────────────────────────────────────────────────────────────
SELECT c.category_id, cd.name as papir_name, c.category_mf,
       mcd.name as mff_name
FROM categoria c
JOIN category_description cd ON cd.category_id = c.category_id AND cd.language_id = 2
LEFT JOIN menufold_mff.oc_category_description mcd ON mcd.category_id = c.category_mf AND mcd.language_id = 2
WHERE c.category_mf IS NOT NULL
ORDER BY c.parent_id, c.sort_order;
