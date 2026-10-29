<?php
declare(strict_types=1);

/**
 * Document renderer.  print.php?doc=<name>&id=<id>
 * Each document is an A4 HTML page designed for "Print → Save as PDF".
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;

if (!Auth::check()) { header('Location: login.php'); exit; }

$docs = [
    'customer_order'  => 'orders.view',
    'farm_order'      => 'orders.view',
    'confirmed_order' => 'orders.view',
    'delivery_note'   => 'orders.view',
    'packing_list'    => 'packing.view',
    'invoice'         => null,           // checked inside (invoices / other sales / freight)
    'receipt'         => 'customer_payments.view',
    'statement'       => 'statements.view',
    'journey'         => 'reports.view',
    'report'          => 'reports.view',
];
$doc = preg_replace('/[^a-z_]/', '', (string)($_GET['doc'] ?? ''));
$id = (int)($_GET['id'] ?? 0);

try {
    if (!array_key_exists($doc, $docs)) throw new HttpException('Unknown document.', 404);
    if ($docs[$doc]) Auth::require($docs[$doc]);
    if ($doc === 'invoice' && !Auth::canAny(['invoices.view', 'other_sales.view', 'freight.view', 'orders.view'])) throw new HttpException('No access.', 403);

    $company = [
        'name' => setting('company_name', 'Magnate Flowers Limited'), 'tagline' => setting('company_tagline'),
        'address' => setting('company_address'), 'email' => setting('company_email'), 'phone' => setting('company_phone'),
        'website' => setting('company_website'), 'pin' => setting('company_pin'),
    ];
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)setting('doc_accent')) ? setting('doc_accent') : '#BE185D';
    $banks = DB::all('SELECT b.*, c.code AS currency FROM bank_accounts b LEFT JOIN currencies c ON c.id = b.currency_id WHERE b.is_active = 1 AND b.show_on_invoice = 1 ORDER BY b.sort_order, b.bank_name');

    // Each doc template fills: $docTitle, $docNo, $docDate, $docMeta (array label=>value), and echoes its body.
    $docTitle = ''; $docNo = ''; $docDate = ''; $docBadge = ''; $showBanks = false; $signatures = []; $landscape = false;
    require_once __DIR__ . '/docs/_helpers.php';
    ob_start();
    require __DIR__ . '/docs/' . $doc . '.php';
    $body = ob_get_clean();
    require __DIR__ . '/docs/_layout.php';
} catch (HttpException $e) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($e->getCode() ?: 400);
    echo '<!doctype html><meta charset="utf-8"><title>Document</title><body style="font-family:system-ui;padding:40px;color:#334155"><h2>Cannot show this document</h2><p>'
        . e($e->getMessage()) . '</p><p><a href="javascript:window.close()">Close</a></p></body>';
}
