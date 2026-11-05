<?php
// Accès à la racine du projet (ex. http://localhost/immo/) : redirection vers le dossier public.
header('Location: public/' . (is_file(__DIR__ . '/storage/install.lock') || is_file(__DIR__ . '/config/config.local.php') ? '' : 'install.php'));
exit;
