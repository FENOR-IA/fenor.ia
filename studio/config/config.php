<?php
// Sessão dura 8 horas
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
require_once __DIR__ . '/db.php';

function config(): array {
    $s = fenorSettings();
    return [
        'base_domain'         => $s['BASE_DOMAIN']          ?? 'localhost',
        'admin_email'         => $s['ADMIN_EMAIL']           ?? '',
        'admin_password_hash' => $s['ADMIN_PASSWORD_HASH']   ?? '',
        'apps_path'           => $s['APPS_PATH']             ?? '/var/www',
        'terminal_url'        => $s['TERMINAL_URL']          ?? '',
        'cf_token'            => $s['CF_TOKEN']              ?? '',
        'cf_zone_id'          => $s['CF_ZONE_ID']            ?? '',
        'cf_tunnel_id'        => $s['CF_TUNNEL_ID']          ?? '',
        'db_driver'           => fenorEnv()['DB_DRIVER']     ?? 'pgsql',
    ];
}

// Compatibilidade — permite $config = require 'config.php'
return config();
