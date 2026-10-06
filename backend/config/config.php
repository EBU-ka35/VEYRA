<?php
/**
 * VEYRA — Application Configuration
 *
 * Copy this file or edit values for your environment.
 * Never commit real production credentials to public repos.
 */

declare(strict_types=1);

return [
    // ----------------------------------------------------------
    // App
    // ----------------------------------------------------------
    'app_name'     => 'VEYRA',
    'app_url'      => 'http://localhost',           // no trailing slash
    'app_env'      => 'local',                      // local | staging | production
    'app_debug'    => true,                         // false in production
    'timezone'     => 'Africa/Lagos',

    // ----------------------------------------------------------
    // Database
    // ----------------------------------------------------------
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'veyra',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // ----------------------------------------------------------
    // Session
    // ----------------------------------------------------------
    'session' => [
        'name'     => 'VEYRA_SESSION',
        'lifetime' => 60 * 60 * 8,                  // 8 hours
        'secure'   => false,                        // true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // ----------------------------------------------------------
    // Auth
    // ----------------------------------------------------------
    'auth' => [
        'password_min'       => 8,
        'reset_token_ttl'    => 60 * 60,            // 1 hour
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],

    // ----------------------------------------------------------
    // CORS (for frontend calling API from another origin)
    // ----------------------------------------------------------
    'cors' => [
        'allowed_origins' => [
            'http://localhost',
            'http://localhost:3000',
            'http://127.0.0.1',
            'http://127.0.0.1:5500',               // Live Server
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    ],

    // ----------------------------------------------------------
    // Paths (relative to backend root)
    // ----------------------------------------------------------
    'paths' => [
        'root' => dirname(__DIR__),
    ],
];
