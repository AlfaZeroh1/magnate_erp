<?php
declare(strict_types=1);

namespace App\Core;

/** Normalised access to request input (JSON body, form POST or query string). */
final class Request
{
    private array $data;
    public array $files;

    public function __construct()
    {
        $data = $_GET;
        unset($data['r']);
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ctype, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input') ?: '[]', true);
            if (is_array($json)) $data = array_merge($data, $json);
        } else {
            $data = array_merge($data, $_POST);
        }
        $this->data = $data;
        $this->files = $_FILES;
    }

    public function all(): array { return $this->data; }

    public function has(string $key): bool { return array_key_exists($key, $this->data); }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->data[$key] ?? $default;
        return is_scalar($v) ? trim((string)$v) : $default;
    }

    /** Null for empty strings — handy for optional DB columns. */
    public function strOrNull(string $key): ?string
    {
        $v = $this->str($key);
        return $v === '' ? null : $v;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->data[$key] ?? null;
        return is_numeric($v) ? (int)$v : $default;
    }

    public function intOrNull(string $key): ?int
    {
        $v = $this->data[$key] ?? null;
        return is_numeric($v) && (int)$v > 0 ? (int)$v : null;
    }

    public function num(string $key, float $default = 0.0): float
    {
        $v = $this->data[$key] ?? null;
        if (is_string($v)) $v = str_replace(',', '', $v);
        return is_numeric($v) ? (float)$v : $default;
    }

    public function numOrNull(string $key): ?float
    {
        $v = $this->data[$key] ?? null;
        if (is_string($v)) $v = str_replace(',', '', trim($v));
        return ($v === '' || $v === null || !is_numeric($v)) ? null : (float)$v;
    }

    public function date(string $key, ?string $default = null): ?string
    {
        $v = $this->str($key);
        if ($v === '') return $default;
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : $default;
    }

    public function arr(string $key): array
    {
        $v = $this->data[$key] ?? [];
        if (is_string($v)) {
            $j = json_decode($v, true);
            return is_array($j) ? $j : [];
        }
        return is_array($v) ? $v : [];
    }

    public function bool(string $key): bool
    {
        $v = $this->data[$key] ?? false;
        return in_array($v, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
