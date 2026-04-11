-- 013_organization_defaults.sql
-- Поля organization для дефолтних значень нових замовлень
-- та маркер платника ПДВ.

ALTER TABLE `organization`
  ADD COLUMN `is_vat_payer` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Платник ПДВ — впливає на vat_rate позицій нових замовлень'
    AFTER `vat_number`,
  ADD COLUMN `default_store_id` INT NULL
    COMMENT 'Дефолтний склад нових замовлень для цієї організації'
    AFTER `is_default`,
  ADD COLUMN `default_delivery_method_id` INT NULL
    COMMENT 'Дефолтний спосіб доставки',
  ADD COLUMN `default_payment_method_id_legal` INT NULL
    COMMENT 'Дефолтний спосіб оплати для контрагентів-юросіб (company/fop)',
  ADD COLUMN `default_payment_method_id_person` INT NULL
    COMMENT 'Дефолтний спосіб оплати для контрагентів-фізосіб (person)';

-- Платники ПДВ
UPDATE `organization`
   SET `is_vat_payer` = 1
 WHERE `name` LIKE '%ПАПІР ІНВЕСТ%'
    OR `name` LIKE '%Папір Інвест%'
    OR `name` LIKE '%Архкор%'
    OR `name` LIKE '%АРХКОР%';

-- Розумні дефолти для всіх організацій:
--   склад=1 (Основний склад), доставка=3 (Нова Пошта),
--   оплата юр=1 (bank_company), оплата фіз=2 (bank_personal)
UPDATE `organization`
   SET `default_store_id`                 = 1,
       `default_delivery_method_id`       = 3,
       `default_payment_method_id_legal`  = 1,
       `default_payment_method_id_person` = 2
 WHERE `default_store_id` IS NULL;