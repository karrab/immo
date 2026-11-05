<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Journal des traces (qui a fait quoi, quand, d'où) et historique des biens.
 */
final class Audit
{
    public static function log(string $action, string $module, ?int $entiteId, string $description, array $data = [], ?string $username = null): void
    {
        try {
            $user = Auth::user();
            Database::insert('traces', [
                'user_id'     => $user['id'] ?? null,
                'username'    => mb_substr($user['username'] ?? $username ?? '', 0, 60) ?: null,
                'action'      => $action,
                'module'      => $module,
                'entite_id'   => $entiteId,
                'description' => mb_substr($description, 0, 255),
                'donnees'     => $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip'          => client_ip(),
                'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('Audit::log : ' . $e->getMessage());
        }
    }

    /** Ajoute un événement à l'historique d'un bien. */
    public static function bien(int $bienId, string $type, string $description, array $data = []): void
    {
        Database::insert('bien_historique', [
            'bien_id'        => $bienId,
            'type_evenement' => $type,
            'description'    => mb_substr($description, 0, 255),
            'donnees'        => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'user_id'        => Auth::id(),
        ]);
    }

    /** Différences entre deux tableaux (champ => [ancien, nouveau]). */
    public static function diff(array $before, array $after, array $ignore = ['updated_at', 'updated_by', 'created_at']): array
    {
        $changes = [];
        foreach ($after as $k => $v) {
            if (in_array($k, $ignore, true) || !array_key_exists($k, $before)) {
                continue;
            }
            if ((string) $before[$k] !== (string) $v) {
                $changes[$k] = [$before[$k], $v];
            }
        }
        return $changes;
    }
}
