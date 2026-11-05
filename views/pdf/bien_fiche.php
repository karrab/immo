<?php $b = $bien; ?>
<h1>Fiche d'immobilisation — <?= e($b['code_inventaire']) ?></h1>
<h2>Identification</h2>
<table class="kv">
    <tr><td class="k">Code inventaire</td><td><?= e($b['code_inventaire']) ?></td><td class="k">Statut / état</td><td><?= e(label(STATUTS_BIEN, $b['statut'])) ?> / <?= e(label(ETATS_BIEN, $b['etat'])) ?></td></tr>
    <tr><td class="k">Désignation</td><td colspan="3"><?= e($b['designation']) ?></td></tr>
    <tr><td class="k">Catégorie</td><td><?= e($b['categorie_code'] . ' — ' . $b['categorie']) ?></td><td class="k">Comptes</td><td><?= e($b['compte_comptable']) ?> / <?= e($b['compte_amortissement']) ?></td></tr>
    <tr><td class="k">Marque / modèle</td><td><?= e(trim($b['marque'] . ' ' . $b['modele'])) ?></td><td class="k">N° de série</td><td><?= e($b['numero_serie']) ?></td></tr>
</table>
<h2>Acquisition</h2>
<table class="kv">
    <tr><td class="k">Fournisseur</td><td><?= e($b['fournisseur']) ?></td><td class="k">N° facture</td><td><?= e($b['numero_facture']) ?></td></tr>
    <tr><td class="k">Date d'acquisition</td><td><?= date_fr($b['date_acquisition']) ?></td><td class="k">Mise en service</td><td><?= date_fr($b['date_mise_service']) ?></td></tr>
    <tr><td class="k">Valeur d'acquisition</td><td><strong><?= money($b['valeur_acquisition']) ?></strong></td><td class="k">Valeur résiduelle</td><td><?= money($b['valeur_residuelle']) ?></td></tr>
    <tr><td class="k">Amortissement</td><td><?= e(label(METHODES_AMORT, $b['methode_amortissement'])) ?> sur <?= (int) $b['duree_amortissement'] ?> ans (<?= number_format($plan['taux'], 2, ',', ' ') ?> %)</td>
        <td class="k">VNC au 31/12/<?= $situation['annee'] ?></td><td><strong><?= money($situation['vnc']) ?></strong></td></tr>
</table>
<h2>Localisation et affectation</h2>
<table class="kv">
    <tr><td class="k">Local</td><td><?= e(trim(($b['local_code'] ?? '') . ' — ' . ($b['local_nom'] ?? ''), ' —')) ?: 'Non localisé' ?></td><td class="k">Bâtiment / étage</td><td><?= e($b['batiment']) ?> <?= e($b['etage']) ?></td></tr>
    <tr><td class="k">Service</td><td><?= e($b['service']) ?></td><td class="k">Détenteur</td><td><?= e($b['employe']) ?> <?= $b['matricule'] ? '(' . e($b['matricule']) . ')' : '' ?></td></tr>
    <?php if ($b['observations']): ?><tr><td class="k">Observations</td><td colspan="3"><?= nl2br(e($b['observations'])) ?></td></tr><?php endif; ?>
</table>
<h2>Plan d'amortissement</h2>
<table class="data">
    <thead><tr><th>Exercice</th><th class="r">VNC début</th><th class="r">Taux</th><th class="r">Dotation</th><th class="r">Cumul</th><th class="r">VNC fin</th></tr></thead>
    <tbody><?php foreach ($plan['lignes'] as $l): ?>
        <tr><td><?= $l['annee'] ?></td><td class="r"><?= money($l['vnc_debut'], false) ?></td><td class="r"><?= number_format($l['taux'], 2, ',', ' ') ?> %</td>
            <td class="r"><?= money($l['dotation'], false) ?></td><td class="r"><?= money($l['cumul'], false) ?></td><td class="r"><?= money($l['vnc'], false) ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
<h2>Historique</h2>
<table class="data">
    <thead><tr><th style="width:18%">Date</th><th style="width:16%">Événement</th><th>Description</th><th style="width:18%">Utilisateur</th></tr></thead>
    <tbody><?php foreach ($historique as $h): ?>
        <tr><td><?= datetime_fr($h['created_at']) ?></td><td><?= e(EVENEMENTS_BIEN[$h['type_evenement']][0]) ?></td><td><?= e($h['description']) ?></td><td><?= e($h['utilisateur']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
