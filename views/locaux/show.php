<?php $l = $local;
$actions = '';
if (can('biens.create')) $actions .= '<a class="btn btn-outline-primary" href="' . e(url('biens.create', ['local_id' => $l['id']])) . '"><i class="bi bi-plus-lg"></i> Ajouter un bien</a>';
if (can('transferts.create')) $actions .= '<a class="btn btn-warning" href="' . e(url('transferts.create', ['local_source_id' => $l['id']])) . '"><i class="bi bi-arrow-left-right"></i> Transférer des biens</a>';
if (can('locaux.export')) $actions .= '<a class="btn btn-outline-secondary" target="_blank" href="' . e(url('locaux.inventaire', ['id' => $l['id'], 'inline' => 1])) . '"><i class="bi bi-printer"></i> Imprimer l\'inventaire</a>'
    . '<a class="btn btn-outline-danger" href="' . e(url('locaux.inventaire', ['id' => $l['id']])) . '"><i class="bi bi-file-earmark-pdf"></i> PDF</a>';
if (can('locaux.edit')) $actions .= '<a class="btn btn-primary" href="' . e(url('locaux.edit', ['id' => $l['id']])) . '"><i class="bi bi-pencil"></i> Modifier</a>';
if (can('locaux.delete')) $actions .= partial('delete', ['route' => 'locaux.delete', 'id' => $l['id'], 'label' => $l['code'], 'small' => false]);
?>
<?= partial('page_header', ['title' => $l['code'] . ' — ' . $l['nom'], 'icon' => 'door-open', 'actions' => $actions]) ?>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card h-100"><div class="card-body"><dl class="row fiche mb-0">
        <div class="col-md-4"><dt>Type</dt><dd><?= e(TYPES_LOCAL[$l['type']] ?? $l['type']) ?></dd></div>
        <div class="col-md-4"><dt>Bâtiment / étage</dt><dd><?= e($l['batiment']) ?: '—' ?><?= $l['etage'] !== null ? ' · ' . e($l['etage']) : '' ?></dd></div>
        <div class="col-md-4"><dt>Service</dt><dd><?= e($l['service']) ?: '—' ?></dd></div>
        <div class="col-md-4"><dt>Superficie</dt><dd><?= $l['superficie'] ? e($l['superficie']) . ' m²' : '—' ?></dd></div>
        <div class="col-md-4"><dt>Statut</dt><dd><?= $l['actif'] ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Inactif</span>' ?></dd></div>
        <div class="col-md-4"><dt>Description</dt><dd><?= e($l['description']) ?: '—' ?></dd></div>
    </dl></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><span>Biens présents</span><strong><?= (int) $stats['nb'] ?></strong></div>
        <div class="d-flex justify-content-between"><span>Valeur totale</span><strong><?= money($stats['valeur']) ?></strong></div>
        <hr><div class="small fw-semibold mb-1">Occupants</div>
        <?php foreach ($employes as $e): ?><div class="small"><a href="<?= e(url('employes.show', ['id' => $e['id']])) ?>"><?= e($e['nom'] . ' ' . $e['prenom']) ?></a> <span class="text-body-secondary"><?= e($e['fonction']) ?></span></div><?php endforeach; ?>
        <?php if (!$employes): ?><div class="small text-body-secondary">Aucun</div><?php endif; ?>
    </div></div></div>
</div>
<div class="card"><div class="card-header"><i class="bi bi-box-seam me-1"></i> Biens présents dans ce local</div>
<div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Désignation</th><th>Catégorie</th><th>Détenteur</th><th class="text-end">Valeur</th><th>État</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $b): ?>
        <tr><td><a class="fw-semibold" href="<?= e(url('biens.show', ['id' => $b['id']])) ?>"><?= e($b['code_inventaire']) ?></a></td><td><?= e($b['designation']) ?></td>
            <td class="small"><?= e($b['categorie']) ?></td><td class="small"><?= e($b['employe']) ?></td>
            <td class="text-end"><?= money($b['valeur_acquisition'], false) ?></td><td><?= badge(ETATS_BIEN, $b['etat']) ?></td><td><?= badge(STATUTS_BIEN, $b['statut']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">Aucun bien dans ce local.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $stats['nb']]) ?>
