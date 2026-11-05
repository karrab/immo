<div class="text-center py-5">
    <div class="display-1 text-danger"><i class="bi bi-bug"></i></div>
    <h1 class="h3">Une erreur est survenue</h1>
    <p class="text-body-secondary">L'erreur a été enregistrée. Veuillez réessayer ou contacter l'administrateur.</p>
    <?php if (!empty($error)): ?><pre class="text-start small bg-light p-3 border rounded"><?= e($error) ?></pre><?php endif; ?>
    <a class="btn btn-primary" href="<?= e(url('dashboard.index')) ?>">Retour à l'accueil</a>
</div>
