<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Listes de référence (menus déroulants) mises en cache dans APCu.
 * Chaque liste est associée à un tag invalidé lors des écritures.
 */
final class Ref
{
    private static function ttl(): int
    {
        return (int) Config::get('cache.ttl_ref', 600);
    }

    public static function categories(): array
    {
        return Cache::rememberTagged('categories', 'ref', self::ttl(), static fn() =>
            Database::all('SELECT id, code, libelle, duree_amortissement FROM categories ORDER BY libelle'));
    }

    public static function services(): array
    {
        return Cache::rememberTagged('services', 'ref', self::ttl(), static fn() =>
            Database::all('SELECT id, code, nom FROM services ORDER BY nom'));
    }

    public static function locaux(): array
    {
        return Cache::rememberTagged('locaux', 'ref', self::ttl(), static fn() =>
            Database::all('SELECT id, code, nom, batiment, etage FROM locaux WHERE actif = 1 ORDER BY code'));
    }

    public static function employes(): array
    {
        return Cache::rememberTagged('employes', 'ref', self::ttl(), static fn() =>
            Database::all("SELECT id, matricule, CONCAT(nom, ' ', prenom) AS nom_complet, local_id
                             FROM employes WHERE actif = 1 ORDER BY nom, prenom"));
    }

    public static function roles(): array
    {
        return Cache::rememberTagged('roles', 'ref', self::ttl(), static fn() =>
            Database::all('SELECT id, nom FROM roles ORDER BY nom'));
    }

    public static function users(): array
    {
        return Cache::rememberTagged('users', 'ref', self::ttl(), static fn() =>
            Database::all('SELECT id, username, nom_complet FROM users ORDER BY username'));
    }

    /** Transforme une liste en tableau id => libellé pour options(). */
    public static function pairs(array $rows, string|callable $label): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = is_callable($label) ? $label($r) : $r[$label];
        }
        return $out;
    }

    public static function localLabel(array $l): string
    {
        return $l['code'] . ' — ' . $l['nom'];
    }
}
