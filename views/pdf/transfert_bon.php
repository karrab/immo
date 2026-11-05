<?php $total = array_sum(array_map(static fn($l) => (float) $l['valeur_acquisition'], $lignes)); ?>
<h1>Bon de transfert n° <?= e($t['numero']) ?></h1>
<table class="kv">
    <tr><td class="k">Date du transfert</td><td><?= date_fr($t['date_transfert']) ?></td><td class="k">Statut</td><td><?= e(label(STATUTS_TRANSFERT, $t['statut'])) ?></td></tr>
    <tr><td class="k">Local source</td><td><?= e($t['source_code'] . ' — ' . $t['source_nom']) ?></td><td class="k">Local destination</td><td><?= e($t['dest_code'] . ' — ' . $t['dest_nom']) ?></td></tr>
    <tr><td class="k">Nouveau détenteur</td><td><?= e($t['employe_dest']) ?: '—' ?></td><td class="k">Motif</td><td><?= e($t['motif']) ?></td></tr>
    <tr><td class="k">Service source</td><td><?= e($t['source_service']) ?></td><td class="k">Validateur (chef)</td><td><?= e($t['chef_source'] ?: 'Administrateur') ?></td></tr>
    <tr><td class="k">Demandé par</td><td><?= e($t['demandeur']) ?> le <?= datetime_fr($t['created_at']) ?></td><td class="k">Traité par</td><td><?= e($t['traitant']) ?> <?= $t['date_traitement'] ? 'le ' . datetime_fr($t['date_traitement']) : '' ?></td></tr>
    <?php if ($t['commentaire']): ?><tr><td class="k">Commentaire</td><td colspan="3"><?= e($t['commentaire']) ?></td></tr><?php endif; ?>
</table>
<h2>Biens concernés (<?= count($lignes) ?>)</h2>
<table class="data">
    <thead><tr><th>#</th><th>Code</th><th>Désignation</th><th>Marque / modèle</th><th>N° série</th><th>Ancien détenteur</th><th>État</th><th class="r">Valeur</th></tr></thead>
    <tbody><?php foreach ($lignes as $i => $l): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($l['code_inventaire']) ?></td><td><?= e($l['designation']) ?></td><td><?= e(trim($l['marque'] . ' ' . $l['modele'])) ?></td>
            <td><?= e($l['numero_serie']) ?></td><td><?= e($l['employe_source']) ?></td><td><?= e(label(ETATS_BIEN, $l['etat'])) ?></td><td class="r"><?= money($l['valeur_acquisition'], false) ?></td></tr>
    <?php endforeach; ?>
    <tr class="total"><td colspan="7">TOTAL</td><td class="r"><?= money($total) ?></td></tr></tbody>
</table>
<table class="sign"><tr><td><strong>Remis par</strong> (local source)<br>Nom, date et signature</td><td><strong>Reçu par</strong> (local destination)<br>Nom, date et signature</td><td><strong>Chef de service</strong><br><?= e($t['chef_source']) ?><br>Visa</td></tr></table>
