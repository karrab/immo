<div class="text-center py-5">
    <div class="display-1 text-danger"><i class="bi bi-shield-x"></i></div>
    <h1 class="h3">Accès refusé</h1>
    <p class="text-body-secondary">Vous n'avez pas la permission nécessaire<?= !empty($permission) ? ' (<code>' . e($permission) . '</code>)' : '' ?>.</p>
    <a class="btn btn-primary" href="<?= e(url('dashboard.index')) ?>">Retour à l'accueil</a>
</div>
