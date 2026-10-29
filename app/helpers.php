<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Settings;

/** HTML-escape for templates. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Number formatting used by documents and server-rendered pages. */
function money($amount, int $decimals = 2): string
{
    return number_format((float)($amount ?? 0), $decimals, '.', ',');
}

function qty($n): string
{
    $n = (float)($n ?? 0);
    return floor($n) == $n ? number_format($n) : number_format($n, 2);
}

function fdate(?string $date, string $format = 'd M Y'): string
{
    if (!$date || str_starts_with($date, '0000')) return '';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function setting(string $key, $default = '')
{
    return Settings::get($key, $default);
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return 'assets/' . ltrim($path, '/') . '?v=' . $v;
}

function avatar_url(?array $user): string
{
    if ($user && !empty($user['avatar']) && is_file(ROOT . '/uploads/avatars/' . $user['avatar'])) {
        return 'uploads/avatars/' . rawurlencode($user['avatar']);
    }
    return '';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out ?: '?';
}

/** Order stage metadata shared by PHP pages and documents. */
function stage_meta(): array
{
    return [
        'draft'     => ['Draft',          'secondary', 'bi-pencil-square'],
        'sourcing'  => ['Sourcing',       'info',      'bi-send'],
        'confirmed' => ['Farm Confirmed', 'primary',   'bi-patch-check'],
        'received'  => ['Delivery Checked','teal',     'bi-clipboard2-check'],
        'packing'   => ['Packing',        'warning',   'bi-box-seam'],
        'invoiced'  => ['Invoiced',       'success',   'bi-receipt'],
        'closed'    => ['Closed',         'dark',      'bi-lock'],
        'cancelled' => ['Cancelled',      'danger',    'bi-x-octagon'],
    ];
}
