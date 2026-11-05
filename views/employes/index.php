<?php use App\Core\Ref; $f = $filters;
$actions = (can('employes.export') ? partial('export', ['route' => 'employes.export']) : '')
    . (can('employes.create') ? '<a class="btn btn-primary" href="' . e(url('employes.create')) . '"><i class="bi bi-plus-lg"></i> Nouvel employé</a>' : ''); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'people', 'actions' => $actions]) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="employes.index">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Recherche</label><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Matricule, nom, prénom, fonction"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Service</label><select class="form-select" name="service_id"><?= options(Ref::pairs(Ref::services(), 'nom'), $f['service_id'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Local</label><select class="form-select js-select" name="local_id"><?= options(Ref::pairs(Ref::locaux(), 'code'), $f['local_id'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Actif</label><select class="form-select" name="actif"><?= options(['1' => 'Oui', '0' => 'Non'], $f['actif'], 'Tous') ?></select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filtrer</button><a class="btn btn-outline-secondary" href="<?= e(url('employes.index')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th><?= sort_link('matricule', 'Matricule') ?></th><th><?= sort_link('nom', 'Nom & prénom') ?></th><th>Fonction</th><th>Service</th><th>Local</th><th class="text-end">Biens</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $e): ?>
        <tr class="<?= $e['actif'] ? '' : 'text-body-secondary' ?>">
            <td class="fw-semibold"><?= e($e['matricule']) ?></td>
            <td><a href="<?= e(url('employes.show', ['id' => $e['id']])) ?>"><?= e($e['nom'] . ' ' . $e['prenom']) ?></a><?= $e['actif'] ? '' : ' <span class="badge text-bg-secondary">inactif</span>' ?></td>
            <td class="small"><?= e($e['fonction']) ?></td><td class="small"><?= e($e['service']) ?></td>
            <td class="small"><?= e($e['local_code']) ?></td>
            <td class="text-end"><a href="<?= e(url('biens.index', ['employe_id' => $e['id']])) ?>"><?= (int) $e['nb_biens'] ?></a></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('employes.show', ['id' => $e['id']])) ?>"><i class="bi bi-eye"></i></a>
                <?php if (can('employes.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('employes.edit', ['id' => $e['id']])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('employes.export')): ?><a class="btn btn-sm btn-outline-dark" target="_blank" href="<?= e(url('employes.decharge', ['id' => $e['id'], 'inline' => 1])) ?>" title="Fiche de détention"><i class="bi bi-printer"></i></a><?php endif; ?>
                <?php if (can('employes.delete')): ?><?= partial('delete', ['route' => 'employes.delete', 'id' => $e['id'], 'label' => $e['nom'] . ' ' . $e['prenom']]) ?><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">Aucun employé.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
