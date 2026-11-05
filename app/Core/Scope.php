<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Périmètre de visibilité des données selon l'utilisateur connecté.
 *
 *  - Vision globale : super-administrateur ou permission « biens.view_all ».
 *  - Chef de service (employé lié de type chef_service) : biens, employés
 *    et transferts de SON service.
 *  - Employé : uniquement les biens qui lui sont affectés.
 *  - Utilisateur sans employé lié et sans vision globale : rien.
 *
 * Un bien appartient au service s'il est détenu par un employé du service
 * ou s'il se trouve dans un local rattaché au service.
 */
final class Scope
{
    public const ALL = 'all';
    public const SERVICE = 'service';
    public const EMPLOYE = 'employe';
    public const NONE = 'none';

    /** @return array{0: string, 1: ?int} [type, id du service ou de l'employé] */
    public static function current(): array
    {
        $u = Auth::user();
        if (!$u) {
            return [self::NONE, null];
        }
        if ((int) $u['is_super'] === 1 || Auth::can('biens.view_all')) {
            return [self::ALL, null];
        }
        if (!$u['employe_id'] || (int) $u['employe_actif'] !== 1) {
            return [self::NONE, null];
        }
        if ($u['employe_type'] === 'chef_service' && $u['employe_service_id']) {
            return [self::SERVICE, (int) $u['employe_service_id']];
        }
        return [self::EMPLOYE, (int) $u['employe_id']];
    }

    public static function isGlobal(): bool
    {
        return self::current()[0] === self::ALL;
    }

    /** Clé de cache propre au périmètre. */
    public static function key(): string
    {
        [$t, $id] = self::current();
        return $t . ($id ? ":$id" : '');
    }

    /** Service dont l'utilisateur connecté est le chef (ou null). */
    public static function chefOfService(): ?int
    {
        $u = Auth::user();
        return $u && $u['employe_type'] === 'chef_service' && (int) $u['employe_actif'] === 1 && $u['employe_service_id']
            ? (int) $u['employe_service_id'] : null;
    }

    /** Libellé affiché (titre des listes). */
    public static function label(): string
    {
        [$t] = self::current();
        $u = Auth::user();
        return match ($t) {
            self::SERVICE => 'Biens du service ' . ($u['service_nom'] ?? ''),
            self::EMPLOYE => 'Mes biens',
            self::NONE    => 'Aucun bien (compte non rattaché à un employé)',
            default       => 'Biens / Immobilisations',
        };
    }

    /**
     * Ajoute la restriction sur les biens. La requête doit joindre
     * biens AS b, locaux AS l (b.local_id) et employes AS e (b.employe_id).
     */
    public static function applyBiens(array &$where, array &$params, string $b = 'b', string $l = 'l', string $e = 'e'): void
    {
        [$t, $id] = self::current();
        switch ($t) {
            case self::ALL:
                return;
            case self::SERVICE:
                $where[] = "($e.service_id = ? OR $l.service_id = ?)";
                array_push($params, $id, $id);
                return;
            case self::EMPLOYE:
                $where[] = "$b.employe_id = ?";
                $params[] = $id;
                return;
            default:
                $where[] = '1 = 0';
        }
    }

    /** Le bien (avec employe_service_id / local_service_id) est-il visible ? */
    public static function canSeeBien(array $bien): bool
    {
        [$t, $id] = self::current();
        return match ($t) {
            self::ALL     => true,
            self::SERVICE => (int) ($bien['employe_service_id'] ?? 0) === $id || (int) ($bien['local_service_id'] ?? 0) === $id,
            self::EMPLOYE => (int) ($bien['employe_id'] ?? 0) === $id,
            default       => false,
        };
    }

    /** Restriction sur les employés (alias e). */
    public static function applyEmployes(array &$where, array &$params, string $e = 'e'): void
    {
        [$t, $id] = self::current();
        if ($t === self::SERVICE) {
            $where[] = "$e.service_id = ?";
            $params[] = $id;
        } elseif ($t === self::EMPLOYE) {
            $where[] = "$e.id = ?";
            $params[] = $id;
        } elseif ($t === self::NONE) {
            $where[] = '1 = 0';
        }
    }

    public static function canSeeEmploye(array $emp): bool
    {
        [$t, $id] = self::current();
        return match ($t) {
            self::ALL     => true,
            self::SERVICE => (int) ($emp['service_id'] ?? 0) === $id,
            self::EMPLOYE => (int) $emp['id'] === $id,
            default       => false,
        };
    }

    /**
     * Restriction sur les transferts (alias t, ls = local source, ld = local destination) :
     * le chef voit les transferts sortant ou entrant dans son service ;
     * l'employé ceux qui concernent ses biens ou dont il est destinataire.
     */
    public static function applyTransferts(array &$where, array &$params, string $t = 't', string $ls = 'ls', string $ld = 'ld'): void
    {
        [$type, $id] = self::current();
        switch ($type) {
            case self::ALL:
                return;
            case self::SERVICE:
                $where[] = "($ls.service_id = ? OR $ld.service_id = ?)";
                array_push($params, $id, $id);
                return;
            case self::EMPLOYE:
                $where[] = "($t.employe_destination_id = ? OR EXISTS (SELECT 1 FROM transfert_lignes tlx WHERE tlx.transfert_id = $t.id AND tlx.employe_source_id = ?))";
                array_push($params, $id, $id);
                return;
            default:
                $where[] = '1 = 0';
        }
    }

    public static function canSeeTransfert(array $tr): bool
    {
        [$type, $id] = self::current();
        if ($type === self::ALL) {
            return true;
        }
        $w = ['t.id = ?'];
        $p = [(int) $tr['id']];
        self::applyTransferts($w, $p);
        return (bool) Database::value(
            'SELECT 1 FROM transferts t JOIN locaux ls ON ls.id = t.local_source_id JOIN locaux ld ON ld.id = t.local_destination_id WHERE '
            . implode(' AND ', $w), $p);
    }
}
