<?php
declare(strict_types=1);

/**
 * Single entry bootstrap: config, autoload, error handling, session.
 * Every public script (index.php, api.php, print.php, login.php ...) requires this first.
 */

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);

$configFile = APP . '/config.php';
if (!is_file($configFile)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    $configFile = APP . '/config.sample.php';
}
$GLOBALS['__config'] = require $configFile;

function config(string $path, $default = null)
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return $default;
        $value = $value[$key];
    }
    return $value;
}

date_default_timezone_set(config('app.timezone', 'Africa/Nairobi'));

// PSR-4-ish autoloader: App\Core\DB -> app/Core/DB.php
spl_autoload_register(function (string $class) {
    if (strncmp($class, 'App\\', 4) !== 0) return;
    $file = APP . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

require APP . '/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
set_error_handler(function (int $no, string $str, string $file, int $line) {
    if (!(error_reporting() & $no)) return false;
    throw new ErrorException($str, 0, $no, $file, $line);
});

App\Core\Session::start();
