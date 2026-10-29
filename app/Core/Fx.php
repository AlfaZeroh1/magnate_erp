<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Exchange rates. Convention: rate = KES per 1 unit of currency (base = 1).
 * A rate applies from its effective_date until a newer one is set,
 * so a transaction always gets "the latest rate on or before its date".
 */
final class Fx
{
    private static array $cache = [];

    public static function baseId(): int
    {
        static $id = null;
        return $id ??= (int)(DB::val('SELECT id FROM currencies WHERE is_base = 1 LIMIT 1') ?? 1);
    }

    public static function baseCode(): string
    {
        static $code = null;
        return $code ??= (string)(DB::val('SELECT code FROM currencies WHERE is_base = 1 LIMIT 1') ?? 'KES');
    }

    /** Returns null when no rate exists on/before the date. */
    public static function rate(int $currencyId, ?string $date = null): ?float
    {
        if ($currencyId === self::baseId()) return 1.0;
        $date = $date ?: date('Y-m-d');
        $k = "$currencyId|$date";
        if (!array_key_exists($k, self::$cache)) {
            $v = DB::val('SELECT rate FROM exchange_rates WHERE currency_id = :c AND effective_date <= :d
                          ORDER BY effective_date DESC LIMIT 1', ['c' => $currencyId, 'd' => $date]);
            self::$cache[$k] = $v === null ? null : (float)$v;
        }
        return self::$cache[$k];
    }

    /** Like rate() but throws a friendly error the UI turns into a "set rate" prompt. */
    public static function rateOrFail(int $currencyId, ?string $date = null): float
    {
        $r = self::rate($currencyId, $date);
        if ($r === null || $r <= 0) {
            $code = DB::val('SELECT code FROM currencies WHERE id = :id', ['id' => $currencyId]) ?? '?';
            throw new HttpException("No exchange rate for {$code} on or before " . ($date ?: 'today') . '. Set it under Pricing → Exchange Rates.', 409,
                ['_fx' => ['currency_id' => $currencyId, 'code' => $code]]);
        }
        return $r;
    }

    public static function convert(float $amount, int $fromId, int $toId, ?string $date = null): float
    {
        if ($fromId === $toId) return $amount;
        return $amount * self::rateOrFail($fromId, $date) / self::rateOrFail($toId, $date);
    }

    /**
     * Active non-base currencies with no rate set in the current month.
     * Drives the "set your rates" prompt on login.
     */
    public static function missingThisMonth(): array
    {
        return DB::all("SELECT c.id, c.code, c.name,
                               (SELECT er.rate FROM exchange_rates er WHERE er.currency_id = c.id ORDER BY er.effective_date DESC LIMIT 1) AS last_rate,
                               (SELECT er.effective_date FROM exchange_rates er WHERE er.currency_id = c.id ORDER BY er.effective_date DESC LIMIT 1) AS last_date
                          FROM currencies c
                         WHERE c.is_active = 1 AND c.is_base = 0
                           AND NOT EXISTS (SELECT 1 FROM exchange_rates e
                                            WHERE e.currency_id = c.id
                                              AND e.effective_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))
                         ORDER BY c.code");
    }
}
