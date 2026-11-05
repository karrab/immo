<h1>Liste des locaux</h1>
<div class="filters"><?= count($rows) ?> local(aux)</div>
<table class="data">
    <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Bâtiment</th><th>Étage</th><th>Service</th><th class="r">Biens</th><th class="r">Valeur</th><th>Actif</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['nom']) ?></td><td><?= e(TYPES_LOCAL[$r['type']] ?? $r['type']) ?></td><td><?= e($r['batiment']) ?></td><td><?= e($r['etage']) ?></td>
            <td><?= e($r['service']) ?></td><td class="r"><?= (int) $r['nb_biens'] ?></td><td class="r"><?= money($r['valeur_biens'], false) ?></td><td><?= $r['actif'] ? 'Oui' : 'Non' ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
