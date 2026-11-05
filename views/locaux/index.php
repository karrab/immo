<?php use App\Core\Ref; $f = $filters;
$actions = (can('locaux.export') ? partial('export', ['route' => 'locaux.export']) : '')
    . (can('locaux.create') ? '<a class="btn btn-primary" href="' . e(url('locaux.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau local</a>' : ''); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'door-open', 'actions' => $actions]) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="locaux.index">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Recherche</label><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Code ou nom"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Type</label><select class="form-select" name="type"><?= options(TYPES_LOCAL, $f['type'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Bâtiment</label><select class="form-select" name="batiment"><?= options(array_combine($batiments, $batiments) ?: [], $f['batiment'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Service</label><select class="form-select" name="service_id"><?= options(Ref::pairs(Ref::services(), 'nom'), $f['service_id'], 'Tous') ?></select></div>
        <div class="col-md-1"><label class="form-label small mb-1">Actif</label><select class="form-select" name="actif"><?= options(['1' => 'Oui', '0' => 'Non'], $f['actif'], 'Tous') ?></select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filtrer</button><a class="btn btn-outline-secondary" href="<?= e(url('locaux.index')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Bâtiment / étage</th><th>Service</th><th class="text-end">Biens</th><th class="text-end">Valeur</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $l): ?>
        <tr class="<?= $l['actif'] ? '' : 'text-body-secondary' ?>">
            <td class="fw-semibold"><a href="<?= e(url('locaux.show', ['id' => $l['id']])) ?>"><?= e($l['code']) ?></a></td>
            <td><?= e($l['nom']) ?><?= $l['actif'] ? '' : ' <span class="badge text-bg-secondary">inactif</span>' ?></td>
            <td class="small"><?= e(TYPES_LOCAL[$l['type']] ?? $l['type']) ?></td>
            <td class="small"><?= e($l['batiment']) ?><?= $l['etage'] !== null ? ' · ét. ' . e($l['etage']) : '' ?></td>
            <td class="small"><?= e($l['service']) ?></td>
            <td class="text-end"><?= (int) $l['nb_biens'] ?></td>
            <td class="text-end text-nowrap"><?= money($l['valeur_biens'], false) ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('locaux.show', ['id' => $l['id']])) ?>"><i class="bi bi-eye"></i></a>
                <?php if (can('locaux.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('locaux.edit', ['id' => $l['id']])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('locaux.export')): ?><a class="btn btn-sm btn-outline-dark" target="_blank" href="<?= e(url('locaux.inventaire', ['id' => $l['id'], 'inline' => 1])) ?>" title="Imprimer l'inventaire du local"><i class="bi bi-printer"></i></a><?php endif; ?>
                <?php if (can('locaux.delete')): ?><?= partial('delete', ['route' => 'locaux.delete', 'id' => $l['id'], 'label' => $l['code']]) ?><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">Aucun local.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
