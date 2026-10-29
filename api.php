<?php
declare(strict_types=1);

/**
 * JSON API front controller.   api.php?r=<module>.<action>
 * Response envelope: { ok: bool, data?: any, message?: string, errors?: {field: msg} }
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$modules = [
    'resource'  => App\Api\ResourceApi::class,
    'fx'        => App\Api\FxApi::class,
    'orders'    => App\Api\OrdersApi::class,
    'packing'   => App\Api\PackingApi::class,
    'invoices'  => App\Api\InvoicesApi::class,
    'farmrec'   => App\Api\FarmRecordsApi::class,
    'pricing'   => App\Api\PricingApi::class,
    'freight'   => App\Api\FreightApi::class,
    'statements'=> App\Api\StatementsApi::class,
    'reports'   => App\Api\ReportsApi::class,
    'dashboard' => App\Api\DashboardApi::class,
    'admin'     => App\Api\AdminApi::class,
    'profile'   => App\Api\ProfileApi::class,
];

$respond = function (int $status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
};

try {
    $route = (string)($_GET['r'] ?? '');
    if (!preg_match('/^([a-z]+)\.([a-z_]+)$/', $route, $m) || !isset($modules[$m[1]])) {
        throw new HttpException('Unknown endpoint.', 404);
    }
    if (!Auth::check()) throw new HttpException('Your session has ended. Please sign in again.', 401);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') Csrf::verify();

    $class = $modules[$m[1]];
    $api = new $class(new Request());
    $data = $api->dispatch($m[2], $method);

    $body = ['ok' => true, 'data' => $data];
    if (is_array($data) && isset($data['_message'])) {
        $body['message'] = $data['_message'];
        unset($body['data']['_message']);
    }
    $respond(200, $body);
} catch (HttpException $e) {
    $respond($e->getCode() ?: 400, ['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors ?: null]);
} catch (PDOException $e) {
    $msg = 'A database error occurred.';
    $info = $e->errorInfo[1] ?? 0;
    if ($info === 1062) $msg = 'That record already exists (duplicate value).';
    elseif ($info === 1451) $msg = 'This record is in use elsewhere and cannot be deleted. Deactivate it instead.';
    elseif ($info === 1452) $msg = 'A linked record does not exist.';
    error_log('[magnate] ' . $e->getMessage());
    $respond(400, ['ok' => false, 'message' => $msg, 'debug' => config('app.debug') ? $e->getMessage() : null]);
} catch (Throwable $e) {
    error_log('[magnate] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $respond(500, ['ok' => false, 'message' => 'Something went wrong on the server.', 'debug' => config('app.debug') ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : null]);
}
