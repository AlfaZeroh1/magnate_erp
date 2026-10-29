<?php
declare(strict_types=1);

namespace App\Core;

/** Activity / audit log. Every write in the system goes through here. */
final class Log
{
    public static function write(string $entity, ?int $entityId, string $action, string $description, ?int $orderId = null, ?array $meta = null): void
    {
        DB::insert('activity_log', [
            'user_id'     => Auth::id() ?? ($_SESSION['uid'] ?? null),
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'order_id'    => $orderId,
            'action'      => $action,
            'description' => mb_substr($description, 0, 500),
            'meta'        => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
