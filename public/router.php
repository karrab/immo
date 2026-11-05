<?php
/**
 * Routeur pour le serveur PHP intégré (développement uniquement) :
 *   php -S localhost:8000 -t public public/router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) {
    return false; // fichier statique servi tel quel
}
require __DIR__ . '/index.php';
