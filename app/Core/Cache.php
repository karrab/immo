<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache mémoire partagé basé sur APCu, avec repli sur un cache
 * de requête (tableau) si APCu n'est pas disponible (ex. CLI).
 *
 * Invalidation par "tags" versionnés : chaque tag possède un numéro de
 * version stocké dans APCu ; les clés incluent cette version. Invalider
 * un tag = changer sa version, les anciennes entrées expirent seules.
 */
final class Cache
{
    private static ?bool $apcu = null;
    private static array $local = [];

    public static function enabled(): bool
    {
        return self::$apcu ??= (function_exists('apcu_enabled') && apcu_enabled());
    }

    private static function k(string $key): string
    {
        return Config::get('cache.prefix', 'gestimmo:') . $key;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $k = self::k($key);
        if (self::enabled()) {
            $v = apcu_fetch($k, $ok);
            return $ok ? $v : $default;
        }
        return array_key_exists($k, self::$local) ? self::$local[$k] : $default;
    }

    public static function set(string $key, mixed $value, int $ttl = 300): void
    {
        $k = self::k($key);
        if (self::enabled()) {
            apcu_store($k, $value, $ttl);
        } else {
            self::$local[$k] = $value;
        }
    }

    public static function delete(string $key): void
    {
        $k = self::k($key);
        if (self::enabled()) {
            apcu_delete($k);
        }
        unset(self::$local[$k]);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $k = self::k($key);
        if (self::enabled()) {
            $v = apcu_fetch($k, $ok);
            if ($ok) {
                return $v;
            }
            $v = $callback();
            apcu_store($k, $v, $ttl);
            return $v;
        }
        if (!array_key_exists($k, self::$local)) {
            self::$local[$k] = $callback();
        }
        return self::$local[$k];
    }

    public static function tagVersion(string $tag): int
    {
        $v = self::get("tag:$tag");
        if ($v === null) {
            $v = (int) (microtime(true) * 1000);
            self::set("tag:$tag", $v, 0);
        }
        return (int) $v;
    }

    /** remember() dont la clé dépend de la version du tag. */
    public static function rememberTagged(string $tag, string $key, int $ttl, callable $callback): mixed
    {
        return self::remember("$tag:" . self::tagVersion($tag) . ":$key", $ttl, $callback);
    }

    public static function invalidate(string ...$tags): void
    {
        foreach ($tags as $tag) {
            $next = max(self::tagVersion($tag) + 1, (int) (microtime(true) * 1000));
            self::set("tag:$tag", $next, 0);
        }
    }

    /** Compteur atomique avec TTL (ex. anti brute-force). */
    public static function increment(string $key, int $ttl): int
    {
        $k = self::k($key);
        if (self::enabled()) {
            apcu_add($k, 0, $ttl);
            $v = apcu_inc($k, 1, $ok, $ttl);
            return $ok ? (int) $v : 1;
        }
        // Repli : compteur en session (moins robuste, mais fonctionnel)
        $_SESSION['_cnt'][$key] = ($_SESSION['_cnt'][$key] ?? 0) + 1;
        return $_SESSION['_cnt'][$key];
    }

    public static function counter(string $key): int
    {
        if (self::enabled()) {
            return (int) (apcu_fetch(self::k($key)) ?: 0);
        }
        return (int) ($_SESSION['_cnt'][$key] ?? 0);
    }

    public static function resetCounter(string $key): void
    {
        self::delete($key);
        unset($_SESSION['_cnt'][$key]);
    }

    public static function stats(): array
    {
        if (!self::enabled()) {
            return ['enabled' => false];
        }
        $info = apcu_cache_info(true);
        $sma  = apcu_sma_info(true);
        return [
            'enabled'  => true,
            'entries'  => $info['num_entries'] ?? 0,
            'hits'     => $info['num_hits'] ?? 0,
            'misses'   => $info['num_misses'] ?? 0,
            'mem_size' => $info['mem_size'] ?? 0,
            'avail'    => $sma['avail_mem'] ?? 0,
        ];
    }
}
