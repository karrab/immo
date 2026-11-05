<?php use App\Core\Ref; $isEdit = !empty($item['id']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'person', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('employes.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card" style="max-width: 900px"><div class="card-body">
<form method="post" action="<?= e(url($isEdit ? 'employes.update' : 'employes.store')) ?>">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Matricule *</label><input class="form-control text-uppercase<?= invalid($errors, 'matricule') ?>" name="matricule" value="<?= e($item['matricule'] ?? '') ?>" maxlength="30" required><?= field_error($errors, 'matricule') ?></div>
        <div class="col-md-4"><label class="form-label">Nom *</label><input class="form-control<?= invalid($errors, 'nom') ?>" name="nom" value="<?= e($item['nom'] ?? '') ?>" maxlength="80" required><?= field_error($errors, 'nom') ?></div>
        <div class="col-md-5"><label class="form-label">Prénom *</label><input class="form-control<?= invalid($errors, 'prenom') ?>" name="prenom" value="<?= e($item['prenom'] ?? '') ?>" maxlength="80" required><?= field_error($errors, 'prenom') ?></div>
        <div class="col-md-6"><label class="form-label">Type *</label>
            <select class="form-select<?= invalid($errors, 'type') ?>" name="type"><?= options(TYPES_EMPLOYE, $item['type'] ?? 'employe') ?></select><?= field_error($errors, 'type') ?>
            <div class="form-text">Le <strong>chef de service</strong> voit tous les biens de son service et valide / rejette / annule les transferts sortant de son service (un seul chef par service). L'<strong>employé</strong> ne voit que ses biens.</div></div>
        <div class="col-md-6"><label class="form-label">Fonction</label><input class="form-control" name="fonction" value="<?= e($item['fonction'] ?? '') ?>" maxlength="120"></div>
        <div class="col-md-6"><label class="form-label">Date d'embauche</label><input type="date" class="form-control" name="date_embauche" value="<?= e($item['date_embauche'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Service</label><select class="form-select<?= invalid($errors, 'service_id') ?>" name="service_id"><?= options(Ref::pairs(Ref::services(), 'nom'), $item['service_id'] ?? null, '— Aucun —') ?></select><?= field_error($errors, 'service_id') ?></div>
        <div class="col-md-6"><label class="form-label">Bureau / local</label><select class="form-select js-select<?= invalid($errors, 'local_id') ?>" name="local_id"><?= options(Ref::pairs(Ref::locaux(), [Ref::class, 'localLabel']), $item['local_id'] ?? null, '— Aucun —') ?></select><?= field_error($errors, 'local_id') ?></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control<?= invalid($errors, 'email') ?>" name="email" value="<?= e($item['email'] ?? '') ?>" maxlength="150"><?= field_error($errors, 'email') ?></div>
        <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" value="<?= e($item['telephone'] ?? '') ?>" maxlength="30"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" <?= !empty($item['actif']) ? 'checked' : '' ?>><label class="form-check-label" for="actif">Employé actif</label></div></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('employes.index')) ?>">Annuler</a></div>
</form>
</div></div>
