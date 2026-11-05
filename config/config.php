<?php
/**
 * Configuration de l'application.
 * Les valeurs peuvent être surchargées par variables d'environnement
 * ou par un fichier config/config.local.php (non versionné) qui retourne un tableau.
 */
$env = static fn(string $k, $d = null) => ($v = getenv($k)) !== false && $v !== '' ? $v : $d;

$config = [
    'app' => [
        'name'        => $env('APP_NAME', 'GestImmo'),
        'debug'       => filter_var($env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOL),
        'timezone'    => $env('APP_TIMEZONE', 'Africa/Casablanca'),
        'devise'      => $env('APP_DEVISE', 'DH'),
        'organisation'=> $env('APP_ORGANISATION', 'Mon Organisation'),
        'per_page'    => 25,          // taille de page par défaut
        'export_max'  => 5000,        // nombre max de lignes dans un export PDF / CSV
        'session_ttl' => 7200,        // inactivité max (secondes)
    ],
    'db' => [
        'host'       => $env('DB_HOST', '127.0.0.1'),
        'port'       => (int) $env('DB_PORT', 3306),
        'name'       => $env('DB_NAME', 'gestimmo'),
        'user'       => $env('DB_USER', 'root'),
        'pass'       => $env('DB_PASS', ''),
        'persistent' => filter_var($env('DB_PERSISTENT', '0'), FILTER_VALIDATE_BOOL),
    ],
    'cache' => [
        'prefix'      => $env('CACHE_PREFIX', 'gestimmo:'),
        'ttl_ref'     => 600,   // listes de référence (catégories, locaux...)
        'ttl_count'   => 120,   // compteurs de résultats
        'ttl_stats'   => 300,   // statistiques du tableau de bord
        'ttl_perms'   => 600,   // permissions des rôles
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lock_seconds' => 900,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}
return $config;
