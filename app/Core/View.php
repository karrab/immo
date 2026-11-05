<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Rend une vue (views/$view.php) dans un layout. */
    public static function render(string $view, array $data = [], ?string $layout = 'layout/main'): string
    {
        $content = self::fetch($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::fetch($layout, $data + ['content' => $content]);
    }

    /** Rend une vue sans layout et retourne le HTML. */
    public static function fetch(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vue introuvable : $view");
        }
        // Portée isolée : les variables de la vue ne peuvent pas masquer celles de la méthode
        $render = static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        };
        ob_start();
        try {
            $render($file, $data);
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
