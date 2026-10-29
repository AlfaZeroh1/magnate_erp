<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Race-safe document numbers.
 *   order    OD00001      (global)
 *   invoice  INV260001    (yearly)  — shared by order invoices, other sales and freight invoices
 *   packing  PL260001 / consignment GL260001 (yearly)
 *   freight  FB260001     (yearly)
 */
final class Seq
{
    private const FORMATS = [
        'order'   => ['OD',  false, 5],
        'invoice' => ['INV', true, 4],
        'packing' => ['PL',  true, 4],
        'freight' => ['FB',  true, 4],
    ];

    /** Reserve and return the next number. Must run inside a transaction to be gap-free. */
    public static function next(string $name, ?string $date = null): string
    {
        [$prefix, $yearly, $pad] = self::FORMATS[$name];
        $period = $yearly ? date('y', strtotime($date ?? 'today')) : '';

        return DB::tx(function () use ($name, $period, $prefix, $pad) {
            DB::run('INSERT IGNORE INTO sequences (name, period, next_value) VALUES (:n, :p, 1)', ['n' => $name, 'p' => $period]);
            $n = (int)DB::val('SELECT next_value FROM sequences WHERE name = :n AND period = :p FOR UPDATE', ['n' => $name, 'p' => $period]);
            DB::run('UPDATE sequences SET next_value = next_value + 1 WHERE name = :n AND period = :p', ['n' => $name, 'p' => $period]);
            return $prefix . $period . str_pad((string)$n, $pad, '0', STR_PAD_LEFT);
        });
    }

    /** Preview without reserving (for forms). */
    public static function peek(string $name, ?string $date = null): string
    {
        [$prefix, $yearly, $pad] = self::FORMATS[$name];
        $period = $yearly ? date('y', strtotime($date ?? 'today')) : '';
        $n = (int)(DB::val('SELECT next_value FROM sequences WHERE name = :n AND period = :p', ['n' => $name, 'p' => $period]) ?? 1);
        return $prefix . $period . str_pad((string)$n, $pad, '0', STR_PAD_LEFT);
    }
}
