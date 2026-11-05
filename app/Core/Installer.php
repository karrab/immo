<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Installation de la base de données (utilisé par bin/install.php et public/install.php).
 */
final class Installer
{
    public const LOCK = BASE_PATH . '/storage/install.lock';

    public static function isInstalled(): bool
    {
        return is_file(self::LOCK);
    }

    /** Connexion au serveur MySQL (sans base sélectionnée). */
    public static function connect(string $host, int $port, string $user, string $pass): PDO
    {
        return new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    /** La base contient-elle déjà les tables de l'application ? */
    public static function hasTables(PDO $pdo, string $db): bool
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name IN ('biens','users')");
        $st->execute([$db]);
        return (int) $st->fetchColumn() > 0;
    }

    /**
     * Crée la base, le schéma et les données initiales.
     * @return list<string> journal des étapes
     */
    public static function install(PDO $pdo, string $db, bool $demo, ?string $adminPassword = null): array
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db)) {
            throw new \InvalidArgumentException('Nom de base invalide (lettres, chiffres et _ uniquement).');
        }
        $log = [];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db`");
        $log[] = "Base « $db » prête";

        self::runFile($pdo, (string) file_get_contents(BASE_PATH . '/database/schema.sql'));
        $log[] = 'Tables créées (schema.sql)';

        $seed = (string) file_get_contents(BASE_PATH . '/database/seed.sql');
        if (!$demo) {
            // Permissions, rôles et compte admin uniquement
            $cut = strpos($seed, 'INSERT INTO categories');
            $seed = substr($seed, 0, $cut !== false ? $cut : null);
            $seed = preg_replace('/-- Comptes de démonstration.*?;\s*$/ms', '', $seed) ?? $seed;
        }
        self::runFile($pdo, $seed);
        $log[] = $demo ? 'Données de démonstration chargées (seed.sql)' : 'Rôles, permissions et compte admin créés';

        if ($adminPassword !== null && $adminPassword !== '') {
            $st = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
            $st->execute([password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $log[] = 'Mot de passe administrateur défini';
        }
        return $log;
    }

    private static function runFile(PDO $pdo, string $sql): void
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql) ?: [])) as $stmt) {
            $pdo->exec($stmt);
        }
    }

    /** Contenu de config/config.local.php. */
    public static function configFile(array $db, array $app): string
    {
        return "<?php\n// Généré par l'installateur le " . date('d/m/Y H:i') . "\nreturn " . var_export(['app' => $app, 'db' => $db], true) . ";\n";
    }

    public static function lock(): void
    {
        @mkdir(dirname(self::LOCK), 0775, true);
        file_put_contents(self::LOCK, date('c') . "\n");
    }
}
