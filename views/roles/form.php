<?php $isEdit = !empty($item['id']); $super = !empty($item['is_super']); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'shield-lock', 'actions' => '<a class="btn btn-outline-secondary" href="' . e(url('roles.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<form method="post" action="<?= e(url($isEdit ? 'roles.update' : 'roles.store')) ?>">
    <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Nom du rôle *</label><input class="form-control<?= invalid($errors, 'nom') ?>" name="nom" value="<?= e($item['nom'] ?? '') ?>" maxlength="80" required><?= field_error($errors, 'nom') ?></div>
        <div class="col-md-8"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($item['description'] ?? '') ?>" maxlength="255"></div>
    </div></div>
    <?php if ($super): ?><div class="alert alert-info"><i class="bi bi-info-circle"></i> Ce rôle est super-administrateur : il dispose implicitement de toutes les permissions.</div><?php endif; ?>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-ui-checks-grid me-1"></i> Matrice des permissions</span>
        <div><button type="button" class="btn btn-sm btn-outline-primary" onclick="document.querySelectorAll('.perm').forEach(c=>c.checked=true)">Tout cocher</button>
             <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm').forEach(c=>c.checked=false)">Tout décocher</button></div></div>
        <div class="card-body perm-matrix"><div class="row g-3">
        <?php foreach ($modules as $module => $perms): ?>
            <div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100">
                <div class="d-flex justify-content-between mb-2"><strong><?= e(MODULES[$module] ?? $module) ?></strong>
                    <input class="form-check-input" type="checkbox" title="Tout le module" onchange="this.closest('.border').querySelectorAll('.perm').forEach(c=>c.checked=this.checked)"></div>
                <?php foreach ($perms as $p): ?>
                    <div class="form-check"><input class="form-check-input perm" type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" id="p<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $selected, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="p<?= (int) $p['id'] ?>"><?= e($p['libelle']) ?> <code class="text-body-secondary"><?= e($p['code']) ?></code></label></div>
                <?php endforeach; ?>
            </div></div>
        <?php endforeach; ?>
        </div></div>
    </div>
    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button> <a class="btn btn-outline-secondary" href="<?= e(url('roles.index')) ?>">Annuler</a>
</form>
