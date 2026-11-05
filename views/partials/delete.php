<?php
/** Bouton supprimer (POST + CSRF + modale de confirmation).
 *  @var string $route @var int $id @var string $label @var bool $small */
$small ??= true;
$text ??= '';
?>
<form method="post" action="<?= e(url($route)) ?>" class="d-inline" data-confirm="<?= e('Supprimer définitivement « ' . $label . ' » ?') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <button type="submit" class="btn <?= $small ? 'btn-sm' : '' ?> btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i><?= $text ? ' ' . e($text) : '' ?></button>
</form>
