<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '') ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="guest-bg d-flex align-items-center min-vh-100">
<div class="container">
    <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?> mx-auto" style="max-width:420px"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</div>
</body>
</html>
