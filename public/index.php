<?php
/**
 * Contrôleur frontal : index.php?r=module.action
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\View;

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$routes = [
    'auth'       => App\Controllers\AuthController::class,
    'dashboard'  => App\Controllers\DashboardController::class,
    'biens'      => App\Controllers\BiensController::class,
    'categories' => App\Controllers\CategoriesController::class,
    'services'   => App\Controllers\ServicesController::class,
    'locaux'     => App\Controllers\LocauxController::class,
    'employes'   => App\Controllers\EmployesController::class,
    'transferts' => App\Controllers\TransfertsController::class,
    'users'      => App\Controllers\UsersController::class,
    'roles'      => App\Controllers\RolesController::class,
    'traces'     => App\Controllers\TracesController::class,
];

$route = is_string($_GET['r'] ?? null) ? $_GET['r'] : 'dashboard.index';
[$module, $action] = array_pad(explode('.', $route, 2), 2, 'index');

// Accès public : uniquement la page de connexion
if (!Auth::check() && $module !== 'auth') {
    header('Location: ' . url('auth.login'));
    exit;
}

try {
    if (!isset($routes[$module]) || !preg_match('/^[a-z][a-zA-Z]*$/', $action)) {
        throw new DomainException('Page introuvable');
    }
    $controller = new $routes[$module]();
    if (!method_exists($controller, $action) || !(new ReflectionMethod($controller, $action))->isPublic()) {
        throw new DomainException('Page introuvable');
    }
    $controller->$action();
} catch (DomainException $e) {
    http_response_code(404);
    echo View::render('errors/404', ['title' => 'Introuvable', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo View::render('errors/500', [
        'title' => 'Erreur',
        'error' => config('app.debug') ? $e->getMessage() . "\n" . $e->getTraceAsString() : null,
    ], Auth::check() ? 'layout/main' : 'layout/guest');
}
