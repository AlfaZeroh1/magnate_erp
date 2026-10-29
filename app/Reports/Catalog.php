<?php
declare(strict_types=1);

namespace App\Reports;

use App\Core\DB;
use App\Services\OrderService;
use App\Services\StatementService;

/**
 * Report catalogue. A report is either SQL (with :from :to :customer_id :farm_id :agent_id placeholders)
 * or a PHP callable returning rows. Columns: [key, label, type(text|int|qty|money|pct|date|stage)].
 * Optional chart: [type, label column, value columns].
 */
final class Catalog
{
    public static function groups(): array
    {
        return [
            'Orders & operations' => 'bi-flower1',
            'Sales'               => 'bi-graph-up',
            'Farms & purchasing'  => 'bi-tree',
            'Finance'             => 'bi-cash-coin',
            'Freight space'       => 'bi-airplane',
            'System'              => 'bi-gear',
        ];
    }

    public static function all(): array
    {
        $inv = "i.status = 'open' AND i.invoice_date BETWEEN :from AND :to";
        return [
            // ---------------------------------------------------------------- ORDERS
            'orders_register' => [
                'group' => 'Orders & operations', 'title' => 'Orders register', 'icon' => 'bi-list-check',
                'desc' => 'Every order with stage and quantities at each checkpoint.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT o.order_no, o.order_date, o.departure_date, c.name AS customer, o.stage,
                                 (SELECT COALESCE(SUM(stems),0) FROM order_lines WHERE order_id = o.id) AS ordered,
                                 SUM(la.confirmed_stems) AS confirmed, SUM(la.received_stems) AS received, SUM(la.rejected_stems) AS rejected,
                                 cur.code AS currency, i.invoice_no, i.total AS invoiced
                            FROM orders o JOIN customers c ON c.id = o.customer_id JOIN currencies cur ON cur.id = o.currency_id
                            LEFT JOIN order_lines ol ON ol.order_id = o.id LEFT JOIN line_allocations la ON la.order_line_id = ol.id
                            LEFT JOIN invoices i ON i.order_id = o.id AND i.kind = 'order' AND i.status = 'open'
                           WHERE o.departure_date BETWEEN :from AND :to AND (:customer_id IS NULL OR o.customer_id = :customer_id)
                           GROUP BY o.id, o.order_no, o.order_date, o.departure_date, c.name, o.stage, cur.code, i.invoice_no, i.total
                           ORDER BY o.departure_date DESC",
                'columns' => [['order_no', 'Order'], ['order_date', 'Ordered', 'date'], ['departure_date', 'Departure', 'date'], ['customer', 'Customer'], ['stage', 'Stage', 'stage'],
                              ['ordered', 'Ordered', 'int'], ['confirmed', 'Confirmed', 'int'], ['received', 'Received', 'int'], ['rejected', 'Rejected', 'int'],
                              ['currency', 'Cur'], ['invoice_no', 'Invoice'], ['invoiced', 'Invoiced', 'money']],
                'totals' => ['ordered', 'confirmed', 'received', 'rejected'], 'link' => ['order_no' => 'order'],
            ],
            'order_profitability' => [
                'group' => 'Orders & operations', 'title' => 'Order profitability', 'icon' => 'bi-piggy-bank', 'financial' => true,
                'desc' => 'Revenue, farm cost, other costs, expenses and profit for every order (base currency).', 'filters' => ['date', 'customer'],
                'php' => function (array $f) {
                    $out = [];
                    foreach (DB::all("SELECT o.id FROM orders o WHERE o.stage <> 'cancelled' AND o.departure_date BETWEEN :from AND :to
                                       AND (:customer_id IS NULL OR o.customer_id = :customer_id) ORDER BY o.departure_date DESC",
                        ['from' => $f['from'], 'to' => $f['to'], 'customer_id' => $f['customer_id']]) as $r) {
                        $o = OrderService::find((int)$r['id']);
                        $m = OrderService::financials($o);
                        $out[] = ['order_no' => $o['order_no'], 'departure_date' => $o['departure_date'], 'customer' => $o['customer_name'], 'stage' => $o['stage'],
                                  'stems' => $m['qty'][$m['basis']] ?? 0, 'revenue' => $m['total_revenue_base'], 'farm_cost' => $m['farm_cost_base'],
                                  'other' => $m['other_cost_base'] + $m['expenses_base'], 'profit' => $m['profit_base'], 'margin' => $m['margin_pct'],
                                  'per_stem' => ($m['qty'][$m['basis']] ?? 0) > 0 ? round($m['profit_base'] / $m['qty'][$m['basis']], 2) : null,
                                  'basis' => $m['projected'] ? 'projected' : 'actual'];
                    }
                    return $out;
                },
                'columns' => [['order_no', 'Order'], ['departure_date', 'Departure', 'date'], ['customer', 'Customer'], ['stage', 'Stage', 'stage'], ['stems', 'Stems', 'int'],
                              ['revenue', 'Revenue', 'money'], ['farm_cost', 'Farm cost', 'money'], ['other', 'Other & expenses', 'money'], ['profit', 'Profit', 'money'],
                              ['margin', 'Margin', 'pct'], ['per_stem', 'Profit/stem', 'money'], ['basis', 'Basis']],
                'totals' => ['stems', 'revenue', 'farm_cost', 'other', 'profit'], 'link' => ['order_no' => 'order'],
                'chart' => ['bar', 'order_no', ['profit'], 25],
            ],
            'fulfilment_funnel' => [
                'group' => 'Orders & operations', 'title' => 'Fulfilment funnel by month', 'icon' => 'bi-funnel',
                'desc' => 'Ordered → allocated → confirmed → received stems per month, with fill rates.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT DATE_FORMAT(o.departure_date, '%Y-%m') AS month, COUNT(DISTINCT o.id) AS orders,
                                 SUM(la.allocated_stems) AS allocated, SUM(la.confirmed_stems) AS confirmed, SUM(la.received_stems) AS received,
                                 ROUND(SUM(la.confirmed_stems) / NULLIF(SUM(la.allocated_stems),0) * 100, 1) AS confirm_rate,
                                 ROUND(SUM(la.received_stems) / NULLIF(SUM(la.confirmed_stems),0) * 100, 1) AS delivery_rate
                            FROM orders o JOIN order_lines ol ON ol.order_id = o.id JOIN line_allocations la ON la.order_line_id = ol.id
                           WHERE o.stage <> 'cancelled' AND o.departure_date BETWEEN :from AND :to AND (:customer_id IS NULL OR o.customer_id = :customer_id)
                           GROUP BY DATE_FORMAT(o.departure_date, '%Y-%m') ORDER BY month",
                'columns' => [['month', 'Month'], ['orders', 'Orders', 'int'], ['allocated', 'Asked of farms', 'int'], ['confirmed', 'Confirmed', 'int'], ['received', 'Received', 'int'],
                              ['confirm_rate', 'Confirm rate', 'pct'], ['delivery_rate', 'Delivery accuracy', 'pct']],
                'totals' => ['orders', 'allocated', 'confirmed', 'received'], 'chart' => ['bar', 'month', ['allocated', 'confirmed', 'received']],
            ],
            'pipeline_status' => [
                'group' => 'Orders & operations', 'title' => 'Pipeline by stage', 'icon' => 'bi-kanban',
                'desc' => 'How many orders (and stems / value) sit at each stage right now.', 'filters' => [],
                'sql' => "SELECT o.stage, COUNT(*) AS orders, SUM(x.stems) AS stems, SUM(x.value * o.exchange_rate) AS value_base, MIN(o.departure_date) AS earliest
                            FROM orders o JOIN (SELECT order_id, SUM(stems) AS stems, SUM(stems * selling_price) AS value FROM order_lines GROUP BY order_id) x ON x.order_id = o.id
                           WHERE o.stage NOT IN ('closed','cancelled')
                           GROUP BY o.stage ORDER BY FIELD(o.stage,'draft','sourcing','confirmed','received','packing','invoiced')",
                'columns' => [['stage', 'Stage', 'stage'], ['orders', 'Orders', 'int'], ['stems', 'Stems', 'int'], ['value_base', 'Value (base)', 'money'], ['earliest', 'Earliest departure', 'date']],
                'totals' => ['orders', 'stems', 'value_base'], 'chart' => ['doughnut', 'stage', ['orders']],
            ],
            'delivery_variance' => [
                'group' => 'Orders & operations', 'title' => 'Delivery check variances', 'icon' => 'bi-clipboard2-x',
                'desc' => 'Every farm line where what arrived differs from what the farm confirmed, with the reason.', 'filters' => ['date', 'farm'],
                'sql' => "SELECT o.order_no, o.departure_date, f.name AS farm, v.name AS variety, l.name AS length,
                                 la.confirmed_stems AS confirmed, la.received_stems AS received, (la.received_stems - la.confirmed_stems) AS variance,
                                 la.rejected_stems AS rejected, la.variance_reason AS reason
                            FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN orders o ON o.id = ol.order_id
                            JOIN farms f ON f.id = la.farm_id JOIN varieties v ON v.id = ol.variety_id JOIN lengths l ON l.id = ol.length_id
                           WHERE la.received_stems IS NOT NULL AND (la.received_stems <> COALESCE(la.confirmed_stems,0) OR la.rejected_stems > 0)
                             AND o.departure_date BETWEEN :from AND :to AND (:farm_id IS NULL OR la.farm_id = :farm_id)
                           ORDER BY o.departure_date DESC",
                'columns' => [['order_no', 'Order'], ['departure_date', 'Departure', 'date'], ['farm', 'Farm'], ['variety', 'Variety'], ['length', 'Length'],
                              ['confirmed', 'Confirmed', 'int'], ['received', 'Received', 'int'], ['variance', 'Variance', 'int'], ['rejected', 'Rejected', 'int'], ['reason', 'Reason']],
                'totals' => ['confirmed', 'received', 'variance', 'rejected'],
            ],

            // ---------------------------------------------------------------- SALES
            'sales_by_month' => [
                'group' => 'Sales', 'title' => 'Sales by month', 'icon' => 'bi-calendar3', 'desc' => 'Invoiced sales per month split by type (base currency).', 'filters' => ['date', 'customer'],
                'sql' => "SELECT DATE_FORMAT(i.invoice_date, '%Y-%m') AS month, COUNT(*) AS invoices,
                                 SUM(CASE WHEN i.kind = 'order' THEN i.base_total ELSE 0 END) AS flowers,
                                 SUM(CASE WHEN i.kind = 'other_sale' THEN i.base_total ELSE 0 END) AS other_sales,
                                 SUM(CASE WHEN i.kind = 'freight' THEN i.base_total ELSE 0 END) AS freight,
                                 SUM(i.base_total) AS total
                            FROM invoices i WHERE $inv AND (:customer_id IS NULL OR i.bill_to_customer_id = :customer_id)
                           GROUP BY DATE_FORMAT(i.invoice_date, '%Y-%m') ORDER BY month",
                'columns' => [['month', 'Month'], ['invoices', 'Invoices', 'int'], ['flowers', 'Flowers', 'money'], ['other_sales', 'Other sales', 'money'], ['freight', 'Freight', 'money'], ['total', 'Total', 'money']],
                'totals' => ['invoices', 'flowers', 'other_sales', 'freight', 'total'], 'chart' => ['bar', 'month', ['flowers', 'other_sales', 'freight'], null, true],
            ],
            'sales_by_customer' => [
                'group' => 'Sales', 'title' => 'Sales by customer', 'icon' => 'bi-people', 'desc' => 'Ranking of customers by invoiced value, stems and boxes.', 'filters' => ['date'],
                'sql' => "SELECT c.name AS customer, co.name AS country, COUNT(*) AS invoices, SUM(i.boxes) AS boxes,
                                 (SELECT COALESCE(SUM(il.quantity),0) FROM invoice_lines il JOIN invoices i2 ON i2.id = il.invoice_id
                                   WHERE i2.bill_to_customer_id = c.id AND i2.status = 'open' AND i2.kind = 'order' AND i2.invoice_date BETWEEN :from AND :to) AS stems,
                                 SUM(i.base_total) AS sales
                            FROM invoices i JOIN customers c ON c.id = i.bill_to_customer_id LEFT JOIN countries co ON co.id = c.country_id
                           WHERE $inv GROUP BY c.id, c.name, co.name ORDER BY sales DESC",
                'columns' => [['customer', 'Customer'], ['country', 'Country'], ['invoices', 'Invoices', 'int'], ['boxes', 'Boxes', 'qty'], ['stems', 'Stems', 'int'], ['sales', 'Sales (base)', 'money']],
                'totals' => ['invoices', 'boxes', 'stems', 'sales'], 'chart' => ['bar', 'customer', ['sales'], 15],
            ],
            'sales_by_variety' => [
                'group' => 'Sales', 'title' => 'Sales by variety & length', 'icon' => 'bi-flower2', 'desc' => 'Stems and value invoiced per variety and length, with average price.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT il.description AS variety, il.length_label AS length, SUM(il.quantity) AS stems, SUM(il.amount * i.exchange_rate) AS sales,
                                 ROUND(SUM(il.amount * i.exchange_rate) / NULLIF(SUM(il.quantity),0), 4) AS avg_price
                            FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id
                           WHERE $inv AND i.kind = 'order' AND (:customer_id IS NULL OR i.bill_to_customer_id = :customer_id)
                           GROUP BY il.description, il.length_label ORDER BY sales DESC",
                'columns' => [['variety', 'Variety'], ['length', 'Length'], ['stems', 'Stems', 'int'], ['sales', 'Sales (base)', 'money'], ['avg_price', 'Avg / stem (base)', 'money']],
                'totals' => ['stems', 'sales'], 'chart' => ['bar', 'variety', ['sales'], 15],
            ],
            'sales_by_country' => [
                'group' => 'Sales', 'title' => 'Sales by destination country', 'icon' => 'bi-globe2', 'desc' => 'Where your flowers go.', 'filters' => ['date'],
                'sql' => "SELECT COALESCE(co.name, 'Unknown') AS country, COUNT(DISTINCT c.id) AS customers, COUNT(*) AS invoices, SUM(i.base_total) AS sales
                            FROM invoices i JOIN customers c ON c.id = i.customer_id LEFT JOIN countries co ON co.id = c.country_id
                           WHERE $inv GROUP BY co.name ORDER BY sales DESC",
                'columns' => [['country', 'Country'], ['customers', 'Customers', 'int'], ['invoices', 'Invoices', 'int'], ['sales', 'Sales (base)', 'money']],
                'totals' => ['customers', 'invoices', 'sales'], 'chart' => ['doughnut', 'country', ['sales']],
            ],
            'invoice_register' => [
                'group' => 'Sales', 'title' => 'Invoice register', 'icon' => 'bi-receipt', 'desc' => 'All invoices with amounts in invoice and base currency.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT i.invoice_no, i.invoice_date, i.due_date, i.kind, c.name AS bill_to, cur.code AS currency, i.total, i.exchange_rate, i.base_total, i.status
                            FROM invoices i JOIN customers c ON c.id = i.bill_to_customer_id JOIN currencies cur ON cur.id = i.currency_id
                           WHERE i.invoice_date BETWEEN :from AND :to AND (:customer_id IS NULL OR i.bill_to_customer_id = :customer_id) ORDER BY i.invoice_date DESC, i.id DESC",
                'columns' => [['invoice_no', 'Invoice'], ['invoice_date', 'Date', 'date'], ['due_date', 'Due', 'date'], ['kind', 'Type'], ['bill_to', 'Bill to'], ['currency', 'Cur'],
                              ['total', 'Amount', 'money'], ['exchange_rate', 'Rate', 'rate'], ['base_total', 'Base', 'money'], ['status', 'Status']],
                'totals' => ['base_total'],
            ],
            'other_sales_register' => [
                'group' => 'Sales', 'title' => 'Other sales register', 'icon' => 'bi-bag-plus', 'desc' => 'Sales captured outside the order pipeline.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT i.invoice_no, i.invoice_date, b.name AS bill_to, c.name AS customer, i.shipment_details, i.s_number, i.boxes, cur.code AS currency, i.total, i.base_total
                            FROM invoices i JOIN customers b ON b.id = i.bill_to_customer_id JOIN customers c ON c.id = i.customer_id JOIN currencies cur ON cur.id = i.currency_id
                           WHERE i.kind = 'other_sale' AND $inv AND (:customer_id IS NULL OR i.bill_to_customer_id = :customer_id) ORDER BY i.invoice_date DESC",
                'columns' => [['invoice_no', 'Invoice'], ['invoice_date', 'Date', 'date'], ['bill_to', 'Bill to'], ['customer', 'Customer'], ['shipment_details', 'Shipment'], ['s_number', 'S no'],
                              ['boxes', 'Boxes', 'qty'], ['currency', 'Cur'], ['total', 'Price', 'money'], ['base_total', 'Base', 'money']],
                'totals' => ['boxes', 'base_total'],
            ],

            // ---------------------------------------------------------------- FARMS
            'farm_performance' => [
                'group' => 'Farms & purchasing', 'title' => 'Farm performance scorecard', 'icon' => 'bi-award',
                'desc' => 'Per farm: how much they confirm of what you ask, and how accurately they deliver what they confirm.', 'filters' => ['date', 'farm'],
                'sql' => "SELECT f.name AS farm, COUNT(DISTINCT o.id) AS orders, SUM(la.allocated_stems) AS asked, SUM(la.confirmed_stems) AS confirmed, SUM(la.received_stems) AS received,
                                 SUM(la.rejected_stems) AS rejected,
                                 ROUND(SUM(la.confirmed_stems) / NULLIF(SUM(la.allocated_stems),0) * 100, 1) AS confirm_rate,
                                 ROUND(SUM(la.received_stems) / NULLIF(SUM(CASE WHEN la.received_stems IS NOT NULL THEN la.confirmed_stems END),0) * 100, 1) AS delivery_rate,
                                 ROUND(SUM(la.rejected_stems) / NULLIF(SUM(la.received_stems) + SUM(la.rejected_stems),0) * 100, 1) AS reject_rate
                            FROM line_allocations la JOIN order_lines ol ON ol.id = la.order_line_id JOIN orders o ON o.id = ol.order_id JOIN farms f ON f.id = la.farm_id
                           WHERE o.stage NOT IN ('draft','cancelled') AND o.departure_date BETWEEN :from AND :to AND (:farm_id IS NULL OR la.farm_id = :farm_id)
                           GROUP BY f.id, f.name ORDER BY asked DESC",
                'columns' => [['farm', 'Farm'], ['orders', 'Orders', 'int'], ['asked', 'Asked', 'int'], ['confirmed', 'Confirmed', 'int'], ['received', 'Received', 'int'], ['rejected', 'Rejected', 'int'],
                              ['confirm_rate', 'Confirm rate', 'pct'], ['delivery_rate', 'Delivery accuracy', 'pct'], ['reject_rate', 'Reject rate', 'pct']],
                'totals' => ['orders', 'asked', 'confirmed', 'received', 'rejected'], 'chart' => ['bar', 'farm', ['confirm_rate', 'delivery_rate'], 15],
            ],
            'purchases_by_farm' => [
                'group' => 'Farms & purchasing', 'title' => 'Purchases by farm', 'icon' => 'bi-basket', 'financial' => true,
                'desc' => 'Farm bills (received flowers) per farm in base currency.', 'filters' => ['date', 'farm'],
                'sql' => "SELECT f.name AS farm, cur.code AS currency, COUNT(*) AS bills, SUM(COALESCE(fo.farm_invoice_amount, fo.cost_amount)) AS amount,
                                 SUM(COALESCE(fo.farm_invoice_amount, fo.cost_amount) * fo.exchange_rate) AS base,
                                 SUM(fo.farm_invoice_no IS NULL OR fo.farm_invoice_no = '') AS missing_invoice
                            FROM farm_orders fo JOIN farms f ON f.id = fo.farm_id JOIN orders o ON o.id = fo.order_id JOIN currencies cur ON cur.id = fo.currency_id
                           WHERE fo.status = 'received' AND o.departure_date BETWEEN :from AND :to AND (:farm_id IS NULL OR fo.farm_id = :farm_id)
                           GROUP BY f.id, f.name, cur.code ORDER BY base DESC",
                'columns' => [['farm', 'Farm'], ['currency', 'Cur'], ['bills', 'Bills', 'int'], ['amount', 'Amount', 'money'], ['base', 'Base', 'money'], ['missing_invoice', 'Missing farm inv.', 'int']],
                'totals' => ['bills', 'base', 'missing_invoice'], 'chart' => ['bar', 'farm', ['base'], 15],
            ],
            'farm_price_list' => [
                'group' => 'Farms & purchasing', 'title' => 'Price list & margins', 'icon' => 'bi-tags', 'desc' => 'Current farm buying prices next to selling prices.', 'filters' => ['farm'],
                'sql' => "SELECT f.name AS farm, v.name AS variety, l.name AS length, c.code AS currency, fp.buying_price,
                                 COALESCE(fp.selling_price, CASE WHEN sp.currency_id = fp.currency_id THEN sp.price END) AS selling_price,
                                 ROUND((COALESCE(fp.selling_price, CASE WHEN sp.currency_id = fp.currency_id THEN sp.price END) - fp.buying_price)
                                       / NULLIF(COALESCE(fp.selling_price, CASE WHEN sp.currency_id = fp.currency_id THEN sp.price END),0) * 100, 1) AS margin
                            FROM farm_prices fp JOIN farms f ON f.id = fp.farm_id JOIN varieties v ON v.id = fp.variety_id JOIN lengths l ON l.id = fp.length_id
                            JOIN currencies c ON c.id = fp.currency_id LEFT JOIN selling_prices sp ON sp.variety_id = fp.variety_id AND sp.length_id = fp.length_id
                           WHERE fp.is_active = 1 AND (:farm_id IS NULL OR fp.farm_id = :farm_id) ORDER BY f.name, v.name, l.sort_order",
                'columns' => [['farm', 'Farm'], ['variety', 'Variety'], ['length', 'Length'], ['currency', 'Cur'], ['buying_price', 'Buying', 'money4'], ['selling_price', 'Selling', 'money4'], ['margin', 'Margin', 'pct']],
            ],

            // ---------------------------------------------------------------- FINANCE
            'profit_loss' => [
                'group' => 'Finance', 'title' => 'Profit & loss by month', 'icon' => 'bi-bar-chart-steps', 'financial' => true,
                'desc' => 'Sales and other charges less farm cost, freight space cost, other costs and expenses (base currency).', 'filters' => ['date'],
                'php' => function (array $f) {
                    $p = ['from' => $f['from'], 'to' => $f['to']];
                    $m = [];
                    $add = function (string $sql, string $col) use (&$m, $p) {
                        foreach (DB::all($sql, $p) as $r) { $m[$r['month']] = ($m[$r['month']] ?? []) + []; $m[$r['month']][$col] = (float)$r['v']; }
                    };
                    $add("SELECT DATE_FORMAT(invoice_date,'%Y-%m') month, SUM(base_total) v FROM invoices WHERE status='open' AND invoice_date BETWEEN :from AND :to GROUP BY month", 'sales');
                    $add("SELECT DATE_FORMAT(cost_date,'%Y-%m') month, SUM(selling_base) v FROM customer_other_costs WHERE cost_date BETWEEN :from AND :to GROUP BY month", 'other_income');
                    $add("SELECT DATE_FORMAT(o.departure_date,'%Y-%m') month, SUM(COALESCE(fo.farm_invoice_amount, fo.cost_amount) * fo.exchange_rate) v FROM farm_orders fo JOIN orders o ON o.id = fo.order_id WHERE fo.status='received' AND o.departure_date BETWEEN :from AND :to GROUP BY month", 'farm_cost');
                    $add("SELECT DATE_FORMAT(departure_date,'%Y-%m') month, SUM(base_cost) v FROM freight_bookings WHERE status <> 'cancelled' AND departure_date BETWEEN :from AND :to GROUP BY month", 'freight_cost');
                    $add("SELECT DATE_FORMAT(cost_date,'%Y-%m') month, SUM(buying_base) v FROM customer_other_costs WHERE cost_date BETWEEN :from AND :to GROUP BY month", 'other_cost');
                    $add("SELECT DATE_FORMAT(expense_date,'%Y-%m') month, SUM(base_amount) v FROM expenses WHERE expense_date BETWEEN :from AND :to GROUP BY month", 'expenses');
                    ksort($m);
                    $rows = [];
                    foreach ($m as $month => $v) {
                        $income = ($v['sales'] ?? 0) + ($v['other_income'] ?? 0);
                        $cost = ($v['farm_cost'] ?? 0) + ($v['freight_cost'] ?? 0) + ($v['other_cost'] ?? 0) + ($v['expenses'] ?? 0);
                        $rows[] = ['month' => $month, 'sales' => $v['sales'] ?? 0, 'other_income' => $v['other_income'] ?? 0, 'farm_cost' => $v['farm_cost'] ?? 0,
                                   'freight_cost' => $v['freight_cost'] ?? 0, 'other_cost' => $v['other_cost'] ?? 0, 'expenses' => $v['expenses'] ?? 0,
                                   'net' => round($income - $cost, 2), 'margin' => $income > 0 ? round(($income - $cost) / $income * 100, 1) : null];
                    }
                    return $rows;
                },
                'columns' => [['month', 'Month'], ['sales', 'Sales', 'money'], ['other_income', 'Other charges', 'money'], ['farm_cost', 'Farm cost', 'money'], ['freight_cost', 'Freight space', 'money'],
                              ['other_cost', 'Other costs', 'money'], ['expenses', 'Expenses', 'money'], ['net', 'Net profit', 'money'], ['margin', 'Margin', 'pct']],
                'totals' => ['sales', 'other_income', 'farm_cost', 'freight_cost', 'other_cost', 'expenses', 'net'], 'chart' => ['line', 'month', ['sales', 'net']],
            ],
            'receivables_aging' => [
                'group' => 'Finance', 'title' => 'Receivables ageing', 'icon' => 'bi-hourglass-split', 'desc' => 'What each customer owes, by age (FIFO).', 'filters' => [],
                'php' => fn(array $f) => array_map(fn($r) => ['name' => $r['name'], 'currency' => $r['currency'], 'balance' => $r['balance']] + $r['aging'] + ['last' => $r['last']],
                    StatementService::balances('customer')),
                'columns' => [['name', 'Customer'], ['currency', 'Cur'], ['balance', 'Balance', 'money'], ['current', '0–30', 'money'], ['d30', '31–60', 'money'], ['d60', '61–90', 'money'], ['d90', '91–120', 'money'], ['over90', '120+', 'money'], ['last', 'Last activity', 'date']],
            ],
            'payables_aging' => [
                'group' => 'Finance', 'title' => 'Payables (farms & agents)', 'icon' => 'bi-hourglass', 'desc' => 'What you owe each farm and shipping agent, by age.', 'filters' => [],
                'php' => function (array $f) {
                    $rows = [];
                    foreach (['farm' => 'Farm', 'agent' => 'Agent'] as $t => $label) {
                        foreach (StatementService::balances($t) as $r) $rows[] = ['type' => $label, 'name' => $r['name'], 'currency' => $r['currency'], 'balance' => $r['balance']] + $r['aging'];
                    }
                    return $rows;
                },
                'columns' => [['type', 'Type'], ['name', 'Name'], ['currency', 'Cur'], ['balance', 'Balance', 'money'], ['current', '0–30', 'money'], ['d30', '31–60', 'money'], ['d60', '61–90', 'money'], ['d90', '91–120', 'money'], ['over90', '120+', 'money']],
            ],
            'cash_flow' => [
                'group' => 'Finance', 'title' => 'Cash in vs cash out', 'icon' => 'bi-arrow-left-right', 'desc' => 'Customer receipts against farm, agent and expense payments per month (base).', 'filters' => ['date'],
                'sql' => "SELECT month, SUM(cin) AS cash_in, SUM(farms) AS farms, SUM(agents) AS agents, SUM(exp) AS expenses, SUM(cin) - SUM(farms) - SUM(agents) - SUM(exp) AS net
                            FROM (
                              SELECT DATE_FORMAT(payment_date,'%Y-%m') month, base_amount cin, 0 farms, 0 agents, 0 exp FROM customer_payments WHERE payment_date BETWEEN :from AND :to
                              UNION ALL SELECT DATE_FORMAT(payment_date,'%Y-%m'), 0, base_amount, 0, 0 FROM farm_payments WHERE payment_date BETWEEN :from AND :to
                              UNION ALL SELECT DATE_FORMAT(payment_date,'%Y-%m'), 0, 0, base_amount, 0 FROM agent_payments WHERE payment_date BETWEEN :from AND :to
                              UNION ALL SELECT DATE_FORMAT(expense_date,'%Y-%m'), 0, 0, 0, base_amount FROM expenses WHERE expense_date BETWEEN :from AND :to AND shipping_agent_id IS NULL
                            ) t GROUP BY month ORDER BY month",
                'columns' => [['month', 'Month'], ['cash_in', 'Received', 'money'], ['farms', 'Paid farms', 'money'], ['agents', 'Paid agents', 'money'], ['expenses', 'Expenses', 'money'], ['net', 'Net', 'money']],
                'totals' => ['cash_in', 'farms', 'agents', 'expenses', 'net'], 'chart' => ['bar', 'month', ['cash_in', 'farms', 'agents', 'expenses']],
            ],
            'customer_payments_register' => [
                'group' => 'Finance', 'title' => 'Customer payments', 'icon' => 'bi-cash-coin', 'desc' => 'Receipts in the period.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT p.payment_date, c.name AS customer, p.details, p.method, p.reference, cur.code AS currency, p.amount, p.exchange_rate, p.base_amount
                            FROM customer_payments p JOIN customers c ON c.id = p.customer_id JOIN currencies cur ON cur.id = p.currency_id
                           WHERE p.payment_date BETWEEN :from AND :to AND (:customer_id IS NULL OR p.customer_id = :customer_id) ORDER BY p.payment_date DESC",
                'columns' => [['payment_date', 'Date', 'date'], ['customer', 'Customer'], ['details', 'Details'], ['method', 'Method'], ['reference', 'Ref'], ['currency', 'Cur'], ['amount', 'Amount', 'money'], ['exchange_rate', 'Rate', 'rate'], ['base_amount', 'Base', 'money']],
                'totals' => ['base_amount'],
            ],
            'farm_payments_register' => [
                'group' => 'Finance', 'title' => 'Farm payments', 'icon' => 'bi-wallet2', 'desc' => 'Payments made to farms.', 'filters' => ['date', 'farm'],
                'sql' => "SELECT p.payment_date, f.name AS farm, p.details, p.method, p.reference, cur.code AS currency, p.amount, p.exchange_rate, p.base_amount
                            FROM farm_payments p JOIN farms f ON f.id = p.farm_id JOIN currencies cur ON cur.id = p.currency_id
                           WHERE p.payment_date BETWEEN :from AND :to AND (:farm_id IS NULL OR p.farm_id = :farm_id) ORDER BY p.payment_date DESC",
                'columns' => [['payment_date', 'Date', 'date'], ['farm', 'Farm'], ['details', 'Details'], ['method', 'Method'], ['reference', 'Ref'], ['currency', 'Cur'], ['amount', 'Amount', 'money'], ['exchange_rate', 'Rate', 'rate'], ['base_amount', 'Base', 'money']],
                'totals' => ['base_amount'],
            ],
            'agent_payments_register' => [
                'group' => 'Finance', 'title' => 'Agent payments', 'icon' => 'bi-truck', 'desc' => 'Payments made to shipping agents.', 'filters' => ['date', 'agent'],
                'sql' => "SELECT p.payment_date, a.name AS agent, p.details, p.method, p.reference, cur.code AS currency, p.amount, p.exchange_rate, p.base_amount
                            FROM agent_payments p JOIN shipping_agents a ON a.id = p.shipping_agent_id JOIN currencies cur ON cur.id = p.currency_id
                           WHERE p.payment_date BETWEEN :from AND :to AND (:agent_id IS NULL OR p.shipping_agent_id = :agent_id) ORDER BY p.payment_date DESC",
                'columns' => [['payment_date', 'Date', 'date'], ['agent', 'Agent'], ['details', 'Details'], ['method', 'Method'], ['reference', 'Ref'], ['currency', 'Cur'], ['amount', 'Amount', 'money'], ['exchange_rate', 'Rate', 'rate'], ['base_amount', 'Base', 'money']],
                'totals' => ['base_amount'],
            ],
            'expenses_by_type' => [
                'group' => 'Finance', 'title' => 'Expenses by type', 'icon' => 'bi-pie-chart', 'desc' => 'Where the money goes, and how much is tied to orders.', 'filters' => ['date'],
                'sql' => "SELECT et.name AS type, COUNT(*) AS entries, SUM(e.base_amount) AS total,
                                 SUM(CASE WHEN e.order_id IS NOT NULL THEN e.base_amount ELSE 0 END) AS on_orders,
                                 SUM(CASE WHEN e.order_id IS NULL AND e.freight_booking_id IS NULL THEN e.base_amount ELSE 0 END) AS overhead
                            FROM expenses e JOIN expense_types et ON et.id = e.expense_type_id
                           WHERE e.expense_date BETWEEN :from AND :to GROUP BY et.id, et.name ORDER BY total DESC",
                'columns' => [['type', 'Type'], ['entries', 'Entries', 'int'], ['total', 'Total', 'money'], ['on_orders', 'On orders', 'money'], ['overhead', 'Overhead', 'money']],
                'totals' => ['entries', 'total', 'on_orders', 'overhead'], 'chart' => ['doughnut', 'type', ['total']],
            ],
            'expenses_register' => [
                'group' => 'Finance', 'title' => 'Expenses register', 'icon' => 'bi-credit-card-2-back', 'desc' => 'Every expense with its link to orders / bookings.', 'filters' => ['date'],
                'sql' => "SELECT e.expense_date, et.name AS type, e.description, e.payee, o.order_no, fb.booking_no, cur.code AS currency, e.amount, e.base_amount
                            FROM expenses e JOIN expense_types et ON et.id = e.expense_type_id JOIN currencies cur ON cur.id = e.currency_id
                            LEFT JOIN orders o ON o.id = e.order_id LEFT JOIN freight_bookings fb ON fb.id = e.freight_booking_id
                           WHERE e.expense_date BETWEEN :from AND :to ORDER BY e.expense_date DESC",
                'columns' => [['expense_date', 'Date', 'date'], ['type', 'Type'], ['description', 'Description'], ['payee', 'Paid to'], ['order_no', 'Order'], ['booking_no', 'Booking'], ['currency', 'Cur'], ['amount', 'Amount', 'money'], ['base_amount', 'Base', 'money']],
                'totals' => ['base_amount'],
            ],
            'other_costs_margin' => [
                'group' => 'Finance', 'title' => 'Other costs: charged vs paid', 'icon' => 'bi-plus-slash-minus', 'desc' => 'Data loggers, freight, fumigation… what you charged vs what they cost.', 'filters' => ['date', 'customer'],
                'sql' => "SELECT coc.details, COUNT(*) AS entries, SUM(coc.selling_base) AS charged, SUM(coc.buying_base) AS cost, SUM(coc.selling_base - coc.buying_base) AS margin
                            FROM customer_other_costs coc WHERE coc.cost_date BETWEEN :from AND :to AND (:customer_id IS NULL OR coc.customer_id = :customer_id)
                           GROUP BY coc.details ORDER BY charged DESC",
                'columns' => [['details', 'Details'], ['entries', 'Entries', 'int'], ['charged', 'Charged', 'money'], ['cost', 'Cost', 'money'], ['margin', 'Margin', 'money']],
                'totals' => ['entries', 'charged', 'cost', 'margin'],
            ],

            // ---------------------------------------------------------------- FREIGHT
            'freight_utilization' => [
                'group' => 'Freight space', 'title' => 'Booking utilisation & margin', 'icon' => 'bi-speedometer2', 'desc' => 'How full each booking flew and what it earned.', 'filters' => ['date', 'agent'],
                'sql' => "SELECT b.booking_no, b.departure_date, CONCAT(b.origin, '→', b.destination) AS route, a.name AS agent, b.capacity_kg,
                                 COALESCE(SUM(fa.chargeable_kg),0) AS sold_kg, ROUND(COALESCE(SUM(fa.chargeable_kg),0) / b.capacity_kg * 100, 1) AS utilization,
                                 b.base_cost AS cost, COALESCE(SUM(fa.base_amount),0) AS sales, COALESCE(SUM(fa.base_amount),0) - b.base_cost AS margin
                            FROM freight_bookings b JOIN shipping_agents a ON a.id = b.shipping_agent_id LEFT JOIN freight_allocations fa ON fa.booking_id = b.id
                           WHERE b.status <> 'cancelled' AND b.departure_date BETWEEN :from AND :to AND (:agent_id IS NULL OR b.shipping_agent_id = :agent_id)
                           GROUP BY b.id, b.booking_no, b.departure_date, b.origin, b.destination, a.name, b.capacity_kg, b.base_cost ORDER BY b.departure_date DESC",
                'columns' => [['booking_no', 'Booking'], ['departure_date', 'Departure', 'date'], ['route', 'Route'], ['agent', 'Agent'], ['capacity_kg', 'Capacity kg', 'qty'], ['sold_kg', 'Sold kg', 'qty'],
                              ['utilization', 'Utilisation', 'pct'], ['cost', 'Cost', 'money'], ['sales', 'Sales', 'money'], ['margin', 'Margin', 'money']],
                'totals' => ['capacity_kg', 'sold_kg', 'cost', 'sales', 'margin'], 'chart' => ['bar', 'booking_no', ['cost', 'sales'], 20],
            ],
            'freight_by_customer' => [
                'group' => 'Freight space', 'title' => 'Freight customers', 'icon' => 'bi-people', 'desc' => 'Who buys your cargo space.', 'filters' => ['date'],
                'sql' => "SELECT c.name AS customer, COUNT(DISTINCT fa.booking_id) AS bookings, SUM(fa.boxes) AS boxes, SUM(fa.chargeable_kg) AS kg,
                                 SUM(fa.base_amount) AS sales, ROUND(SUM(fa.base_amount) / NULLIF(SUM(fa.chargeable_kg),0), 2) AS per_kg
                            FROM freight_allocations fa JOIN freight_bookings b ON b.id = fa.booking_id JOIN customers c ON c.id = fa.customer_id
                           WHERE b.departure_date BETWEEN :from AND :to GROUP BY c.id, c.name ORDER BY sales DESC",
                'columns' => [['customer', 'Customer'], ['bookings', 'Flights', 'int'], ['boxes', 'Boxes', 'int'], ['kg', 'Chargeable kg', 'qty'], ['sales', 'Sales', 'money'], ['per_kg', 'Avg / kg', 'money']],
                'totals' => ['boxes', 'kg', 'sales'],
            ],

            // ---------------------------------------------------------------- SYSTEM
            'fx_history' => [
                'group' => 'System', 'title' => 'Exchange rate history', 'icon' => 'bi-currency-exchange', 'desc' => 'Every rate set and when.', 'filters' => ['date'],
                'sql' => "SELECT er.effective_date, c.code AS currency, er.rate, er.notes, u.full_name AS set_by
                            FROM exchange_rates er JOIN currencies c ON c.id = er.currency_id LEFT JOIN users u ON u.id = er.created_by
                           WHERE c.is_base = 0 AND er.effective_date BETWEEN :from AND :to ORDER BY er.effective_date DESC",
                'columns' => [['effective_date', 'From', 'date'], ['currency', 'Currency'], ['rate', 'Rate', 'rate'], ['notes', 'Notes'], ['set_by', 'Set by']],
                'chart' => ['line', 'effective_date', ['rate']],
            ],
            'user_activity' => [
                'group' => 'System', 'title' => 'User activity', 'icon' => 'bi-person-lines-fill', 'desc' => 'Who did how much, and when they were last active.', 'filters' => ['date'],
                'sql' => "SELECT u.full_name AS user, r.name AS role, COUNT(al.id) AS actions,
                                 SUM(al.action = 'create') AS created, SUM(al.action LIKE 'stage:%') AS stage_moves, MAX(al.created_at) AS last_action, u.last_login_at
                            FROM users u JOIN roles r ON r.id = u.role_id
                            LEFT JOIN activity_log al ON al.user_id = u.id AND DATE(al.created_at) BETWEEN :from AND :to
                           GROUP BY u.id, u.full_name, r.name, u.last_login_at ORDER BY actions DESC",
                'columns' => [['user', 'User'], ['role', 'Role'], ['actions', 'Actions', 'int'], ['created', 'Records created', 'int'], ['stage_moves', 'Order steps', 'int'], ['last_action', 'Last action', 'date'], ['last_login_at', 'Last sign-in', 'date']],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] + ['key' => $key] : null;
    }
}
