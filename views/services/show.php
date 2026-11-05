<?php
$actions = '';
if (can('biens.view')) $actions .= '<a class="btn btn-outline-primary" href="' . e(url('biens.index', ['service_id' => $s['id']])) . '"><i class="bi bi-box-seam"></i> Biens du service</a>';
if (can('biens.export')) $actions .= '<a class="btn btn-outline-secondary" target="_blank" href="' . e(url('biens.export', ['service_id' => $s['id'], 'inline' => 1])) . '"><i class="bi bi-printer"></i> Imprimer les biens</a>';
if (can('employes.create')) $actions .= '<a class="btn btn-outline-primary" href="' . e(url('employes.create', ['service_id' => $s['id']])) . '"><i class="bi bi-person-plus"></i> Ajouter un employé</a>';
if (can('services.edit')) $actions .= '<a class="btn btn-primary" href="' . e(url('services.edit', ['id' => $s['id']])) . '"><i class="bi bi-pencil"></i> Modifier / chef</a>';
if (can('services.delete')) $actions .= partial('delete', ['route' => 'services.delete', 'id' => $s['id'], 'label' => $s['nom'], 'small' => false]);
?>
<?= partial('page_header', ['title' => $s['code'] . ' — ' . $s['nom'], 'icon' => 'diagram-3', 'actions' => $actions]) ?>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">Chef de service</div>
        <?php if ($s['chef_id']): ?>
            <div class="fs-5 fw-semibold"><i class="bi bi-person-badge text-primary"></i>
                <?= can('employes.view') ? '<a href="' . e(url('employes.show', ['id' => $s['chef_id']])) . '">' . e($s['chef']) . '</a>' : e($s['chef']) ?></div>
            <div class="small text-body-secondary"><?= e($s['chef_matricule']) ?> · valide les transferts du service</div>
        <?php else: ?>
            <div class="fs-6"><span class="badge text-bg-warning">Aucun chef désigné</span></div>
            <div class="small text-body-secondary">Les transferts sortant de ce service ne pourront être validés que par l'administrateur.</div>
        <?php endif; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">Biens du service</div>
        <div class="fs-5 fw-semibold"><?= number_format((int) $stats['nb'], 0, ',', ' ') ?></div>
        <div class="small">Valeur : <strong><?= money($stats['valeur']) ?></strong></div>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">Effectif / locaux</div>
        <div class="fs-5 fw-semibold"><?= (int) $s['nb_employes'] ?> employé(s) · <?= (int) $s['nb_locaux'] ?> local(aux)</div>
        <div class="small text-body-secondary"><?= e($s['description']) ?></div>
    </div></div></div>
</div>

<?php if ($enAttente && can('transferts.view')): ?>
<div class="card mb-3 border-warning"><div class="card-header bg-warning-subtle"><i class="bi bi-hourglass-split me-1"></i> Transferts en attente de validation par le chef (<?= count($enAttente) ?>)</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>N°</th><th>Date</th><th>Trajet</th><th class="text-end">Biens</th><th></th></tr></thead>
        <tbody><?php foreach ($enAttente as $t): ?>
            <tr><td><a href="<?= e(url('transferts.show', ['id' => $t['id']])) ?>"><?= e($t['numero']) ?></a></td><td><?= date_fr($t['date_transfert']) ?></td>
                <td><?= e($t['source']) ?> <i class="bi bi-arrow-right"></i> <?= e($t['destination']) ?></td><td class="text-end"><?= (int) $t['nb_biens'] ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= e(url('transferts.show', ['id' => $t['id']])) ?>">Traiter</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card"><div class="card-header"><i class="bi bi-people me-1"></i> Employés</div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Matricule</th><th>Nom</th><th>Type</th><th>Fonction</th><th class="text-end">Biens</th></tr></thead>
            <tbody>
            <?php foreach ($employes['rows'] as $e): ?>
                <tr class="<?= $e['actif'] ? '' : 'text-body-secondary' ?>"><td><?= e($e['matricule']) ?></td>
                    <td><?= can('employes.view') ? '<a href="' . e(url('employes.show', ['id' => $e['id']])) . '">' . e($e['nom'] . ' ' . $e['prenom']) . '</a>' : e($e['nom'] . ' ' . $e['prenom']) ?></td>
                    <td><?= badge(TYPES_EMPLOYE, $e['type']) ?></td><td class="small"><?= e($e['fonction']) ?></td>
                    <td class="text-end"><?= (int) $e['nb_biens'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$employes['rows']): ?><tr><td colspan="5" class="text-center text-body-secondary py-3">Aucun employé.</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
        <?= partial('pager', ['page' => $employes, 'total' => $s['nb_employes'], 'prefix' => 'e_']) ?>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-header"><i class="bi bi-door-open me-1"></i> Locaux</div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Nom</th><th class="text-end">Biens</th></tr></thead>
            <tbody>
            <?php foreach ($locaux['rows'] as $l): ?>
                <tr><td><?= can('locaux.view') ? '<a href="' . e(url('locaux.show', ['id' => $l['id']])) . '">' . e($l['code']) . '</a>' : e($l['code']) ?></td>
                    <td><?= e($l['nom']) ?><div class="small text-body-secondary"><?= e(TYPES_LOCAL[$l['type']] ?? '') ?> · <?= e($l['batiment']) ?></div></td>
                    <td class="text-end"><?= (int) $l['nb_biens'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$locaux['rows']): ?><tr><td colspan="3" class="text-center text-body-secondary py-3">Aucun local.</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
        <?= partial('pager', ['page' => $locaux, 'total' => $s['nb_locaux'], 'prefix' => 'l_']) ?>
    </div>
</div>
