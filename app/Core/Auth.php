<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static ?array $perms = null;

    private const MAX_ATTEMPTS = 6;      // per username+ip
    private const LOCK_MINUTES = 15;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    /** The signed-in user, re-read from the DB each request so deactivation / role changes apply instantly. */
    public static function user(): ?array
    {
        if (self::$user !== null) return self::$user;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) return null;
        $u = DB::one('SELECT u.*, r.name AS role_name, r.is_super
                        FROM users u JOIN roles r ON r.id = u.role_id
                       WHERE u.id = :id', ['id' => (int)$id]);
        if (!$u || $u['status'] !== 'active') {
            self::logout();
            return null;
        }
        unset($u['password_hash']);
        return self::$user = $u;
    }

    public static function attempt(string $login, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $login = trim($login);
        $recent = (int)DB::val(
            'SELECT COUNT(*) FROM login_attempts
              WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ' . self::LOCK_MINUTES . ' MINUTE)
                AND (username = :u OR ip = :ip)', ['u' => $login, 'ip' => $ip]);
        if ($recent >= self::MAX_ATTEMPTS * 2) {
            return [false, 'Too many failed attempts. Try again in ' . self::LOCK_MINUTES . ' minutes.'];
        }

        $u = DB::one('SELECT id, password_hash, status FROM users WHERE username = :l OR email = :l LIMIT 1', ['l' => $login]);
        $ok = $u && password_verify($password, $u['password_hash']);
        DB::insert('login_attempts', ['username' => mb_substr($login, 0, 100), 'ip' => $ip, 'success' => $ok ? 1 : 0]);

        if (!$ok) return [false, 'Incorrect username or password.'];
        if ($u['status'] !== 'active') return [false, 'This account is inactive. Contact your administrator.'];

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['just_logged_in'] = true;
        unset($_SESSION['_csrf']);
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $u['id']]);
        self::$user = null;
        Log::write('auth', (int)$u['id'], 'login', 'Signed in');
        return [true, ''];
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$perms = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }

    /** Effective permission set: role permissions +/- user overrides. */
    public static function permissions(): array
    {
        if (self::$perms !== null) return self::$perms;
        $u = self::user();
        if (!$u) return self::$perms = [];
        if ((int)$u['is_super'] === 1) return self::$perms = Perms::all();

        $set = array_flip(DB::col('SELECT permission FROM role_permissions WHERE role_id = :r', ['r' => $u['role_id']]));
        foreach (DB::all('SELECT permission, allow FROM user_permissions WHERE user_id = :u', ['u' => $u['id']]) as $o) {
            if ((int)$o['allow'] === 1) $set[$o['permission']] = true;
            else unset($set[$o['permission']]);
        }
        return self::$perms = array_keys($set);
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) return false;
        if ((int)$u['is_super'] === 1) return true;
        return in_array($perm, self::permissions(), true);
    }

    public static function canAny(array $perms): bool
    {
        foreach ($perms as $p) if (self::can($p)) return true;
        return false;
    }

    public static function require(string $perm): void
    {
        if (!self::check()) throw new HttpException('Please sign in.', 401);
        if (!self::can($perm)) throw new HttpException('You do not have the right to do this (' . $perm . ').', 403);
    }

    public static function isSuper(): bool
    {
        $u = self::user();
        return $u && (int)$u['is_super'] === 1;
    }
}
