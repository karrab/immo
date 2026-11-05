<?php
$data = $t['donnees'] ? json_decode($t['donnees'], true) : null;
$links = ['biens' => 'biens.show', 'locaux' => 'locaux.show', 'employes' => 'employes.show', 'transferts' => 'transferts.show'];
?>
<?= partial('page_header', ['title' => $title, 'icon' => 'journal-text', 'actions' => '<a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>
<div class="card mb-3"><div class="card-body"><dl class="row fiche mb-0">
    <div class="col-md-3"><dt>Date</dt><dd><?= datetime_fr($t['created_at']) ?></dd></div>
    <div class="col-md-3"><dt>Utilisateur</dt><dd><?= e($t['username']) ?> <span class="text-body-secondary small"><?= e($t['nom_complet']) ?></span></dd></div>
    <div class="col-md-3"><dt>Module / action</dt><dd><?= e(MODULES[$t['module']] ?? $t['module']) ?> · <span class="badge text-bg-light border"><?= e($t['action']) ?></span></dd></div>
    <div class="col-md-3"><dt>Entité</dt><dd><?php if ($t['entite_id'] && isset($links[$t['module']]) && $t['action'] !== 'suppression'): ?>
        <a href="<?= e(url($links[$t['module']], ['id' => $t['entite_id']])) ?>">#<?= (int) $t['entite_id'] ?></a><?php else: ?><?= $t['entite_id'] ? '#' . (int) $t['entite_id'] : '—' ?><?php endif; ?></dd></div>
    <div class="col-md-12"><dt>Description</dt><dd><?= e($t['description']) ?></dd></div>
    <div class="col-md-3"><dt>Adresse IP</dt><dd><?= e($t['ip']) ?></dd></div>
    <div class="col-md-9"><dt>Navigateur</dt><dd class="small"><?= e($t['user_agent']) ?></dd></div>
</dl></div></div>
<?php if ($data): ?>
<div class="card"><div class="card-header">Données</div><div class="card-body">
    <?php if (array_is_list($data) === false && count(array_filter($data, static fn($v) => is_array($v) && array_is_list($v) && count($v) === 2)) === count($data)): ?>
        <table class="table table-sm"><thead><tr><th>Champ</th><th>Ancienne valeur</th><th>Nouvelle valeur</th></tr></thead><tbody>
        <?php foreach ($data as $k => [$old, $new]): ?><tr><td class="fw-semibold"><?= e($k) ?></td><td class="text-danger"><?= e(is_scalar($old) ? $old : json_encode($old)) ?></td><td class="text-success"><?= e(is_scalar($new) ? $new : json_encode($new)) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    <?php else: ?>
        <pre class="json mb-0"><?= e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    <?php endif; ?>
</div></div>
<?php endif; ?>
