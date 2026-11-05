<?php
/**
 * Installation en ligne de commande : crée la base, le schéma et les données initiales.
 *   php bin/install.php            (schéma + données de démonstration)
 *   php bin/install.php --no-seed  (schéma seul + compte admin)
 *
 * Sans ligne de commande : ouvrir http://localhost/immo/public/install.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p style="font-family:sans-serif">Ce script s\'exécute en ligne de commande (<code>php bin/install.php</code>).<br>'
       . 'Pour installer depuis le navigateur, ouvrez <a href="../public/install.php">public/install.php</a>.</p>';
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Installer;

$args = $argv ?? [];
$demo = !in_array('--no-seed', $args, true);
$db = Config::get('db');

try {
    $pdo = Installer::connect($db['host'], (int) $db['port'], $db['user'], $db['pass']);
    echo "Installation de la base « {$db['name']} »" . PHP_EOL;
    foreach (Installer::install($pdo, $db['name'], $demo) as $line) {
        echo "  ✓ $line" . PHP_EOL;
    }
    Installer::lock();
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . PHP_EOL
        . 'Vérifiez les accès MySQL dans config/config.local.php (voir config.local.php.example).' . PHP_EOL);
    exit(1);
}
echo PHP_EOL . "Terminé. Connexion : admin / Admin@123 (changez le mot de passe)." . PHP_EOL;
