<h1>Liste des services</h1>
<div class="filters"><?= count($rows) ?> service(s)</div>
<table class="data">
    <thead><tr><th>Code</th><th>Nom</th><th>Chef de service</th><th class="r">Locaux</th><th class="r">Employés</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['nom']) ?></td><td><?= e($r['chef']) ?: '—' ?></td>
            <td class="r"><?= (int) $r['nb_locaux'] ?></td><td class="r"><?= (int) $r['nb_employes'] ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
