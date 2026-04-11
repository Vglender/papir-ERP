-- 023_finance_cash_local_fk.sql
-- Перехід finance_cash на локальні int FK як джерело правди.
--
-- Принцип: Papir — джерело правди. counterparty_id / organization_id (локальні int)
-- — головні поля. agent_ms / organization_ms / id_ms — суто маппінг для синку
-- зі застарілою МС: коли МС зникне, нічого не зламається.
--
-- finance_bank уже працює за цією моделлю (cp_id). Підтягуємо finance_cash до неї.
-- 2026-04-11

-- ── 1. Колонки ───────────────────────────────────────────────────────────────

ALTER TABLE `finance_cash`
  ADD COLUMN `counterparty_id` INT NULL AFTER `agent_ms_type`,
  ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `organization_ms`,
  ADD KEY `idx_fc_counterparty_id` (`counterparty_id`),
  ADD KEY `idx_fc_organization_id` (`organization_id`);

-- ── 2. Backfill counterparty_id з agent_ms ───────────────────────────────────
-- Резолвимо UUID контрагента МС → локальний counterparty.id для всіх рядків,
-- де ми вже маємо локального cp за цим id_ms. 39354 рядків мають резолвабельний
-- agent_ms, ~1097 — legacy без локального cp (counterparty_id залишиться NULL,
-- це коректно: контрагента більше нема в локальній БД).

UPDATE `finance_cash` fc
JOIN `counterparty` cp ON cp.id_ms = fc.agent_ms
SET fc.counterparty_id = cp.id
WHERE fc.agent_ms IS NOT NULL AND fc.counterparty_id IS NULL;

-- ── 3. Backfill organization_id з organization_ms ────────────────────────────

UPDATE `finance_cash` fc
JOIN `organization` org ON org.id_ms = fc.organization_ms
SET fc.organization_id = org.id
WHERE fc.organization_ms IS NOT NULL AND fc.organization_id IS NULL;