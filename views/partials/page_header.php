<?php /** @var string $title @var string $icon @var string $actions */ ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?php if (!empty($icon)): ?><i class="bi bi-<?= e($icon) ?> text-primary me-2"></i><?php endif; ?><?= e($title) ?></h1>
    <div class="d-flex flex-wrap gap-2 d-print-none"><?= $actions ?? '' ?></div>
</div>
