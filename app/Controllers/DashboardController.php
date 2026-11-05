<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Scope;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->authorize('dashboard.view');
        $ttl = (int) config('cache.ttl_stats', 300);
        $global = Scope::isGlobal();

        // Statistiques limitées au périmètre (tout / service / employé), cache APCu par périmètre
        $w = [];
        $p = [];
        Scope::applyBiens($w, $p);
        $from = ' FROM biens b LEFT JOIN locaux l ON l.id = b.local_id LEFT JOIN employes e ON e.id = b.employe_id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        $and = $w ? ' AND ' : ' WHERE ';

        $stats = Cache::rememberTagged('biens', 'dashboard:' . Scope::key(), $ttl, static function () use ($from, $and, $p, $global) {
            $tot = Database::one('SELECT COUNT(*) AS nb, COALESCE(SUM(b.valeur_acquisition),0) AS valeur' . $from, $p);
            return [
                'nb_biens'   => (int) $tot['nb'],
                'valeur'     => (float) $tot['valeur'],
                'par_statut' => Database::all('SELECT b.statut, COUNT(*) AS nb, SUM(b.valeur_acquisition) AS valeur' . $from . ' GROUP BY b.statut', $p),
                'par_categorie' => Database::all(
                    'SELECT c.libelle, COUNT(*) AS nb, COALESCE(SUM(b.valeur_acquisition),0) AS valeur'
                    . str_replace(' FROM biens b', ' FROM biens b JOIN categories c ON c.id = b.categorie_id', $from)
                    . ' GROUP BY c.id, c.libelle ORDER BY valeur DESC LIMIT 8', $p),
                'par_annee' => Database::all(
                    'SELECT YEAR(b.date_acquisition) AS annee, COUNT(*) AS nb, SUM(b.valeur_acquisition) AS valeur'
                    . $from . ' GROUP BY YEAR(b.date_acquisition) ORDER BY annee DESC LIMIT 6', $p),
                'nb_locaux'   => $global ? (int) Database::value('SELECT COUNT(*) FROM locaux WHERE actif = 1') : null,
                'nb_employes' => $global ? (int) Database::value('SELECT COUNT(*) FROM employes WHERE actif = 1') : null,
                'sans_local'  => (int) Database::value('SELECT COUNT(*)' . $from . $and . 'b.local_id IS NULL', $p),
            ];
        });

        // Transferts visibles + nombre à valider par le chef connecté
        $tw = [];
        $tp = [];
        Scope::applyTransferts($tw, $tp);
        $chefSid = Scope::chefOfService();
        $transferts = can('transferts.view') ? Cache::rememberTagged('transferts', 'dashboard:' . Scope::key(), $ttl, static function () use ($tw, $tp, $chefSid) {
            $where = $tw ? ' WHERE ' . implode(' AND ', $tw) : '';
            return [
                'a_valider' => $chefSid ? (int) Database::value(
                    "SELECT COUNT(*) FROM transferts t JOIN locaux ls ON ls.id = t.local_source_id WHERE t.statut = 'en_attente' AND ls.service_id = ?", [$chefSid]) : 0,
                'en_attente' => (int) Database::value(
                    "SELECT COUNT(*) FROM transferts t JOIN locaux ls ON ls.id = t.local_source_id JOIN locaux ld ON ld.id = t.local_destination_id"
                    . ($where ? $where . " AND t.statut = 'en_attente'" : " WHERE t.statut = 'en_attente'"), $tp),
                'derniers' => Database::all(
                    'SELECT t.id, t.numero, t.date_transfert, t.statut, ls.code AS source, ld.code AS destination,
                            (SELECT COUNT(*) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS nb_biens
                       FROM transferts t
                       JOIN locaux ls ON ls.id = t.local_source_id
                       JOIN locaux ld ON ld.id = t.local_destination_id' . $where . '
                      ORDER BY t.id DESC LIMIT 6', $tp),
            ];
        }) : ['a_valider' => 0, 'en_attente' => 0, 'derniers' => []];

        $traces = can('traces.view')
            ? Database::all('SELECT id, username, action, module, description, created_at FROM traces ORDER BY id DESC LIMIT 8')
            : [];

        $this->render('dashboard/index', [
            'title'      => 'Tableau de bord',
            'scope'      => Scope::current()[0],
            'scopeLabel' => Scope::label(),
            'chefSid'    => $chefSid,
            'stats'      => $stats,
            'transferts' => $transferts,
            'traces'     => $traces,
            'cache'      => \App\Core\Cache::stats(),
            'opcache'    => function_exists('opcache_get_status') ? (@opcache_get_status(false) ?: null) : null,
        ]);
    }
}
