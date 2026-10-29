<?php
/**
 * Copy this file to app/config.php and adjust.
 * app/config.php is never committed / shared.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'magnate',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'     => 'Magnate',
        'timezone' => 'Africa/Nairobi',
        // Show PHP errors in responses. Keep false in production.
        'debug'    => false,
        // Set true when served over HTTPS so the session cookie is Secure.
        'https'    => false,
        // Minutes of inactivity before a user is signed out.
        'session_idle_minutes' => 120,
    ],
    'uploads' => [
        'avatar_max_kb' => 2048,
    ],
];
