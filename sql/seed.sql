-- =====================================================================
--  MAGNATE FLOWERS ERP — base seed (run after schema.sql)
--  The first administrator account is created by /install.php
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES
 ('company_name',    'Magnate Flowers Limited'),
 ('company_tagline', 'Fresh-cut flower exporters'),
 ('company_address', 'P.O. Box 27742-00506, Nairobi, Kenya'),
 ('company_email',   'info@magnateflowers.co.ke'),
 ('company_phone',   '+254 712 345 678'),
 ('company_website', 'www.magnateflowers.co.ke'),
 ('company_pin',     'P051234567V'),
 ('base_currency',   'KES'),
 ('default_origin',  'NBO'),
 ('volumetric_divisor', '6000'),
 ('invoice_terms',   'All invoices to be paid before the day of shipping.'),
 ('origin_declaration', 'The exporter of the products covered by this document declares that, except where otherwise clearly indicated, these products are of Kenyan preferential origin.'),
 ('doc_accent',      '#BE185D');

INSERT INTO roles (id, name, description, is_super, is_system) VALUES
 (1, 'Administrator',      'Full access to everything',                          1, 1),
 (2, 'Operations Manager', 'Runs the order pipeline, freight and master data',   0, 1),
 (3, 'Accountant',         'Payments, costs, expenses, statements and reports',  0, 1),
 (4, 'Viewer',             'Read-only access',                                   0, 1);

-- Operations
INSERT INTO role_permissions (role_id, permission) VALUES
 (2,'dashboard.view'),
 (2,'orders.view'),(2,'orders.create'),(2,'orders.edit'),(2,'orders.delete'),(2,'orders.confirm'),(2,'orders.receive'),(2,'orders.pack'),(2,'orders.invoice'),
 (2,'packing.view'),(2,'packing.edit'),
 (2,'invoices.view'),
 (2,'farm_records.view'),(2,'farm_records.edit'),
 (2,'freight.view'),(2,'freight.create'),(2,'freight.edit'),(2,'freight.invoice'),
 (2,'farm_prices.view'),(2,'farm_prices.edit'),(2,'selling_prices.view'),(2,'selling_prices.edit'),
 (2,'exchange_rates.view'),
 (2,'customers.view'),(2,'customers.create'),(2,'customers.edit'),
 (2,'farms.view'),(2,'farms.create'),(2,'farms.edit'),
 (2,'shipping_agents.view'),(2,'shipping_agents.create'),(2,'shipping_agents.edit'),
 (2,'varieties.view'),(2,'varieties.create'),(2,'varieties.edit'),
 (2,'setup.view'),(2,'setup.edit'),
 (2,'reports.view');

-- Accounts
INSERT INTO role_permissions (role_id, permission) VALUES
 (3,'dashboard.view'),
 (3,'orders.view'),(3,'orders.invoice'),
 (3,'packing.view'),
 (3,'invoices.view'),(3,'invoices.void'),
 (3,'farm_records.view'),(3,'farm_records.edit'),
 (3,'freight.view'),(3,'freight.invoice'),
 (3,'customer_payments.view'),(3,'customer_payments.create'),(3,'customer_payments.edit'),(3,'customer_payments.delete'),
 (3,'farm_payments.view'),(3,'farm_payments.create'),(3,'farm_payments.edit'),(3,'farm_payments.delete'),
 (3,'agent_payments.view'),(3,'agent_payments.create'),(3,'agent_payments.edit'),(3,'agent_payments.delete'),
 (3,'other_sales.view'),(3,'other_sales.create'),(3,'other_sales.edit'),(3,'other_sales.delete'),
 (3,'other_costs.view'),(3,'other_costs.create'),(3,'other_costs.edit'),(3,'other_costs.delete'),
 (3,'expenses.view'),(3,'expenses.create'),(3,'expenses.edit'),(3,'expenses.delete'),
 (3,'statements.view'),
 (3,'reports.view'),(3,'reports.financial'),
 (3,'farm_prices.view'),(3,'selling_prices.view'),
 (3,'exchange_rates.view'),(3,'exchange_rates.edit'),
 (3,'customers.view'),(3,'farms.view'),(3,'shipping_agents.view'),(3,'varieties.view'),(3,'setup.view');

-- Viewer
INSERT INTO role_permissions (role_id, permission) VALUES
 (4,'dashboard.view'),(4,'orders.view'),(4,'packing.view'),(4,'invoices.view'),(4,'farm_records.view'),
 (4,'freight.view'),(4,'farm_prices.view'),(4,'selling_prices.view'),(4,'exchange_rates.view'),
 (4,'customers.view'),(4,'farms.view'),(4,'shipping_agents.view'),(4,'varieties.view'),(4,'reports.view');

INSERT INTO currencies (id, code, name, symbol, is_base, is_active) VALUES
 (1, 'KES', 'Kenya Shilling',  'KSh', 1, 1),
 (2, 'USD', 'US Dollar',       '$',   0, 1),
 (3, 'EUR', 'Euro',            '€',   0, 1),
 (4, 'GBP', 'Pound Sterling',  '£',   0, 1);

INSERT INTO exchange_rates (currency_id, rate, effective_date, notes) VALUES
 (1, 1, '2000-01-01', 'Base currency');

INSERT INTO sequences (name, period, next_value) VALUES ('order','',1);

INSERT INTO expense_types (name) VALUES
 ('Freight & Handling'),('Clearing & Documentation'),('Phytosanitary'),('Packaging Materials'),
 ('Transport / Trucking'),('Fuel & Parking'),('Salaries & Wages'),('Rent & Utilities'),
 ('Bank Charges'),('Communication'),('Office Supplies'),('Other');

INSERT INTO lengths (name, sort_order) VALUES
 ('30',30),('35',35),('40',40),('45',45),('50',50),('55',55),('60',60),('70',70),('80',80);

INSERT INTO box_types (name, length_cm, width_cm, height_cm) VALUES
 ('Standard',100,50,30),('Half Box',100,40,15),('Quarter Box',100,25,15);

INSERT INTO countries (name, iso2) VALUES
 ('Kenya','KE'),('Netherlands','NL'),('United Kingdom','GB'),('Germany','DE'),('United Arab Emirates','AE'),
 ('Kuwait','KW'),('Iraq','IQ'),('Saudi Arabia','SA'),('Qatar','QA'),('Russia','RU'),('Japan','JP'),
 ('Australia','AU'),('Uganda','UG'),('Tanzania','TZ'),('Rwanda','RW');

INSERT INTO order_by (name) VALUES ('Main');

-- From the v1 bank.csv (edit under Administration → Company Settings)
INSERT INTO bank_accounts (bank_name, branch, account_name, account_number, swift_code, sort_order) VALUES
 ('Equity Bank', 'Westlands', 'Magnate Flowers Limited', '1234567890', 'EQBLKENA', 1),
 ('KCB Bank', 'Upper Hill', 'Magnate Flowers Limited', '2345678901', 'KCBLKENX', 2),
 ('Co-operative Bank', 'Karen', 'Magnate Flowers Limited', '3456789012', 'COOPKENA', 3);
