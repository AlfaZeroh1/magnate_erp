<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper. Everything is a prepared statement.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], (int)($c['port'] ?? 3306), $c['name'], $c['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Emulation lets a named parameter appear more than once (used by report filters).
                PDO::ATTR_EMULATE_PREPARES   => true,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            $tz = (new \DateTime())->format('P');
            self::$pdo->exec("SET time_zone = '{$tz}'");
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = PDO::PARAM_STR;
            if (is_int($value)) $type = PDO::PARAM_INT;
            elseif (is_bool($value)) { $type = PDO::PARAM_INT; $value = (int)$value; }
            elseif ($value === null) $type = PDO::PARAM_NULL;
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** First column of first row. */
    public static function val(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** List of first-column values. */
    public static function col(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table,
            implode(',', array_map(fn($c) => "`$c`", $cols)),
            implode(',', array_map(fn($c) => ":$c", $cols)));
        self::run($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (!$data) return 0;
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "`$col` = :set_$col";
            $params["set_$col"] = $val;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        return self::run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    /** Nested-safe transaction helper. */
    public static function tx(callable $fn)
    {
        $pdo = self::pdo();
        if (self::$txDepth === 0) $pdo->beginTransaction();
        self::$txDepth++;
        try {
            $result = $fn();
            self::$txDepth--;
            if (self::$txDepth === 0) $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            self::$txDepth--;
            if (self::$txDepth === 0 && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
