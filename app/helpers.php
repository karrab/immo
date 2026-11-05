<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

// ---------------------------------------------------------------------
// Énumérations métier
// ---------------------------------------------------------------------
const STATUTS_BIEN = [
    'en_service'    => ['En service',    'success'],
    'en_stock'      => ['En stock',      'secondary'],
    'en_reparation' => ['En réparation', 'warning'],
    'reforme'       => ['Réformé',       'danger'],
    'cede'          => ['Cédé',          'dark'],
];
const ETATS_BIEN = [
    'neuf'         => ['Neuf',         'primary'],
    'bon'          => ['Bon',          'success'],
    'moyen'        => ['Moyen',        'info'],
    'mauvais'      => ['Mauvais',      'warning'],
    'hors_service' => ['Hors service', 'danger'],
];
const TYPES_LOCAL = [
    'bureau'        => 'Bureau',
    'salle_reunion' => 'Salle de réunion',
    'magasin'       => 'Magasin',
    'atelier'       => 'Atelier',
    'archive'       => 'Archives',
    'laboratoire'   => 'Laboratoire / technique',
    'autre'         => 'Autre',
];
const STATUTS_TRANSFERT = [
    'en_attente' => ['En attente', 'warning'],
    'valide'     => ['Validé',     'success'],
    'rejete'     => ['Rejeté',     'danger'],
    'annule'     => ['Annulé',     'secondary'],
];
const TYPES_EMPLOYE = [
    'employe'      => ['Employé',          'secondary'],
    'chef_service' => ['Chef de service',  'primary'],
];
const METHODES_AMORT = ['lineaire' => 'Linéaire', 'degressif' => 'Dégressif'];
const EVENEMENTS_BIEN = [
    'creation'          => ['Création',          'primary',   'plus-circle'],
    'modification'      => ['Modification',      'info',      'pencil'],
    'transfert'         => ['Transfert',         'warning',   'arrow-left-right'],
    'affectation'       => ['Affectation',       'success',   'person-check'],
    'changement_statut' => ['Changement statut', 'danger',    'flag'],
    'changement_etat'   => ['Changement état',   'secondary', 'activity'],
];
const MODULES = [
    'dashboard'  => 'Tableau de bord',
    'biens'      => 'Biens',
    'categories' => 'Catégories',
    'services'   => 'Services',
    'locaux'     => 'Locaux',
    'employes'   => 'Employés',
    'transferts' => 'Transferts',
    'users'      => 'Utilisateurs',
    'roles'      => 'Rôles & permissions',
    'traces'     => 'Traces',
    'auth'       => 'Authentification',
];

// ---------------------------------------------------------------------
// Fonctions utilitaires
// ---------------------------------------------------------------------
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function url(string $route, array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    return 'index.php?' . http_build_query(['r' => $route] + $params);
}

/** URL courante en remplaçant / retirant des paramètres GET. */
function url_with(array $overrides = [], array $remove = []): string
{
    $q = $_GET;
    foreach ($remove as $k) {
        unset($q[$k]);
    }
    $q = array_merge($q, $overrides);
    $q = array_filter($q, static fn($v) => $v !== null && $v !== '');
    return 'index.php?' . http_build_query($q);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    return 'assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function csrf_field(): string
{
    return Csrf::field();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function user(): ?array
{
    return Auth::user();
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = [$type, $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function money(mixed $v, bool $withDevise = true): string
{
    return number_format((float) $v, 2, ',', ' ') . ($withDevise ? ' ' . config('app.devise') : '');
}

function date_fr(?string $d): string
{
    if (!$d) {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '';
}

function datetime_fr(?string $d): string
{
    if (!$d) {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y H:i', $ts) : '';
}

function badge(array $map, ?string $key): string
{
    if ($key === null || !isset($map[$key])) {
        return '';
    }
    [$label, $color] = $map[$key];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}

function label(array $map, ?string $key): string
{
    if ($key === null || !isset($map[$key])) {
        return '';
    }
    return is_array($map[$key]) ? $map[$key][0] : $map[$key];
}

/** Génère des <option> à partir d'un tableau valeur => libellé. */
function options(array $items, mixed $selected = null, ?string $placeholder = null): string
{
    $html = $placeholder !== null ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($items as $value => $lbl) {
        $lbl = is_array($lbl) ? $lbl[0] : $lbl;
        $sel = $selected !== null && (string) $selected === (string) $value ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($lbl) . '</option>';
    }
    return $html;
}

/** Message d'erreur de validation pour un champ (Bootstrap invalid-feedback). */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
}

function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/** Lien de tri pour l'en-tête d'un tableau. */
function sort_link(string $column, string $label): string
{
    $current = $_GET['sort'] ?? '';
    $dir = strtolower($_GET['dir'] ?? 'desc');
    $newDir = ($current === $column && $dir === 'desc') ? 'asc' : 'desc';
    $icon = $current === $column ? ($dir === 'asc' ? ' <i class="bi bi-sort-up"></i>' : ' <i class="bi bi-sort-down"></i>') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(url_with(['sort' => $column, 'dir' => $newDir], ['after', 'before'])) . '">' . e($label) . $icon . '</a>';
}

/** Politique de mot de passe : retourne un message d'erreur ou null. */
function password_policy_error(string $p): ?string
{
    if (mb_strlen($p) < 8) {
        return 'Au moins 8 caractères.';
    }
    if (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/\d/', $p)) {
        return 'Doit contenir une majuscule, une minuscule et un chiffre.';
    }
    return null;
}

/** Inclut une vue partielle. */
function partial(string $name, array $data = []): string
{
    return App\Core\View::fetch('partials/' . $name, $data);
}
