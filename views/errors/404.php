<div class="text-center py-5">
    <div class="display-1 text-warning"><i class="bi bi-question-circle"></i></div>
    <h1 class="h3">Introuvable</h1>
    <p class="text-body-secondary"><?= e($message ?? 'La page demandée n\'existe pas.') ?></p>
    <a class="btn btn-primary" href="<?= e(url('dashboard.index')) ?>">Retour à l'accueil</a>
</div>
