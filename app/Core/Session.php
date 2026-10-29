<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('MAGNATESESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (bool)config('app.https', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        // Idle timeout
        $idle = (int)config('app.session_idle_minutes', 120) * 60;
        $now = time();
        if (isset($_SESSION['_last']) && $idle > 0 && ($now - $_SESSION['_last']) > $idle) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash'] = 'Your session expired. Please sign in again.';
        }
        $_SESSION['_last'] = $now;
    }

    public static function flash(?string $msg = null): ?string
    {
        if ($msg !== null) { $_SESSION['_flash'] = $msg; return null; }
        $m = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $m;
    }
}
