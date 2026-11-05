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

final class EmployesController extends Controller
{
    private const SELECT = "SELECT e.*, s.nom AS service, l.code AS local_code, l.nom AS local_nom,
            (SELECT COUNT(*) FROM biens b WHERE b.employe_id = e.id) AS nb_biens
       FROM employes e
       LEFT JOIN services s ON s.id = e.service_id
       LEFT JOIN locaux l ON l.id = e.local_id";

    private const SORTS = ['nom' => ['e.nom', 'nom'], 'matricule' => ['e.matricule', 'matricule'], 'id' => ['e.id', 'id']];

    private function filters(): array
    {
        $w = [];
        $p = [];
        $f = [
            'q'          => $this->q('q'),
            'service_id' => $this->qint('service_id'),
            'local_id'   => $this->qint('local_id'),
            'actif'      => isset($_GET['actif']) && $_GET['actif'] !== '' ? (int) (bool) $_GET['actif'] : null,
        ];
        if ($f['q']) {
            $w[] = '(e.matricule LIKE ? OR e.nom LIKE ? OR e.prenom LIKE ? OR e.fonction LIKE ?)';
            array_push($p, $f['q'] . '%', $f['q'] . '%', $f['q'] . '%', '%' . $f['q'] . '%');
        }
        if ($f['service_id']) { $w[] = 'e.service_id = ?'; $p[] = $f['service_id']; }
        if ($f['local_id'])   { $w[] = 'e.local_id = ?';   $p[] = $f['local_id']; }
        if ($f['actif'] !== null) { $w[] = 'e.actif = ?';  $p[] = $f['actif']; }
        return [$w, $p, $f];
    }

    private function sort(): array
    {
        $k = is_string($_GET['sort'] ?? null) && isset(self::SORTS[$_GET['sort']]) ? $_GET['sort'] : 'nom';
        $dir = strtolower((string) ($_GET['dir'] ?? ($k === 'id' ? 'desc' : 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        return [...self::SORTS[$k], $dir];
    }

    public function index(): void
    {
        $this->authorize('employes.view');
        [$w, $p, $f] = $this->filters();
        [$se, $sk, $dir] = $this->sort();
        $page = (new Keyset(self::SELECT, $w, $p, $se, $sk, 'e.id', $dir, $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $total = $this->cachedCount('employes', 'SELECT COUNT(*) FROM employes e' . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p);
        $this->render('employes/index', ['title' => 'Employés', 'page' => $page, 'filters' => $f, 'total' => $total]);
    }

    public function export(): void
    {
        $this->authorize('employes.export');
        [$w, $p] = $this->filters();
        [$se, , $dir] = $this->sort();
        $rows = Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY $se $dir, e.id $dir LIMIT " . (int) config('app.export_max'), $p);
        Audit::log('export', 'employes', null, 'Export de la liste des employés (' . count($rows) . ' lignes)');
        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('employes_' . date('Ymd') . '.csv', ['Matricule', 'Nom', 'Prénom', 'Fonction', 'Service', 'Local', 'Email', 'Téléphone', 'Nb biens', 'Actif'],
                array_map(static fn($r) => [$r['matricule'], $r['nom'], $r['prenom'], $r['fonction'], $r['service'], $r['local_code'],
                    $r['email'], $r['telephone'], $r['nb_biens'], $r['actif'] ? 'Oui' : 'Non'], $rows));
        }
        $html = View::render('pdf/employes_liste', ['title' => 'Liste des employés', 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'employes_' . date('Ymd') . '.pdf', 'landscape', !empty($_GET['inline']));
    }

    private function find(int $id): array
    {
        return Database::one(
            'SELECT e.*, s.nom AS service, l.code AS local_code, l.nom AS local_nom
               FROM employes e LEFT JOIN services s ON s.id = e.service_id LEFT JOIN locaux l ON l.id = e.local_id
              WHERE e.id = ?', [$id]) ?? $this->notFound('Employé introuvable.');
    }

    public function show(): void
    {
        $this->authorize('employes.view');
        $emp = $this->find((int) $this->qint('id'));
        $biens = Database::all(
            'SELECT b.id, b.code_inventaire, b.designation, b.etat, b.statut, b.valeur_acquisition, l.code AS local_code
               FROM biens b LEFT JOIN locaux l ON l.id = b.local_id WHERE b.employe_id = ? ORDER BY b.code_inventaire LIMIT 500',
            [$emp['id']]
        );
        $this->render('employes/show', ['title' => $emp['nom'] . ' ' . $emp['prenom'], 'emp' => $emp, 'biens' => $biens]);
    }

    /** Fiche de détention (décharge) des biens affectés à l'employé. */
    public function decharge(): void
    {
        $this->authorize('employes.export');
        $emp = $this->find((int) $this->qint('id'));
        $biens = Database::all(
            'SELECT b.code_inventaire, b.designation, b.marque, b.modele, b.numero_serie, b.etat, b.valeur_acquisition, l.code AS local_code
               FROM biens b LEFT JOIN locaux l ON l.id = b.local_id WHERE b.employe_id = ? ORDER BY b.code_inventaire',
            [$emp['id']]
        );
        Audit::log('impression', 'employes', (int) $emp['id'], 'Impression fiche de détention ' . $emp['matricule']);
        $html = View::render('pdf/employe_decharge', ['title' => 'Fiche de détention', 'emp' => $emp, 'biens' => $biens], 'layout/pdf');
        Pdf::stream($html, 'detention_' . $emp['matricule'] . '.pdf', 'portrait', !empty($_GET['inline']));
    }

    public function create(): void
    {
        $this->authorize('employes.create');
        $this->render('employes/form', ['title' => 'Nouvel employé', 'item' => ['actif' => 1], 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('employes.edit');
        $this->render('employes/form', ['title' => 'Modifier l\'employé', 'item' => $this->find((int) $this->qint('id')), 'errors' => []]);
    }

    public function store(): void
    {
        $this->authorize('employes.create');
        $this->save(null);
    }

    public function update(): void
    {
        $this->authorize('employes.edit');
        $this->save((int) $this->int('id'));
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        $before = $id ? (Database::one('SELECT * FROM employes WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $d = [
            'matricule'     => strtoupper((string) $this->str('matricule', 30)),
            'nom'           => mb_strtoupper((string) $this->str('nom', 80)),
            'prenom'        => $this->str('prenom', 80),
            'fonction'      => $this->str('fonction', 120),
            'service_id'    => $this->int('service_id'),
            'local_id'      => $this->int('local_id'),
            'email'         => $this->str('email', 150),
            'telephone'     => $this->str('telephone', 30),
            'date_embauche' => $this->date('date_embauche'),
            'actif'         => empty($_POST['actif']) ? 0 : 1,
        ];
        $e = [];
        if (!preg_match('/^[A-Z0-9_\-]{1,30}$/', $d['matricule'])) $e['matricule'] = 'Matricule obligatoire (lettres, chiffres).';
        if ($d['nom'] === '') $e['nom'] = 'Nom obligatoire.';
        if (!$d['prenom']) $e['prenom'] = 'Prénom obligatoire.';
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Email invalide.';
        if ($d['service_id'] && !in_array($d['service_id'], array_column(Ref::services(), 'id'))) $e['service_id'] = 'Service invalide.';
        if ($d['local_id'] && !in_array($d['local_id'], array_column(Ref::locaux(), 'id'))) $e['local_id'] = 'Local invalide.';
        if (!$e) {
            try {
                if ($id) {
                    Database::update('employes', $d, 'id = ?', [$id]);
                    Audit::log('modification', 'employes', $id, 'Modification employé ' . $d['matricule'], Audit::diff($before, $d));
                } else {
                    $id = Database::insert('employes', $d);
                    Audit::log('creation', 'employes', $id, 'Création employé ' . $d['matricule'], $d);
                }
                Cache::invalidate('employes', 'biens');
                flash('success', 'Employé enregistré.');
                $this->redirect('employes.show', ['id' => $id]);
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e['matricule'] = 'Ce matricule existe déjà.';
            }
        }
        $this->render('employes/form', ['title' => $id ? 'Modifier l\'employé' : 'Nouvel employé', 'item' => $d + ['id' => $id], 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('employes.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $emp = $this->find($id);
        if ((int) Database::value('SELECT COUNT(*) FROM biens WHERE employe_id = ?', [$id]) > 0) {
            flash('danger', 'Des biens sont encore affectés à cet employé : réaffectez-les ou désactivez l\'employé.');
            $this->redirect('employes.show', ['id' => $id]);
        }
        $this->safeDelete('employes', $id, 'employes', $emp['matricule'] . ' ' . $emp['nom'], 'employes.index', ['employes']);
    }
}
