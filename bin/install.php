<?php
/**
 * Installation : crée la base, le schéma et les données initiales.
 *   php bin/install.php            (schéma + données de démonstration)
 *   php bin/install.php --no-seed  (schéma seul + compte admin)
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;

$seed = !in_array('--no-seed', $argv, true);
$db = Config::get('db');
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']), $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $db['name']));
$pdo->exec(sprintf('USE `%s`', $db['name']));

$run = static function (string $file) use ($pdo): void {
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    echo "  ✓ " . basename($file) . PHP_EOL;
};

echo "Installation de la base « {$db['name']} »" . PHP_EOL;
$run(dirname(__DIR__) . '/database/schema.sql');
if ($seed) {
    $run(dirname(__DIR__) . '/database/seed.sql');
} else {
    // Uniquement permissions, rôles et admin
    $seedSql = (string) file_get_contents(dirname(__DIR__) . '/database/seed.sql');
    $cut = strpos($seedSql, 'INSERT INTO categories');
    file_put_contents($tmp = tempnam(sys_get_temp_dir(), 'seed'), substr($seedSql, 0, $cut ?: null));
    $run($tmp);
    unlink($tmp);
}
echo PHP_EOL . "Terminé. Connexion : admin / Admin@123 (changez le mot de passe)." . PHP_EOL;
