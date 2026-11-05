<?= partial('page_header', ['title' => $title, 'icon' => 'key']) ?>
<div class="card" style="max-width: 520px">
    <div class="card-body">
        <form method="post" action="<?= e(url('auth.password')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Mot de passe actuel</label>
                <input type="password" name="current" class="form-control<?= invalid($errors, 'current') ?>" required><?= field_error($errors, 'current') ?></div>
            <div class="mb-3"><label class="form-label">Nouveau mot de passe</label>
                <input type="password" name="new" class="form-control<?= invalid($errors, 'new') ?>" required><?= field_error($errors, 'new') ?>
                <div class="form-text">8 caractères minimum, avec majuscule, minuscule et chiffre.</div></div>
            <div class="mb-3"><label class="form-label">Confirmation</label>
                <input type="password" name="confirm" class="form-control<?= invalid($errors, 'confirm') ?>" required><?= field_error($errors, 'confirm') ?></div>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
        </form>
    </div>
</div>
