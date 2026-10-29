<?php
declare(strict_types=1);

/**
 * Page front controller.   index.php?p=<page>
 * Pages are full page loads (bookmarkable, back-button friendly); data comes from api.php.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Resources;

if (!Auth::check()) {
    header('Location: login.php' . (isset($_GET['p']) ? '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '') : ''));
    exit;
}

$user = Auth::user();
if ((int)$user['must_change_password'] === 1 && ($_GET['p'] ?? '') !== 'profile') {
    header('Location: index.php?p=profile&force=1');
    exit;
}

// page => [file, permission, title, icon]
$pages = [
    'dashboard'      => ['dashboard.php',      'dashboard.view',      'Dashboard',          'bi-grid-1x2'],
    'orders'         => ['orders.php',         'orders.view',         'Orders',             'bi-flower1'],
    'order'          => ['order.php',          'orders.view',         'Order',              'bi-flower1'],
    'packing_lists'  => ['packing_lists.php',  'packing.view',        'Packing Lists',      'bi-box-seam'],
    'invoices'       => ['invoices.php',       'invoices.view',       'Invoices',           'bi-receipt'],
    'farm_records'   => ['farm_records.php',   'farm_records.view',   'Farm Records',       'bi-journal-text'],
    'freight'        => ['freight.php',        'freight.view',        'Freight Space',      'bi-airplane'],
    'freight_booking'=> ['freight_booking.php','freight.view',        'Freight Booking',    'bi-airplane'],
    'statements'     => ['statements.php',     'statements.view',     'Statements',         'bi-file-earmark-ruled'],
    'reports'        => ['reports.php',        'reports.view',        'Reports',            'bi-bar-chart-line'],
    'report'         => ['report.php',         'reports.view',        'Report',             'bi-bar-chart-line'],
    'order_journey'  => ['order_journey.php',  'reports.view',        'Order Journey',      'bi-signpost-split'],
    'farm_prices'    => ['farm_prices.php',    'farm_prices.view',    'Farm Prices',        'bi-tags'],
    'selling_prices' => ['selling_prices.php', 'selling_prices.view', 'Selling Prices',     'bi-currency-dollar'],
    'exchange_rates' => ['exchange_rates.php', 'exchange_rates.view', 'Exchange Rates',     'bi-currency-exchange'],
    'setup'          => ['setup.php',          'setup.view',          'Setup Lists',        'bi-sliders'],
    'users'          => ['users.php',          'users.view',          'Users',              'bi-person-gear'],
    'roles'          => ['roles.php',          'roles.view',          'Roles & Rights',     'bi-shield-lock'],
    'settings'       => ['settings.php',       'settings.view',       'Company Settings',   'bi-building-gear'],
    'activity'       => ['activity.php',       'activity.view',       'Activity Log',       'bi-clock-history'],
    'profile'        => ['profile.php',        null,                  'My Profile',         'bi-person-circle'],
];
// Generic CRUD pages driven by Resources.php
foreach (['customers', 'farms', 'shipping_agents', 'varieties', 'customer_payments', 'farm_payments', 'agent_payments', 'other_sales', 'other_costs', 'expenses'] as $res) {
    $def = Resources::get($res);
    $pages[$res] = ['resource.php', $def['perm']['view'], $def['title'], $def['icon']];
}

$p = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard'));
if (!isset($pages[$p])) $p = 'dashboard';
[$file, $perm, $pageTitle, $pageIcon] = $pages[$p];

// Users without dashboard rights land on the first page they can open.
if ($perm && !Auth::can($perm)) {
    if ($p === 'dashboard') {
        foreach (App\Core\Menu::visible() as [, $items]) { header('Location: index.php?p=' . $items[0][0]); exit; }
        $file = '403.php';
    } else {
        $file = '403.php';
    }
}
$resourceName = $file === 'resource.php' ? $p : null;

ob_start();
require __DIR__ . '/pages/' . $file;
$content = ob_get_clean();

require __DIR__ . '/pages/_layout.php';
