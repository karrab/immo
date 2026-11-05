<?php use App\Core\Ref; $isEdit = !empty($item['id']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'person-gear', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('users.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card" style="max-width: 900px"><div class="card-body">
<form method="post" action="<?= e(url($isEdit ? 'users.update' : 'users.store')) ?>" autocomplete="off">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Identifiant *</label><input class="form-control<?= invalid($errors, 'username') ?>" name="username" value="<?= e($item['username'] ?? '') ?>" maxlength="60" required><?= field_error($errors, 'username') ?></div>
        <div class="col-md-8"><label class="form-label">Nom complet *</label><input class="form-control<?= invalid($errors, 'nom_complet') ?>" name="nom_complet" value="<?= e($item['nom_complet'] ?? '') ?>" maxlength="150" required><?= field_error($errors, 'nom_complet') ?></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control<?= invalid($errors, 'email') ?>" name="email" value="<?= e($item['email'] ?? '') ?>" maxlength="150"><?= field_error($errors, 'email') ?></div>
        <div class="col-md-6"><label class="form-label">Rôle *</label><select class="form-select<?= invalid($errors, 'role_id') ?>" name="role_id" required><?= options(Ref::pairs(Ref::roles(), 'nom'), $item['role_id'] ?? null, '— Choisir —') ?></select><?= field_error($errors, 'role_id') ?></div>
        <div class="col-md-6"><label class="form-label">Employé lié</label><select class="form-select js-select<?= invalid($errors, 'employe_id') ?>" name="employe_id"><?= options(Ref::pairs(Ref::employes(), static fn($e) => $e['nom_complet'] . ' (' . $e['matricule'] . ')'), $item['employe_id'] ?? null, '— Aucun —') ?></select><?= field_error($errors, 'employe_id') ?>
            <div class="form-text">Détermine le périmètre : un <strong>employé</strong> voit ses biens, un <strong>chef de service</strong> voit les biens de son service et valide ses transferts (sauf si le rôle a « voir tous les biens »).</div></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input<?= invalid($errors, 'actif') ?>" type="checkbox" name="actif" value="1" id="actif" <?= !empty($item['actif']) ? 'checked' : '' ?>><label class="form-check-label" for="actif">Compte actif</label><?= field_error($errors, 'actif') ?></div></div>
        <div class="col-12"><hr><h6><?= $isEdit ? 'Réinitialiser le mot de passe <small class="text-body-secondary">(laisser vide pour conserver)</small>' : 'Mot de passe' ?></h6></div>
        <div class="col-md-6"><label class="form-label">Mot de passe<?= $isEdit ? '' : ' *' ?></label><input type="password" class="form-control<?= invalid($errors, 'password') ?>" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>><?= field_error($errors, 'password') ?>
            <div class="form-text">8 caractères minimum, avec majuscule, minuscule et chiffre.</div></div>
        <div class="col-md-6"><label class="form-label">Confirmation</label><input type="password" class="form-control" name="password_confirm" autocomplete="new-password"></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('users.index')) ?>">Annuler</a></div>
</form>
</div></div>
