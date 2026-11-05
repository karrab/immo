<?php
/**
 * Préchargement OPcache (PHP >= 7.4) : les classes du noyau, des services et
 * des contrôleurs sont compilées et liées une seule fois au démarrage de
 * PHP-FPM, puis restent en mémoire partagée pour toutes les requêtes.
 *
 * php.ini :
 *   opcache.preload=/chemin/vers/gestimmo/preload.php
 *   opcache.preload_user=www-data
 *
 * NB : après un déploiement, redémarrer PHP-FPM (le préchargement n'est pas revalidé).
 */
declare(strict_types=1);

$base = __DIR__;

// L'autoloader résout l'héritage (ex. Controller avant BiensController)
spl_autoload_register(static function (string $class) use ($base): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $base . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

foreach (['Core', 'Services', 'Controllers'] as $dir) {
    foreach (glob("$base/app/$dir/*.php") ?: [] as $file) {
        $class = 'App\\' . $dir . '\\' . basename($file, '.php');
        class_exists($class) || interface_exists($class);
    }
}
