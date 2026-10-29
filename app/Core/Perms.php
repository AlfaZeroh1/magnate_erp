<?php
declare(strict_types=1);

namespace App\Core;

/**
 * The single registry of every right in the system.
 * The Rights page renders this as a matrix; the API checks against it.
 */
final class Perms
{
    public const LABELS = [
        'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
        'confirm' => 'Farm confirm', 'receive' => 'Delivery check', 'pack' => 'Packing',
        'invoice' => 'Invoice', 'reopen' => 'Re-open / cancel', 'void' => 'Void',
        'financial' => 'Financial figures',
    ];

    public static function registry(): array
    {
        $crud = ['view', 'create', 'edit', 'delete'];
        return [
            'Operations' => [
                'dashboard'      => ['Dashboard', ['view']],
                'orders'         => ['Orders', ['view', 'create', 'edit', 'delete', 'confirm', 'receive', 'pack', 'invoice', 'reopen']],
                'packing'        => ['Packing Lists', ['view', 'edit']],
                'invoices'       => ['Invoices', ['view', 'void']],
                'farm_records'   => ['Farm Records', ['view', 'edit']],
                'freight'        => ['Freight Space', ['view', 'create', 'edit', 'delete', 'invoice']],
            ],
            'Finance' => [
                'customer_payments' => ['Customer Payments', $crud],
                'farm_payments'     => ['Farm Payments', $crud],
                'agent_payments'    => ['Agent Payments', $crud],
                'other_sales'       => ['Customer Other Sales', $crud],
                'other_costs'       => ['Customer Other Costs', $crud],
                'expenses'          => ['Expenses', $crud],
                'statements'        => ['Statements', ['view']],
                'reports'           => ['Reports', ['view', 'financial']],
            ],
            'Pricing' => [
                'farm_prices'    => ['Farm Prices', ['view', 'edit']],
                'selling_prices' => ['Selling Prices', ['view', 'edit']],
                'exchange_rates' => ['Exchange Rates', ['view', 'edit']],
            ],
            'Master Data' => [
                'customers'       => ['Customers', $crud],
                'farms'           => ['Farms', $crud],
                'shipping_agents' => ['Shipping Agents', $crud],
                'varieties'       => ['Varieties', $crud],
                'setup'           => ['Setup lists (lengths, box types, currencies...)', ['view', 'edit']],
            ],
            'Administration' => [
                'users'    => ['Users', $crud],
                'roles'    => ['Roles & Rights', ['view', 'edit']],
                'settings' => ['Company Settings', ['view', 'edit']],
                'activity' => ['Activity Log', ['view']],
            ],
        ];
    }

    /** Flat list of every valid permission key. */
    public static function all(): array
    {
        $out = [];
        foreach (self::registry() as $modules) {
            foreach ($modules as $key => [$label, $actions]) {
                foreach ($actions as $a) $out[] = "$key.$a";
            }
        }
        return $out;
    }

    public static function isValid(string $perm): bool
    {
        return in_array($perm, self::all(), true);
    }
}
