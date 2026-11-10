-- ============================================================================
--  Magnate ERP — database changes (2026-11-10)
--  Apply once to an existing database that matches sql/latest.sql:
--      mysql -u <user> -p magnate < sql/dbchanges.sql      (or: SOURCE sql/dbchanges.sql;)
--  Back up first:  mysqldump -u <user> -p magnate > magnate-before-dbchanges.sql
--
--  1. Variety photos
--  2. Bank code / branch code on bank accounts (printed on invoices)
--  3. Packing list: airline, arrival date/note, net weight
--  4. Invoices: net weight (kg) captured before invoicing
--  5. Invoice lines: colour, category, supplying farm (Commercial Invoice layout)
--  6. Settings: net weight per box, second email, company details from the company's own documents
--  7. Bank account from the company's commercial invoice
--  "Drop points" are shown as ENTRY everywhere in the app; the table keeps its name (drop_points).
-- ============================================================================
SET NAMES utf8mb4;

-- 1. Variety photos (files live in uploads/varieties/)
ALTER TABLE varieties
  ADD COLUMN image VARCHAR(190) NULL AFTER colour;

-- 2. Bank details as printed on the commercial invoice
ALTER TABLE bank_accounts
  ADD COLUMN bank_code   VARCHAR(20) NULL AFTER swift_code,
  ADD COLUMN branch_code VARCHAR(20) NULL AFTER bank_code;

-- 3. Packing list: "The shipment will arrive on Monday evening (19th October) on MS AWB 077 5756 5255"
ALTER TABLE packing_lists
  ADD COLUMN airline       VARCHAR(60)   NULL AFTER awb_no,
  ADD COLUMN net_weight_kg DECIMAL(10,2) NULL AFTER volume_weight_kg,
  ADD COLUMN arrival_date  DATE          NULL AFTER net_weight_kg,
  ADD COLUMN arrival_note  VARCHAR(150)  NULL AFTER arrival_date;

-- 4. Net weight (kg) on the invoice ("Kilogrammes" / "NET WEIGHT")
ALTER TABLE invoices
  ADD COLUMN net_weight_kg DECIMAL(10,2) NULL AFTER boxes;

-- 5. Invoice lines carry colour, category and the supplying farm ("Company")
ALTER TABLE invoice_lines
  ADD COLUMN colour         VARCHAR(60)  NULL AFTER order_by_label,
  ADD COLUMN category_label VARCHAR(150) NULL AFTER colour,
  ADD COLUMN farm_id        INT UNSIGNED NULL AFTER category_label,
  ADD COLUMN farm_label     VARCHAR(150) NULL AFTER farm_id;

-- backfill colour/category on invoice lines that already exist
UPDATE invoice_lines il
  JOIN varieties v ON v.id = il.variety_id
  LEFT JOIN product_categories pc ON pc.id = v.category_id
   SET il.colour = COALESCE(il.colour, v.colour),
       il.category_label = COALESCE(il.category_label, pc.name);

-- 6. Settings
INSERT INTO settings (`key`, `value`) VALUES
  ('kg_per_box',      '15'),
  ('company_email2',  'sales@magnateflowers.com'),
  ('company_address', 'P.O. Box 190-00515, Nairobi, Kenya'),
  ('company_email',   'magnateflowers@gmail.com'),
  ('company_phone',   '+254 722 777 051'),
  ('company_website', 'www.magnateflowers.com')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- the installer's sample KRA PIN is not a real one: blank it so it is not printed
UPDATE settings SET `value` = '' WHERE `key` = 'company_pin' AND `value` = 'P051234567V';

-- 7. Bank account printed on invoices (from the company's commercial invoice)
INSERT INTO bank_accounts (bank_name, branch, account_name, account_number, swift_code, bank_code, branch_code, show_on_invoice, is_active, sort_order)
SELECT 'Equity Bank Ltd', 'JKIA', 'Magnate Flowers Ltd', '0550296514728', 'EQBLKENA', '068', '148', 1, 1, 0
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM bank_accounts WHERE account_number = '0550296514728');

-- hide the installer's sample accounts (placeholder numbers) from invoices
UPDATE bank_accounts SET show_on_invoice = 0, is_active = 0
 WHERE account_number IN ('1234567890', '2345678901', '3456789012');
