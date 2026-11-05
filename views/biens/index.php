<?php
use App\Core\Ref;
$f = $filters;
$actions = (can('biens.export') ? partial('export', ['route' => 'biens.export']) : '')
    . (can('biens.create') ? '<a class="btn btn-primary" href="' . e(url('biens.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau bien</a>' : '');
?>
<?= partial('page_header', ['title' => $title, 'icon' => 'box-seam', 'actions' => $actions]) ?>

<form class="card mb-3 d-print-none" method="get" action="index.php">
    <input type="hidden" name="r" value="biens.index">
    <input type="hidden" name="sort" value="<?= e($_GET['sort'] ?? '') ?>"><input type="hidden" name="dir" value="<?= e($_GET['dir'] ?? '') ?>">
    <div class="card-body pb-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-6 col-lg-5">
                <label class="form-label small mb-1">Recherche texte</label>
                <input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Code, désignation, marque, modèle, n° de série, fournisseur…">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small mb-1">Statut</label>
                <select class="form-select" name="statut"><?= options(STATUTS_BIEN, $f['statut'], 'Tous') ?></select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small mb-1">Catégorie</label>
                <select class="form-select" name="categorie_id"><?= options(Ref::pairs(Ref::categories(), 'libelle'), $f['categorie_id'], 'Toutes') ?></select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Rechercher</button>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advanced" title="Recherche avancée"><i class="bi bi-sliders"></i></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('biens.index')) ?>" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
        <div class="collapse <?= $advanced ? 'show' : '' ?>" id="advanced">
            <hr class="my-3">
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label small mb-1">Code inventaire (commence par)</label>
                    <input class="form-control" name="code" value="<?= e($f['code']) ?>"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Local</label>
                    <select class="form-select js-select" name="local_id"><?= options(Ref::pairs(Ref::locaux(), [Ref::class, 'localLabel']), $f['local_id'], 'Tous') ?></select></div>
                <div class="col-md-3"><label class="form-label small mb-1">Service</label>
                    <select class="form-select" name="service_id"><?= options(Ref::pairs(Ref::services(), 'nom'), $f['service_id'], 'Tous') ?></select></div>
                <div class="col-md-3"><label class="form-label small mb-1">Employé détenteur</label>
                    <select class="form-select js-select" name="employe_id"><?= options(Ref::pairs(Ref::employes(), static fn($e) => $e['nom_complet'] . ' (' . $e['matricule'] . ')'), $f['employe_id'], 'Tous') ?></select></div>
                <div class="col-md-2"><label class="form-label small mb-1">État</label>
                    <select class="form-select" name="etat"><?= options(ETATS_BIEN, $f['etat'], 'Tous') ?></select></div>
                <div class="col-md-2"><label class="form-label small mb-1">Acquis du</label>
                    <input class="form-control" type="date" name="date_du" value="<?= e($f['date_du']) ?>"></div>
                <div class="col-md-2"><label class="form-label small mb-1">au</label>
                    <input class="form-control" type="date" name="date_au" value="<?= e($f['date_au']) ?>"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Valeur min</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="valeur_min" value="<?= e($f['valeur_min']) ?>"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Valeur max</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="valeur_max" value="<?= e($f['valeur_max']) ?>"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Fournisseur</label>
                    <input class="form-control" name="fournisseur" value="<?= e($f['fournisseur']) ?>"></div>
                <div class="col-12"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sans_local" value="1" id="sans_local" <?= $f['sans_local'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="sans_local">Uniquement les biens sans local</label></div></div>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between small">
        <span><?= number_format($total, 0, ',', ' ') ?> bien(s) trouvé(s)</span>
        <span>Valeur totale : <strong><?= money($somme) ?></strong></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead><tr>
                <th><?= sort_link('code', 'Code') ?></th>
                <th><?= sort_link('designation', 'Désignation') ?></th>
                <th>Catégorie</th>
                <th>Local</th>
                <th>Détenteur</th>
                <th><?= sort_link('date', 'Acquisition') ?></th>
                <th class="text-end"><?= sort_link('valeur', 'Valeur') ?></th>
                <th>État</th><th>Statut</th>
                <th class="text-end d-print-none">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $b): ?>
                <tr>
                    <td class="text-nowrap"><a class="fw-semibold" href="<?= e(url('biens.show', ['id' => $b['id']])) ?>"><?= e($b['code_inventaire']) ?></a></td>
                    <td><?= e($b['designation']) ?><div class="small text-body-secondary"><?= e(trim($b['marque'] . ' ' . $b['modele'])) ?><?= $b['numero_serie'] ? ' · S/N ' . e($b['numero_serie']) : '' ?></div></td>
                    <td class="small"><?= e($b['categorie']) ?></td>
                    <td class="small text-nowrap"><?php if ($b['local_id']): ?><a href="<?= e(url('locaux.show', ['id' => $b['local_id']])) ?>"><?= e($b['local_code']) ?></a><?php else: ?><span class="text-body-secondary">—</span><?php endif; ?></td>
                    <td class="small"><?php if ($b['employe_id']): ?><a href="<?= e(url('employes.show', ['id' => $b['employe_id']])) ?>"><?= e($b['employe']) ?></a><?php endif; ?></td>
                    <td class="text-nowrap"><?= date_fr($b['date_acquisition']) ?></td>
                    <td class="text-end text-nowrap"><?= money($b['valeur_acquisition'], false) ?></td>
                    <td><?= badge(ETATS_BIEN, $b['etat']) ?></td>
                    <td><?= badge(STATUTS_BIEN, $b['statut']) ?></td>
                    <td class="text-end text-nowrap d-print-none">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('biens.show', ['id' => $b['id']])) ?>" title="Fiche"><i class="bi bi-eye"></i></a>
                        <?php if (can('biens.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('biens.edit', ['id' => $b['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('biens.export')): ?><a class="btn btn-sm btn-outline-dark" target="_blank" href="<?= e(url('biens.pdf', ['id' => $b['id'], 'inline' => 1])) ?>" title="Imprimer la fiche (PDF)"><i class="bi bi-printer"></i></a><?php endif; ?>
                        <?php if (can('biens.delete')): ?><?= partial('delete', ['route' => 'biens.delete', 'id' => $b['id'], 'label' => $b['code_inventaire']]) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$page['rows']): ?><tr><td colspan="10" class="text-center text-body-secondary py-4">Aucun bien ne correspond aux critères.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= partial('pager', ['page' => $page, 'total' => $total]) ?>
