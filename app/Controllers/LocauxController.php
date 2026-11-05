<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\Keyset;
use App\Core\Pdf;
use App\Core\Ref;
use App\Core\View;

final class LocauxController extends Controller
{
    private const SELECT = 'SELECT l.*, s.nom AS service,
            (SELECT COUNT(*) FROM biens b WHERE b.local_id = l.id) AS nb_biens,
            (SELECT COALESCE(SUM(b.valeur_acquisition),0) FROM biens b WHERE b.local_id = l.id) AS valeur_biens
       FROM locaux l LEFT JOIN services s ON s.id = l.service_id';

    private function filters(): array
    {
        $w = [];
        $p = [];
        $f = [
            'q'          => $this->q('q'),
            'type'       => $this->enum('type', TYPES_LOCAL, $_GET),
            'batiment'   => $this->q('batiment'),
            'service_id' => $this->qint('service_id'),
            'actif'      => isset($_GET['actif']) && $_GET['actif'] !== '' ? (int) (bool) $_GET['actif'] : null,
        ];
        if ($f['q'])          { $w[] = '(l.code LIKE ? OR l.nom LIKE ?)'; array_push($p, $f['q'] . '%', '%' . $f['q'] . '%'); }
        if ($f['type'])       { $w[] = 'l.type = ?'; $p[] = $f['type']; }
        if ($f['batiment'])   { $w[] = 'l.batiment = ?'; $p[] = $f['batiment']; }
        if ($f['service_id']) { $w[] = 'l.service_id = ?'; $p[] = $f['service_id']; }
        if ($f['actif'] !== null) { $w[] = 'l.actif = ?'; $p[] = $f['actif']; }
        return [$w, $p, $f];
    }

    private function batiments(): array
    {
        return Cache::rememberTagged('locaux', 'batiments', 600, static fn() =>
            array_column(Database::all('SELECT DISTINCT batiment FROM locaux WHERE batiment IS NOT NULL ORDER BY batiment'), 'batiment'));
    }

    public function index(): void
    {
        $this->authorize('locaux.view');
        [$w, $p, $f] = $this->filters();
        $page = (new Keyset(self::SELECT, $w, $p, 'l.code', 'code', 'l.id', 'ASC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $total = $this->cachedCount('locaux', 'SELECT COUNT(*) FROM locaux l' . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p);
        $this->render('locaux/index', ['title' => 'Locaux / Bureaux', 'page' => $page, 'filters' => $f, 'total' => $total, 'batiments' => $this->batiments()]);
    }

    public function export(): void
    {
        $this->authorize('locaux.export');
        [$w, $p] = $this->filters();
        $rows = Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY l.code LIMIT ' . (int) config('app.export_max'), $p);
        Audit::log('export', 'locaux', null, 'Export de la liste des locaux (' . count($rows) . ' lignes)');
        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('locaux_' . date('Ymd') . '.csv', ['Code', 'Nom', 'Type', 'Bâtiment', 'Étage', 'Service', 'Nb biens', 'Valeur', 'Actif'],
                array_map(static fn($r) => [$r['code'], $r['nom'], TYPES_LOCAL[$r['type']] ?? $r['type'], $r['batiment'], $r['etage'], $r['service'],
                    $r['nb_biens'], number_format((float) $r['valeur_biens'], 2, ',', ''), $r['actif'] ? 'Oui' : 'Non'], $rows));
        }
        $html = View::render('pdf/locaux_liste', ['title' => 'Liste des locaux', 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'locaux_' . date('Ymd') . '.pdf', 'landscape', !empty($_GET['inline']));
    }

    private function find(int $id): array
    {
        return Database::one('SELECT l.*, s.nom AS service FROM locaux l LEFT JOIN services s ON s.id = l.service_id WHERE l.id = ?', [$id])
            ?? $this->notFound('Local introuvable.');
    }

    public function show(): void
    {
        $this->authorize('locaux.view');
        $local = $this->find((int) $this->qint('id'));
        $select = "SELECT b.id, b.code_inventaire, b.designation, b.etat, b.statut, b.valeur_acquisition, c.libelle AS categorie,
                          CONCAT(e.nom, ' ', e.prenom) AS employe
                     FROM biens b LEFT JOIN categories c ON c.id = b.categorie_id LEFT JOIN employes e ON e.id = b.employe_id";
        $page = (new Keyset($select, ['b.local_id = ?'], [$local['id']], 'b.id', 'id', 'b.id', 'DESC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $stats = Database::one('SELECT COUNT(*) AS nb, COALESCE(SUM(valeur_acquisition),0) AS valeur FROM biens WHERE local_id = ?', [$local['id']]);
        $employes = Database::all('SELECT id, matricule, nom, prenom, fonction FROM employes WHERE local_id = ? AND actif = 1 ORDER BY nom', [$local['id']]);
        $this->render('locaux/show', ['title' => 'Local ' . $local['code'], 'local' => $local, 'page' => $page, 'stats' => $stats, 'employes' => $employes]);
    }

    /** Fiche inventaire du local (liste des biens présents) à signer. */
    public function inventaire(): void
    {
        $this->authorize('locaux.export');
        $local = $this->find((int) $this->qint('id'));
        $rows = Database::all(
            "SELECT b.code_inventaire, b.designation, b.marque, b.modele, b.numero_serie, b.etat, b.valeur_acquisition,
                    CONCAT(e.nom, ' ', e.prenom) AS employe
               FROM biens b LEFT JOIN employes e ON e.id = b.employe_id
              WHERE b.local_id = ? ORDER BY b.code_inventaire",
            [$local['id']]
        );
        Audit::log('impression', 'locaux', (int) $local['id'], 'Impression inventaire du local ' . $local['code']);
        $html = View::render('pdf/local_inventaire', ['title' => 'Inventaire du local ' . $local['code'], 'local' => $local, 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'inventaire_' . $local['code'] . '.pdf', 'portrait', !empty($_GET['inline']));
    }

    public function create(): void
    {
        $this->authorize('locaux.create');
        $this->render('locaux/form', ['title' => 'Nouveau local', 'item' => ['type' => 'bureau', 'actif' => 1], 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('locaux.edit');
        $this->render('locaux/form', ['title' => 'Modifier le local', 'item' => $this->find((int) $this->qint('id')), 'errors' => []]);
    }

    public function store(): void
    {
        $this->authorize('locaux.create');
        $this->save(null);
    }

    public function update(): void
    {
        $this->authorize('locaux.edit');
        $this->save((int) $this->int('id'));
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        $before = $id ? (Database::one('SELECT * FROM locaux WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $d = [
            'code'        => strtoupper((string) $this->str('code', 30)),
            'nom'         => $this->str('nom', 150),
            'type'        => $this->enum('type', TYPES_LOCAL) ?? 'bureau',
            'batiment'    => $this->str('batiment', 100),
            'etage'       => $this->str('etage', 20),
            'service_id'  => $this->int('service_id'),
            'superficie'  => $this->decimal('superficie'),
            'description' => $this->str('description', 255),
            'actif'       => empty($_POST['actif']) ? 0 : 1,
        ];
        $e = [];
        if (!preg_match('/^[A-Z0-9_\-\/.]{1,30}$/', $d['code'])) $e['code'] = 'Code obligatoire (lettres, chiffres, - _ / .).';
        if (!$d['nom']) $e['nom'] = 'Nom obligatoire.';
        if ($d['service_id'] && !in_array($d['service_id'], array_column(Ref::services(), 'id'))) $e['service_id'] = 'Service invalide.';
        if (!$e) {
            try {
                if ($id) {
                    Database::update('locaux', $d, 'id = ?', [$id]);
                    Audit::log('modification', 'locaux', $id, 'Modification du local ' . $d['code'], Audit::diff($before, $d));
                } else {
                    $id = Database::insert('locaux', $d);
                    Audit::log('creation', 'locaux', $id, 'Création du local ' . $d['code'], $d);
                }
                Cache::invalidate('locaux', 'biens');
                flash('success', 'Local enregistré.');
                $this->redirect('locaux.show', ['id' => $id]);
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e['code'] = 'Ce code existe déjà.';
            }
        }
        $this->render('locaux/form', ['title' => $id ? 'Modifier le local' : 'Nouveau local', 'item' => $d + ['id' => $id], 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('locaux.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $l = $this->find($id);
        if ((int) Database::value('SELECT COUNT(*) FROM biens WHERE local_id = ?', [$id]) > 0) {
            flash('danger', 'Ce local contient encore des biens : transférez-les avant de le supprimer.');
            $this->redirect('locaux.show', ['id' => $id]);
        }
        $this->safeDelete('locaux', $id, 'locaux', $l['code'] . ' ' . $l['nom'], 'locaux.index', ['locaux', 'employes']);
    }
}
