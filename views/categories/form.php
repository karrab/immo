<?php $isEdit = !empty($item['id']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'tags', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('categories.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card" style="max-width: 760px"><div class="card-body">
<form method="post" action="<?= e(url($isEdit ? 'categories.update' : 'categories.store')) ?>">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Code *</label><input class="form-control text-uppercase<?= invalid($errors, 'code') ?>" name="code" value="<?= e($item['code'] ?? '') ?>" maxlength="20" required><?= field_error($errors, 'code') ?></div>
        <div class="col-md-8"><label class="form-label">Libellé *</label><input class="form-control<?= invalid($errors, 'libelle') ?>" name="libelle" value="<?= e($item['libelle'] ?? '') ?>" maxlength="150" required><?= field_error($errors, 'libelle') ?></div>
        <div class="col-md-4"><label class="form-label">Compte d'immobilisation</label><input class="form-control" name="compte_comptable" value="<?= e($item['compte_comptable'] ?? '') ?>" maxlength="20"></div>
        <div class="col-md-4"><label class="form-label">Compte d'amortissement</label><input class="form-control" name="compte_amortissement" value="<?= e($item['compte_amortissement'] ?? '') ?>" maxlength="20"></div>
        <div class="col-md-4"><label class="form-label">Durée d'amortissement (ans) *</label><input type="number" min="1" max="100" class="form-control<?= invalid($errors, 'duree_amortissement') ?>" name="duree_amortissement" value="<?= e($item['duree_amortissement'] ?? '') ?>" required><?= field_error($errors, 'duree_amortissement') ?></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('categories.index')) ?>">Annuler</a></div>
</form>
</div></div>
