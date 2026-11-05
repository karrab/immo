<?php use App\Core\Ref; $f = $filters; ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'journal-text', 'actions' => can('traces.export') ? partial('export', ['route' => 'traces.export']) : '']) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="traces.index">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small mb-1">Utilisateur</label><select class="form-select" name="user_id"><?= options(Ref::pairs(Ref::users(), 'username'), $f['user_id'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Module</label><select class="form-select" name="module"><?= options(MODULES, $f['module'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Action</label><select class="form-select" name="action"><?= options(array_combine($actions, $actions) ?: [], $f['action'], 'Toutes') ?></select></div>
        <div class="col-md-1"><label class="form-label small mb-1">ID entité</label><input class="form-control" type="number" name="entite_id" value="<?= e($f['entite_id']) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Du</label><input class="form-control" type="date" name="date_du" value="<?= e($f['date_du']) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Au</label><input class="form-control" type="date" name="date_au" value="<?= e($f['date_au']) ?>"></div>
        <div class="col-md-1"><label class="form-label small mb-1">IP</label><input class="form-control" name="ip" value="<?= e($f['ip']) ?>"></div>
        <div class="col-md-6"><label class="form-label small mb-1">Description contient</label><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>"></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filtrer</button><a class="btn btn-outline-secondary" href="<?= e(url('traces.index')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>#</th><th>Date</th><th>Utilisateur</th><th>Module</th><th>Action</th><th>Description</th><th>IP</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $t): ?>
        <tr><td class="text-body-secondary"><?= (int) $t['id'] ?></td><td class="text-nowrap small"><?= datetime_fr($t['created_at']) ?></td>
            <td><?= e($t['username']) ?></td><td class="small"><?= e(MODULES[$t['module']] ?? $t['module']) ?></td>
            <td><span class="badge text-bg-light border"><?= e($t['action']) ?></span></td>
            <td class="small"><?= e($t['description']) ?></td><td class="small text-body-secondary"><?= e($t['ip']) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('traces.show', ['id' => $t['id']])) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">Aucune trace.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page]) ?>
