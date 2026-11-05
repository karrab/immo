<?php
$messages = [
    1045 => 'Accès refusé : l\'utilisateur ou le mot de passe MySQL est incorrect.',
    1044 => 'Cet utilisateur MySQL n\'a pas accès à la base configurée.',
    1049 => 'La base de données n\'existe pas encore.',
    1146 => 'Les tables de l\'application n\'existent pas : l\'installation n\'a pas été faite.',
    2002 => 'Le serveur MySQL est injoignable : est-il démarré (XAMPP → Start MySQL) ?',
    2006 => 'La connexion au serveur MySQL a été perdue.',
];
?>
<div class="card mx-auto shadow-lg" style="max-width: 560px"><div class="card-body p-4 text-center">
    <div class="display-5 text-warning"><i class="bi bi-database-exclamation"></i></div>
    <h1 class="h4">Base de données indisponible</h1>
    <p class="text-body-secondary"><?= e($messages[$code] ?? 'Erreur de connexion.') ?></p>
    <?php if ($installable): ?>
        <a class="btn btn-primary" href="install.php"><i class="bi bi-download"></i> Lancer l'installation</a>
    <?php else: ?>
        <p class="small">Vérifiez les accès dans <code>config/config.local.php</code>.</p>
    <?php endif; ?>
</div></div>
