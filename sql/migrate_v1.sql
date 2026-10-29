-- =====================================================================
--  Import a v1 database into v2.
--  1) Import your old dump (sql/latest.sql) into a separate database, e.g. `magnate_legacy`.
--  2) Create the v2 database with schema.sql + seed.sql.
--  3) Run this file against the v2 database. If your legacy DB has another name,
--     replace every `magnate_legacy` below (install.php does this for you).
--
--  v1 linked records by NAME; here every name is resolved to an ID.
--  v1 had no delivery-check step: for orders already packed/invoiced, received = confirmed.
--  v1 had no selling prices: line selling price is taken from the v1 farm price the
--  old invoice used. Review prices on migrated open orders.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- currencies & rates ----------
INSERT IGNORE INTO currencies (code, name, is_active)
SELECT UPPER(TRIM(abbreviation)), name, 1 FROM magnate_legacy.currencies WHERE TRIM(abbreviation) <> '';

DROP TEMPORARY TABLE IF EXISTS _cur;
CREATE TEMPORARY TABLE _cur (legacy_name VARCHAR(255) PRIMARY KEY, currency_id INT UNSIGNED);
INSERT IGNORE INTO _cur SELECT lc.name, c.id FROM magnate_legacy.currencies lc JOIN currencies c ON c.code = UPPER(TRIM(lc.abbreviation));
INSERT IGNORE INTO _cur SELECT c.code, c.id FROM currencies c;
INSERT IGNORE INTO _cur SELECT c.name, c.id FROM currencies c;

INSERT INTO exchange_rates (currency_id, rate, effective_date, notes)
SELECT m.currency_id, er.exchange_rate, er.date, 'Imported from v1'
  FROM magnate_legacy.exchange_rates er JOIN _cur m ON m.legacy_name = er.currency
 ORDER BY er.id
ON DUPLICATE KEY UPDATE rate = VALUES(rate);

-- ---------- lookups ----------
INSERT IGNORE INTO countries (name, is_active) SELECT TRIM(name), LOWER(status) = 'active' FROM magnate_legacy.countries;
INSERT IGNORE INTO drop_points (name, is_active) SELECT TRIM(name), LOWER(status) = 'active' FROM magnate_legacy.drop_points;
INSERT IGNORE INTO product_categories (name, is_active) SELECT TRIM(name), LOWER(status) = 'active' FROM magnate_legacy.product_categories;
INSERT IGNORE INTO product_categories (name, is_active) SELECT DISTINCT TRIM(`group`), 1 FROM magnate_legacy.varieties WHERE TRIM(`group`) <> '';
INSERT IGNORE INTO box_types (name, is_active) SELECT TRIM(name), LOWER(status) = 'active' FROM magnate_legacy.box_types;
INSERT IGNORE INTO lengths (name, sort_order, is_active) SELECT TRIM(name), CAST(TRIM(name) AS UNSIGNED), LOWER(status) = 'active' FROM magnate_legacy.lengths;
INSERT IGNORE INTO order_by (name, is_active) SELECT TRIM(name), LOWER(status) = 'active' FROM magnate_legacy.order_by;
INSERT IGNORE INTO expense_types (name, is_active) SELECT TRIM(name), LOWER(status) = 'active' FROM magnate_legacy.expense_types;
-- lengths / marks used in data but missing from the lists
INSERT IGNORE INTO lengths (name, sort_order) SELECT DISTINCT TRIM(length), CAST(TRIM(length) AS UNSIGNED) FROM magnate_legacy.order_details;
INSERT IGNORE INTO lengths (name, sort_order) SELECT DISTINCT TRIM(length), CAST(TRIM(length) AS UNSIGNED) FROM magnate_legacy.farm_prices;
INSERT IGNORE INTO order_by (name) SELECT DISTINCT TRIM(ordered_by) FROM magnate_legacy.order_details WHERE TRIM(ordered_by) <> '';
INSERT IGNORE INTO expense_types (name) SELECT DISTINCT TRIM(name) FROM magnate_legacy.expenses;

-- ---------- master data ----------
INSERT IGNORE INTO varieties (name, category_id, is_active)
SELECT TRIM(v.variety), pc.id, LOWER(v.status) = 'active'
  FROM magnate_legacy.varieties v LEFT JOIN product_categories pc ON pc.name = TRIM(v.`group`);
INSERT IGNORE INTO varieties (name) SELECT DISTINCT TRIM(variety) FROM magnate_legacy.order_details;
INSERT IGNORE INTO varieties (name) SELECT DISTINCT TRIM(variety) FROM magnate_legacy.farm_prices;

INSERT IGNORE INTO farms (code, name, address, contact_person, email, phone, currency_id)
SELECT TRIM(f.code), TRIM(f.name), f.address, f.contact_person, f.email, f.tel, COALESCE(m.currency_id, (SELECT id FROM currencies WHERE code = 'USD'))
  FROM magnate_legacy.farms f LEFT JOIN _cur m ON m.legacy_name = f.currency;

INSERT IGNORE INTO customers (code, name, truck, drop_point_id, currency_id, country_id, terms, is_active)
SELECT TRIM(c.code), TRIM(c.name), NULLIF(c.truck, ''), dp.id, COALESCE(m.currency_id, (SELECT id FROM currencies WHERE code = 'USD')), co.id, c.terms, LOWER(COALESCE(c.status, 'active')) = 'active'
  FROM magnate_legacy.customers c
  LEFT JOIN _cur m ON m.legacy_name = c.currency
  LEFT JOIN drop_points dp ON dp.name = TRIM(c.drop_point)
  LEFT JOIN countries co ON co.name = TRIM(c.country);

INSERT IGNORE INTO shipping_agents (name, contact_person, email, phone, is_active)
SELECT TRIM(name), contact_person, email, tel, LOWER(status) = 'active' FROM magnate_legacy.shipping_agent;

INSERT IGNORE INTO farm_prices (farm_id, variety_id, length_id, buying_price, currency_id, is_active)
SELECT f.id, v.id, l.id, fp.price, COALESCE(m.currency_id, f.currency_id), LOWER(fp.status) = 'active'
  FROM magnate_legacy.farm_prices fp
  JOIN farms f ON f.name = TRIM(fp.farm) JOIN varieties v ON v.name = TRIM(fp.variety) JOIN lengths l ON l.name = TRIM(fp.length)
  LEFT JOIN _cur m ON m.legacy_name = fp.currency
 ORDER BY fp.id DESC;

-- ---------- users (password hashes carry over) ----------
INSERT IGNORE INTO users (id, full_name, username, email, phone, password_hash, role_id, status, created_at)
SELECT u.id, u.full_name, u.username, u.email, u.phone, u.password, IF(LOWER(u.role) = 'admin', 1, 4), IF(LOWER(u.status) = 'active', 'active', 'inactive'), u.created_on
  FROM magnate_legacy.users u;

-- ---------- orders ----------
INSERT INTO orders (id, order_no, customer_id, order_date, departure_date, box_type_id, shipping_agent_id, drop_point_id, currency_id, exchange_rate, stage, remarks, created_by, created_at)
SELECT o.id, COALESCE(o.orderid, CONCAT('OD', LPAD(o.id, 5, '0'))), c.id, o.order_date, o.delivery_date, bt.id, sa.id,
       (SELECT dp.id FROM magnate_legacy.packing_list pl JOIN drop_points dp ON dp.name = TRIM(pl.drop_point) WHERE pl.order_id = o.id LIMIT 1),
       c.currency_id,
       COALESCE((SELECT er.rate FROM exchange_rates er WHERE er.currency_id = c.currency_id AND er.effective_date <= o.order_date ORDER BY er.effective_date DESC LIMIT 1),
                (SELECT er.rate FROM exchange_rates er WHERE er.currency_id = c.currency_id ORDER BY er.effective_date LIMIT 1), 1),
       CASE
         WHEN EXISTS (SELECT 1 FROM magnate_legacy.packing_list pl WHERE pl.order_id = o.id AND pl.status = 'Invoiced') THEN 'invoiced'
         WHEN EXISTS (SELECT 1 FROM magnate_legacy.packing_list pl WHERE pl.order_id = o.id) THEN 'packing'
         WHEN NOT EXISTS (SELECT 1 FROM magnate_legacy.order_details od WHERE od.order_id = o.id) THEN 'draft'
         WHEN NOT EXISTS (SELECT 1 FROM magnate_legacy.order_details od JOIN magnate_legacy.sourcing_farm_details s ON s.order_detail_id = od.id
                           WHERE od.order_id = o.id AND COALESCE(s.confirmed, 0) = 0) THEN 'confirmed'
         ELSE 'sourcing'
       END,
       o.remarks, o.createdby, o.createdon
  FROM magnate_legacy.orders o
  JOIN customers c ON c.name = TRIM(o.customer)
  LEFT JOIN box_types bt ON bt.name = TRIM(o.box_type)
  LEFT JOIN shipping_agents sa ON sa.name = TRIM(o.shipping_agent);

UPDATE orders SET sourcing_at = created_at WHERE stage <> 'draft';
UPDATE orders SET confirmed_at = created_at WHERE stage IN ('confirmed','received','packing','invoiced');
UPDATE orders o JOIN magnate_legacy.packing_list pl ON pl.order_id = o.id SET o.received_at = pl.created_on, o.packed_at = pl.created_on;

INSERT INTO order_lines (id, order_id, variety_id, length_id, order_by_id, stems, selling_price, remarks, created_by, created_at)
SELECT od.id, od.order_id, v.id, l.id, ob.id, od.ordered_stems,
       COALESCE((SELECT lfp.price FROM magnate_legacy.sourcing_farm_details s JOIN magnate_legacy.farm_prices lfp
                   ON lfp.farm = s.farm AND lfp.variety = od.variety AND lfp.length = od.length
                  WHERE s.order_detail_id = od.id ORDER BY lfp.id DESC LIMIT 1), 0),
       od.remarks, od.createdby, od.createdon
  FROM magnate_legacy.order_details od
  JOIN orders o ON o.id = od.order_id
  JOIN varieties v ON v.name = TRIM(od.variety) JOIN lengths l ON l.name = TRIM(od.length)
  LEFT JOIN order_by ob ON ob.name = TRIM(od.ordered_by);

INSERT INTO line_allocations (id, order_line_id, farm_id, allocated_stems, buying_price, currency_id, confirmed_stems, pack_rate, confirmed_boxes,
                              received_stems, received_boxes, confirmed_at, received_at)
SELECT s.id, s.order_detail_id, f.id, s.allocated,
       COALESCE(fp.buying_price, 0), COALESCE(fp.currency_id, f.currency_id),
       CASE WHEN o.stage = 'sourcing' AND COALESCE(s.confirmed, 0) = 0 THEN NULL ELSE COALESCE(s.confirmed, 0) END,
       NULLIF(s.pack_rate, 0), s.boxes,
       CASE WHEN o.stage IN ('packing','invoiced') THEN COALESCE(s.confirmed, 0) END,
       CASE WHEN o.stage IN ('packing','invoiced') THEN s.boxes END,
       s.lasteditedon, CASE WHEN o.stage IN ('packing','invoiced') THEN o.packed_at END
  FROM magnate_legacy.sourcing_farm_details s
  JOIN order_lines ol ON ol.id = s.order_detail_id
  JOIN orders o ON o.id = ol.order_id
  JOIN farms f ON f.name = TRIM(s.farm)
  LEFT JOIN farm_prices fp ON fp.farm_id = f.id AND fp.variety_id = ol.variety_id AND fp.length_id = ol.length_id;

-- one farm order per farm per order (also the farm bill once received)
INSERT INTO farm_orders (order_id, farm_id, farm_order_no, status, currency_id, exchange_rate, cost_amount, farm_invoice_no, farm_invoice_amount, phyto_kg, agent_awb, comments, sent_at, confirmed_at, received_at)
SELECT o.id, f.id, CONCAT(o.order_no, '-', UPPER(REPLACE(f.code, ' ', ''))),
       CASE WHEN o.stage IN ('packing','invoiced') THEN 'received' WHEN o.stage = 'confirmed' THEN 'confirmed' ELSE 'sent' END,
       MIN(la.currency_id),
       1,
       CASE WHEN o.stage IN ('packing','invoiced') THEN ROUND(SUM(COALESCE(la.received_stems, 0) * la.buying_price), 2) ELSE 0 END,
       MAX(s.farm_invoice_no), NULLIF(SUM(s.value_amount), 0), NULLIF(SUM(s.phyto_kg), 0), MAX(s.agent_airway_bill), MAX(s.comments),
       o.sourcing_at, o.confirmed_at, o.received_at
  FROM line_allocations la
  JOIN order_lines ol ON ol.id = la.order_line_id
  JOIN orders o ON o.id = ol.order_id
  JOIN farms f ON f.id = la.farm_id
  JOIN magnate_legacy.sourcing_farm_details s ON s.id = la.id
 WHERE o.stage <> 'draft'
 GROUP BY o.id, f.id, o.order_no, f.code, o.stage, o.departure_date, o.sourcing_at, o.confirmed_at, o.received_at;

UPDATE farm_orders fo JOIN orders o ON o.id = fo.order_id
   SET fo.exchange_rate = COALESCE((SELECT er.rate FROM exchange_rates er WHERE er.currency_id = fo.currency_id AND er.effective_date <= o.departure_date
                                     ORDER BY er.effective_date DESC LIMIT 1), 1);

-- ---------- packing lists ----------
INSERT INTO packing_lists (id, order_id, packing_list_no, consignment_no, drop_point_id, status, created_by, created_at)
SELECT pl.id, pl.order_id, pl.packing_list_no, pl.consignment_no, dp.id, IF(pl.status = 'Invoiced', 'invoiced', 'open'), pl.created_by, pl.created_on
  FROM magnate_legacy.packing_list pl JOIN orders o ON o.id = pl.order_id
  LEFT JOIN drop_points dp ON dp.name = TRIM(pl.drop_point);

-- ---------- invoices (re-built from received stems x line selling price) ----------
INSERT INTO invoices (id, invoice_no, kind, order_id, packing_list_id, customer_id, bill_to_customer_id, invoice_date, due_date, currency_id, exchange_rate, total, base_total, boxes, created_by, created_at)
SELECT i.id, i.invoice_no, 'order', o.id, pl.id, o.customer_id, o.customer_id, DATE(i.created_on), DATE(i.created_on),
       COALESCE(m.currency_id, o.currency_id),
       COALESCE((SELECT er.rate FROM exchange_rates er WHERE er.currency_id = COALESCE(m.currency_id, o.currency_id) AND er.effective_date <= DATE(i.created_on) ORDER BY er.effective_date DESC LIMIT 1), 1),
       0, 0, NULL, i.created_by, i.created_on
  FROM magnate_legacy.invoices i
  JOIN packing_lists pl ON pl.id = i.packing_list_id
  JOIN orders o ON o.id = pl.order_id
  LEFT JOIN _cur m ON m.legacy_name = i.currency;

INSERT INTO invoice_lines (invoice_id, description, variety_id, length_label, order_by_label, quantity, unit, pack_rate, boxes, unit_price, amount, sort_order)
SELECT inv.id, v.name, v.id, l.name, ob.name, SUM(la.received_stems), 'stems',
       ROUND(SUM(la.received_stems) / NULLIF(SUM(la.received_boxes), 0), 2), SUM(la.received_boxes),
       ol.selling_price, ROUND(SUM(la.received_stems) * ol.selling_price, 2), ol.id
  FROM invoices inv
  JOIN order_lines ol ON ol.order_id = inv.order_id
  JOIN line_allocations la ON la.order_line_id = ol.id
  JOIN varieties v ON v.id = ol.variety_id JOIN lengths l ON l.id = ol.length_id LEFT JOIN order_by ob ON ob.id = ol.order_by_id
 WHERE inv.kind = 'order'
 GROUP BY inv.id, ol.id, v.name, v.id, l.name, ob.name, ol.selling_price
HAVING SUM(la.received_stems) > 0;

UPDATE invoices i
   SET i.total = (SELECT COALESCE(SUM(amount), 0) FROM invoice_lines WHERE invoice_id = i.id),
       i.boxes = (SELECT SUM(boxes) FROM invoice_lines WHERE invoice_id = i.id);
UPDATE invoices SET base_total = ROUND(total * exchange_rate, 2);
UPDATE orders o JOIN invoices i ON i.order_id = o.id SET o.invoiced_at = i.created_at;

-- ---------- expenses ----------
-- v1 stored amount in KES and base_amount in the foreign currency; v2 stores amount in its currency and base in KES.
INSERT INTO expenses (expense_date, expense_type_id, description, amount, currency_id, exchange_rate, base_amount)
SELECT e.entry_date, et.id, COALESCE(NULLIF(e.other_details, ''), e.name),
       IF(ABS(e.amount - e.base_amount * e.exchange_rate) < 1, e.base_amount, e.amount),
       COALESCE(m.currency_id, (SELECT id FROM currencies WHERE is_base = 1)),
       e.exchange_rate,
       IF(ABS(e.amount - e.base_amount * e.exchange_rate) < 1, e.amount, ROUND(e.amount * e.exchange_rate, 2))
  FROM magnate_legacy.expenses e JOIN expense_types et ON et.name = TRIM(e.name)
  LEFT JOIN _cur m ON m.legacy_name = e.currency;

-- ---------- continue document numbering after the imported numbers ----------
INSERT INTO sequences (name, period, next_value)
SELECT 'order', '', COALESCE(MAX(CAST(SUBSTRING(order_no, 3) AS UNSIGNED)), 0) + 1 FROM orders WHERE order_no REGEXP '^OD[0-9]+$'
ON DUPLICATE KEY UPDATE next_value = GREATEST(next_value, VALUES(next_value));

INSERT INTO sequences (name, period, next_value)
SELECT 'invoice', SUBSTRING(invoice_no, 4, 2), MAX(CAST(SUBSTRING(invoice_no, 6) AS UNSIGNED)) + 1
  FROM invoices WHERE invoice_no REGEXP '^INV[0-9]{6,}$' GROUP BY SUBSTRING(invoice_no, 4, 2)
ON DUPLICATE KEY UPDATE next_value = GREATEST(next_value, VALUES(next_value));

-- v1 numbered packing lists by row id within the year, so continue after the highest number of any year
INSERT INTO sequences (name, period, next_value)
SELECT 'packing', SUBSTRING(packing_list_no, 3, 2), MAX(CAST(SUBSTRING(packing_list_no, 5) AS UNSIGNED)) + 1
  FROM packing_lists WHERE packing_list_no REGEXP '^PL[0-9]{6,}$' GROUP BY SUBSTRING(packing_list_no, 3, 2)
ON DUPLICATE KEY UPDATE next_value = GREATEST(next_value, VALUES(next_value));

INSERT INTO activity_log (entity, action, description) VALUES ('system', 'migrate', 'Data imported from v1');

DROP TEMPORARY TABLE IF EXISTS _cur;
SET FOREIGN_KEY_CHECKS = 1;
