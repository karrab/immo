<?php
/** Boutons Imprimer / Exporter PDF / CSV pour une liste filtrée. @var string $route */
?>
<div class="btn-group">
    <a class="btn btn-outline-secondary" target="_blank" href="<?= e(url_with(['r' => $route, 'inline' => 1], ['after', 'before', 'per_page'])) ?>"><i class="bi bi-printer"></i> Imprimer</a>
    <a class="btn btn-outline-danger" href="<?= e(url_with(['r' => $route], ['after', 'before', 'per_page'])) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a class="btn btn-outline-success" href="<?= e(url_with(['r' => $route, 'format' => 'csv'], ['after', 'before', 'per_page'])) ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
</div>
