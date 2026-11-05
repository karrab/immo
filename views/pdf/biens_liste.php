<?php $total = array_sum(array_map(static fn($r) => (float) $r['valeur_acquisition'], $rows)); ?>
<h1>Liste des biens (immobilisations)</h1>
<div class="filters">
    <?php if ($filters): foreach ($filters as $k => $v): ?><strong><?= e($k) ?> :</strong> <?= e($v) ?> &nbsp; <?php endforeach; else: ?>Tous les biens<?php endif; ?>
    — <?= count($rows) ?> bien(s)<?= count($rows) >= $max ? ' (limité à ' . $max . ')' : '' ?>
</div>
<table class="data">
    <thead><tr><th>Code</th><th>Désignation</th><th>Catégorie</th><th>Marque / modèle</th><th>N° série</th><th>Acquisition</th><th class="r">Valeur</th><th>État</th><th>Statut</th><th>Local</th><th>Détenteur</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['code_inventaire']) ?></td><td><?= e($r['designation']) ?></td><td><?= e($r['categorie']) ?></td>
            <td><?= e(trim($r['marque'] . ' ' . $r['modele'])) ?></td><td><?= e($r['numero_serie']) ?></td><td><?= date_fr($r['date_acquisition']) ?></td>
            <td class="r"><?= money($r['valeur_acquisition'], false) ?></td><td><?= e(label(ETATS_BIEN, $r['etat'])) ?></td><td><?= e(label(STATUTS_BIEN, $r['statut'])) ?></td>
            <td><?= e($r['local_code']) ?></td><td><?= e($r['employe']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="total"><td colspan="6">TOTAL</td><td class="r"><?= money($total) ?></td><td colspan="4"></td></tr>
    </tbody>
</table>
