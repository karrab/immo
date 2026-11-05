<?php $isEdit = !empty($item['id']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'diagram-3', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('services.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card" style="max-width: 760px"><div class="card-body">
<form method="post" action="<?= e(url($isEdit ? 'services.update' : 'services.store')) ?>">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Code *</label><input class="form-control text-uppercase<?= invalid($errors, 'code') ?>" name="code" value="<?= e($item['code'] ?? '') ?>" maxlength="20" required><?= field_error($errors, 'code') ?></div>
        <div class="col-md-8"><label class="form-label">Nom *</label><input class="form-control<?= invalid($errors, 'nom') ?>" name="nom" value="<?= e($item['nom'] ?? '') ?>" maxlength="150" required><?= field_error($errors, 'nom') ?></div>
        <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($item['description'] ?? '') ?>" maxlength="255"></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('services.index')) ?>">Annuler</a></div>
</form>
</div></div>
