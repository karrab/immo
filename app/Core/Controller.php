<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        echo View::render($view, $data);
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'Accès refusé', 'permission' => $permission]);
            exit;
        }
    }

    protected function redirect(string $route, array $params = []): never
    {
        header('Location: ' . url($route, $params));
        exit;
    }

    protected function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Méthode non autorisée');
        }
        if (!Csrf::verify()) {
            http_response_code(419);
            echo View::render('errors/419', ['title' => 'Session expirée']);
            exit;
        }
    }

    protected function notFound(string $msg = 'Élément introuvable.'): never
    {
        http_response_code(404);
        echo View::render('errors/404', ['title' => 'Introuvable', 'message' => $msg]);
        exit;
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- Lecture des entrées -------------------------------------------

    protected function str(string $key, int $max = 255, ?array $src = null): ?string
    {
        $src ??= $_POST;
        $v = $src[$key] ?? null;
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    protected function int(string $key, ?array $src = null): ?int
    {
        $src ??= $_POST;
        $v = $src[$key] ?? null;
        return is_scalar($v) && filter_var($v, FILTER_VALIDATE_INT) !== false ? (int) $v : null;
    }

    protected function decimal(string $key, ?array $src = null): ?string
    {
        $src ??= $_POST;
        $v = $src[$key] ?? null;
        if (!is_string($v) || trim($v) === '') {
            return null;
        }
        $v = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($v));
        return is_numeric($v) ? sprintf('%.2f', (float) $v) : null;
    }

    protected function date(string $key, ?array $src = null): ?string
    {
        $src ??= $_POST;
        $v = $src[$key] ?? null;
        if (!is_string($v) || $v === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    protected function enum(string $key, array $allowed, ?array $src = null): ?string
    {
        $src ??= $_POST;
        $v = $src[$key] ?? null;
        return is_string($v) && array_key_exists($v, $allowed) ? $v : null;
    }

    /** Paramètre GET texte. */
    protected function q(string $key, int $max = 100): ?string
    {
        return $this->str($key, $max, $_GET);
    }

    protected function qint(string $key): ?int
    {
        return $this->int($key, $_GET);
    }

    protected function perPage(string $prefix = ''): int
    {
        $pp = $this->qint($prefix . 'per_page') ?? (int) Config::get('app.per_page', 25);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    /** Pagine une requête keyset avec les curseurs GET (préfixables). */
    protected function keyset(string $select, array $where, array $params, string $sortExpr, string $sortKey, string $idExpr, string $dir, string $prefix = ''): array
    {
        return (new Keyset($select, $where, $params, $sortExpr, $sortKey, $idExpr, $dir, $this->perPage($prefix)))
            ->paginate($this->q($prefix . 'after', 200), $this->q($prefix . 'before', 200));
    }

    /** Compte (mis en cache APCu) des lignes correspondant à un filtre. */
    protected function cachedCount(string $tag, string $sql, array $params): int
    {
        return (int) Cache::rememberTagged($tag, 'count:' . md5($sql . serialize($params)), (int) Config::get('cache.ttl_count', 120),
            static fn() => (int) Database::value($sql, $params));
    }

    /** Supprime une ligne en gérant les contraintes d'intégrité. */
    protected function safeDelete(string $table, int $id, string $module, string $label, string $back, array $tags): never
    {
        try {
            $n = Database::delete($table, 'id = ?', [$id]);
            if ($n) {
                Audit::log('suppression', $module, $id, "Suppression : $label");
                Cache::invalidate(...$tags);
                flash('success', 'Suppression effectuée.');
            } else {
                flash('warning', 'Élément déjà supprimé.');
            }
        } catch (\PDOException $e) {
            if (Database::isConstraintError($e)) {
                flash('danger', 'Suppression impossible : cet élément est encore utilisé ailleurs.');
            } else {
                throw $e;
            }
        }
        $this->redirect($back);
    }
}
