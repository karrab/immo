<?php
$b = $bien;
$actions = '';
if (can('transferts.create') && $b['local_id']) $actions .= '<a class="btn btn-warning" href="' . e(url('transferts.create', ['bien_id' => $b['id']])) . '"><i class="bi bi-arrow-left-right"></i> Transférer</a>';
if (can('biens.edit')) $actions .= '<a class="btn btn-primary" href="' . e(url('biens.edit', ['id' => $b['id']])) . '"><i class="bi bi-pencil"></i> Modifier</a>';
if (can('biens.export')) $actions .= '<a class="btn btn-outline-secondary" target="_blank" href="' . e(url('biens.pdf', ['id' => $b['id'], 'inline' => 1])) . '"><i class="bi bi-printer"></i> Imprimer</a>'
    . '<a class="btn btn-outline-danger" href="' . e(url('biens.pdf', ['id' => $b['id']])) . '"><i class="bi bi-file-earmark-pdf"></i> PDF</a>';
if (can('biens.delete')) $actions .= partial('delete', ['route' => 'biens.delete', 'id' => $b['id'], 'label' => $b['code_inventaire'], 'small' => false, 'text' => 'Supprimer']);
$actions .= '<a class="btn btn-outline-secondary" href="' . e(url('biens.index')) . '"><i class="bi bi-list"></i> Liste</a>';
?>
<?= partial('page_header', ['title' => $b['code_inventaire'] . ' — ' . $b['designation'], 'icon' => 'box-seam', 'actions' => $actions]) ?>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-card-text me-1"></i> Fiche du bien</span>
                <span><?= badge(STATUTS_BIEN, $b['statut']) ?> <?= badge(ETATS_BIEN, $b['etat']) ?></span></div>
            <div class="card-body">
                <dl class="row fiche mb-0">
                    <div class="col-md-4"><dt>Code inventaire</dt><dd class="fw-semibold"><?= e($b['code_inventaire']) ?></dd></div>
                    <div class="col-md-8"><dt>Désignation</dt><dd><?= e($b['designation']) ?></dd></div>
                    <div class="col-md-4"><dt>Catégorie</dt><dd><?= e($b['categorie_code'] . ' — ' . $b['categorie']) ?></dd></div>
                    <div class="col-md-4"><dt>Compte immobilisation</dt><dd><?= e($b['compte_comptable']) ?: '—' ?></dd></div>
                    <div class="col-md-4"><dt>Compte amortissement</dt><dd><?= e($b['compte_amortissement']) ?: '—' ?></dd></div>
                    <div class="col-md-4"><dt>Marque / modèle</dt><dd><?= e(trim($b['marque'] . ' ' . $b['modele'])) ?: '—' ?></dd></div>
                    <div class="col-md-4"><dt>N° de série</dt><dd><?= e($b['numero_serie']) ?: '—' ?></dd></div>
                    <div class="col-md-4"><dt>Fournisseur / facture</dt><dd><?= e($b['fournisseur']) ?: '—' ?><?= $b['numero_facture'] ? ' · ' . e($b['numero_facture']) : '' ?></dd></div>
                    <div class="col-md-4"><dt>Date d'acquisition</dt><dd><?= date_fr($b['date_acquisition']) ?></dd></div>
                    <div class="col-md-4"><dt>Mise en service</dt><dd><?= date_fr($b['date_mise_service']) ?: '—' ?></dd></div>
                    <div class="col-md-4"><dt>Valeur d'acquisition</dt><dd class="fw-semibold"><?= money($b['valeur_acquisition']) ?></dd></div>
                    <?php if ($b['observations']): ?><div class="col-12"><dt>Observations</dt><dd><?= nl2br(e($b['observations'])) ?></dd></div><?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-graph-down me-1"></i> Plan d'amortissement
                <span class="small text-body-secondary">— <?= e(label(METHODES_AMORT, $plan['methode'])) ?>, <?= (int) $b['duree_amortissement'] ?> ans, taux <?= number_format($plan['taux'], 2, ',', ' ') ?> %</span></div>
            <div class="table-responsive"><table class="table table-sm table-striped mb-0">
                <thead><tr><th>Exercice</th><th class="text-end">VNC début</th><th class="text-end">Taux</th><th class="text-end">Dotation</th><th class="text-end">Cumul</th><th class="text-end">VNC fin</th></tr></thead>
                <tbody>
                <?php foreach ($plan['lignes'] as $l): ?>
                    <tr class="<?= $l['annee'] === (int) date('Y') ? 'table-primary' : '' ?>">
                        <td><?= $l['annee'] ?></td><td class="text-end"><?= money($l['vnc_debut'], false) ?></td>
                        <td class="text-end"><?= number_format($l['taux'], 2, ',', ' ') ?> %</td>
                        <td class="text-end"><?= money($l['dotation'], false) ?></td><td class="text-end"><?= money($l['cumul'], false) ?></td>
                        <td class="text-end fw-semibold"><?= money($l['vnc'], false) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>

        <?php if ($transferts): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> Transferts</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>N°</th><th>Date</th><th>De</th><th>Vers</th><th>Statut</th></tr></thead>
                <tbody><?php foreach ($transferts as $t): ?>
                    <tr><td><a href="<?= e(url('transferts.show', ['id' => $t['id']])) ?>"><?= e($t['numero']) ?></a></td><td><?= date_fr($t['date_transfert']) ?></td>
                        <td><?= e($t['source']) ?></td><td><?= e($t['destination']) ?></td><td><?= badge(STATUTS_TRANSFERT, $t['statut']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calculator me-1"></i> Situation au 31/12/<?= $situation['annee'] ?></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Valeur brute</span><strong><?= money($b['valeur_acquisition']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Dotation de l'exercice</span><span><?= money($situation['dotation']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Amortissements cumulés</span><span><?= money($situation['cumul']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between bg-primary-subtle"><span>Valeur nette comptable</span><strong><?= money($situation['vnc']) ?></strong></li>
            </ul>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-geo-alt me-1"></i> Localisation</div>
            <div class="card-body">
                <dl class="fiche mb-0">
                    <dt>Local</dt>
                    <dd><?php if ($b['local_id']): ?><a href="<?= e(url('locaux.show', ['id' => $b['local_id']])) ?>"><?= e($b['local_code'] . ' — ' . $b['local_nom']) ?></a>
                        <div class="small text-body-secondary"><?= e(trim(($b['batiment'] ?? '') . ($b['etage'] !== null ? ', étage ' . $b['etage'] : ''), ', ')) ?></div>
                        <?php else: ?><span class="text-body-secondary">Non localisé</span><?php endif; ?></dd>
                    <dt>Service</dt><dd><?= e($b['service']) ?: '—' ?></dd>
                    <dt>Détenteur</dt>
                    <dd><?php if ($b['employe_id']): ?><a href="<?= e(url('employes.show', ['id' => $b['employe_id']])) ?>"><?= e($b['employe']) ?></a>
                        <div class="small text-body-secondary"><?= e($b['matricule'] . ($b['fonction'] ? ' · ' . $b['fonction'] : '')) ?></div>
                        <?php else: ?>—<?php endif; ?></dd>
                </dl>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Historique du bien</div>
            <div class="card-body">
                <ul class="timeline mb-0">
                    <?php foreach ($historique as $h): [$lbl, $color, $icon] = EVENEMENTS_BIEN[$h['type_evenement']]; ?>
                        <li>
                            <span class="dot bg-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></span>
                            <div class="small fw-semibold"><?= e($lbl) ?></div>
                            <div class="small"><?= e($h['description']) ?></div>
                            <div class="small text-body-secondary"><?= datetime_fr($h['created_at']) ?> · <?= e($h['utilisateur'] ?? 'système') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-footer small text-body-secondary">
                Créé par <?= e($b['cree_par'] ?? '—') ?> le <?= datetime_fr($b['created_at']) ?><br>
                Modifié par <?= e($b['modifie_par'] ?? '—') ?> le <?= datetime_fr($b['updated_at']) ?>
            </div>
        </div>
    </div>
</div>
