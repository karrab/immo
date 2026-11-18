<?php $total = array_sum(array_map(static fn($l) => (float) $l['valeur_acquisition'], $lignes)); ?>
<h1>Bon de transfert n° <?= e($t['numero']) ?></h1>
<table class="kv">
    <tr><td class="k">Date du transfert</td><td><?= date_fr($t['date_transfert']) ?></td><td class="k">Statut</td><td><?= e(label(STATUTS_TRANSFERT, $t['statut'])) ?></td></tr>
    <tr><td class="k">Local source</td><td><?= e($t['source_code'] . ' — ' . $t['source_nom']) ?></td><td class="k">Local destination</td><td><?= e($t['dest_code'] . ' — ' . $t['dest_nom']) ?></td></tr>
    <tr><td class="k">Nouveau détenteur</td><td><?= e($t['employe_dest']) ?: '—' ?></td><td class="k">Motif</td><td><?= e($t['motif']) ?></td></tr>
    <tr><td class="k">Service source</td><td><?= e($t['source_service']) ?></td><td class="k">Service de réception</td><td><?= e($t['dest_service']) ?></td></tr>
    <tr><td class="k">Remis par</td><td><?= e($t['remis_par']) ?: '—' ?></td><td class="k">Reçu par</td><td><?= e($t['recu_par']) ?: '—' ?></td></tr>
    <tr><td class="k">Chef service source (validateur)</td><td><?= e($t['chef_source'] ?: 'Administrateur') ?></td><td class="k">Chef service de réception</td><td><?= e($t['chef_dest']) ?: '—' ?></td></tr>
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
<?php
// Cadre de signature : nom pré-rempli, ou ligne à compléter à la main
$sig = static function (string $titre, string $sous, ?string $nom, ?string $fonction = null): string {
    return '<td><strong>' . e($titre) . '</strong><br><span class="muted">' . e($sous) . '</span><br><br>'
        . ($nom ? 'Nom : <strong>' . e($nom) . '</strong>' . ($fonction ? '<br><span class="muted">' . e($fonction) . '</span>' : '') : 'Nom : ............................')
        . '<br><br>Date : ........ / ........ / ............<br><br>Signature :</td>';
};
?>
<table class="sign sign2">
    <tr>
        <?= $sig('Remis par', 'Local source ' . $t['source_code'], $t['remis_par'], $t['remis_par_fonction']) ?>
        <?= $sig('Reçu par', 'Local destination ' . $t['dest_code'], $t['recu_par'] ?: $t['employe_dest'], $t['recu_par_fonction']) ?>
    </tr>
    <tr>
        <?= $sig('Chef du service source (validation)', $t['source_service'] ?: 'Service source', $t['chef_source']) ?>
        <?= $sig('Chef du service de réception', $t['dest_service'] ?: 'Service de réception', $t['chef_dest']) ?>
    </tr>
</table>
