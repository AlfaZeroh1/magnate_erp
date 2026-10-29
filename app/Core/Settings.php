<?php
declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (DB::all('SELECT `key`, `value` FROM settings') as $r) {
                self::$cache[$r['key']] = $r['value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = '')
    {
        $all = self::all();
        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        DB::run('INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $key, 'v' => $value]);
        if (self::$cache !== null) self::$cache[$key] = $value;
    }
}
