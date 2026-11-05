<?php
use App\Core\Ref;
$b = $bien;
$isEdit = !empty($b['id']);
$v = static fn(string $k) => e($b[$k] ?? '');
?>
<?= partial('page_header', ['title' => $title, 'icon' => $isEdit ? 'pencil-square' : 'plus-square',
    'actions' => '<a class="btn btn-outline-secondary" href="' . e($isEdit ? url('biens.show', ['id' => $b['id']]) : url('biens.index')) . '"><i class="bi bi-arrow-left"></i> Retour</a>']) ?>

<?php if ($errors): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>

<form method="post" action="<?= e(url($isEdit ? 'biens.update' : 'biens.store')) ?>" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><?php endif; ?>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-card-text me-1"></i> Identification</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Code inventaire</label>
                        <input class="form-control<?= invalid($errors, 'code_inventaire') ?>" name="code_inventaire" value="<?= $v('code_inventaire') ?>" maxlength="40" placeholder="Auto si vide">
                        <?= field_error($errors, 'code_inventaire') ?>
                        <?php if (!$isEdit): ?><div class="form-text">Généré automatiquement (CAT-AAAA-00001) si laissé vide.</div><?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input class="form-control<?= invalid($errors, 'designation') ?>" name="designation" value="<?= $v('designation') ?>" maxlength="255" required>
                        <?= field_error($errors, 'designation') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                        <select class="form-select<?= invalid($errors, 'categorie_id') ?>" name="categorie_id" id="categorie_id" required>
                            <option value="">— Choisir —</option>
                            <?php foreach (Ref::categories() as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" data-duree="<?= (int) $c['duree_amortissement'] ?>" <?= (string) ($b['categorie_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' — ' . $c['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'categorie_id') ?>
                    </div>
                    <div class="col-md-3"><label class="form-label">Marque</label><input class="form-control" name="marque" value="<?= $v('marque') ?>" maxlength="100"></div>
                    <div class="col-md-3"><label class="form-label">Modèle</label><input class="form-control" name="modele" value="<?= $v('modele') ?>" maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label">N° de série</label><input class="form-control" name="numero_serie" value="<?= $v('numero_serie') ?>" maxlength="100"></div>
                    <div class="col-md-4">
                        <label class="form-label">État <span class="text-danger">*</span></label>
                        <select class="form-select<?= invalid($errors, 'etat') ?>" name="etat" required><?= options(ETATS_BIEN, $b['etat'] ?? null, '— Choisir —') ?></select>
                        <?= field_error($errors, 'etat') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Statut <span class="text-danger">*</span></label>
                        <select class="form-select<?= invalid($errors, 'statut') ?>" name="statut" required><?= options(STATUTS_BIEN, $b['statut'] ?? null, '— Choisir —') ?></select>
                        <?= field_error($errors, 'statut') ?>
                    </div>
                    <div class="col-12"><label class="form-label">Observations</label><textarea class="form-control" name="observations" rows="3"><?= $v('observations') ?></textarea></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-receipt me-1"></i> Acquisition & amortissement</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">Fournisseur</label><input class="form-control" name="fournisseur" value="<?= $v('fournisseur') ?>" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label">N° de facture</label><input class="form-control" name="numero_facture" value="<?= $v('numero_facture') ?>" maxlength="60"></div>
                    <div class="col-md-4">
                        <label class="form-label">Date d'acquisition <span class="text-danger">*</span></label>
                        <input type="date" class="form-control<?= invalid($errors, 'date_acquisition') ?>" name="date_acquisition" value="<?= $v('date_acquisition') ?>" max="<?= date('Y-m-d') ?>" required>
                        <?= field_error($errors, 'date_acquisition') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date de mise en service</label>
                        <input type="date" class="form-control<?= invalid($errors, 'date_mise_service') ?>" name="date_mise_service" value="<?= $v('date_mise_service') ?>">
                        <?= field_error($errors, 'date_mise_service') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valeur d'acquisition (<?= e(config('app.devise')) ?>) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control<?= invalid($errors, 'valeur_acquisition') ?>" name="valeur_acquisition" value="<?= $v('valeur_acquisition') ?>" required>
                        <?= field_error($errors, 'valeur_acquisition') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Méthode d'amortissement</label>
                        <select class="form-select" name="methode_amortissement"><?= options(METHODES_AMORT, $b['methode_amortissement'] ?? 'lineaire') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Durée (années)</label>
                        <input type="number" min="1" max="100" class="form-control<?= invalid($errors, 'duree_amortissement') ?>" name="duree_amortissement" id="duree_amortissement" value="<?= $v('duree_amortissement') ?>" placeholder="Durée de la catégorie">
                        <?= field_error($errors, 'duree_amortissement') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valeur résiduelle</label>
                        <input type="number" step="0.01" min="0" class="form-control<?= invalid($errors, 'valeur_residuelle') ?>" name="valeur_residuelle" value="<?= $v('valeur_residuelle') ?>">
                        <?= field_error($errors, 'valeur_residuelle') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-geo-alt me-1"></i> Localisation & affectation</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Local / bureau</label>
                        <?php if ($isEdit): ?>
                            <input class="form-control" value="<?= e(trim(($b['local_code'] ?? '') . ' — ' . ($b['local_nom'] ?? ''), ' —')) ?: 'Non localisé' ?>" disabled>
                            <div class="form-text">Le changement de local se fait par un <a href="<?= e(url('transferts.create', ['bien_id' => $b['id']])) ?>">transfert</a> pour garantir la traçabilité.</div>
                        <?php else: ?>
                            <select class="form-select js-select<?= invalid($errors, 'local_id') ?>" name="local_id"><?= options(Ref::pairs(Ref::locaux(), [Ref::class, 'localLabel']), $b['local_id'] ?? null, '— Aucun —') ?></select>
                            <?= field_error($errors, 'local_id') ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Employé détenteur</label>
                        <select class="form-select js-select<?= invalid($errors, 'employe_id') ?>" name="employe_id"><?= options(Ref::pairs(Ref::employes(), static fn($e) => $e['nom_complet'] . ' (' . $e['matricule'] . ')'), $b['employe_id'] ?? null, '— Aucun —') ?></select>
                        <?= field_error($errors, 'employe_id') ?>
                    </div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Enregistrer</button>
                <a class="btn btn-outline-secondary" href="<?= e($isEdit ? url('biens.show', ['id' => $b['id']]) : url('biens.index')) ?>">Annuler</a>
            </div>
        </div>
    </div>
</form>
<script>
document.getElementById('categorie_id').addEventListener('change', (e) => {
    const d = e.target.selectedOptions[0]?.dataset.duree, f = document.getElementById('duree_amortissement');
    if (d && !f.value) f.value = d;
});
</script>
