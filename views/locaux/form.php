<?php use App\Core\Ref; $isEdit = !empty($item['id']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'door-open', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('locaux.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card" style="max-width: 900px"><div class="card-body">
<form method="post" action="<?= e(url($isEdit ? 'locaux.update' : 'locaux.store')) ?>">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Code *</label><input class="form-control text-uppercase<?= invalid($errors, 'code') ?>" name="code" value="<?= e($item['code'] ?? '') ?>" maxlength="30" required placeholder="ex. B-A-101"><?= field_error($errors, 'code') ?></div>
        <div class="col-md-8"><label class="form-label">Nom *</label><input class="form-control<?= invalid($errors, 'nom') ?>" name="nom" value="<?= e($item['nom'] ?? '') ?>" maxlength="150" required><?= field_error($errors, 'nom') ?></div>
        <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="type"><?= options(TYPES_LOCAL, $item['type'] ?? 'bureau') ?></select></div>
        <div class="col-md-4"><label class="form-label">Bâtiment</label><input class="form-control" name="batiment" value="<?= e($item['batiment'] ?? '') ?>" maxlength="100"></div>
        <div class="col-md-2"><label class="form-label">Étage</label><input class="form-control" name="etage" value="<?= e($item['etage'] ?? '') ?>" maxlength="20"></div>
        <div class="col-md-2"><label class="form-label">Superficie (m²)</label><input type="number" step="0.01" min="0" class="form-control" name="superficie" value="<?= e($item['superficie'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Service</label><select class="form-select<?= invalid($errors, 'service_id') ?>" name="service_id"><?= options(Ref::pairs(Ref::services(), 'nom'), $item['service_id'] ?? null, '— Aucun —') ?></select><?= field_error($errors, 'service_id') ?></div>
        <div class="col-md-6"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($item['description'] ?? '') ?>" maxlength="255"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" <?= !empty($item['actif']) ? 'checked' : '' ?>><label class="form-check-label" for="actif">Local actif</label></div></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('locaux.index')) ?>">Annuler</a></div>
</form>
</div></div>
