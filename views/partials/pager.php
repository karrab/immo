<?php
/** @var array $page  résultat Keyset::paginate() */
$pp = (int) ($_GET['per_page'] ?? config('app.per_page', 25));
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-print-none">
    <div class="d-flex align-items-center gap-2 small text-body-secondary">
        <span><?= count($page['rows']) ?> ligne(s) affichée(s)<?= isset($total) ? ' sur ' . number_format((int) $total, 0, ',', ' ') : '' ?></span>
        <select class="form-select form-select-sm w-auto js-per-page" aria-label="Lignes par page">
            <?php foreach ([10, 25, 50, 100] as $n): ?>
                <option value="<?= e(url_with(['per_page' => $n], ['after', 'before'])) ?>" <?= $pp === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
            <?php endforeach; ?>
        </select>
    </div>
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page['prev'] ? '' : 'disabled' ?>">
                <a class="page-link" href="<?= e(url_with([], ['after', 'before'])) ?>" title="Début"><i class="bi bi-chevron-double-left"></i></a>
            </li>
            <li class="page-item <?= $page['prev'] ? '' : 'disabled' ?>">
                <a class="page-link" href="<?= $page['prev'] ? e(url_with(['before' => $page['prev']], ['after'])) : '#' ?>"><i class="bi bi-chevron-left"></i> Précédent</a>
            </li>
            <li class="page-item <?= $page['next'] ? '' : 'disabled' ?>">
                <a class="page-link" href="<?= $page['next'] ? e(url_with(['after' => $page['next']], ['before'])) : '#' ?>">Suivant <i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
    </nav>
</div>
