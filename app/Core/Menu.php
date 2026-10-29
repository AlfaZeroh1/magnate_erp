<?php
declare(strict_types=1);

namespace App\Core;

/** Sidebar navigation. Items are hidden when the user lacks the view right. */
final class Menu
{
    public static function items(): array
    {
        return [
            ['', [
                ['dashboard', 'Dashboard', 'bi-grid-1x2', 'dashboard.view'],
            ]],
            ['Operations', [
                ['orders',        'Orders',          'bi-flower1',        'orders.view'],
                ['packing_lists', 'Packing Lists',   'bi-box-seam',       'packing.view'],
                ['invoices',      'Invoices',        'bi-receipt',        'invoices.view'],
                ['farm_records',  'Farm Records',    'bi-journal-text',   'farm_records.view'],
                ['freight',       'Freight Space',   'bi-airplane',       'freight.view'],
            ]],
            ['Finance', [
                ['customer_payments', 'Customer Payments', 'bi-cash-coin',        'customer_payments.view'],
                ['farm_payments',     'Farm Payments',     'bi-wallet2',          'farm_payments.view'],
                ['agent_payments',    'Agent Payments',    'bi-truck',            'agent_payments.view'],
                ['other_sales',       'Other Sales',       'bi-bag-plus',         'other_sales.view'],
                ['other_costs',       'Other Costs',       'bi-plus-slash-minus', 'other_costs.view'],
                ['expenses',          'Expenses',          'bi-credit-card-2-back','expenses.view'],
                ['statements',        'Statements',        'bi-file-earmark-ruled','statements.view'],
            ]],
            ['Insights', [
                ['reports', 'Reports', 'bi-bar-chart-line', 'reports.view'],
            ]],
            ['Pricing', [
                ['farm_prices',    'Farm Prices',    'bi-tags',            'farm_prices.view'],
                ['selling_prices', 'Selling Prices', 'bi-currency-dollar', 'selling_prices.view'],
                ['exchange_rates', 'Exchange Rates', 'bi-currency-exchange','exchange_rates.view'],
            ]],
            ['Master Data', [
                ['customers',       'Customers',       'bi-people',         'customers.view'],
                ['farms',           'Farms',           'bi-tree',           'farms.view'],
                ['shipping_agents', 'Shipping Agents', 'bi-send-check',     'shipping_agents.view'],
                ['varieties',       'Varieties',       'bi-flower2',        'varieties.view'],
                ['setup',           'Setup Lists',     'bi-sliders',        'setup.view'],
            ]],
            ['Administration', [
                ['users',    'Users',          'bi-person-gear',   'users.view'],
                ['roles',    'Roles & Rights', 'bi-shield-lock',   'roles.view'],
                ['settings', 'Company Settings','bi-building-gear','settings.view'],
                ['activity', 'Activity Log',   'bi-clock-history', 'activity.view'],
            ]],
        ];
    }

    /** Menu filtered to what the current user may see. */
    public static function visible(): array
    {
        $out = [];
        foreach (self::items() as [$group, $items]) {
            $items = array_values(array_filter($items, fn($i) => Auth::can($i[3])));
            if ($items) $out[] = [$group, $items];
        }
        return $out;
    }
}
