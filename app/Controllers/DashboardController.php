<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Controller;
use App\Core\Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->authorize('dashboard.view');
        $ttl = (int) config('cache.ttl_stats', 300);

        $stats = Cache::rememberTagged('biens', 'dashboard', $ttl, static function () {
            $tot = Database::one('SELECT COUNT(*) AS nb, COALESCE(SUM(valeur_acquisition),0) AS valeur FROM biens');
            return [
                'nb_biens'   => (int) $tot['nb'],
                'valeur'     => (float) $tot['valeur'],
                'par_statut' => Database::all('SELECT statut, COUNT(*) AS nb, SUM(valeur_acquisition) AS valeur FROM biens GROUP BY statut'),
                'par_categorie' => Database::all(
                    'SELECT c.libelle, COUNT(b.id) AS nb, COALESCE(SUM(b.valeur_acquisition),0) AS valeur
                       FROM categories c LEFT JOIN biens b ON b.categorie_id = c.id
                      GROUP BY c.id, c.libelle ORDER BY valeur DESC LIMIT 8'),
                'par_annee' => Database::all(
                    'SELECT YEAR(date_acquisition) AS annee, COUNT(*) AS nb, SUM(valeur_acquisition) AS valeur
                       FROM biens GROUP BY YEAR(date_acquisition) ORDER BY annee DESC LIMIT 6'),
                'nb_locaux'   => (int) Database::value('SELECT COUNT(*) FROM locaux WHERE actif = 1'),
                'nb_employes' => (int) Database::value('SELECT COUNT(*) FROM employes WHERE actif = 1'),
                'sans_local'  => (int) Database::value('SELECT COUNT(*) FROM biens WHERE local_id IS NULL'),
            ];
        });

        $transferts = Cache::rememberTagged('transferts', 'dashboard', $ttl, static fn() => [
            'en_attente' => (int) Database::value("SELECT COUNT(*) FROM transferts WHERE statut = 'en_attente'"),
            'derniers'   => Database::all(
                'SELECT t.id, t.numero, t.date_transfert, t.statut, ls.code AS source, ld.code AS destination,
                        (SELECT COUNT(*) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS nb_biens
                   FROM transferts t
                   JOIN locaux ls ON ls.id = t.local_source_id
                   JOIN locaux ld ON ld.id = t.local_destination_id
                  ORDER BY t.id DESC LIMIT 6'),
        ]);

        $traces = can('traces.view')
            ? Database::all('SELECT id, username, action, module, description, created_at FROM traces ORDER BY id DESC LIMIT 8')
            : [];

        $this->render('dashboard/index', [
            'title'      => 'Tableau de bord',
            'stats'      => $stats,
            'transferts' => $transferts,
            'traces'     => $traces,
            'cache'      => Cache::stats(),
            'opcache'    => function_exists('opcache_get_status') ? (@opcache_get_status(false) ?: null) : null,
        ]);
    }
}
