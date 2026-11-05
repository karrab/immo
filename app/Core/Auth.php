<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification par session + contrôle des permissions (RBAC).
 * Les permissions de chaque rôle sont mises en cache dans APCu.
 */
final class Auth
{
    private static array|false|null $user = null;

    public static function user(): ?array
    {
        if (self::$user === null) {
            self::$user = false;
            $uid = $_SESSION['uid'] ?? null;
            if ($uid) {
                $row = Database::one(
                    'SELECT u.id, u.username, u.nom_complet, u.email, u.role_id, u.employe_id, u.actif,
                            r.nom AS role_nom, r.is_super
                       FROM users u JOIN roles r ON r.id = u.role_id
                      WHERE u.id = ?',
                    [$uid]
                );
                if ($row && (int) $row['actif'] === 1) {
                    self::$user = $row;
                } else {
                    self::logout();
                }
            }
        }
        return self::$user ?: null;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Tente une connexion. Retourne null si OK, sinon un message d'erreur.
     */
    public static function attempt(string $username, string $password): ?string
    {
        $max  = (int) Config::get('security.login_max_attempts', 5);
        $lock = (int) Config::get('security.login_lock_seconds', 900);
        $key  = 'login:' . sha1(client_ip() . '|' . mb_strtolower($username));

        if (Cache::counter($key) >= $max) {
            Audit::log('login_bloque', 'auth', null, "Connexion bloquée pour « $username » (trop de tentatives)", [], $username);
            return 'Trop de tentatives. Réessayez dans ' . (int) ceil($lock / 60) . ' minutes.';
        }

        $user = Database::one('SELECT id, username, password_hash, actif FROM users WHERE username = ?', [$username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Cache::increment($key, $lock);
            Audit::log('login_echec', 'auth', $user['id'] ?? null, "Échec de connexion pour « $username »", [], $username);
            return 'Identifiant ou mot de passe incorrect.';
        }
        if ((int) $user['actif'] !== 1) {
            return 'Ce compte est désactivé.';
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }

        Cache::resetCounter($key);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        self::$user = null;

        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()], 'id = ?', [$user['id']]);
        Audit::log('login', 'auth', (int) $user['id'], 'Connexion réussie');
        return null;
    }

    public static function logout(): void
    {
        self::$user = false;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            session_destroy();
        }
    }

    /** Ensemble des codes de permission (clé => true) du rôle. */
    public static function permissions(int $roleId): array
    {
        return Cache::rememberTagged('roles', "perms:$roleId", (int) Config::get('cache.ttl_perms', 600), static function () use ($roleId) {
            $codes = Database::all(
                'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
                [$roleId]
            );
            return array_fill_keys(array_column($codes, 'code'), true);
        });
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ((int) $u['is_super'] === 1) {
            return true;
        }
        return isset(self::permissions((int) $u['role_id'])[$permission]);
    }
}
