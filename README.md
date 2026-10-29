# Magnate Flowers ERP — v2

Vanilla **PHP 8.1+ / MySQL 8** (or MariaDB 10.6+) with front-end libraries from CDNs (Bootstrap 5.3, Bootstrap Icons, jQuery, DataTables 2, Select2, SweetAlert2, Chart.js). No build step, no Composer.

## Install

1. Copy this folder to your web root (e.g. `htdocs/magnate`). Apache with `.htaccess` enabled is recommended (it hides `app/`, `sql/`, `docs/`).
2. Make `app/` writable for the first run (so the installer can write `app/config.php`) and `uploads/avatars/` writable always.
3. Open `http://localhost/magnate/install.php` and follow the 3 steps:
   - **Database** – connection details (the database is created if missing).
   - **Tables** – creates the schema + base data. To bring over your v1 data, first import `latest.sql` into a separate DB (e.g. `magnate_legacy`) and type its name here.
   - **Administrator** – your first admin account. (Skipped when v1 users are imported — sign in with your old username/password.)
4. Delete `install.php` on the live server.

Manual alternative: `mysql magnate < sql/schema.sql`, then `sql/seed.sql`, optionally `sql/migrate_v1.sql`, and copy `app/config.sample.php` → `app/config.php`.

## Architecture

```
index.php        page front controller        index.php?p=orders
api.php          JSON API front controller    api.php?r=orders.save   (CSRF on every POST)
print.php        documents (A4, print → PDF)  print.php?doc=invoice&id=12
app/
  bootstrap.php  config, autoloader, error handling, session
  Core/          DB (PDO, prepared statements only), Auth, Perms, Csrf, Fx, Seq, Log, Resources, Menu ...
  Api/           one class per module; rules() maps every action to a right
  Services/      OrderService (pipeline rules), StatementService, InsightService, Upload
  Reports/       Catalog.php — every report is declared here
pages/           server-rendered page shells (+ small inline scripts)
assets/js/       app.js (API client, auto-forms, tables, FX prompt), page scripts
docs/            document templates
sql/             schema.sql, seed.sql, migrate_v1.sql
```

Key decisions:

- **Real foreign keys everywhere.** v1 joined on names (rename a farm and its prices broke); v2 uses IDs.
- **One generic CRUD engine.** `app/Core/Resources.php` declares customers, farms, agents, varieties, the setup lists, payments, other sales, other costs, expenses and bank accounts. That one definition gives you the list, filters, totals, add/edit modal, validation, FX conversion and audit log. To add a field, add one line.
- **Rights enforced on the server.** Every API action declares its right in `rules()`; the menu and buttons only hide what the server would refuse anyway.
- **Money is frozen at transaction time.** Every financial row stores its exchange rate and base (KES) amount, so old reports never move when today's rate changes.
- **Race-safe numbering.** `sequences` table + row lock. Order invoices, other sales and freight invoices share one invoice series, so every invoice number is unique.
- **Full audit trail.** `activity_log` records every write, and the order timeline is one indexed query on it.

## The order pipeline

```
Draft ─▶ Sourcing ─▶ Farm confirmed ─▶ Delivery checked ─▶ Packing ─▶ Invoiced ─▶ Closed
```

1. **Capture** the customer order. Each line is a variety + length + mark + stems + selling price. The price defaults from *Selling Prices*, converted to the order currency. Split the stems across farms; each farm's **buying price** comes from *Farm Prices*.
2. **Send to farms.** This creates one farm order per farm (printable, or emailed from the Farm orders tab).
3. **Farm confirmation.** Record the stems confirmed, the pack rate and the boxes. Pack rate and boxes calculate each other.
4. **Delivery check (new).** Record the stems each farm actually delivered against what it confirmed, plus any rejects and the reason for any difference. Completing it posts the farm bills, at received stems × buying price.
5. **Packing list.** Built from received stems. Fill in AWB, flight, weights and drop point.
6. **Invoice.** Billed on received stems × selling price, at the invoice-date exchange rate. Other costs recorded against the invoice print as extra charges.
7. **Costs & profit** tab: revenue − farm cost − other costs − expenses linked to the order.

Documents for each step: Customer Order, Farm Orders, Confirmed Order, Delivery Check Note, Packing List and Invoice. Statements and Receipts are also available.

Voiding an order invoice sends the order back to *Packing*. **Re-open one step** undoes the last stage.

## Freight space (side business)

The company books cargo space with an agent or airline and sells it per kg to customers who send their own flowers.

- **Booking:** capacity (kg) × cost per kg, plus handling. This is owed to the agent and shows on the agent statement.
- **Sale:** chargeable kg × rate, plus extras. Chargeable kg is the higher of gross weight and volumetric weight (L×W×H ÷ 6000 per box; the divisor is set in Company Settings).
- **Invoice:** one click per customer. Freight invoices use the normal invoice numbers and appear on customer statements.
- **Tracking:** utilisation, break-even kg and margin. The dashboard warns when a booking departing within 7 days is under 70% sold.

## Exchange rates

Rates are **KES per 1 unit**. A rate applies from its effective date until a newer one is set.

On sign-in, any active currency without a rate for the current month triggers a prompt to set it. Users without the right to set rates are told to ask someone who has it. If a save needs a rate that is missing, the same prompt appears.

## Rights

Roles (Administrator, Operations Manager, Accountant, Viewer, or your own) carry rights per module and action. You can also grant or revoke individual rights for a single user on the *Users* page. `reports.financial` controls who can see profit and margins.

## Not verified yet

This build was written without a PHP runtime available. JavaScript was syntax-checked and the PHP passed static checks (balanced code, every class and API route resolves). **Run through the checklist below on a real server before going live**:

- [ ] Install fresh, create admin, sign in, set exchange rates from the prompt
- [ ] Create customer, farm, varieties, farm prices, a selling price
- [ ] Order → lines → send → confirm → delivery check (with a short delivery) → pack → invoice → print every document
- [ ] Payment, statement, other cost on the invoice, expense on the order → Order Journey report
- [ ] Freight booking → sell space → invoice
- [ ] A Viewer user cannot see/do restricted things
- [ ] Import v1 data into a copy and compare a few orders/invoices
