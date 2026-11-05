<?php
/**
 * Installation depuis le navigateur : http://localhost/immo/public/install.php
 *  1. connexion au serveur MySQL, 2. création de la base et des tables,
 *  3. écriture de config/config.local.php, 4. verrouillage (storage/install.lock).
 * Une fois l'installation faite, cette page est désactivée.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Installer;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$done = Installer::isInstalled();
$errors = [];
$log = [];
$configWritten = null;
$configContent = null;

$timezones = ['Africa/Casablanca' => 'Maroc', 'Africa/Algiers' => 'Algérie', 'Africa/Tunis' => 'Tunisie', 'Europe/Paris' => 'France', 'UTC' => 'UTC'];
$f = [
    'host' => '127.0.0.1', 'port' => '3306', 'name' => 'gestimmo', 'user' => 'root', 'pass' => '',
    'organisation' => 'Mon Organisation', 'devise' => 'DH', 'timezone' => 'Africa/Casablanca',
    'demo' => '1', 'overwrite' => '', 'admin_pass' => '',
];

// Vérification de l'environnement
$checks = [
    ['PHP ≥ 8.1 (actuel : ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true],
    ['Extension pdo_mysql', extension_loaded('pdo_mysql'), true],
    ['Extension mbstring', extension_loaded('mbstring'), true],
    ['Dossier config/ accessible en écriture', is_writable(BASE_PATH . '/config'), false],
    ['Dossier storage/ accessible en écriture', is_writable(BASE_PATH . '/storage'), true],
    ['Dompdf installé (composer install) — sinon PDF via impression navigateur', class_exists(\Dompdf\Dompdf::class), false],
    ['APCu (cache) — recommandé', function_exists('apcu_enabled') && apcu_enabled(), false],
    ['OPcache — recommandé', function_exists('opcache_get_status') && (bool) (@opcache_get_status(false)['opcache_enabled'] ?? false), false],
];
$blocking = array_filter($checks, static fn($c) => $c[2] && !$c[1]);

if (!$done && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($f as $k => $v) {
        $f[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
    }
    $f['pass'] = (string) ($_POST['pass'] ?? '');            // pas de trim sur un mot de passe
    $f['admin_pass'] = (string) ($_POST['admin_pass'] ?? '');

    if (!Csrf::verify()) $errors[] = 'Session expirée : rechargez la page.';
    if ($blocking) $errors[] = 'Corrigez d\'abord les prérequis en rouge.';
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $f['name'])) $errors[] = 'Nom de base invalide (lettres, chiffres, _).';
    if (!ctype_digit($f['port'])) $errors[] = 'Port invalide.';
    if (!isset($timezones[$f['timezone']])) $f['timezone'] = 'UTC';
    if ($f['admin_pass'] !== '' && ($msg = password_policy_error($f['admin_pass']))) $errors[] = 'Mot de passe admin : ' . $msg;

    if (!$errors) {
        try {
            $pdo = Installer::connect($f['host'], (int) $f['port'], $f['user'], $f['pass']);
            if (Installer::hasTables($pdo, $f['name']) && $f['overwrite'] !== '1') {
                $errors[] = "La base « {$f['name']} » contient déjà une installation. Cochez « Écraser » pour la réinitialiser (toutes les données seront perdues) ou choisissez un autre nom.";
            } else {
                $log = Installer::install($pdo, $f['name'], $f['demo'] === '1', $f['admin_pass'] ?: null);
                $configContent = Installer::configFile(
                    ['host' => $f['host'], 'port' => (int) $f['port'], 'name' => $f['name'], 'user' => $f['user'], 'pass' => $f['pass']],
                    ['organisation' => $f['organisation'] ?: 'Mon Organisation', 'devise' => $f['devise'] ?: 'DH', 'timezone' => $f['timezone']]
                );
                $configWritten = @file_put_contents(BASE_PATH . '/config/config.local.php', $configContent) !== false;
                Installer::lock();
                $done = true;
            }
        } catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? $e->getCode();
            $errors[] = match ((int) $code) {
                1045    => 'Accès refusé : utilisateur ou mot de passe MySQL incorrect.',
                2002    => 'Impossible de joindre MySQL : le serveur est-il démarré (XAMPP → Start MySQL) ? Hôte/port corrects ?',
                1044    => "Cet utilisateur MySQL n'a pas le droit de créer ou d'utiliser cette base.",
                default => 'Erreur MySQL : ' . $e->getMessage(),
            };
        } catch (Throwable $e) {
            $errors[] = 'Erreur : ' . $e->getMessage();
        }
    }
}
$v = static fn(string $k) => htmlspecialchars($f[$k], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Installation · GestImmo</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
</head>
<body class="bg-body-tertiary">
<div class="container py-4" style="max-width: 820px">
    <div class="text-center mb-4">
        <div class="display-5 text-primary"><i class="bi bi-buildings"></i></div>
        <h1 class="h3">Installation de GestImmo</h1>
        <p class="text-body-secondary mb-0">Gestion des immobilisations</p>
    </div>

    <?php if ($done && $log): ?>
        <div class="card shadow-sm"><div class="card-body p-4">
            <h2 class="h5 text-success"><i class="bi bi-check-circle-fill"></i> Installation terminée</h2>
            <ul class="list-unstyled mb-3"><?php foreach ($log as $l): ?><li><i class="bi bi-check text-success"></i> <?= e($l) ?></li><?php endforeach; ?></ul>
            <?php if ($configWritten): ?>
                <p><i class="bi bi-check text-success"></i> Configuration enregistrée dans <code>config/config.local.php</code>.</p>
            <?php else: ?>
                <div class="alert alert-warning">Le fichier <code>config/config.local.php</code> n'a pas pu être écrit (droits du dossier).
                    Créez-le vous-même avec ce contenu :</div>
                <textarea class="form-control font-monospace small mb-3" rows="12" readonly><?= e($configContent) ?></textarea>
            <?php endif; ?>
            <div class="alert alert-info mb-3">
                Connexion : <strong>admin</strong> / <strong><?= $f['admin_pass'] !== '' ? 'le mot de passe choisi' : 'Admin@123' ?></strong>
                <?php if ($f['demo'] === '1'): ?><br>Comptes de démonstration (mot de passe <code>Demo@1234</code>) : <code>samira</code> et <code>nadia</code> (chefs de service), <code>omar</code> (employé).<?php endif; ?>
            </div>
            <p class="small text-body-secondary">Cette page est maintenant désactivée. Pour réinstaller, supprimez <code>storage/install.lock</code>.</p>
            <a class="btn btn-primary btn-lg" href="index.php"><i class="bi bi-box-arrow-in-right"></i> Ouvrir l'application</a>
        </div></div>

    <?php elseif ($done): ?>
        <div class="card shadow-sm"><div class="card-body p-4 text-center">
            <h2 class="h5"><i class="bi bi-lock"></i> L'application est déjà installée</h2>
            <p class="text-body-secondary">Pour des raisons de sécurité, l'installateur est désactivé.<br>
                Pour réinstaller, supprimez le fichier <code>storage/install.lock</code> puis rechargez cette page.</p>
            <a class="btn btn-primary" href="index.php">Ouvrir l'application</a>
        </div></div>

    <?php else: ?>
        <div class="card shadow-sm mb-3"><div class="card-header fw-semibold"><i class="bi bi-list-check"></i> 1. Vérification du serveur</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($checks as [$label, $ok, $required]): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= e($label) ?></span>
                        <?php if ($ok): ?><span class="badge text-bg-success">OK</span>
                        <?php elseif ($required): ?><span class="badge text-bg-danger">Requis</span>
                        <?php else: ?><span class="badge text-bg-warning">Facultatif</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" class="card shadow-sm" autocomplete="off">
            <?= csrf_field() ?>
            <div class="card-header fw-semibold"><i class="bi bi-database"></i> 2. Connexion MySQL / MariaDB</div>
            <div class="card-body row g-3">
                <div class="col-md-8"><label class="form-label">Serveur</label><input class="form-control" name="host" value="<?= $v('host') ?>" required></div>
                <div class="col-md-4"><label class="form-label">Port</label><input class="form-control" name="port" value="<?= $v('port') ?>" required></div>
                <div class="col-md-4"><label class="form-label">Nom de la base</label><input class="form-control" name="name" value="<?= $v('name') ?>" required>
                    <div class="form-text">Créée si elle n'existe pas.</div></div>
                <div class="col-md-4"><label class="form-label">Utilisateur</label><input class="form-control" name="user" value="<?= $v('user') ?>" required></div>
                <div class="col-md-4"><label class="form-label">Mot de passe</label><input class="form-control" type="password" name="pass" value="<?= $v('pass') ?>">
                    <div class="form-text">XAMPP : <code>root</code> sans mot de passe.</div></div>
                <div class="col-12"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="overwrite" <?= $f['overwrite'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label text-danger" for="overwrite">Écraser une installation existante dans cette base (les données seront supprimées)</label></div></div>
            </div>
            <div class="card-header fw-semibold border-top"><i class="bi bi-gear"></i> 3. Paramètres</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label">Organisation</label><input class="form-control" name="organisation" value="<?= $v('organisation') ?>"></div>
                <div class="col-md-2"><label class="form-label">Devise</label><input class="form-control" name="devise" value="<?= $v('devise') ?>" maxlength="5"></div>
                <div class="col-md-4"><label class="form-label">Fuseau horaire</label><select class="form-select" name="timezone">
                    <?php foreach ($timezones as $tz => $lbl): ?><option value="<?= e($tz) ?>" <?= $f['timezone'] === $tz ? 'selected' : '' ?>><?= e("$lbl ($tz)") ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-md-6"><label class="form-label">Mot de passe du compte <code>admin</code></label>
                    <input class="form-control" type="password" name="admin_pass" autocomplete="new-password" placeholder="Vide = Admin@123">
                    <div class="form-text">8 caractères min. avec majuscule, minuscule et chiffre.</div></div>
                <div class="col-md-6 d-flex align-items-center"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="demo" value="1" id="demo" <?= $f['demo'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="demo">Charger les données de démonstration<br><span class="small text-body-secondary">(services, locaux, employés, biens, comptes chef / employé)</span></label></div></div>
            </div>
            <div class="card-footer bg-body d-grid">
                <button class="btn btn-primary btn-lg" <?= $blocking ? 'disabled' : '' ?>><i class="bi bi-download"></i> Installer</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
