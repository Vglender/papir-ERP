-- ============================================================
-- Міграція 003: таблиці класів ваги та довжини в Papir
-- + маппінги на сайти (off/mff)
-- ============================================================

-- ── Класи ваги ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS weight_class (
    weight_class_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    value           DECIMAL(15,8) NOT NULL DEFAULT 1.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS weight_class_description (
    weight_class_id INT NOT NULL,
    language_id     INT NOT NULL,
    title           VARCHAR(32) NOT NULL,
    unit            VARCHAR(4)  NOT NULL,
    PRIMARY KEY (weight_class_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Папір використовує ті самі ID що off/mff: 1=кг, 2=г
INSERT IGNORE INTO weight_class (weight_class_id, value) VALUES
    (1, 1.00000000),    -- кг (базова одиниця)
    (2, 1000.00000000); -- г  (1кг = 1000г)

INSERT IGNORE INTO weight_class_description (weight_class_id, language_id, title, unit) VALUES
    (1, 1, 'Килограмм',   'кг'),
    (1, 2, 'Кілограм',    'кг'),
    (2, 1, 'Грамм',       'г'),
    (2, 2, 'Грам',        'г');

-- ── Класи довжини ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS length_class (
    length_class_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    value           DECIMAL(15,8) NOT NULL DEFAULT 1.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS length_class_description (
    length_class_id INT NOT NULL,
    language_id     INT NOT NULL,
    title           VARCHAR(32) NOT NULL,
    unit            VARCHAR(4)  NOT NULL,
    PRIMARY KEY (length_class_id, language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ID відповідають off/mff: 1=см, 2=мм
INSERT IGNORE INTO length_class (length_class_id, value) VALUES
    (1, 1.00000000),   -- см (базова одиниця)
    (2, 10.00000000);  -- мм (1см = 10мм)

INSERT IGNORE INTO length_class_description (length_class_id, language_id, title, unit) VALUES
    (1, 1, 'Сантиметр',  'см'),
    (1, 2, 'Сантиметр',  'см'),
    (2, 1, 'Миллиметр',  'мм'),
    (2, 2, 'Міліметр',   'мм');

-- ── Маппінги класів на сайти ─────────────────────────────────

CREATE TABLE IF NOT EXISTS weight_class_site_mapping (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    weight_class_id     INT NOT NULL,
    site_id             INT NOT NULL,
    site_weight_class_id INT NOT NULL,
    UNIQUE KEY (weight_class_id, site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO weight_class_site_mapping (weight_class_id, site_id, site_weight_class_id) VALUES
    (1, 1, 1),  -- Papir кг → off кг (id=1)
    (1, 2, 1),  -- Papir кг → mff кг (id=1)
    (2, 1, 2),  -- Papir г  → off г  (id=2)
    (2, 2, 2);  -- Papir г  → mff г  (id=2)

CREATE TABLE IF NOT EXISTS length_class_site_mapping (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    length_class_id     INT NOT NULL,
    site_id             INT NOT NULL,
    site_length_class_id INT NOT NULL,
    UNIQUE KEY (length_class_id, site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO length_class_site_mapping (length_class_id, site_id, site_length_class_id) VALUES
    (1, 1, 1),  -- Papir см → off см (id=1)
    (1, 2, 1),  -- Papir см → mff см (id=1)
    (2, 1, 2),  -- Papir мм → off мм (id=2)
    (2, 2, 2);  -- Papir мм → mff мм (id=2)

-- ── Виправити weight_class_id / length_class_id в product_papir ─

-- Більшість ваг введено в грамах (значення < 10000 для більшості)
-- Встановлюємо г як дефолт де клас не встановлено
UPDATE product_papir
SET weight_class_id = 2
WHERE weight > 0 AND (weight_class_id = 0 OR weight_class_id IS NULL);

-- Довжини/ширини/висоти введені в міліметрах (значення від 1 до 10000 мм типово)
UPDATE product_papir
SET length_class_id = 2
WHERE (length > 0 OR width > 0 OR height > 0)
  AND (length_class_id = 0 OR length_class_id IS NULL);
