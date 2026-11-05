<?php use App\Core\Ref; $f = $filters;
$actions = (can('transferts.export') ? partial('export', ['route' => 'transferts.export']) : '')
    . (can('transferts.create') ? '<a class="btn btn-primary" href="' . e(url('transferts.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau transfert</a>' : ''); ?>
<?= partial('page_header', ['title' => $title, 'icon' => 'arrow-left-right', 'actions' => $actions]) ?>
<form class="card card-body mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="transferts.index">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small mb-1">N° transfert</label><input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="TR-…"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Code bien</label><input class="form-control" name="bien" value="<?= e($f['bien']) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Statut</label><select class="form-select" name="statut"><?= options(STATUTS_TRANSFERT, $f['statut'], 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Local (source ou dest.)</label><select class="form-select js-select" name="local_id"><?= options(Ref::pairs(Ref::locaux(), 'code'), $f['local_id'], 'Tous') ?></select></div>
        <div class="col-md-1"><label class="form-label small mb-1">Du</label><input type="date" class="form-control" name="date_du" value="<?= e($f['date_du']) ?>"></div>
        <div class="col-md-1"><label class="form-label small mb-1">Au</label><input type="date" class="form-control" name="date_au" value="<?= e($f['date_au']) ?>"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filtrer</button><a class="btn btn-outline-secondary" href="<?= e(url('transferts.index')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>N°</th><th>Date</th><th>Local source</th><th></th><th>Local destination</th><th class="text-end">Biens</th><th>Motif</th><th>Statut</th><th>Demandé par</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $t): ?>
        <tr>
            <td class="fw-semibold text-nowrap"><a href="<?= e(url('transferts.show', ['id' => $t['id']])) ?>"><?= e($t['numero']) ?></a></td>
            <td class="text-nowrap"><?= date_fr($t['date_transfert']) ?></td>
            <td class="small"><?= e($t['source_code']) ?><div class="text-body-secondary"><?= e($t['source_nom']) ?></div></td>
            <td><i class="bi bi-arrow-right text-primary"></i></td>
            <td class="small"><?= e($t['dest_code']) ?><div class="text-body-secondary"><?= e($t['dest_nom']) ?></div></td>
            <td class="text-end"><?= (int) $t['nb_biens'] ?></td>
            <td class="small"><?= e($t['motif']) ?></td>
            <td><?= badge(STATUTS_TRANSFERT, $t['statut']) ?></td>
            <td class="small"><?= e($t['demandeur']) ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('transferts.show', ['id' => $t['id']])) ?>"><i class="bi bi-eye"></i></a>
                <?php if (can('transferts.export')): ?><a class="btn btn-sm btn-outline-dark" target="_blank" href="<?= e(url('transferts.pdf', ['id' => $t['id'], 'inline' => 1])) ?>" title="Imprimer le bon"><i class="bi bi-printer"></i></a><?php endif; ?>
                <?php if (can('transferts.delete') && $t['statut'] !== 'valide'): ?><?= partial('delete', ['route' => 'transferts.delete', 'id' => $t['id'], 'label' => $t['numero']]) ?><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="10" class="text-center text-body-secondary py-4">Aucun transfert.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
