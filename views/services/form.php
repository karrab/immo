<?php $isEdit = !empty($item['id']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'diagram-3', 'actions' => '<a class="btn btn-outline-secondary" href="' . e($isEdit ? url('services.show', ['id' => $item['id']]) : url('services.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card" style="max-width: 820px"><div class="card-body">
<form method="post" action="<?= e(url($isEdit ? 'services.update' : 'services.store')) ?>">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Code *</label><input class="form-control text-uppercase<?= invalid($errors, 'code') ?>" name="code" value="<?= e($item['code'] ?? '') ?>" maxlength="20" required><?= field_error($errors, 'code') ?></div>
        <div class="col-md-8"><label class="form-label">Nom *</label><input class="form-control<?= invalid($errors, 'nom') ?>" name="nom" value="<?= e($item['nom'] ?? '') ?>" maxlength="150" required><?= field_error($errors, 'nom') ?></div>
        <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($item['description'] ?? '') ?>" maxlength="255"></div>
        <div class="col-12">
            <label class="form-label">Chef de service</label>
            <?php if ($isEdit): ?>
                <select class="form-select js-select<?= invalid($errors, 'chef_id') ?>" name="chef_id">
                    <option value="">— Aucun chef —</option>
                    <?php foreach ($candidats as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) ($item['chef_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nom_complet'] . ' (' . $c['matricule'] . ')' . ($c['fonction'] ? ' — ' . $c['fonction'] : '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'chef_id') ?>
                <div class="form-text">Choisi parmi les employés actifs du service. L'ancien chef redevient simple employé. Le chef voit tous les biens du service et valide / rejette / annule les transferts qui en sortent.</div>
            <?php else: ?>
                <div class="form-text">Le chef se désigne après la création, une fois des employés rattachés au service.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('services.index')) ?>">Annuler</a></div>
</form>
</div></div>
