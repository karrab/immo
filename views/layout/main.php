<?php
/** @var string $content @var string $title */
$route = $_GET['r'] ?? 'dashboard.index';
$module = explode('.', $route)[0];
$u = user();
$menu = [
    ['dashboard',  'dashboard.view',  'speedometer2',     'Tableau de bord'],
    ['biens',      'biens.view',      'box-seam',         'Biens'],
    ['transferts', 'transferts.view', 'arrow-left-right', 'Transferts'],
    ['locaux',     'locaux.view',     'door-open',        'Locaux'],
    ['employes',   'employes.view',   'people',           'Employés'],
    ['categories', 'categories.view', 'tags',             'Catégories'],
    ['services',   'services.view',   'diagram-3',        'Services'],
];
$admin = [
    ['users',  'users.view',  'person-gear',  'Utilisateurs'],
    ['roles',  'roles.view',  'shield-lock',  'Rôles & permissions'],
    ['traces', 'traces.view', 'journal-text', 'Journal des traces'],
];
$link = static function (array $m) use ($module): string {
    [$mod, $perm, $icon, $label] = $m;
    if (!can($perm)) return '';
    $active = $module === $mod ? ' active' : '';
    return '<li class="nav-item"><a class="nav-link' . $active . '" href="' . e(url($mod . '.index')) . '"><i class="bi bi-' . $icon . ' me-2"></i>' . e($label) . '</a></li>';
};
$adminLinks = implode('', array_map($link, $admin));
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
    <title><?= e($title ?? '') ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/tom-select/tom-select.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm d-print-none">
    <div class="container-fluid">
        <button class="btn btn-primary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list"></i></button>
        <a class="navbar-brand fw-semibold" href="<?= e(url('dashboard.index')) ?>"><i class="bi bi-buildings me-1"></i><?= e(config('app.name')) ?></a>
        <?php if (can('biens.view')): ?>
        <form class="d-none d-md-flex ms-3 flex-grow-1" style="max-width:420px" action="index.php" method="get" role="search">
            <input type="hidden" name="r" value="biens.index">
            <div class="input-group input-group-sm">
                <input class="form-control" type="search" name="q" placeholder="Rechercher un bien (code, désignation, n° série)…" value="<?= $module === 'biens' ? e($_GET['q'] ?? '') : '' ?>">
                <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <?php endif; ?>
        <div class="ms-auto dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i><?= e($u['nom_complet'] ?? '') ?>
                <span class="badge bg-light text-primary ms-1 d-none d-sm-inline"><?= e($u['role_nom'] ?? '') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('auth.password')) ?>"><i class="bi bi-key me-2"></i>Changer mon mot de passe</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('auth.logout')) ?>"><?= csrf_field() ?>
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 p-0 bg-body-tertiary border-end sidebar d-print-none">
            <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
                <div class="offcanvas-header"><h5 class="offcanvas-title"><?= e(config('app.name')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button></div>
                <div class="offcanvas-body d-block p-2">
                    <ul class="nav nav-pills flex-column">
                        <?= implode('', array_map($link, $menu)) ?>
                    </ul>
                    <?php if ($adminLinks): ?>
                        <div class="text-uppercase small text-body-secondary fw-semibold mt-4 mb-1 px-3">Administration</div>
                        <ul class="nav nav-pills flex-column"><?= $adminLinks ?></ul>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
        <main class="col-lg-10 px-md-4 py-3">
            <?php foreach (flashes() as [$type, $msg]): ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-print-none" role="alert">
                    <?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<!-- Modale de confirmation (suppression, validation...) -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="confirmMessage">Êtes-vous sûr ?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmOk">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/tom-select/tom-select.complete.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
