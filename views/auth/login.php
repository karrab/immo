<div class="card mx-auto shadow-lg" style="max-width: 420px">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="display-5 text-primary"><i class="bi bi-buildings"></i></div>
            <h1 class="h4 mb-0"><?= e(config('app.name')) ?></h1>
            <p class="text-body-secondary small">Gestion des immobilisations</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('auth.login')) ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Identifiant</label>
                <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input class="form-control" id="username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username"></div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Mot de passe</label>
                <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
            </div>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Se connecter</button>
        </form>
    </div>
</div>
