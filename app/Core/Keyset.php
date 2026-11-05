<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pagination "keyset" (seek method).
 *
 * Au lieu de LIMIT n OFFSET m (qui oblige MySQL à lire puis jeter m lignes),
 * on mémorise la clé (valeur_de_tri, id) de la dernière ligne affichée et on
 * demande les n suivantes :
 *
 *   WHERE (col < :v OR (col = :v AND id < :id)) ORDER BY col DESC, id DESC LIMIT n+1
 *
 * Avec un index (col, id) le coût est constant quelle que soit la page.
 * Le curseur est opaque (base64url de JSON) et transmis via ?after= / ?before=.
 */
final class Keyset
{
    /**
     * @param string $select  Début de requête : "SELECT ... FROM ... JOIN ..." (sans WHERE)
     * @param array  $where   Conditions SQL déjà construites (jointes par AND)
     * @param array  $params  Paramètres positionnels des conditions
     * @param string $sortExpr Expression SQL de tri (ex. "b.date_acquisition")
     * @param string $sortKey  Nom de la colonne correspondante dans les lignes retournées
     * @param string $idExpr   Expression SQL de l'identifiant (départage)
     * @param string $dir      ASC | DESC
     */
    public function __construct(
        private string $select,
        private array $where,
        private array $params,
        private string $sortExpr,
        private string $sortKey,
        private string $idExpr = 'id',
        private string $dir = 'DESC',
        private int $limit = 25,
    ) {
        $this->dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $this->limit = max(1, min(500, $limit));
    }

    /** @return array{rows: array, next: ?string, prev: ?string, first: bool} */
    public function paginate(?string $after = null, ?string $before = null): array
    {
        $where  = $this->where;
        $params = $this->params;

        $backward = false;
        $cursor = null;
        if ($before !== null && $before !== '' && ($c = self::decode($before))) {
            $cursor = $c;
            $backward = true;
        } elseif ($after !== null && $after !== '' && ($c = self::decode($after))) {
            $cursor = $c;
        }

        $dir = $backward ? ($this->dir === 'DESC' ? 'ASC' : 'DESC') : $this->dir;
        $op  = $dir === 'DESC' ? '<' : '>';
        $sameCol = $this->sortExpr === $this->idExpr;

        if ($cursor) {
            if ($sameCol) {
                $where[]  = "{$this->idExpr} $op ?";
                $params[] = $cursor[1];
            } else {
                $where[] = "({$this->sortExpr} $op ? OR ({$this->sortExpr} = ? AND {$this->idExpr} $op ?))";
                array_push($params, $cursor[0], $cursor[0], $cursor[1]);
            }
        }

        $sql = $this->select
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY {$this->sortExpr} $dir" . ($sameCol ? '' : ", {$this->idExpr} $dir")
            . ' LIMIT ' . ($this->limit + 1);

        $rows = Database::all($sql, $params);
        $hasMore = count($rows) > $this->limit;
        if ($hasMore) {
            array_pop($rows);
        }
        if ($backward) {
            $rows = array_reverse($rows);
        }

        $first = $rows[0] ?? null;
        $last  = $rows ? $rows[count($rows) - 1] : null;

        if ($backward) {
            $prev = $hasMore && $first ? $this->encode($first) : null;
            $next = $last ? $this->encode($last) : null;
        } else {
            $prev = $cursor && $first ? $this->encode($first) : null;
            $next = $hasMore && $last ? $this->encode($last) : null;
        }

        return ['rows' => $rows, 'next' => $next, 'prev' => $prev, 'first' => $prev === null];
    }

    private function encode(array $row): string
    {
        $json = json_encode([$row[$this->sortKey], $row['id']]);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function decode(string $cursor): ?array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data) || count($data) !== 2 || !is_numeric($data[1]) || !is_scalar($data[0])) {
            return null;
        }
        return [$data[0], (int) $data[1]];
    }
}
