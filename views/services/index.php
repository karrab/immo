<?php
$actions = partial('export', ['route' => 'services.export'])
    . (can('services.create') ? '<a class="btn btn-primary" href="' . e(url('services.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau service</a>' : '');
?>
<?= partial('page_header', ['title' => $title, 'icon' => 'diagram-3', 'actions' => $actions]) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="services.index">
    <div class="row g-2 align-items-center">
        <div class="col-md-6"><input class="form-control" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Code ou nom"></div>
        <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="sans_chef" value="1" id="sans_chef" <?= ($_GET['sans_chef'] ?? '') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="sans_chef">Services sans chef</label></div></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filtrer</button><a class="btn btn-outline-secondary" href="<?= e(url('services.index')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Nom</th><th>Chef de service</th><th class="text-end">Locaux</th><th class="text-end">Employés</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $s): ?>
        <tr><td class="fw-semibold"><a href="<?= e(url('services.show', ['id' => $s['id']])) ?>"><?= e($s['code']) ?></a></td>
            <td><?= e($s['nom']) ?><div class="small text-body-secondary"><?= e($s['description']) ?></div></td>
            <td><?php if ($s['chef_id']): ?><i class="bi bi-person-badge text-primary"></i> <?= e($s['chef']) ?> <span class="small text-body-secondary"><?= e($s['chef_matricule']) ?></span>
                <?php else: ?><span class="badge text-bg-warning">Aucun chef</span><?php endif; ?></td>
            <td class="text-end"><a href="<?= e(url('locaux.index', ['service_id' => $s['id']])) ?>"><?= (int) $s['nb_locaux'] ?></a></td>
            <td class="text-end"><a href="<?= e(url('employes.index', ['service_id' => $s['id']])) ?>"><?= (int) $s['nb_employes'] ?></a></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('services.show', ['id' => $s['id']])) ?>" title="Fiche"><i class="bi bi-eye"></i></a>
                <?php if (can('services.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('services.edit', ['id' => $s['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('services.delete')): ?><?= partial('delete', ['route' => 'services.delete', 'id' => $s['id'], 'label' => $s['nom']]) ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="6" class="text-center text-body-secondary py-4">Aucun service.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
