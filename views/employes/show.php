<?php $e = $emp;
$actions = '';
if (can('employes.export')) $actions .= '<a class="btn btn-outline-secondary" target="_blank" href="' . e(url('employes.decharge', ['id' => $e['id'], 'inline' => 1])) . '"><i class="bi bi-printer"></i> Fiche de détention</a>'
    . '<a class="btn btn-outline-danger" href="' . e(url('employes.decharge', ['id' => $e['id']])) . '"><i class="bi bi-file-earmark-pdf"></i> PDF</a>';
if (can('employes.edit')) $actions .= '<a class="btn btn-primary" href="' . e(url('employes.edit', ['id' => $e['id']])) . '"><i class="bi bi-pencil"></i> Modifier</a>';
if (can('employes.delete')) $actions .= partial('delete', ['route' => 'employes.delete', 'id' => $e['id'], 'label' => $e['nom'] . ' ' . $e['prenom'], 'small' => false]);
?>
<?= partial('page_header', ['title' => $e['nom'] . ' ' . $e['prenom'], 'icon' => 'person-badge', 'actions' => $actions]) ?>
<div class="card mb-3"><div class="card-body"><dl class="row fiche mb-0">
    <div class="col-md-3"><dt>Matricule</dt><dd class="fw-semibold"><?= e($e['matricule']) ?></dd></div>
    <div class="col-md-3"><dt>Type</dt><dd><?= badge(TYPES_EMPLOYE, $e['type']) ?></dd></div>
    <div class="col-md-3"><dt>Fonction</dt><dd><?= e($e['fonction']) ?: '—' ?></dd></div>
    <div class="col-md-3"><dt>Service</dt><dd><?= $e['service_id'] && can('services.view') ? '<a href="' . e(url('services.show', ['id' => $e['service_id']])) . '">' . e($e['service']) . '</a>' : (e($e['service']) ?: '—') ?></dd></div>
    <div class="col-md-3"><dt>Bureau</dt><dd><?= $e['local_id'] ? '<a href="' . e(url('locaux.show', ['id' => $e['local_id']])) . '">' . e($e['local_code'] . ' — ' . $e['local_nom']) . '</a>' : '—' ?></dd></div>
    <div class="col-md-3"><dt>Email</dt><dd><?= e($e['email']) ?: '—' ?></dd></div>
    <div class="col-md-3"><dt>Téléphone</dt><dd><?= e($e['telephone']) ?: '—' ?></dd></div>
    <div class="col-md-3"><dt>Statut</dt><dd><?= $e['actif'] ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Inactif</span>' ?>
        <span class="small text-body-secondary">· embauché le <?= date_fr($e['date_embauche']) ?: '—' ?></span></dd></div>
</dl></div></div>
<div class="card"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-box-seam me-1"></i> Biens détenus (<?= (int) $stats['nb'] ?>)</span><span>Total : <strong><?= money($stats['valeur']) ?></strong></span></div>
<div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Désignation</th><th>Local</th><th class="text-end">Valeur</th><th>État</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $b): ?>
        <tr><td><a class="fw-semibold" href="<?= e(url('biens.show', ['id' => $b['id']])) ?>"><?= e($b['code_inventaire']) ?></a></td><td><?= e($b['designation']) ?></td>
            <td class="small"><?= e($b['local_code']) ?></td><td class="text-end"><?= money($b['valeur_acquisition'], false) ?></td>
            <td><?= badge(ETATS_BIEN, $b['etat']) ?></td><td><?= badge(STATUTS_BIEN, $b['statut']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="6" class="text-center text-body-secondary py-4">Aucun bien affecté.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= partial('pager', ['page' => $page, 'total' => $stats['nb']]) ?>
