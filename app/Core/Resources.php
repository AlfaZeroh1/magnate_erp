<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Declarative CRUD definitions.
 *
 * One definition here = a full list page + add/edit modal + validation + audit log,
 * served by ResourceApi and rendered by assets/js/resource.js. This replaces ~25
 * near-identical control/model/view files from v1.
 *
 * Field keys:
 *   type      text|textarea|email|tel|number|money|rate|date|select|switch|combo|static
 *   source    options source key (see sources()) for select fields
 *   fills     [targetField => optionExtraKey] auto-fill other fields when a select changes
 *   fxOf      for money fields: show "≈ KES" hint using this resource's fx block
 *   list      false = hide from table; listOnly = only in table
 */
final class Resources
{
    public static function sources(): array
    {
        return [
            'customers'       => ['sql' => "SELECT c.id, c.name AS text, c.code, c.currency_id, c.drop_point_id FROM customers c WHERE c.is_active = 1", 'search' => ['c.name', 'c.code'], 'order' => 'c.name'],
            'farms'           => ['sql' => "SELECT f.id, f.name AS text, f.code, f.currency_id FROM farms f WHERE f.is_active = 1", 'search' => ['f.name', 'f.code'], 'order' => 'f.name'],
            'shipping_agents' => ['sql' => "SELECT a.id, a.name AS text, a.currency_id FROM shipping_agents a WHERE a.is_active = 1", 'search' => ['a.name'], 'order' => 'a.name'],
            'varieties'       => ['sql' => "SELECT v.id, v.name AS text, pc.name AS category FROM varieties v LEFT JOIN product_categories pc ON pc.id = v.category_id WHERE v.is_active = 1", 'search' => ['v.name', 'pc.name'], 'order' => 'v.name'],
            'lengths'         => ['sql' => "SELECT l.id, l.name AS text FROM lengths l WHERE l.is_active = 1", 'search' => ['l.name'], 'order' => 'l.sort_order, l.name'],
            'order_by'        => ['sql' => "SELECT o.id, o.name AS text FROM order_by o WHERE o.is_active = 1", 'search' => ['o.name'], 'order' => 'o.name'],
            'box_types'       => ['sql' => "SELECT b.id, b.name AS text FROM box_types b WHERE b.is_active = 1", 'search' => ['b.name'], 'order' => 'b.name'],
            'drop_points'     => ['sql' => "SELECT d.id, d.name AS text FROM drop_points d WHERE d.is_active = 1", 'search' => ['d.name'], 'order' => 'd.name'],
            'countries'       => ['sql' => "SELECT c.id, c.name AS text FROM countries c WHERE c.is_active = 1", 'search' => ['c.name'], 'order' => 'c.name'],
            'categories'      => ['sql' => "SELECT c.id, c.name AS text FROM product_categories c WHERE c.is_active = 1", 'search' => ['c.name'], 'order' => 'c.name'],
            'currencies'      => ['sql' => "SELECT c.id, CONCAT(c.code, ' — ', c.name) AS text, c.code FROM currencies c WHERE c.is_active = 1", 'search' => ['c.code', 'c.name'], 'order' => 'c.is_base DESC, c.code'],
            'expense_types'   => ['sql' => "SELECT t.id, t.name AS text FROM expense_types t WHERE t.is_active = 1", 'search' => ['t.name'], 'order' => 't.name'],
            'roles'           => ['sql' => "SELECT r.id, r.name AS text FROM roles r WHERE 1=1", 'search' => ['r.name'], 'order' => 'r.name'],
            'orders'          => ['sql' => "SELECT o.id, CONCAT(o.order_no, ' · ', c.name, ' · ', DATE_FORMAT(o.departure_date, '%d %b %Y')) AS text, o.customer_id FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.stage <> 'cancelled'", 'search' => ['o.order_no', 'c.name'], 'order' => 'o.id DESC'],
            'invoices'        => ['sql' => "SELECT i.id, CONCAT(i.invoice_no, ' · ', c.name) AS text, i.bill_to_customer_id AS customer_id, i.currency_id FROM invoices i JOIN customers c ON c.id = i.bill_to_customer_id WHERE i.status = 'open'", 'search' => ['i.invoice_no', 'c.name'], 'order' => 'i.id DESC'],
            'freight_bookings'=> ['sql' => "SELECT b.id, CONCAT(b.booking_no, ' · ', b.destination, ' · ', DATE_FORMAT(b.departure_date, '%d %b')) AS text, b.shipping_agent_id FROM freight_bookings b WHERE b.status <> 'cancelled'", 'search' => ['b.booking_no', 'b.destination', 'b.awb_no'], 'order' => 'b.id DESC'],
        ];
    }

    /** Payment-style FX block reused by finance resources. */
    private static function fx(string $dateField, array $amounts): array
    {
        return ['currency' => 'currency_id', 'rate' => 'exchange_rate', 'date' => $dateField, 'amounts' => $amounts];
    }

    private static function lookup(string $title, string $table, string $icon, array $extra = []): array
    {
        return [
            'title' => $title, 'singular' => rtrim($title, 's'), 'table' => $table, 'icon' => $icon,
            'perm' => ['view' => 'setup.view', 'create' => 'setup.edit', 'edit' => 'setup.edit', 'delete' => 'setup.edit'],
            'fields' => array_merge(
                ['name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true]],
                $extra,
                ['is_active' => ['label' => 'Active', 'type' => 'switch', 'default' => 1]]
            ),
            'order' => 't.name',
        ];
    }

    public static function all(): array
    {
        $statusField = ['is_active' => ['label' => 'Active', 'type' => 'switch', 'default' => 1, 'col' => 12]];
        $terms = ['CIF', 'FOB', 'CPT', 'CFR', 'DAP', 'DDP', 'EXW', 'FCA'];
        $methods = ['Bank Transfer', 'SWIFT', 'M-Pesa', 'Cash', 'Cheque', 'Card', 'Offset'];

        return [
            // ---------------- MASTER DATA ----------------
            'customers' => [
                'title' => 'Customers', 'singular' => 'Customer', 'table' => 'customers', 'icon' => 'bi-people', 'perm' => 'customers',
                'fields' => [
                    'code'           => ['label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 4],
                    'name'           => ['label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 8],
                    'contact_person' => ['label' => 'Contact person', 'type' => 'text', 'list' => false],
                    'email'          => ['label' => 'Email', 'type' => 'email'],
                    'phone'          => ['label' => 'Phone', 'type' => 'tel'],
                    'country_id'     => ['label' => 'Country', 'type' => 'select', 'source' => 'countries'],
                    'drop_point_id'  => ['label' => 'Default drop point', 'type' => 'select', 'source' => 'drop_points'],
                    'truck'          => ['label' => 'Truck', 'type' => 'text', 'list' => false],
                    'currency_id'    => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true],
                    'terms'          => ['label' => 'Incoterm', 'type' => 'combo', 'options' => $terms, 'col' => 3],
                    'credit_days'    => ['label' => 'Credit days', 'type' => 'number', 'default' => 0, 'col' => 3],
                    'address'        => ['label' => 'Address', 'type' => 'textarea', 'col' => 12, 'list' => false],
                ] + $statusField,
                'order' => 't.name',
                'statement' => 'customer',
            ],
            'farms' => [
                'title' => 'Farms', 'singular' => 'Farm', 'table' => 'farms', 'icon' => 'bi-tree', 'perm' => 'farms',
                'fields' => [
                    'code'               => ['label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 4],
                    'name'               => ['label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 8],
                    'contact_person'     => ['label' => 'Contact person', 'type' => 'text'],
                    'phone'              => ['label' => 'Phone', 'type' => 'tel'],
                    'email'              => ['label' => 'Email', 'type' => 'email'],
                    'currency_id'        => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true],
                    'payment_terms_days' => ['label' => 'Payment terms (days)', 'type' => 'number', 'default' => 30, 'list' => false],
                    'address'            => ['label' => 'Address', 'type' => 'text', 'list' => false],
                    'notes'              => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12, 'list' => false],
                ] + $statusField,
                'order' => 't.name',
                'statement' => 'farm',
            ],
            'shipping_agents' => [
                'title' => 'Shipping Agents', 'singular' => 'Shipping Agent', 'table' => 'shipping_agents', 'icon' => 'bi-send-check', 'perm' => 'shipping_agents',
                'fields' => [
                    'name'           => ['label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 12],
                    'contact_person' => ['label' => 'Contact person', 'type' => 'text'],
                    'phone'          => ['label' => 'Phone', 'type' => 'tel'],
                    'email'          => ['label' => 'Email', 'type' => 'email'],
                    'currency_id'    => ['label' => 'Billing currency', 'type' => 'select', 'source' => 'currencies'],
                ] + $statusField,
                'order' => 't.name',
                'statement' => 'agent',
            ],
            'varieties' => [
                'title' => 'Varieties', 'singular' => 'Variety', 'table' => 'varieties', 'icon' => 'bi-flower2', 'perm' => 'varieties',
                'fields' => [
                    'name'        => ['label' => 'Variety', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 12],
                    'category_id' => ['label' => 'Category / group', 'type' => 'select', 'source' => 'categories'],
                    'colour'      => ['label' => 'Colour', 'type' => 'text'],
                ] + $statusField,
                'order' => 't.name',
            ],

            // ---------------- SETUP LISTS ----------------
            'countries'          => self::lookup('Countries', 'countries', 'bi-globe2', ['iso2' => ['label' => 'ISO code', 'type' => 'text', 'col' => 4]]),
            'drop_points'        => self::lookup('Drop Points', 'drop_points', 'bi-geo-alt', ['address' => ['label' => 'Address', 'type' => 'text']]),
            'product_categories' => self::lookup('Categories', 'product_categories', 'bi-collection'),
            'box_types'          => self::lookup('Box Types', 'box_types', 'bi-box', [
                'length_cm' => ['label' => 'Length (cm)', 'type' => 'number', 'col' => 4],
                'width_cm'  => ['label' => 'Width (cm)', 'type' => 'number', 'col' => 4],
                'height_cm' => ['label' => 'Height (cm)', 'type' => 'number', 'col' => 4],
            ]),
            'lengths'       => array_merge(self::lookup('Lengths', 'lengths', 'bi-rulers', ['sort_order' => ['label' => 'Sort order', 'type' => 'number', 'default' => 0, 'col' => 4]]), ['order' => 't.sort_order, t.name']),
            'order_by'      => self::lookup('Order By (marks)', 'order_by', 'bi-bookmark'),
            'expense_types' => self::lookup('Expense Types', 'expense_types', 'bi-tags'),
            'currencies'    => [
                'title' => 'Currencies', 'singular' => 'Currency', 'table' => 'currencies', 'icon' => 'bi-coin',
                'perm' => ['view' => 'setup.view', 'create' => 'setup.edit', 'edit' => 'setup.edit', 'delete' => 'setup.edit'],
                'fields' => [
                    'code'    => ['label' => 'ISO code', 'type' => 'text', 'required' => true, 'unique' => true, 'col' => 4, 'maxlength' => 3, 'upper' => true],
                    'name'    => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 8],
                    'symbol'  => ['label' => 'Symbol', 'type' => 'text', 'col' => 4],
                    'is_base' => ['label' => 'Base', 'type' => 'static', 'format' => 'yesno', 'form' => false],
                    'is_active' => ['label' => 'Active', 'type' => 'switch', 'default' => 1, 'col' => 8],
                ],
                'order' => 't.is_base DESC, t.code',
                'protect' => fn(array $row) => (int)$row['is_base'] === 1 ? 'The base currency cannot be removed.' : null,
            ],
            'bank_accounts' => [
                'title' => 'Bank Accounts', 'singular' => 'Bank Account', 'table' => 'bank_accounts', 'icon' => 'bi-bank',
                'perm' => ['view' => 'settings.view', 'create' => 'settings.edit', 'edit' => 'settings.edit', 'delete' => 'settings.edit'],
                'fields' => [
                    'bank_name'       => ['label' => 'Bank', 'type' => 'text', 'required' => true],
                    'branch'          => ['label' => 'Branch', 'type' => 'text'],
                    'account_name'    => ['label' => 'Account name', 'type' => 'text', 'required' => true],
                    'account_number'  => ['label' => 'Account number', 'type' => 'text', 'required' => true],
                    'swift_code'      => ['label' => 'SWIFT', 'type' => 'text'],
                    'currency_id'     => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies'],
                    'sort_order'      => ['label' => 'Order', 'type' => 'number', 'default' => 0, 'list' => false],
                    'show_on_invoice' => ['label' => 'Show on invoices', 'type' => 'switch', 'default' => 1],
                    'is_active'       => ['label' => 'Active', 'type' => 'switch', 'default' => 1],
                ],
                'order' => 't.sort_order, t.bank_name',
            ],

            // ---------------- FINANCE ----------------
            'customer_payments' => [
                'title' => 'Customer Payments', 'singular' => 'Payment', 'table' => 'customer_payments', 'icon' => 'bi-cash-coin', 'perm' => 'customer_payments',
                'fields' => [
                    'payment_date'  => ['label' => 'Payment date', 'type' => 'date', 'required' => true, 'default' => 'today'],
                    'customer_id'   => ['label' => 'Customer', 'type' => 'select', 'source' => 'customers', 'required' => true, 'fills' => ['currency_id' => 'currency_id']],
                    'details'       => ['label' => 'Details', 'type' => 'text', 'col' => 12],
                    'amount'        => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'col' => 4],
                    'currency_id'   => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true, 'col' => 4],
                    'base_amount'   => ['label' => 'KES amount', 'type' => 'money', 'role' => 'base', 'col' => 4, 'listLabel' => 'Base amount (KES)'],
                    'exchange_rate' => ['label' => 'Exchange rate', 'type' => 'rate', 'col' => 4],
                    'method'        => ['label' => 'Method', 'type' => 'combo', 'options' => $methods, 'col' => 4, 'list' => false],
                    'reference'     => ['label' => 'Reference', 'type' => 'text', 'col' => 4, 'list' => false],
                ],
                'fx' => self::fx('payment_date', ['amount' => 'base_amount']),
                'order' => 't.payment_date DESC, t.id DESC',
                'dateFilter' => 'payment_date',
                'totals' => ['base_amount'],
                'rowDocs' => ['receipt' => 'Receipt'],
            ],
            'farm_payments' => [
                'title' => 'Farm Payments', 'singular' => 'Payment', 'table' => 'farm_payments', 'icon' => 'bi-wallet2', 'perm' => 'farm_payments',
                'fields' => [
                    'payment_date'  => ['label' => 'Payment date', 'type' => 'date', 'required' => true, 'default' => 'today'],
                    'farm_id'       => ['label' => 'Farm', 'type' => 'select', 'source' => 'farms', 'required' => true, 'fills' => ['currency_id' => 'currency_id']],
                    'details'       => ['label' => 'Details', 'type' => 'text', 'col' => 12],
                    'amount'        => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'col' => 4],
                    'currency_id'   => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true, 'col' => 4],
                    'base_amount'   => ['label' => 'KES amount', 'type' => 'money', 'role' => 'base', 'col' => 4, 'listLabel' => 'Base amount (KES)'],
                    'exchange_rate' => ['label' => 'Exchange rate', 'type' => 'rate', 'col' => 4],
                    'method'        => ['label' => 'Method', 'type' => 'combo', 'options' => $methods, 'col' => 4, 'list' => false],
                    'reference'     => ['label' => 'Reference', 'type' => 'text', 'col' => 4, 'list' => false],
                ],
                'fx' => self::fx('payment_date', ['amount' => 'base_amount']),
                'order' => 't.payment_date DESC, t.id DESC',
                'dateFilter' => 'payment_date',
                'totals' => ['base_amount'],
            ],
            'agent_payments' => [
                'title' => 'Agent Payments', 'singular' => 'Payment', 'table' => 'agent_payments', 'icon' => 'bi-truck', 'perm' => 'agent_payments',
                'fields' => [
                    'payment_date'      => ['label' => 'Payment date', 'type' => 'date', 'required' => true, 'default' => 'today'],
                    'shipping_agent_id' => ['label' => 'Shipping agent', 'type' => 'select', 'source' => 'shipping_agents', 'required' => true, 'fills' => ['currency_id' => 'currency_id']],
                    'details'           => ['label' => 'Details', 'type' => 'text', 'col' => 12],
                    'amount'            => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'col' => 4],
                    'currency_id'       => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true, 'col' => 4],
                    'base_amount'       => ['label' => 'KES amount', 'type' => 'money', 'role' => 'base', 'col' => 4, 'listLabel' => 'Base amount (KES)'],
                    'exchange_rate'     => ['label' => 'Exchange rate', 'type' => 'rate', 'col' => 4],
                    'method'            => ['label' => 'Method', 'type' => 'combo', 'options' => $methods, 'col' => 4, 'list' => false],
                    'reference'         => ['label' => 'Reference', 'type' => 'text', 'col' => 4, 'list' => false],
                ],
                'fx' => self::fx('payment_date', ['amount' => 'base_amount']),
                'order' => 't.payment_date DESC, t.id DESC',
                'dateFilter' => 'payment_date',
                'totals' => ['base_amount'],
            ],
            'other_sales' => [
                'title' => 'Customer Other Sales', 'singular' => 'Other Sale', 'table' => 'invoices', 'icon' => 'bi-bag-plus', 'perm' => 'other_sales',
                'where' => "t.kind = 'other_sale'",
                'fields' => [
                    'invoice_date'        => ['label' => 'Date', 'type' => 'date', 'required' => true, 'default' => 'today'],
                    'bill_to_customer_id' => ['label' => 'Bill to', 'type' => 'select', 'source' => 'customers', 'required' => true, 'fills' => ['currency_id' => 'currency_id', 'customer_id' => 'id']],
                    'customer_id'         => ['label' => 'Customer', 'type' => 'select', 'source' => 'customers', 'required' => true],
                    'invoice_no'          => ['label' => 'Invoice no', 'type' => 'static', 'placeholder' => 'Auto (next number)', 'peek' => 'invoice', 'help' => 'Next number in the shared invoice series — always unique'],
                    'shipment_details'    => ['label' => 'Shipment details', 'type' => 'text', 'col' => 12],
                    's_number'            => ['label' => 'S number', 'type' => 'text', 'col' => 4],
                    'boxes'               => ['label' => 'Boxes', 'type' => 'number', 'col' => 4],
                    'currency_id'         => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true, 'col' => 4],
                    'total'               => ['label' => 'Price', 'type' => 'money', 'required' => true, 'col' => 4],
                    'exchange_rate'       => ['label' => 'Exchange rate', 'type' => 'rate', 'col' => 4, 'list' => false],
                    'base_total'          => ['label' => 'KES amount', 'type' => 'money', 'role' => 'base', 'col' => 4, 'list' => false],
                    'notes'               => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12, 'list' => false],
                ],
                'fx' => self::fx('invoice_date', ['total' => 'base_total']),
                'listOrder' => ['invoice_date', 'bill_to_customer_id', 'invoice_no', 'shipment_details', 's_number', 'boxes', 'currency_id', 'total'],
                'order' => 't.invoice_date DESC, t.id DESC',
                'dateFilter' => 'invoice_date',
                'totals' => ['total'],
                'rowDocs' => ['invoice' => 'Print'],
                'beforeSave' => function (array $data, ?array $existing): array {
                    if (!$existing) {
                        $data['kind'] = 'other_sale';
                        $data['invoice_no'] = Seq::next('invoice', $data['invoice_date']);
                        $data['created_by'] = Auth::id();
                        $data['status'] = 'open';
                    } else {
                        unset($data['invoice_no']);
                    }
                    $cust = DB::one('SELECT credit_days FROM customers WHERE id = :id', ['id' => $data['bill_to_customer_id']]);
                    $data['due_date'] = date('Y-m-d', strtotime($data['invoice_date'] . ' +' . (int)($cust['credit_days'] ?? 0) . ' days'));
                    return $data;
                },
                'afterSave' => function (int $id, array $data): void {
                    // Keep a single printable line in sync for the invoice document.
                    DB::delete('invoice_lines', 'invoice_id = :id', ['id' => $id]);
                    DB::insert('invoice_lines', [
                        'invoice_id' => $id, 'description' => $data['shipment_details'] ?: 'Sale',
                        'quantity' => $data['boxes'] ?: 1, 'unit' => $data['boxes'] ? 'boxes' : 'lot',
                        'boxes' => $data['boxes'], 'unit_price' => $data['boxes'] ? round($data['total'] / max(1, (float)$data['boxes']), 4) : $data['total'],
                        'amount' => $data['total'],
                    ]);
                },
            ],
            'other_costs' => [
                'title' => 'Customer Other Costs', 'singular' => 'Other Cost', 'table' => 'customer_other_costs', 'icon' => 'bi-plus-slash-minus', 'perm' => 'other_costs',
                'fields' => [
                    'cost_date'     => ['label' => 'Date', 'type' => 'date', 'required' => true, 'default' => 'today'],
                    'invoice_id'    => ['label' => 'Invoice number', 'type' => 'select', 'source' => 'invoices', 'required' => true, 'fills' => ['customer_id' => 'customer_id', 'currency_id' => 'currency_id']],
                    'customer_id'   => ['label' => 'Customer', 'type' => 'select', 'source' => 'customers', 'readonly' => true, 'help' => 'Filled from the invoice'],
                    'details'       => ['label' => 'Details', 'type' => 'combo', 'options' => ['Data Logger', 'Freight', 'Fumigation', 'Other'], 'required' => true],
                    'currency_id'   => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true, 'col' => 6],
                    'exchange_rate' => ['label' => 'Exchange rate', 'type' => 'rate', 'col' => 6],
                    'selling_price' => ['label' => 'Selling price', 'type' => 'money', 'required' => true, 'col' => 6, 'listLabel' => 'Amount'],
                    'buying_price'  => ['label' => 'Buying price', 'type' => 'money', 'col' => 6],
                    'selling_base'  => ['label' => 'Base amount (KES)', 'type' => 'money', 'role' => 'base', 'form' => false],
                    'buying_base'   => ['label' => 'Cost (KES)', 'type' => 'money', 'role' => 'base', 'form' => false],
                ],
                'fx' => self::fx('cost_date', ['selling_price' => 'selling_base', 'buying_price' => 'buying_base']),
                'listOrder' => ['cost_date', 'customer_id', 'invoice_id', 'selling_price', 'details', 'currency_id', 'selling_base', 'exchange_rate', 'buying_price'],
                'order' => 't.cost_date DESC, t.id DESC',
                'dateFilter' => 'cost_date',
                'totals' => ['selling_base', 'buying_base'],
                'beforeSave' => function (array $data, ?array $existing): array {
                    $inv = DB::one('SELECT bill_to_customer_id, order_id FROM invoices WHERE id = :id', ['id' => $data['invoice_id']]);
                    if (!$inv) throw HttpException::validation(['invoice_id' => 'Invoice not found.']);
                    $data['customer_id'] = (int)$inv['bill_to_customer_id'];
                    if (!$existing) $data['created_by'] = Auth::id();
                    return $data;
                },
                'orderOf' => fn(array $data) => DB::val('SELECT order_id FROM invoices WHERE id = :id', ['id' => $data['invoice_id']]),
            ],
            'expenses' => [
                'title' => 'Expenses', 'singular' => 'Expense', 'table' => 'expenses', 'icon' => 'bi-credit-card-2-back', 'perm' => 'expenses',
                'fields' => [
                    'expense_date'       => ['label' => 'Date', 'type' => 'date', 'required' => true, 'default' => 'today'],
                    'expense_type_id'    => ['label' => 'Type', 'type' => 'select', 'source' => 'expense_types', 'required' => true],
                    'description'        => ['label' => 'Description', 'type' => 'text', 'required' => true, 'col' => 12],
                    'payee'              => ['label' => 'Paid to', 'type' => 'text'],
                    'order_id'           => ['label' => 'Linked order', 'type' => 'select', 'source' => 'orders', 'help' => 'Counts against this order\'s profit'],
                    'shipping_agent_id'  => ['label' => 'Billed by agent', 'type' => 'select', 'source' => 'shipping_agents', 'list' => false, 'help' => 'Shows on the agent statement'],
                    'freight_booking_id' => ['label' => 'Freight booking', 'type' => 'select', 'source' => 'freight_bookings', 'list' => false],
                    'amount'             => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'col' => 4],
                    'currency_id'        => ['label' => 'Currency', 'type' => 'select', 'source' => 'currencies', 'required' => true, 'col' => 4, 'default' => 'base'],
                    'exchange_rate'      => ['label' => 'Exchange rate', 'type' => 'rate', 'col' => 4],
                    'base_amount'        => ['label' => 'KES amount', 'type' => 'money', 'role' => 'base', 'col' => 4],
                    'method'             => ['label' => 'Method', 'type' => 'combo', 'options' => $methods, 'col' => 4, 'list' => false],
                    'reference'          => ['label' => 'Reference', 'type' => 'text', 'col' => 4, 'list' => false],
                ],
                'fx' => self::fx('expense_date', ['amount' => 'base_amount']),
                'order' => 't.expense_date DESC, t.id DESC',
                'dateFilter' => 'expense_date',
                'totals' => ['base_amount'],
                'beforeSave' => function (array $data, ?array $existing): array {
                    if (!$existing) $data['created_by'] = Auth::id();
                    return $data;
                },
                'orderOf' => fn(array $data) => $data['order_id'] ?? null,
            ],
        ];
    }

    public static function get(string $name): array
    {
        $all = self::all();
        if (!isset($all[$name])) throw new HttpException('Unknown resource.', 404);
        $r = $all[$name];
        $r['name'] = $name;
        if (is_string($r['perm'])) {
            $p = $r['perm'];
            $r['perm'] = ['view' => "$p.view", 'create' => "$p.create", 'edit' => "$p.edit", 'delete' => "$p.delete"];
        }
        return $r;
    }

    /** Setup lists rendered as tabs on the Setup page. */
    public static function setupTabs(): array
    {
        return ['lengths', 'box_types', 'order_by', 'product_categories', 'drop_points', 'countries', 'expense_types', 'currencies'];
    }
}
