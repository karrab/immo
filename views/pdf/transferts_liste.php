<h1>Liste des transferts de biens</h1>
<div class="filters"><?= count($rows) ?> transfert(s)</div>
<table class="data">
    <thead><tr><th>Numéro</th><th>Date</th><th>Source</th><th>Destination</th><th>Nouveau détenteur</th><th class="r">Biens</th><th>Motif</th><th>Statut</th><th>Demandé par</th><th>Traité par</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['numero']) ?></td><td><?= date_fr($r['date_transfert']) ?></td><td><?= e($r['source_code']) ?></td><td><?= e($r['dest_code']) ?></td><td><?= e($r['employe_dest']) ?></td>
            <td class="r"><?= (int) $r['nb_biens'] ?></td><td><?= e($r['motif']) ?></td><td><?= e(label(STATUTS_TRANSFERT, $r['statut'])) ?></td><td><?= e($r['demandeur']) ?></td><td><?= e($r['traitant']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
