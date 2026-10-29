-- =====================================================================
--  MAGNATE FLOWERS ERP  —  Database schema v2
--  MySQL 8.0+ / MariaDB 10.6+   ·   utf8mb4
--
--  Conventions
--   * Every reference is a real foreign key (no more joining on names).
--   * Money:  DECIMAL(14,2)   Unit prices: DECIMAL(12,4)   FX: DECIMAL(16,6)
--   * Exchange rates are stored as "KES per 1 unit of currency".
--     The base currency (KES) always has rate 1.
--   * Every financial row stores the exchange_rate used + base_amount (KES)
--     so historical reports never change when today's rate changes.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  SYSTEM / ADMINISTRATION
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  `key`       VARCHAR(80)  NOT NULL PRIMARY KEY,
  `value`     TEXT         NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_super    TINYINT(1)   NOT NULL DEFAULT 0,      -- super roles bypass every check
  is_system   TINYINT(1)   NOT NULL DEFAULT 0,      -- cannot be deleted
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS role_permissions;
CREATE TABLE role_permissions (
  role_id     INT UNSIGNED NOT NULL,
  permission  VARCHAR(80)  NOT NULL,
  PRIMARY KEY (role_id, permission),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name           VARCHAR(150) NOT NULL,
  username            VARCHAR(60)  NOT NULL UNIQUE,
  email               VARCHAR(190) NOT NULL UNIQUE,
  phone               VARCHAR(40)  NULL,
  job_title           VARCHAR(100) NULL,
  password_hash       VARCHAR(255) NOT NULL,
  role_id             INT UNSIGNED NOT NULL,
  avatar              VARCHAR(255) NULL,
  theme               ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
  status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at       DATETIME     NULL,
  password_changed_at DATETIME     NULL,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user overrides on top of the role: allow=1 grants, allow=0 revokes.
DROP TABLE IF EXISTS user_permissions;
CREATE TABLE user_permissions (
  user_id     INT UNSIGNED NOT NULL,
  permission  VARCHAR(80)  NOT NULL,
  allow       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, permission),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(100) NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  success      TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_user_time (username, attempted_at),
  KEY idx_la_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One log for everything. order_id makes the Order Journey timeline a single indexed query.
DROP TABLE IF EXISTS activity_log;
CREATE TABLE activity_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  entity      VARCHAR(50)  NOT NULL,
  entity_id   INT UNSIGNED NULL,
  order_id    INT UNSIGNED NULL,
  action      VARCHAR(50)  NOT NULL,
  description VARCHAR(500) NOT NULL,
  meta        JSON         NULL,
  ip          VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_al_order (order_id, created_at),
  KEY idx_al_entity (entity, entity_id),
  KEY idx_al_user (user_id, created_at),
  KEY idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gap-free, race-safe document numbering (row-locked in a transaction).
DROP TABLE IF EXISTS sequences;
CREATE TABLE sequences (
  name        VARCHAR(40)  NOT NULL,
  period      VARCHAR(10)  NOT NULL DEFAULT '',   -- e.g. '26' for yearly sequences, '' for global
  next_value  INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (name, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS bank_accounts;
CREATE TABLE bank_accounts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bank_name      VARCHAR(150) NOT NULL,
  branch         VARCHAR(150) NULL,
  account_name   VARCHAR(150) NOT NULL,
  account_number VARCHAR(80)  NOT NULL,
  swift_code     VARCHAR(30)  NULL,
  currency_id    INT UNSIGNED NULL,
  show_on_invoice TINYINT(1)  NOT NULL DEFAULT 1,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CURRENCY & EXCHANGE RATES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS currencies;
CREATE TABLE currencies (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code       CHAR(3)      NOT NULL UNIQUE,
  name       VARCHAR(80)  NOT NULL,
  symbol     VARCHAR(8)   NULL,
  is_base    TINYINT(1)   NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS exchange_rates;
CREATE TABLE exchange_rates (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  currency_id    INT UNSIGNED NOT NULL,
  rate           DECIMAL(16,6) NOT NULL,            -- KES per 1 unit
  effective_date DATE          NOT NULL,
  notes          VARCHAR(255)  NULL,
  created_by     INT UNSIGNED  NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rate_day (currency_id, effective_date),
  CONSTRAINT fk_er_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  LOOKUPS (setup forms)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS countries;
CREATE TABLE countries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  iso2 CHAR(2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS drop_points;
CREATE TABLE drop_points (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  address VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_categories;
CREATE TABLE product_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS box_types;
CREATE TABLE box_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  length_cm DECIMAL(6,1) NULL,
  width_cm  DECIMAL(6,1) NULL,
  height_cm DECIMAL(6,1) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS lengths;
CREATE TABLE lengths (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,         -- "50" (cm). Kept as text to allow "Spray" etc.
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_by;
CREATE TABLE order_by (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,        -- sub-client / mark
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS expense_types;
CREATE TABLE expense_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  MASTER DATA
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS varieties;
CREATE TABLE varieties (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL UNIQUE,
  category_id INT UNSIGNED NULL,
  colour      VARCHAR(60)  NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT fk_var_cat FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS farms;
CREATE TABLE farms (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code              VARCHAR(30)  NOT NULL UNIQUE,
  name              VARCHAR(150) NOT NULL UNIQUE,
  address           VARCHAR(255) NULL,
  contact_person    VARCHAR(120) NULL,
  email             VARCHAR(190) NULL,
  phone             VARCHAR(40)  NULL,
  currency_id       INT UNSIGNED NOT NULL,
  payment_terms_days INT         NOT NULL DEFAULT 30,
  notes             TEXT         NULL,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT fk_farm_cur FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(30)  NOT NULL UNIQUE,
  name            VARCHAR(150) NOT NULL UNIQUE,
  contact_person  VARCHAR(120) NULL,
  email           VARCHAR(190) NULL,
  phone           VARCHAR(40)  NULL,
  address         VARCHAR(255) NULL,
  country_id      INT UNSIGNED NULL,
  drop_point_id   INT UNSIGNED NULL,
  truck           VARCHAR(80)  NULL,
  currency_id     INT UNSIGNED NOT NULL,
  terms           VARCHAR(20)  NULL,            -- Incoterm: CIF / FOB / CPT ...
  credit_days     INT          NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT fk_cust_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
  CONSTRAINT fk_cust_drop    FOREIGN KEY (drop_point_id) REFERENCES drop_points(id) ON DELETE SET NULL,
  CONSTRAINT fk_cust_cur     FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_agents;
CREATE TABLE shipping_agents (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL UNIQUE,
  contact_person VARCHAR(120) NULL,
  email          VARCHAR(190) NULL,
  phone          VARCHAR(40)  NULL,
  currency_id    INT UNSIGNED NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT fk_agent_cur FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  PRICING
-- ---------------------------------------------------------------------
-- Farm price list. buying_price feeds farm orders, selling_price is an optional
-- farm-specific override of the general selling price.
DROP TABLE IF EXISTS farm_prices;
CREATE TABLE farm_prices (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  farm_id       INT UNSIGNED NOT NULL,
  variety_id    INT UNSIGNED NOT NULL,
  length_id     INT UNSIGNED NOT NULL,
  buying_price  DECIMAL(12,4) NOT NULL DEFAULT 0,
  selling_price DECIMAL(12,4) NULL,
  currency_id   INT UNSIGNED NOT NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  updated_by    INT UNSIGNED  NULL,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fp (farm_id, variety_id, length_id),
  KEY idx_fp_vl (variety_id, length_id),
  CONSTRAINT fk_fp_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_var  FOREIGN KEY (variety_id) REFERENCES varieties(id) ON DELETE CASCADE,
  CONSTRAINT fk_fp_len  FOREIGN KEY (length_id) REFERENCES lengths(id),
  CONSTRAINT fk_fp_cur  FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- General selling price per variety + length (e.g. Explorer 50cm = USD 0.50).
DROP TABLE IF EXISTS selling_prices;
CREATE TABLE selling_prices (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variety_id  INT UNSIGNED NOT NULL,
  length_id   INT UNSIGNED NOT NULL,
  price       DECIMAL(12,4) NOT NULL,
  currency_id INT UNSIGNED NOT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sp (variety_id, length_id),
  CONSTRAINT fk_sp_var FOREIGN KEY (variety_id) REFERENCES varieties(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_len FOREIGN KEY (length_id) REFERENCES lengths(id),
  CONSTRAINT fk_sp_cur FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ORDER PIPELINE
--  draft -> sourcing -> confirmed -> received -> packing -> invoiced -> closed
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_no          VARCHAR(20)  NOT NULL UNIQUE,
  customer_id       INT UNSIGNED NOT NULL,
  order_date        DATE         NOT NULL,
  departure_date    DATE         NOT NULL,
  box_type_id       INT UNSIGNED NULL,
  shipping_agent_id INT UNSIGNED NULL,
  drop_point_id     INT UNSIGNED NULL,
  currency_id       INT UNSIGNED NOT NULL,
  exchange_rate     DECIMAL(16,6) NOT NULL DEFAULT 1,     -- locked at order date
  stage             ENUM('draft','sourcing','confirmed','received','packing','invoiced','closed','cancelled') NOT NULL DEFAULT 'draft',
  customer_ref      VARCHAR(80)  NULL,
  remarks           TEXT         NULL,
  sourcing_at       DATETIME NULL,
  confirmed_at      DATETIME NULL,
  received_at       DATETIME NULL,
  packed_at         DATETIME NULL,
  invoiced_at       DATETIME NULL,
  closed_at         DATETIME NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by        INT UNSIGNED NULL,
  updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_orders_stage (stage, departure_date),
  KEY idx_orders_customer (customer_id, order_date),
  CONSTRAINT fk_o_cust  FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_o_box   FOREIGN KEY (box_type_id) REFERENCES box_types(id) ON DELETE SET NULL,
  CONSTRAINT fk_o_agent FOREIGN KEY (shipping_agent_id) REFERENCES shipping_agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_o_drop  FOREIGN KEY (drop_point_id) REFERENCES drop_points(id) ON DELETE SET NULL,
  CONSTRAINT fk_o_cur   FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What the customer asked for (one row per variety + length + mark).
DROP TABLE IF EXISTS order_lines;
CREATE TABLE order_lines (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  variety_id    INT UNSIGNED NOT NULL,
  length_id     INT UNSIGNED NOT NULL,
  order_by_id   INT UNSIGNED NULL,
  stems         INT UNSIGNED NOT NULL,
  selling_price DECIMAL(12,4) NOT NULL DEFAULT 0,          -- in the order currency
  remarks       VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ol_order (order_id),
  CONSTRAINT fk_ol_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_ol_var   FOREIGN KEY (variety_id) REFERENCES varieties(id),
  CONSTRAINT fk_ol_len   FOREIGN KEY (length_id) REFERENCES lengths(id),
  CONSTRAINT fk_ol_by    FOREIGN KEY (order_by_id) REFERENCES order_by(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One farm order per farm per customer order. Also acts as the farm bill (payable).
DROP TABLE IF EXISTS farm_orders;
CREATE TABLE farm_orders (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id            INT UNSIGNED NOT NULL,
  farm_id             INT UNSIGNED NOT NULL,
  farm_order_no       VARCHAR(30)  NOT NULL UNIQUE,
  status              ENUM('pending','sent','confirmed','received','cancelled') NOT NULL DEFAULT 'pending',
  currency_id         INT UNSIGNED NOT NULL,
  exchange_rate       DECIMAL(16,6) NOT NULL DEFAULT 1,
  cost_amount         DECIMAL(14,2) NOT NULL DEFAULT 0,   -- received stems x buying price (farm currency)
  farm_invoice_no     VARCHAR(80)  NULL,
  farm_invoice_amount DECIMAL(14,2) NULL,                  -- actual farm invoice (overrides cost_amount if set)
  phyto_kg            DECIMAL(10,2) NULL,
  agent_awb           VARCHAR(80)  NULL,
  comments            TEXT NULL,
  sent_at             DATETIME NULL,
  confirmed_at        DATETIME NULL,
  received_at         DATETIME NULL,
  UNIQUE KEY uq_fo (order_id, farm_id),
  KEY idx_fo_farm (farm_id),
  CONSTRAINT fk_fo_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_fo_farm  FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_fo_cur   FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- How each order line is split across farms, and the three quantity checkpoints:
-- allocated (asked) -> confirmed (farm promise) -> received (actually delivered & accepted).
DROP TABLE IF EXISTS line_allocations;
CREATE TABLE line_allocations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_line_id    INT UNSIGNED NOT NULL,
  farm_id          INT UNSIGNED NOT NULL,
  allocated_stems  INT UNSIGNED NOT NULL,
  buying_price     DECIMAL(12,4) NOT NULL DEFAULT 0,      -- farm currency
  currency_id      INT UNSIGNED NOT NULL,
  confirmed_stems  INT UNSIGNED NULL,
  pack_rate        DECIMAL(10,2) NULL,                    -- stems per box
  confirmed_boxes  DECIMAL(10,2) NULL,
  received_stems   INT UNSIGNED NULL,
  received_boxes   DECIMAL(10,2) NULL,
  rejected_stems   INT UNSIGNED NOT NULL DEFAULT 0,
  variance_reason  VARCHAR(255) NULL,
  confirmed_at     DATETIME NULL,
  received_at      DATETIME NULL,
  KEY idx_la_line (order_line_id),
  KEY idx_la_farm (farm_id),
  CONSTRAINT fk_la_line FOREIGN KEY (order_line_id) REFERENCES order_lines(id) ON DELETE CASCADE,
  CONSTRAINT fk_la_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_la_cur  FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS packing_lists;
CREATE TABLE packing_lists (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id        INT UNSIGNED NOT NULL UNIQUE,
  packing_list_no VARCHAR(20)  NOT NULL UNIQUE,
  consignment_no  VARCHAR(20)  NOT NULL UNIQUE,
  drop_point_id   INT UNSIGNED NULL,
  awb_no          VARCHAR(60)  NULL,
  flight_no       VARCHAR(30)  NULL,
  gross_weight_kg DECIMAL(10,2) NULL,
  volume_weight_kg DECIMAL(10,2) NULL,
  notes           TEXT NULL,
  status          ENUM('open','invoiced') NOT NULL DEFAULT 'open',
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pl_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_pl_drop  FOREIGN KEY (drop_point_id) REFERENCES drop_points(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  FREIGHT SPACE (side business: buy cargo capacity, resell per kg)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS freight_bookings;
CREATE TABLE freight_bookings (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_no        VARCHAR(20)  NOT NULL UNIQUE,
  shipping_agent_id INT UNSIGNED NOT NULL,
  airline           VARCHAR(100) NULL,
  flight_no         VARCHAR(30)  NULL,
  awb_no            VARCHAR(60)  NULL,
  origin            VARCHAR(60)  NOT NULL DEFAULT 'NBO',
  destination       VARCHAR(60)  NOT NULL,
  departure_date    DATE         NOT NULL,
  capacity_kg       DECIMAL(10,2) NOT NULL,
  cost_per_kg       DECIMAL(12,4) NOT NULL,
  other_costs       DECIMAL(14,2) NOT NULL DEFAULT 0,      -- handling, screening, docs ...
  currency_id       INT UNSIGNED NOT NULL,
  exchange_rate     DECIMAL(16,6) NOT NULL DEFAULT 1,
  total_cost        DECIMAL(14,2) NOT NULL DEFAULT 0,
  base_cost         DECIMAL(14,2) NOT NULL DEFAULT 0,
  status            ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
  notes             TEXT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fb_agent FOREIGN KEY (shipping_agent_id) REFERENCES shipping_agents(id),
  CONSTRAINT fk_fb_cur   FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  INVOICING  (order invoices, other sales and freight invoices share one numbering)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_no          VARCHAR(20)  NOT NULL UNIQUE,
  kind                ENUM('order','other_sale','freight') NOT NULL DEFAULT 'order',
  order_id            INT UNSIGNED NULL,
  packing_list_id     INT UNSIGNED NULL,
  freight_booking_id  INT UNSIGNED NULL,
  customer_id         INT UNSIGNED NOT NULL,       -- consignee
  bill_to_customer_id INT UNSIGNED NOT NULL,       -- who pays (statement owner)
  invoice_date        DATE         NOT NULL,
  due_date            DATE         NULL,
  currency_id         INT UNSIGNED NOT NULL,
  exchange_rate       DECIMAL(16,6) NOT NULL DEFAULT 1,
  total               DECIMAL(14,2) NOT NULL DEFAULT 0,
  base_total          DECIMAL(14,2) NOT NULL DEFAULT 0,
  shipment_details    VARCHAR(255) NULL,
  s_number            VARCHAR(60)  NULL,
  boxes               DECIMAL(10,2) NULL,
  notes               TEXT NULL,
  status              ENUM('open','void') NOT NULL DEFAULT 'open',
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_billto (bill_to_customer_id, invoice_date),
  KEY idx_inv_order (order_id),
  CONSTRAINT fk_inv_order  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_pl     FOREIGN KEY (packing_list_id) REFERENCES packing_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_fb     FOREIGN KEY (freight_booking_id) REFERENCES freight_bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_cust   FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_inv_billto FOREIGN KEY (bill_to_customer_id) REFERENCES customers(id),
  CONSTRAINT fk_inv_cur    FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot of what was billed — invoices never change if prices change later.
DROP TABLE IF EXISTS invoice_lines;
CREATE TABLE invoice_lines (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  variety_id  INT UNSIGNED NULL,
  length_label VARCHAR(30) NULL,
  order_by_label VARCHAR(120) NULL,
  quantity    DECIMAL(12,2) NOT NULL DEFAULT 0,     -- stems (or kg for freight)
  unit        VARCHAR(20)  NOT NULL DEFAULT 'stems',
  pack_rate   DECIMAL(10,2) NULL,
  boxes       DECIMAL(10,2) NULL,
  unit_price  DECIMAL(12,4) NOT NULL DEFAULT 0,
  amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort_order  INT NOT NULL DEFAULT 0,
  KEY idx_il_inv (invoice_id),
  CONSTRAINT fk_il_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Space sold to customers on a booking.
DROP TABLE IF EXISTS freight_allocations;
CREATE TABLE freight_allocations (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id      INT UNSIGNED NOT NULL,
  customer_id     INT UNSIGNED NOT NULL,
  goods_description VARCHAR(255) NULL,
  hawb_no         VARCHAR(60)  NULL,                 -- house air waybill
  boxes           INT UNSIGNED NOT NULL DEFAULT 0,
  gross_kg        DECIMAL(10,2) NOT NULL DEFAULT 0,
  volume_kg       DECIMAL(10,2) NOT NULL DEFAULT 0,
  chargeable_kg   DECIMAL(10,2) NOT NULL DEFAULT 0,  -- max(gross, volumetric)
  rate_per_kg     DECIMAL(12,4) NOT NULL,
  extra_charges   DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency_id     INT UNSIGNED NOT NULL,
  exchange_rate   DECIMAL(16,6) NOT NULL DEFAULT 1,
  amount          DECIMAL(14,2) NOT NULL DEFAULT 0,
  base_amount     DECIMAL(14,2) NOT NULL DEFAULT 0,
  invoice_id      INT UNSIGNED NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fa_booking (booking_id),
  CONSTRAINT fk_fa_booking FOREIGN KEY (booking_id) REFERENCES freight_bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_fa_cust    FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_fa_cur     FOREIGN KEY (currency_id) REFERENCES currencies(id),
  CONSTRAINT fk_fa_inv     FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  FINANCE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS customer_payments;
CREATE TABLE customer_payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  payment_date  DATE NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  details       VARCHAR(255) NULL,
  method        VARCHAR(40)  NULL,
  reference     VARCHAR(80)  NULL,
  amount        DECIMAL(14,2) NOT NULL,
  currency_id   INT UNSIGNED NOT NULL,
  exchange_rate DECIMAL(16,6) NOT NULL,
  base_amount   DECIMAL(14,2) NOT NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cp_cust (customer_id, payment_date),
  CONSTRAINT fk_cp_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_cp_cur  FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS farm_payments;
CREATE TABLE farm_payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  payment_date  DATE NOT NULL,
  farm_id       INT UNSIGNED NOT NULL,
  details       VARCHAR(255) NULL,
  method        VARCHAR(40)  NULL,
  reference     VARCHAR(80)  NULL,
  amount        DECIMAL(14,2) NOT NULL,
  currency_id   INT UNSIGNED NOT NULL,
  exchange_rate DECIMAL(16,6) NOT NULL,
  base_amount   DECIMAL(14,2) NOT NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fp_farm_date (farm_id, payment_date),
  CONSTRAINT fk_fpay_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_fpay_cur  FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS agent_payments;
CREATE TABLE agent_payments (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  payment_date      DATE NOT NULL,
  shipping_agent_id INT UNSIGNED NOT NULL,
  details           VARCHAR(255) NULL,
  method            VARCHAR(40)  NULL,
  reference         VARCHAR(80)  NULL,
  amount            DECIMAL(14,2) NOT NULL,
  currency_id       INT UNSIGNED NOT NULL,
  exchange_rate     DECIMAL(16,6) NOT NULL,
  base_amount       DECIMAL(14,2) NOT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ap_agent (shipping_agent_id, payment_date),
  CONSTRAINT fk_apay_agent FOREIGN KEY (shipping_agent_id) REFERENCES shipping_agents(id),
  CONSTRAINT fk_apay_cur   FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra charges against an invoice (data logger, freight, fumigation ...).
-- selling_price is billed to the customer, buying_price is our cost.
DROP TABLE IF EXISTS customer_other_costs;
CREATE TABLE customer_other_costs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cost_date     DATE NOT NULL,
  invoice_id    INT UNSIGNED NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  details       VARCHAR(120) NOT NULL,
  currency_id   INT UNSIGNED NOT NULL,
  exchange_rate DECIMAL(16,6) NOT NULL,
  selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  buying_price  DECIMAL(14,2) NOT NULL DEFAULT 0,
  selling_base  DECIMAL(14,2) NOT NULL DEFAULT 0,
  buying_base   DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_coc_inv (invoice_id),
  KEY idx_coc_cust (customer_id, cost_date),
  CONSTRAINT fk_coc_inv  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_coc_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_coc_cur  FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS expenses;
CREATE TABLE expenses (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  expense_date       DATE NOT NULL,
  expense_type_id    INT UNSIGNED NOT NULL,
  description        VARCHAR(255) NOT NULL,
  payee              VARCHAR(150) NULL,
  order_id           INT UNSIGNED NULL,          -- tie to an order for per-order profit
  freight_booking_id INT UNSIGNED NULL,          -- or to a freight booking
  shipping_agent_id  INT UNSIGNED NULL,          -- billed by an agent -> appears on agent statement
  method             VARCHAR(40)  NULL,
  reference          VARCHAR(80)  NULL,
  amount             DECIMAL(14,2) NOT NULL,
  currency_id        INT UNSIGNED NOT NULL,
  exchange_rate      DECIMAL(16,6) NOT NULL,
  base_amount        DECIMAL(14,2) NOT NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp_date (expense_date),
  KEY idx_exp_order (order_id),
  CONSTRAINT fk_exp_type  FOREIGN KEY (expense_type_id) REFERENCES expense_types(id),
  CONSTRAINT fk_exp_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_fb    FOREIGN KEY (freight_booking_id) REFERENCES freight_bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_agent FOREIGN KEY (shipping_agent_id) REFERENCES shipping_agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_cur   FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
