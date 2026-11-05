<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\Pdf;
use App\Core\Scope;
use App\Core\View;

/**
 * Services / directions : chef de service, employés, locaux, biens, transferts.
 */
final class ServicesController extends Controller
{
    private const SELECT = "SELECT s.*, ch.id AS chef_id, ch.matricule AS chef_matricule, CONCAT(ch.nom, ' ', ch.prenom) AS chef,
                   (SELECT COUNT(*) FROM locaux l WHERE l.service_id = s.id) AS nb_locaux,
                   (SELECT COUNT(*) FROM employes e WHERE e.service_id = s.id) AS nb_employes
              FROM services s
              LEFT JOIN employes ch ON ch.chef_service_id = s.id";

    /** Restreint aux services visibles : tous, ou seulement le sien (chef / employé). */
    private function scopeWhere(array &$w, array &$p): void
    {
        if (Scope::isGlobal()) {
            return;
        }
        $sid = user()['employe_service_id'] ?? null;
        $w[] = 's.id = ?';
        $p[] = (int) $sid;
    }

    private function filters(): array
    {
        $w = [];
        $p = [];
        if ($q = $this->q('q')) {
            $w[] = '(s.code LIKE ? OR s.nom LIKE ?)';
            array_push($p, $q . '%', '%' . $q . '%');
        }
        if (isset($_GET['sans_chef']) && $_GET['sans_chef'] === '1') {
            $w[] = 'ch.id IS NULL';
        }
        $this->scopeWhere($w, $p);
        return [$w, $p];
    }

    public function index(): void
    {
        $this->authorize('services.view');
        [$w, $p] = $this->filters();
        $page = $this->keyset(self::SELECT, $w, $p, 's.nom', 'nom', 's.id', 'ASC');
        $total = $this->cachedCount('services', 'SELECT COUNT(*) FROM services s LEFT JOIN employes ch ON ch.chef_service_id = s.id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p);
        $this->render('services/index', ['title' => 'Services / Directions', 'page' => $page, 'total' => $total]);
    }

    public function export(): void
    {
        $this->authorize('services.view');
        [$w, $p] = $this->filters();
        $rows = Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY s.nom LIMIT ' . (int) config('app.export_max'), $p);
        Audit::log('export', 'services', null, 'Export de la liste des services (' . count($rows) . ' lignes)');
        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('services_' . date('Ymd') . '.csv', ['Code', 'Nom', 'Chef de service', 'Locaux', 'Employés', 'Description'],
                array_map(static fn($r) => [$r['code'], $r['nom'], $r['chef'], $r['nb_locaux'], $r['nb_employes'], $r['description']], $rows));
        }
        $html = View::render('pdf/services_liste', ['title' => 'Liste des services', 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'services_' . date('Ymd') . '.pdf', 'portrait', !empty($_GET['inline']));
    }

    private function find(int $id): array
    {
        $w = ['s.id = ?'];
        $p = [$id];
        $this->scopeWhere($w, $p);
        return Database::one(self::SELECT . ' WHERE ' . implode(' AND ', $w), $p) ?? $this->notFound('Service introuvable.');
    }

    public function show(): void
    {
        $this->authorize('services.view');
        $s = $this->find((int) $this->qint('id'));
        $sid = (int) $s['id'];

        $stats = Cache::rememberTagged('biens', "service:$sid", 300, static fn() => Database::one(
            'SELECT COUNT(*) AS nb, COALESCE(SUM(b.valeur_acquisition),0) AS valeur
               FROM biens b LEFT JOIN locaux l ON l.id = b.local_id LEFT JOIN employes e ON e.id = b.employe_id
              WHERE (e.service_id = ? OR l.service_id = ?)', [$sid, $sid]));

        $employes = $this->keyset(
            "SELECT e.id, e.matricule, e.nom, e.prenom, e.fonction, e.type, e.actif,
                    (SELECT COUNT(*) FROM biens b WHERE b.employe_id = e.id) AS nb_biens
               FROM employes e",
            ['e.service_id = ?'], [$sid], 'e.nom', 'nom', 'e.id', 'ASC', 'e_');

        $locaux = $this->keyset(
            'SELECT l.id, l.code, l.nom, l.type, l.batiment, l.etage, (SELECT COUNT(*) FROM biens b WHERE b.local_id = l.id) AS nb_biens
               FROM locaux l',
            ['l.service_id = ?'], [$sid], 'l.code', 'code', 'l.id', 'ASC', 'l_');

        $enAttente = Database::all(
            "SELECT t.id, t.numero, t.date_transfert, ls.code AS source, ld.code AS destination,
                    (SELECT COUNT(*) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS nb_biens
               FROM transferts t JOIN locaux ls ON ls.id = t.local_source_id JOIN locaux ld ON ld.id = t.local_destination_id
              WHERE t.statut = 'en_attente' AND ls.service_id = ? ORDER BY t.id DESC LIMIT 20", [$sid]);

        $this->render('services/show', [
            'title' => 'Service ' . $s['nom'], 's' => $s, 'stats' => $stats,
            'employes' => $employes, 'locaux' => $locaux, 'enAttente' => $enAttente,
        ]);
    }

    public function create(): void
    {
        $this->authorize('services.create');
        $this->render('services/form', ['title' => 'Nouveau service', 'item' => [], 'candidats' => [], 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('services.edit');
        $item = $this->find((int) $this->qint('id'));
        $this->render('services/form', ['title' => 'Modifier le service', 'item' => $item, 'candidats' => $this->candidats((int) $item['id']), 'errors' => []]);
    }

    /** Employés actifs du service pouvant être désignés chef. */
    private function candidats(int $serviceId): array
    {
        return Database::all("SELECT id, matricule, CONCAT(nom, ' ', prenom) AS nom_complet, fonction FROM employes
                               WHERE service_id = ? AND actif = 1 ORDER BY nom, prenom", [$serviceId]);
    }

    public function store(): void
    {
        $this->authorize('services.create');
        $this->save(null);
    }

    public function update(): void
    {
        $this->authorize('services.edit');
        $this->save((int) $this->int('id'));
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        $before = $id ? $this->find($id) : null;
        $d = [
            'code'        => strtoupper((string) $this->str('code', 20)),
            'nom'         => $this->str('nom', 150),
            'description' => $this->str('description', 255),
        ];
        $chefId = $this->int('chef_id');
        $e = [];
        if (!preg_match('/^[A-Z0-9_\-]{1,20}$/', $d['code'])) $e['code'] = 'Code obligatoire (lettres majuscules, chiffres).';
        if (!$d['nom']) $e['nom'] = 'Nom obligatoire.';
        if ($id && $chefId && !in_array($chefId, array_map('intval', array_column($this->candidats($id), 'id')), true)) {
            $e['chef_id'] = 'Le chef doit être un employé actif de ce service.';
        }
        if (!$e) {
            try {
                Database::transaction(function () use (&$id, $d, $chefId, $before) {
                    if ($id) {
                        Database::update('services', $d, 'id = ?', [$id]);
                        $oldChef = $before['chef_id'] ? (int) $before['chef_id'] : null;
                        if ($oldChef !== $chefId) {
                            // Rétrograde l'ancien chef puis promeut le nouveau (contrainte : un seul chef)
                            Database::run("UPDATE employes SET type = 'employe' WHERE chef_service_id = ?", [$id]);
                            if ($chefId) {
                                Database::update('employes', ['type' => 'chef_service'], 'id = ?', [$chefId]);
                            }
                            Audit::log('chef_service', 'services', $id, 'Changement de chef du service ' . $d['code'],
                                ['chef' => [$before['chef'] ?? null, $chefId ? Database::value("SELECT CONCAT(nom,' ',prenom) FROM employes WHERE id = ?", [$chefId]) : null]]);
                        }
                        Audit::log('modification', 'services', $id, 'Modification service ' . $d['code'], Audit::diff($before, $d));
                    } else {
                        $id = Database::insert('services', $d);
                        Audit::log('creation', 'services', $id, 'Création service ' . $d['code'], $d);
                    }
                });
                Cache::invalidate('services', 'employes');
                flash('success', 'Service enregistré.');
                $this->redirect('services.show', ['id' => $id]);
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e['code'] = 'Ce code existe déjà.';
            }
        }
        $this->render('services/form', ['title' => $id ? 'Modifier le service' : 'Nouveau service',
            'item' => $d + ['id' => $id, 'chef_id' => $chefId], 'candidats' => $id ? $this->candidats($id) : [], 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('services.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $item = $this->find($id);
        if ((int) $item['nb_employes'] > 0 || (int) $item['nb_locaux'] > 0) {
            flash('danger', 'Ce service a encore des employés ou des locaux rattachés : réaffectez-les avant de le supprimer.');
            $this->redirect('services.show', ['id' => $id]);
        }
        $this->safeDelete('services', $id, 'services', $item['code'] . ' ' . $item['nom'], 'services.index', ['services', 'locaux', 'employes']);
    }
}
