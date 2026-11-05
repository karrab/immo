<h1>Liste des employés</h1>
<div class="filters"><?= count($rows) ?> employé(s)</div>
<table class="data">
    <thead><tr><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Type</th><th>Fonction</th><th>Service</th><th>Local</th><th>Email</th><th>Téléphone</th><th class="r">Biens</th><th>Actif</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['matricule']) ?></td><td><?= e($r['nom']) ?></td><td><?= e($r['prenom']) ?></td><td><?= e(label(TYPES_EMPLOYE, $r['type'])) ?></td><td><?= e($r['fonction']) ?></td><td><?= e($r['service']) ?></td>
            <td><?= e($r['local_code']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['telephone']) ?></td><td class="r"><?= (int) $r['nb_biens'] ?></td><td><?= $r['actif'] ? 'Oui' : 'Non' ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
