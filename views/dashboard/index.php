<?php
$cards = [
    ['Biens inventoriés', number_format($stats['nb_biens'], 0, ',', ' '), 'box-seam', 'primary', url('biens.index')],
    ["Valeur d'acquisition", money($stats['valeur']), 'cash-stack', 'success', url('biens.index')],
    ['Transferts en attente', $transferts['en_attente'], 'arrow-left-right', 'warning', url('transferts.index', ['statut' => 'en_attente'])],
    ['Locaux actifs', $stats['nb_locaux'], 'door-open', 'info', url('locaux.index')],
    ['Employés actifs', $stats['nb_employes'], 'people', 'secondary', url('employes.index')],
    ['Biens sans local', $stats['sans_local'], 'geo', 'danger', url('biens.index', ['sans_local' => 1])],
];
$maxCat = max(1, ...array_map(static fn($c) => (float) $c['valeur'], $stats['par_categorie'] ?: [['valeur' => 1]]));
?>
<?= partial('page_header', ['title' => 'Tableau de bord', 'icon' => 'speedometer2',
    'actions' => can('biens.create') ? '<a class="btn btn-primary" href="' . e(url('biens.create')) . '"><i class="bi bi-plus-lg"></i> Nouveau bien</a>' : '']) ?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon, $color, $href]): ?>
    <div class="col-sm-6 col-xl-4">
        <a href="<?= e($href) ?>" class="text-decoration-none">
        <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="icon bg-<?= $color ?>-subtle text-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
            <div><div class="small text-body-secondary"><?= e($label) ?></div><div class="fs-5 fw-semibold text-body text-nowrap"><?= e($value) ?></div></div>
        </div></div></a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-tags me-1"></i> Valeur par catégorie</div>
            <div class="card-body">
                <?php foreach ($stats['par_categorie'] as $c): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span><?= e($c['libelle']) ?> <span class="text-body-secondary">(<?= (int) $c['nb'] ?>)</span></span><span class="fw-semibold"><?= money($c['valeur']) ?></span></div>
                        <div class="progress" style="height:6px"><div class="progress-bar" style="width: <?= round((float) $c['valeur'] / $maxCat * 100, 1) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-flag me-1"></i> Répartition par statut</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($stats['par_statut'] as $s): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a class="text-decoration-none" href="<?= e(url('biens.index', ['statut' => $s['statut']])) ?>"><?= badge(STATUTS_BIEN, $s['statut']) ?></a>
                        <span class="text-end"><strong><?= number_format((int) $s['nb'], 0, ',', ' ') ?></strong><div class="small text-body-secondary text-nowrap"><?= money($s['valeur']) ?></div></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar3 me-1"></i> Acquisitions par année</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($stats['par_annee'] as $a): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a class="text-decoration-none" href="<?= e(url('biens.index', ['date_du' => $a['annee'] . '-01-01', 'date_au' => $a['annee'] . '-12-31'])) ?>"><?= (int) $a['annee'] ?></a>
                        <span><strong><?= (int) $a['nb'] ?></strong> <span class="small text-body-secondary">· <?= money($a['valeur']) ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if (can('transferts.view')): ?>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-arrow-left-right me-1"></i> Derniers transferts</span>
                <a class="small" href="<?= e(url('transferts.index')) ?>">Tout voir</a></div>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead><tr><th>N°</th><th>Date</th><th>Trajet</th><th>Biens</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($transferts['derniers'] as $t): ?>
                    <tr><td><a href="<?= e(url('transferts.show', ['id' => $t['id']])) ?>"><?= e($t['numero']) ?></a></td>
                        <td><?= date_fr($t['date_transfert']) ?></td>
                        <td class="small"><?= e($t['source']) ?> <i class="bi bi-arrow-right"></i> <?= e($t['destination']) ?></td>
                        <td><?= (int) $t['nb_biens'] ?></td><td><?= badge(STATUTS_TRANSFERT, $t['statut']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$transferts['derniers']): ?><tr><td colspan="5" class="text-center text-body-secondary py-3">Aucun transfert.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($traces): ?>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-journal-text me-1"></i> Activité récente</span>
                <a class="small" href="<?= e(url('traces.index')) ?>">Journal complet</a></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($traces as $t): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span><span class="badge text-bg-light border"><?= e($t['action']) ?></span> <?= e($t['description']) ?></span>
                        <span class="text-body-secondary text-nowrap"><?= e($t['username']) ?> · <?= datetime_fr($t['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (user()['is_super'] ?? false): ?>
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-lightning-charge me-1"></i> Performances (OPcache / APCu)</div>
    <div class="card-body row small">
        <div class="col-md-6">
            <strong>OPcache :</strong>
            <?php if ($opcache && !empty($opcache['opcache_enabled'])): ?>
                <span class="badge text-bg-success">actif</span>
                · scripts en cache : <?= (int) ($opcache['opcache_statistics']['num_cached_scripts'] ?? 0) ?>
                · taux de succès : <?= round((float) ($opcache['opcache_statistics']['opcache_hit_rate'] ?? 0), 1) ?> %
                · mémoire utilisée : <?= round(($opcache['memory_usage']['used_memory'] ?? 0) / 1048576, 1) ?> Mo
                <?= !empty($opcache['preload_statistics']) ? '· préchargement actif' : '' ?>
            <?php else: ?><span class="badge text-bg-warning">inactif</span> (activez opcache.enable=1)<?php endif; ?>
        </div>
        <div class="col-md-6">
            <strong>APCu :</strong>
            <?php if ($cache['enabled']): ?>
                <span class="badge text-bg-success">actif</span>
                · entrées : <?= (int) $cache['entries'] ?> · hits : <?= (int) $cache['hits'] ?> · misses : <?= (int) $cache['misses'] ?>
                · mémoire libre : <?= round($cache['avail'] / 1048576, 1) ?> Mo
            <?php else: ?><span class="badge text-bg-warning">inactif</span> (installez l'extension apcu)<?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
