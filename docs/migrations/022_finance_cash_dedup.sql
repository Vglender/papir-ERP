-- 022_finance_cash_dedup.sql
-- Захист від подвійних ПКО, які створює сценарний движок (race condition).
-- 1) Прибираємо існуючий дубль 40447 (ТТН 20451411692389) і його document_link.
-- 2) Додаємо generated-column UNIQUE для папір-створених cashin'ів.
--    Не чіпаємо source='moysklad' — там є legacy-дублі (00001..00004,
--    03612, 03656, 20450697075415) з різними id_ms, які не можна об'єднати.
-- 2026-04-11

-- ── 1. Прибрати дубль 40447 (зберегти 40446, який запушений у МС) ────────────

DELETE FROM `document_link`
WHERE `from_type`='cashin' AND `from_id`=40447;

DELETE FROM `finance_cash`
WHERE `id`=40447;

-- ── 2. Generated-column dedup-ключ для папір-cashin ──────────────────────────
-- Для кожного рядка з source='papir', direction='in' і непорожнім doc_number
-- формуємо стабільний ключ. Для всіх інших рядків ключ = NULL і UNIQUE його
-- ігнорує (NULL-и не конфліктують у UNIQUE).

ALTER TABLE `finance_cash`
  ADD COLUMN `papir_cashin_dedup` VARCHAR(96)
    GENERATED ALWAYS AS (
      CASE
        WHEN `source`='papir' AND `direction`='in'
             AND `doc_number` IS NOT NULL AND `doc_number` <> ''
        THEN CONCAT('p:in:', `doc_number`)
        ELSE NULL
      END
    ) VIRTUAL,
  ADD UNIQUE KEY `uk_finance_cash_papir_in` (`papir_cashin_dedup`);