<?php
/**
 * Génère N biens fictifs pour tester les performances (pagination keyset, index).
 *   php bin/generate.php 100000
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$n = max(1, (int) ($argv[1] ?? 10000));
$cats = Database::all('SELECT id, code, duree_amortissement FROM categories');
$locaux = array_column(Database::all('SELECT id FROM locaux'), 'id');
$emps = array_column(Database::all('SELECT id FROM employes'), 'id');
if (!$cats) {
    exit("Aucune catégorie : lancez d'abord bin/install.php\n");
}
$noms = ['Ordinateur portable', 'Écran 24 pouces', 'Bureau', 'Chaise ergonomique', 'Armoire', 'Imprimante', 'Climatiseur',
         'Téléphone IP', 'Scanner', 'Onduleur', 'Table de réunion', 'Photocopieur', 'Routeur', 'Switch réseau', 'Caisson'];
$marques = ['Dell', 'HP', 'Lenovo', 'Canon', 'Epson', 'Samsung', 'LG', 'Cisco', 'Steelcase', 'Bureau Plus'];
$etats = ['neuf', 'bon', 'bon', 'bon', 'moyen', 'mauvais'];
$statuts = ['en_service', 'en_service', 'en_service', 'en_stock', 'en_reparation', 'reforme'];

$pdo = Database::pdo();
$batch = 1000;
$start = microtime(true);
$prefix = 'GEN' . date('His');
for ($i = 0; $i < $n; $i += $batch) {
    $rows = [];
    $params = [];
    for ($j = $i; $j < min($n, $i + $batch); $j++) {
        $c = $cats[array_rand($cats)];
        $date = date('Y-m-d', mt_rand(strtotime('2012-01-01'), time()));
        $rows[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?)';
        array_push($params,
            sprintf('%s-%s-%07d', $c['code'], $prefix, $j + 1),
            $noms[array_rand($noms)], $c['id'], $marques[array_rand($marques)], 'M' . mt_rand(100, 999),
            strtoupper(bin2hex(random_bytes(5))), $date, round(mt_rand(50000, 50000000) / 100, 2), $c['duree_amortissement'],
            $etats[array_rand($etats)], $statuts[array_rand($statuts)],
            $locaux ? $locaux[array_rand($locaux)] : null, $emps && mt_rand(0, 1) ? $emps[array_rand($emps)] : null
        );
    }
    $pdo->prepare('INSERT INTO biens (code_inventaire, designation, categorie_id, marque, modele, numero_serie, date_acquisition,
                   valeur_acquisition, duree_amortissement, etat, statut, local_id, employe_id) VALUES ' . implode(',', $rows))->execute($params);
    printf("\r%d / %d", min($n, $i + $batch), $n);
}
printf("\n%d biens générés en %.1f s\n", $n, microtime(true) - $start);
