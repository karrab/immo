<?php
$actions = '';
if (can('transferts.export')) $actions .= '<a class="btn btn-outline-secondary" target="_blank" href="' . e(url('transferts.pdf', ['id' => $t['id'], 'inline' => 1])) . '"><i class="bi bi-printer"></i> Imprimer le bon</a>'
    . '<a class="btn btn-outline-danger" href="' . e(url('transferts.pdf', ['id' => $t['id']])) . '"><i class="bi bi-file-earmark-pdf"></i> PDF</a>';
if (can('transferts.delete') && $t['statut'] !== 'valide') $actions .= partial('delete', ['route' => 'transferts.delete', 'id' => $t['id'], 'label' => $t['numero'], 'small' => false, 'text' => 'Supprimer']);
$actions .= '<a class="btn btn-outline-secondary" href="' . e(url('transferts.index')) . '"><i class="bi bi-list"></i> Liste</a>';
$total = array_sum(array_map(static fn($l) => (float) $l['valeur_acquisition'], $lignes));
?>
<?= partial('page_header', ['title' => 'Transfert ' . $t['numero'], 'icon' => 'arrow-left-right', 'actions' => $actions]) ?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body">
            <div class="row align-items-center text-center g-3">
                <div class="col-md-5"><div class="small text-body-secondary">Local source</div><div class="fs-5 fw-semibold"><?= e($t['source_code']) ?></div><div><?= e($t['source_nom']) ?></div></div>
                <div class="col-md-2 fs-1 text-primary"><i class="bi bi-arrow-right-circle"></i></div>
                <div class="col-md-5"><div class="small text-body-secondary">Local destination</div><div class="fs-5 fw-semibold"><?= e($t['dest_code']) ?></div><div><?= e($t['dest_nom']) ?></div></div>
            </div>
        </div></div>
        <div class="card"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-box-seam me-1"></i> Biens transférés (<?= count($lignes) ?>)</span><span>Valeur : <strong><?= money($total) ?></strong></span></div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Code</th><th>Désignation</th><th>N° série</th><th>Ancien détenteur</th><th>État</th><th class="text-end">Valeur</th></tr></thead>
                <tbody><?php foreach ($lignes as $l): ?>
                    <tr><td><a class="fw-semibold" href="<?= e(url('biens.show', ['id' => $l['id']])) ?>"><?= e($l['code_inventaire']) ?></a></td><td><?= e($l['designation']) ?></td>
                        <td class="small"><?= e($l['numero_serie']) ?></td><td class="small"><?= e($l['employe_source']) ?></td><td><?= badge(ETATS_BIEN, $l['etat']) ?></td>
                        <td class="text-end"><?= money($l['valeur_acquisition'], false) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Informations</span><?= badge(STATUTS_TRANSFERT, $t['statut']) ?></div>
            <div class="card-body"><dl class="fiche mb-0">
                <dt>Date du transfert</dt><dd><?= date_fr($t['date_transfert']) ?></dd>
                <dt>Motif</dt><dd><?= e($t['motif']) ?></dd>
                <dt>Nouveau détenteur</dt><dd><?= e($t['employe_dest']) ?: '—' ?></dd>
                <dt>Demandé par</dt><dd><?= e($t['demandeur']) ?> <span class="small text-body-secondary">le <?= datetime_fr($t['created_at']) ?></span></dd>
                <?php if ($t['traite_par']): ?>
                    <dt>Traité par</dt><dd><?= e($t['traitant']) ?> <span class="small text-body-secondary">le <?= datetime_fr($t['date_traitement']) ?></span></dd>
                    <?php if ($t['commentaire']): ?><dt>Commentaire</dt><dd><?= e($t['commentaire']) ?></dd><?php endif; ?>
                <?php endif; ?>
            </dl></div>
        </div>
        <?php if ($t['statut'] === 'en_attente'): ?>
        <div class="card"><div class="card-header">Traitement</div><div class="card-body">
            <?php if (can('transferts.validate')): ?>
            <form method="post" action="<?= e(url('transferts.valider')) ?>" class="mb-3" data-confirm="Valider ce transfert ? Les biens seront déplacés vers <?= e($t['dest_code']) ?>." data-confirm-class="btn-success" data-confirm-label="Valider">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <input class="form-control mb-2" name="commentaire" placeholder="Commentaire (facultatif)" maxlength="255">
                <button class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> Valider le transfert</button>
            </form>
            <form method="post" action="<?= e(url('transferts.rejeter')) ?>" class="mb-3" data-confirm="Rejeter ce transfert ?">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <input class="form-control mb-2" name="commentaire" placeholder="Motif du rejet (obligatoire)" maxlength="255" required>
                <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Rejeter</button>
            </form>
            <?php endif; ?>
            <?php if (can('transferts.validate') || (int) $t['demande_par'] === (int) user()['id']): ?>
            <form method="post" action="<?= e(url('transferts.annuler')) ?>" data-confirm="Annuler cette demande de transfert ?" data-confirm-class="btn-secondary">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn btn-outline-secondary w-100"><i class="bi bi-slash-circle"></i> Annuler la demande</button>
            </form>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
