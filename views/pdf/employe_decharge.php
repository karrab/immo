<?php $e = $emp; $total = array_sum(array_map(static fn($r) => (float) $r['valeur_acquisition'], $biens)); ?>
<h1>Fiche de détention de biens (décharge)</h1>
<table class="kv">
    <tr><td class="k">Matricule</td><td><?= e($e['matricule']) ?></td><td class="k">Nom et prénom</td><td><?= e($e['nom'] . ' ' . $e['prenom']) ?></td></tr>
    <tr><td class="k">Fonction</td><td><?= e($e['fonction']) ?></td><td class="k">Service</td><td><?= e($e['service']) ?></td></tr>
    <tr><td class="k">Bureau</td><td><?= e(trim(($e['local_code'] ?? '') . ' — ' . ($e['local_nom'] ?? ''), ' —')) ?></td><td class="k">Date</td><td><?= date('d/m/Y') ?></td></tr>
</table>
<h2>Biens détenus</h2>
<table class="data">
    <thead><tr><th>#</th><th>Code</th><th>Désignation</th><th>Marque / modèle</th><th>N° série</th><th>Local</th><th>État</th><th class="r">Valeur</th></tr></thead>
    <tbody><?php foreach ($biens as $i => $r): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($r['code_inventaire']) ?></td><td><?= e($r['designation']) ?></td><td><?= e(trim($r['marque'] . ' ' . $r['modele'])) ?></td>
            <td><?= e($r['numero_serie']) ?></td><td><?= e($r['local_code']) ?></td><td><?= e(label(ETATS_BIEN, $r['etat'])) ?></td><td class="r"><?= money($r['valeur_acquisition'], false) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$biens): ?><tr><td colspan="8" class="c muted">Aucun bien affecté.</td></tr><?php endif; ?>
    <tr class="total"><td colspan="7">TOTAL</td><td class="r"><?= money($total) ?></td></tr></tbody>
</table>
<p style="margin-top:14px">Je soussigné(e) <strong><?= e($e['prenom'] . ' ' . $e['nom']) ?></strong> reconnais avoir reçu les biens ci-dessus et m'engage à en prendre soin et à les restituer à la demande de l'administration.</p>
<table class="sign"><tr><td><strong>L'employé</strong><br>Date et signature</td><td><strong>Le responsable hiérarchique</strong><br>Date et signature</td><td><strong>Service patrimoine</strong><br>Visa</td></tr></table>
