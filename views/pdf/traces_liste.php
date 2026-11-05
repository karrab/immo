<h1>Journal des traces</h1>
<div class="filters"><?= count($rows) ?> entrée(s)</div>
<table class="data">
    <thead><tr><th>#</th><th>Date</th><th>Utilisateur</th><th>Module</th><th>Action</th><th>Entité</th><th>Description</th><th>IP</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td><?= (int) $r['id'] ?></td><td><?= datetime_fr($r['created_at']) ?></td><td><?= e($r['username']) ?></td><td><?= e(MODULES[$r['module']] ?? $r['module']) ?></td>
            <td><?= e($r['action']) ?></td><td><?= e($r['entite_id']) ?></td><td><?= e($r['description']) ?></td><td><?= e($r['ip']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
