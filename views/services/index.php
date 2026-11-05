<?= partial('page_header', ['title' => $title, 'icon' => 'diagram-3',
    'actions' => can('services.create') ? '<a class="btn btn-primary" href="' . e(url('services.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau service</a>' : '']) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="services.index">
    <div class="input-group"><input class="form-control" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Code ou nom">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Nom</th><th>Description</th><th class="text-end">Locaux</th><th class="text-end">Employés</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $s): ?>
        <tr><td class="fw-semibold"><?= e($s['code']) ?></td><td><?= e($s['nom']) ?></td><td class="small text-body-secondary"><?= e($s['description']) ?></td>
            <td class="text-end"><a href="<?= e(url('locaux.index', ['service_id' => $s['id']])) ?>"><?= (int) $s['nb_locaux'] ?></a></td>
            <td class="text-end"><a href="<?= e(url('employes.index', ['service_id' => $s['id']])) ?>"><?= (int) $s['nb_employes'] ?></a></td>
            <td class="text-end text-nowrap">
                <?php if (can('services.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('services.edit', ['id' => $s['id']])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('services.delete')): ?><?= partial('delete', ['route' => 'services.delete', 'id' => $s['id'], 'label' => $s['nom']]) ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="6" class="text-center text-body-secondary py-4">Aucun service.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page]) ?>
