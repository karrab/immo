<?php $l = $local; $total = array_sum(array_map(static fn($r) => (float) $r['valeur_acquisition'], $rows)); ?>
<h1>Fiche inventaire du local <?= e($l['code']) ?></h1>
<table class="kv">
    <tr><td class="k">Local</td><td><?= e($l['code'] . ' — ' . $l['nom']) ?></td><td class="k">Type</td><td><?= e(TYPES_LOCAL[$l['type']] ?? $l['type']) ?></td></tr>
    <tr><td class="k">Bâtiment / étage</td><td><?= e($l['batiment']) ?> <?= e($l['etage']) ?></td><td class="k">Service</td><td><?= e($l['service']) ?></td></tr>
    <tr><td class="k">Date d'inventaire</td><td><?= date('d/m/Y') ?></td><td class="k">Nombre de biens</td><td><?= count($rows) ?></td></tr>
</table>
<h2>Biens présents</h2>
<table class="data">
    <thead><tr><th>#</th><th>Code</th><th>Désignation</th><th>Marque / modèle</th><th>N° série</th><th>État</th><th>Détenteur</th><th class="r">Valeur</th><th class="c">Constaté</th></tr></thead>
    <tbody><?php foreach ($rows as $i => $r): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($r['code_inventaire']) ?></td><td><?= e($r['designation']) ?></td><td><?= e(trim($r['marque'] . ' ' . $r['modele'])) ?></td>
            <td><?= e($r['numero_serie']) ?></td><td><?= e(label(ETATS_BIEN, $r['etat'])) ?></td><td><?= e($r['employe']) ?></td>
            <td class="r"><?= money($r['valeur_acquisition'], false) ?></td><td class="c">☐</td></tr>
    <?php endforeach; ?>
    <tr class="total"><td colspan="7">TOTAL</td><td class="r"><?= money($total) ?></td><td></td></tr></tbody>
</table>
<table class="sign"><tr><td><strong>Responsable du local</strong><br>Nom, date et signature</td><td><strong>Agent inventaire</strong><br>Nom, date et signature</td><td><strong>Service patrimoine</strong><br>Visa</td></tr></table>
