<?php
/**
 * Pagination keyset (précédent / suivant).
 * @var array  $page   résultat Keyset::paginate()
 * @var ?int   $total  nombre total (facultatif)
 * @var string $prefix préfixe des paramètres, pour avoir plusieurs listes paginées sur une page
 */
$prefix ??= '';
$a = $prefix . 'after';
$b = $prefix . 'before';
$ppKey = $prefix . 'per_page';
$pp = (int) ($_GET[$ppKey] ?? config('app.per_page', 25));
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 d-print-none">
    <div class="d-flex align-items-center gap-2 small text-body-secondary">
        <span><?= count($page['rows']) ?> ligne(s) affichée(s)<?= isset($total) ? ' sur ' . number_format((int) $total, 0, ',', ' ') : '' ?></span>
        <select class="form-select form-select-sm w-auto js-per-page" aria-label="Lignes par page">
            <?php foreach ([10, 25, 50, 100] as $n): ?>
                <option value="<?= e(url_with([$ppKey => $n], [$a, $b])) ?>" <?= $pp === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
            <?php endforeach; ?>
        </select>
    </div>
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page['prev'] ? '' : 'disabled' ?>">
                <a class="page-link" href="<?= e(url_with([], [$a, $b])) ?>" title="Début"><i class="bi bi-chevron-double-left"></i></a>
            </li>
            <li class="page-item <?= $page['prev'] ? '' : 'disabled' ?>">
                <a class="page-link" href="<?= $page['prev'] ? e(url_with([$b => $page['prev']], [$a])) : '#' ?>"><i class="bi bi-chevron-left"></i> Précédent</a>
            </li>
            <li class="page-item <?= $page['next'] ? '' : 'disabled' ?>">
                <a class="page-link" href="<?= $page['next'] ? e(url_with([$a => $page['next']], [$b])) : '#' ?>">Suivant <i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
    </nav>
</div>
