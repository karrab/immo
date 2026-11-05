<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\Keyset;
use App\Core\Pdf;
use App\Core\Ref;
use App\Core\View;
use App\Services\Amortissement;

final class BiensController extends Controller
{
    private const SELECT = "SELECT b.id, b.code_inventaire, b.designation, b.marque, b.modele, b.numero_serie,
                b.date_acquisition, b.valeur_acquisition, b.etat, b.statut, b.local_id, b.employe_id,
                c.libelle AS categorie, l.code AS local_code, l.nom AS local_nom,
                CONCAT(e.nom, ' ', e.prenom) AS employe
           FROM biens b
           LEFT JOIN categories c ON c.id = b.categorie_id   -- LEFT : force biens comme table pilote (keyset)
           LEFT JOIN locaux l ON l.id = b.local_id
           LEFT JOIN employes e ON e.id = b.employe_id";

    /** Colonnes de tri autorisées : clé URL => [expression SQL, clé de ligne] */
    private const SORTS = [
        'id'          => ['b.id', 'id'],
        'code'        => ['b.code_inventaire', 'code_inventaire'],
        'designation' => ['b.designation', 'designation'],
        'date'        => ['b.date_acquisition', 'date_acquisition'],
        'valeur'      => ['b.valeur_acquisition', 'valeur_acquisition'],
    ];

    private const FIELDS = [
        'code_inventaire' => 'Code inventaire', 'designation' => 'Désignation', 'categorie_id' => 'Catégorie',
        'marque' => 'Marque', 'modele' => 'Modèle', 'numero_serie' => 'N° de série', 'fournisseur' => 'Fournisseur',
        'numero_facture' => 'N° facture', 'date_acquisition' => "Date d'acquisition", 'date_mise_service' => 'Date de mise en service',
        'valeur_acquisition' => "Valeur d'acquisition", 'valeur_residuelle' => 'Valeur résiduelle',
        'duree_amortissement' => 'Durée (ans)', 'methode_amortissement' => 'Méthode', 'etat' => 'État', 'statut' => 'Statut',
        'local_id' => 'Local', 'employe_id' => 'Employé', 'observations' => 'Observations',
    ];

    // ------------------------------------------------------------------
    // Liste + recherche avancée
    // ------------------------------------------------------------------

    /** Construit les conditions WHERE à partir des filtres de recherche avancée. */
    private function filters(): array
    {
        $w = [];
        $p = [];
        $f = [
            'q'            => $this->q('q', 150),
            'code'         => $this->q('code', 40),
            'categorie_id' => $this->qint('categorie_id'),
            'local_id'     => $this->qint('local_id'),
            'service_id'   => $this->qint('service_id'),
            'employe_id'   => $this->qint('employe_id'),
            'statut'       => $this->enum('statut', STATUTS_BIEN, $_GET),
            'etat'         => $this->enum('etat', ETATS_BIEN, $_GET),
            'date_du'      => $this->date('date_du', $_GET),
            'date_au'      => $this->date('date_au', $_GET),
            'valeur_min'   => $this->decimal('valeur_min', $_GET),
            'valeur_max'   => $this->decimal('valeur_max', $_GET),
            'fournisseur'  => $this->q('fournisseur', 150),
            'sans_local'   => !empty($_GET['sans_local']),
        ];

        if ($f['q'] !== null) {
            $words = array_filter(
                preg_split('/[\s\-+<>()~*"@]+/u', $f['q']) ?: [],
                static fn($t) => mb_strlen($t) >= 3
            );
            $like = $f['q'] . '%';
            if ($words) {
                $boolean = implode(' ', array_map(static fn($t) => '+' . $t . '*', $words));
                $w[] = '(MATCH(b.designation, b.marque, b.modele, b.numero_serie, b.fournisseur) AGAINST (? IN BOOLEAN MODE)
                         OR b.code_inventaire LIKE ? OR b.numero_serie LIKE ?)';
                array_push($p, $boolean, $like, $like);
            } else {
                $w[] = '(b.code_inventaire LIKE ? OR b.numero_serie LIKE ? OR b.designation LIKE ?)';
                array_push($p, $like, $like, $like);
            }
        }
        if ($f['code'] !== null)         { $w[] = 'b.code_inventaire LIKE ?'; $p[] = $f['code'] . '%'; }
        if ($f['categorie_id'] !== null) { $w[] = 'b.categorie_id = ?';       $p[] = $f['categorie_id']; }
        if ($f['local_id'] !== null)     { $w[] = 'b.local_id = ?';           $p[] = $f['local_id']; }
        if ($f['service_id'] !== null)   { $w[] = 'l.service_id = ?';         $p[] = $f['service_id']; }
        if ($f['employe_id'] !== null)   { $w[] = 'b.employe_id = ?';         $p[] = $f['employe_id']; }
        if ($f['statut'] !== null)       { $w[] = 'b.statut = ?';             $p[] = $f['statut']; }
        if ($f['etat'] !== null)         { $w[] = 'b.etat = ?';               $p[] = $f['etat']; }
        if ($f['date_du'] !== null)      { $w[] = 'b.date_acquisition >= ?';  $p[] = $f['date_du']; }
        if ($f['date_au'] !== null)      { $w[] = 'b.date_acquisition <= ?';  $p[] = $f['date_au']; }
        if ($f['valeur_min'] !== null)   { $w[] = 'b.valeur_acquisition >= ?'; $p[] = $f['valeur_min']; }
        if ($f['valeur_max'] !== null)   { $w[] = 'b.valeur_acquisition <= ?'; $p[] = $f['valeur_max']; }
        if ($f['fournisseur'] !== null)  { $w[] = 'b.fournisseur LIKE ?';     $p[] = $f['fournisseur'] . '%'; }
        if ($f['sans_local'])            { $w[] = 'b.local_id IS NULL'; }

        return [$w, $p, $f];
    }

    private function sort(): array
    {
        $key = $_GET['sort'] ?? 'id';
        $key = is_string($key) && isset(self::SORTS[$key]) ? $key : 'id';
        $dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return [...self::SORTS[$key], $dir];
    }

    public function index(): void
    {
        $this->authorize('biens.view');
        [$where, $params, $filters] = $this->filters();
        [$sortExpr, $sortKey, $dir] = $this->sort();

        $page = (new Keyset(self::SELECT, $where, $params, $sortExpr, $sortKey, 'b.id', $dir, $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));

        $countSql = 'SELECT COUNT(*) FROM biens b LEFT JOIN locaux l ON l.id = b.local_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $total = $this->cachedCount('biens', $countSql, $params);
        $sumSql = 'SELECT COALESCE(SUM(b.valeur_acquisition),0) FROM biens b LEFT JOIN locaux l ON l.id = b.local_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $somme = (float) Cache::rememberTagged('biens', 'sum:' . md5($sumSql . serialize($params)), 120,
            static fn() => Database::value($sumSql, $params));

        $this->render('biens/index', [
            'title'    => 'Biens / Immobilisations',
            'page'     => $page,
            'total'    => $total,
            'somme'    => $somme,
            'filters'  => $filters,
            'advanced' => (bool) array_filter(array_diff_key($filters, ['q' => 1, 'statut' => 1, 'categorie_id' => 1])),
        ]);
    }

    /** Export de la liste filtrée (PDF ou CSV). */
    public function export(): void
    {
        $this->authorize('biens.export');
        [$where, $params, $filters] = $this->filters();
        [$sortExpr, , $dir] = $this->sort();
        $max = (int) config('app.export_max', 5000);
        $sql = self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY $sortExpr $dir" . ($sortExpr !== 'b.id' ? ", b.id $dir" : '') . " LIMIT $max";
        $rows = Database::all($sql, $params);

        Audit::log('export', 'biens', null, 'Export de la liste des biens (' . count($rows) . ' lignes, ' . ($_GET['format'] ?? 'pdf') . ')', ['filtres' => array_filter($filters)]);

        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('biens_' . date('Ymd_His') . '.csv',
                ['Code inventaire', 'Désignation', 'Catégorie', 'Marque', 'Modèle', 'N° série', 'Date acquisition', 'Valeur', 'État', 'Statut', 'Local', 'Employé'],
                array_map(static fn($r) => [
                    $r['code_inventaire'], $r['designation'], $r['categorie'], $r['marque'], $r['modele'], $r['numero_serie'],
                    date_fr($r['date_acquisition']), number_format((float) $r['valeur_acquisition'], 2, ',', ''),
                    label(ETATS_BIEN, $r['etat']), label(STATUTS_BIEN, $r['statut']),
                    trim(($r['local_code'] ?? '') . ' ' . ($r['local_nom'] ?? '')), $r['employe'],
                ], $rows));
        }

        $html = View::render('pdf/biens_liste', [
            'title'   => 'Liste des biens',
            'rows'    => $rows,
            'filters' => $this->describeFilters($filters),
            'max'     => $max,
        ], 'layout/pdf');
        Pdf::stream($html, 'biens_' . date('Ymd_His') . '.pdf', 'landscape', !empty($_GET['inline']));
    }

    /** Description lisible des filtres actifs (pour l'en-tête des exports). */
    private function describeFilters(array $f): array
    {
        $out = [];
        $find = static function (array $rows, ?int $id, callable $lbl) {
            foreach ($rows as $r) {
                if ((int) $r['id'] === $id) {
                    return $lbl($r);
                }
            }
            return (string) $id;
        };
        if ($f['q'])            $out['Recherche'] = $f['q'];
        if ($f['code'])         $out['Code'] = $f['code'] . '…';
        if ($f['categorie_id']) $out['Catégorie'] = $find(Ref::categories(), $f['categorie_id'], static fn($r) => $r['libelle']);
        if ($f['local_id'])     $out['Local'] = $find(Ref::locaux(), $f['local_id'], [Ref::class, 'localLabel']);
        if ($f['service_id'])   $out['Service'] = $find(Ref::services(), $f['service_id'], static fn($r) => $r['nom']);
        if ($f['employe_id'])   $out['Employé'] = $find(Ref::employes(), $f['employe_id'], static fn($r) => $r['nom_complet']);
        if ($f['statut'])       $out['Statut'] = label(STATUTS_BIEN, $f['statut']);
        if ($f['etat'])         $out['État'] = label(ETATS_BIEN, $f['etat']);
        if ($f['date_du'])      $out['Acquis depuis le'] = date_fr($f['date_du']);
        if ($f['date_au'])      $out["Acquis jusqu'au"] = date_fr($f['date_au']);
        if ($f['valeur_min'])   $out['Valeur min'] = money($f['valeur_min']);
        if ($f['valeur_max'])   $out['Valeur max'] = money($f['valeur_max']);
        if ($f['fournisseur'])  $out['Fournisseur'] = $f['fournisseur'];
        if ($f['sans_local'])   $out['Localisation'] = 'Sans local';
        return $out;
    }

    // ------------------------------------------------------------------
    // Fiche bien
    // ------------------------------------------------------------------

    private function find(int $id): array
    {
        $bien = Database::one(
            "SELECT b.*, c.code AS categorie_code, c.libelle AS categorie, c.compte_comptable, c.compte_amortissement,
                    l.code AS local_code, l.nom AS local_nom, l.batiment, l.etage, s.nom AS service,
                    e.matricule, CONCAT(e.nom, ' ', e.prenom) AS employe, e.fonction,
                    uc.nom_complet AS cree_par, uu.nom_complet AS modifie_par
               FROM biens b
               JOIN categories c ON c.id = b.categorie_id
               LEFT JOIN locaux l ON l.id = b.local_id
               LEFT JOIN services s ON s.id = l.service_id
               LEFT JOIN employes e ON e.id = b.employe_id
               LEFT JOIN users uc ON uc.id = b.created_by
               LEFT JOIN users uu ON uu.id = b.updated_by
              WHERE b.id = ?",
            [$id]
        );
        if (!$bien) {
            $this->notFound('Bien introuvable.');
        }
        return $bien;
    }

    private function ficheData(array $bien): array
    {
        $plan = Amortissement::plan(
            (float) $bien['valeur_acquisition'],
            (float) $bien['valeur_residuelle'],
            (int) $bien['duree_amortissement'],
            $bien['methode_amortissement'],
            $bien['date_mise_service'] ?: $bien['date_acquisition']
        );
        $historique = Database::all(
            'SELECT h.*, u.nom_complet AS utilisateur FROM bien_historique h
               LEFT JOIN users u ON u.id = h.user_id
              WHERE h.bien_id = ? ORDER BY h.id DESC LIMIT 200',
            [$bien['id']]
        );
        $transferts = Database::all(
            'SELECT t.id, t.numero, t.date_transfert, t.statut, ls.code AS source, ld.code AS destination
               FROM transfert_lignes tl
               JOIN transferts t ON t.id = tl.transfert_id
               JOIN locaux ls ON ls.id = t.local_source_id
               JOIN locaux ld ON ld.id = t.local_destination_id
              WHERE tl.bien_id = ? ORDER BY t.id DESC',
            [$bien['id']]
        );
        return [
            'bien'       => $bien,
            'plan'       => $plan,
            'situation'  => Amortissement::situation($plan, (float) $bien['valeur_acquisition']),
            'historique' => $historique,
            'transferts' => $transferts,
        ];
    }

    public function show(): void
    {
        $this->authorize('biens.view');
        $bien = $this->find((int) $this->qint('id'));
        $this->render('biens/show', ['title' => 'Fiche bien ' . $bien['code_inventaire']] + $this->ficheData($bien));
    }

    public function pdf(): void
    {
        $this->authorize('biens.export');
        $bien = $this->find((int) $this->qint('id'));
        Audit::log('impression', 'biens', (int) $bien['id'], 'Impression de la fiche ' . $bien['code_inventaire']);
        $html = View::render('pdf/bien_fiche', ['title' => 'Fiche bien ' . $bien['code_inventaire']] + $this->ficheData($bien), 'layout/pdf');
        Pdf::stream($html, 'fiche_' . $bien['code_inventaire'] . '.pdf', 'portrait', !empty($_GET['inline']));
    }

    // ------------------------------------------------------------------
    // Création / modification / suppression
    // ------------------------------------------------------------------

    public function create(): void
    {
        $this->authorize('biens.create');
        $bien = [
            'date_acquisition' => date('Y-m-d'), 'etat' => 'neuf', 'statut' => 'en_service',
            'methode_amortissement' => 'lineaire', 'valeur_residuelle' => '0.00',
            'local_id' => $this->qint('local_id'),
        ];
        $this->render('biens/form', ['title' => 'Nouveau bien', 'bien' => $bien, 'errors' => []]);
    }

    public function store(): void
    {
        $this->authorize('biens.create');
        $this->requirePost();
        [$data, $errors] = $this->validate(null);
        if ($errors) {
            $this->render('biens/form', ['title' => 'Nouveau bien', 'bien' => $data, 'errors' => $errors]);
            return;
        }

        try {
            $id = Database::transaction(function () use (&$data) {
                if (empty($data['code_inventaire'])) {
                    $data['code_inventaire'] = $this->generateCode((int) $data['categorie_id'], $data['date_acquisition']);
                }
                $data['created_by'] = $data['updated_by'] = Auth::id();
                $id = Database::insert('biens', $data);
                Audit::bien($id, 'creation', 'Création du bien ' . $data['code_inventaire']);
                return $id;
            });
        } catch (\PDOException $e) {
            if (Database::isDuplicate($e)) {
                $this->render('biens/form', ['title' => 'Nouveau bien', 'bien' => $data,
                    'errors' => ['code_inventaire' => 'Ce code inventaire existe déjà.']]);
                return;
            }
            throw $e;
        }

        Audit::log('creation', 'biens', $id, 'Création du bien ' . $data['code_inventaire'], $data);
        Cache::invalidate('biens');
        flash('success', 'Bien ' . $data['code_inventaire'] . ' créé avec succès.');
        $this->redirect('biens.show', ['id' => $id]);
    }

    public function edit(): void
    {
        $this->authorize('biens.edit');
        $bien = $this->find((int) $this->qint('id'));
        $this->render('biens/form', ['title' => 'Modifier ' . $bien['code_inventaire'], 'bien' => $bien, 'errors' => []]);
    }

    public function update(): void
    {
        $this->authorize('biens.edit');
        $this->requirePost();
        $id = (int) $this->int('id');
        $before = Database::one('SELECT * FROM biens WHERE id = ?', [$id]) ?? $this->notFound();

        [$data, $errors] = $this->validate($before);
        if ($errors) {
            $this->render('biens/form', ['title' => 'Modifier ' . $before['code_inventaire'], 'bien' => $data + $this->find($id), 'errors' => $errors]);
            return;
        }
        // Le local ne change que via un transfert (traçabilité)
        $data['local_id'] = $before['local_id'];
        if (empty($data['code_inventaire'])) {
            $data['code_inventaire'] = $before['code_inventaire'];
        }
        $changes = Audit::diff($before, $data);
        if (!$changes) {
            flash('info', 'Aucune modification.');
            $this->redirect('biens.show', ['id' => $id]);
        }

        try {
            Database::transaction(function () use ($id, $data, $changes) {
                $data['updated_by'] = Auth::id();
                Database::update('biens', $data, 'id = ?', [$id]);
                $this->logChanges($id, $changes);
            });
        } catch (\PDOException $e) {
            if (Database::isDuplicate($e)) {
                $this->render('biens/form', ['title' => 'Modifier', 'bien' => $data + $this->find($id),
                    'errors' => ['code_inventaire' => 'Ce code inventaire existe déjà.']]);
                return;
            }
            throw $e;
        }

        Audit::log('modification', 'biens', $id, 'Modification du bien ' . $data['code_inventaire'], $changes);
        Cache::invalidate('biens');
        flash('success', 'Bien modifié avec succès.');
        $this->redirect('biens.show', ['id' => $id]);
    }

    /** Ventile les changements en événements d'historique typés. */
    private function logChanges(int $id, array $changes): void
    {
        if (isset($changes['statut'])) {
            [$a, $b] = $changes['statut'];
            Audit::bien($id, 'changement_statut', 'Statut : ' . label(STATUTS_BIEN, $a) . ' → ' . label(STATUTS_BIEN, $b), ['statut' => $changes['statut']]);
            unset($changes['statut']);
        }
        if (isset($changes['etat'])) {
            [$a, $b] = $changes['etat'];
            Audit::bien($id, 'changement_etat', 'État : ' . label(ETATS_BIEN, $a) . ' → ' . label(ETATS_BIEN, $b), ['etat' => $changes['etat']]);
            unset($changes['etat']);
        }
        if (array_key_exists('employe_id', $changes)) {
            [$a, $b] = $changes['employe_id'];
            $name = static fn($eid) => $eid ? (string) Database::value("SELECT CONCAT(nom,' ',prenom) FROM employes WHERE id = ?", [$eid]) : 'aucun';
            Audit::bien($id, 'affectation', 'Affectation : ' . $name($a) . ' → ' . $name($b), ['employe_id' => $changes['employe_id']]);
            unset($changes['employe_id']);
        }
        if ($changes) {
            $labels = array_map(static fn($k) => self::FIELDS[$k] ?? $k, array_keys($changes));
            Audit::bien($id, 'modification', 'Modification : ' . implode(', ', $labels), $changes);
        }
    }

    public function delete(): void
    {
        $this->authorize('biens.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $bien = Database::one('SELECT * FROM biens WHERE id = ?', [$id]) ?? $this->notFound();
        if (Database::value("SELECT 1 FROM transfert_lignes tl JOIN transferts t ON t.id = tl.transfert_id
                              WHERE tl.bien_id = ? AND t.statut = 'en_attente' LIMIT 1", [$id])) {
            flash('danger', 'Ce bien fait partie d\'un transfert en attente : suppression impossible.');
            $this->redirect('biens.show', ['id' => $id]);
        }
        Database::delete('biens', 'id = ?', [$id]);
        Audit::log('suppression', 'biens', $id, 'Suppression du bien ' . $bien['code_inventaire'], $bien);
        Cache::invalidate('biens', 'transferts');
        flash('success', 'Bien ' . $bien['code_inventaire'] . ' supprimé.');
        $this->redirect('biens.index');
    }

    /** Code inventaire : CAT-AAAA-00001 (séquence atomique par catégorie et année). */
    private function generateCode(int $categorieId, string $date): string
    {
        $cat = (string) Database::value('SELECT code FROM categories WHERE id = ?', [$categorieId]);
        $year = substr($date, 0, 4);
        $n = Database::nextSequence("bien:$cat:$year");
        return sprintf('%s-%s-%05d', $cat, $year, $n);
    }

    /** @return array{0: array, 1: array} [données normalisées, erreurs] */
    private function validate(?array $before): array
    {
        $d = [
            'code_inventaire'       => $this->str('code_inventaire', 40),
            'designation'           => $this->str('designation', 255),
            'categorie_id'          => $this->int('categorie_id'),
            'marque'                => $this->str('marque', 100),
            'modele'                => $this->str('modele', 100),
            'numero_serie'          => $this->str('numero_serie', 100),
            'fournisseur'           => $this->str('fournisseur', 150),
            'numero_facture'        => $this->str('numero_facture', 60),
            'date_acquisition'      => $this->date('date_acquisition'),
            'date_mise_service'     => $this->date('date_mise_service'),
            'valeur_acquisition'    => $this->decimal('valeur_acquisition'),
            'valeur_residuelle'     => $this->decimal('valeur_residuelle') ?? '0.00',
            'duree_amortissement'   => $this->int('duree_amortissement'),
            'methode_amortissement' => $this->enum('methode_amortissement', METHODES_AMORT) ?? 'lineaire',
            'etat'                  => $this->enum('etat', ETATS_BIEN),
            'statut'                => $this->enum('statut', STATUTS_BIEN),
            'local_id'              => $this->int('local_id'),
            'employe_id'            => $this->int('employe_id'),
            'observations'          => $this->str('observations', 5000),
        ];
        $e = [];
        if ($d['code_inventaire'] !== null && !preg_match('/^[A-Za-z0-9\-_\/.]+$/', $d['code_inventaire'])) {
            $e['code_inventaire'] = 'Caractères autorisés : lettres, chiffres, - _ / .';
        }
        if ($d['designation'] === null) {
            $e['designation'] = 'La désignation est obligatoire.';
        }
        $cats = array_column(Ref::categories(), null, 'id');
        if (!$d['categorie_id'] || !isset($cats[$d['categorie_id']])) {
            $e['categorie_id'] = 'Choisissez une catégorie.';
        }
        if ($d['date_acquisition'] === null) {
            $e['date_acquisition'] = 'Date invalide.';
        } elseif ($d['date_acquisition'] > date('Y-m-d')) {
            $e['date_acquisition'] = 'La date ne peut pas être dans le futur.';
        }
        if ($d['date_mise_service'] && $d['date_acquisition'] && $d['date_mise_service'] < $d['date_acquisition']) {
            $e['date_mise_service'] = "Doit être postérieure à la date d'acquisition.";
        }
        if ($d['valeur_acquisition'] === null || (float) $d['valeur_acquisition'] < 0) {
            $e['valeur_acquisition'] = 'Montant invalide.';
        }
        if ((float) $d['valeur_residuelle'] < 0 || ($d['valeur_acquisition'] !== null && (float) $d['valeur_residuelle'] > (float) $d['valeur_acquisition'])) {
            $e['valeur_residuelle'] = "Doit être comprise entre 0 et la valeur d'acquisition.";
        }
        if ($d['duree_amortissement'] === null && isset($cats[$d['categorie_id']])) {
            $d['duree_amortissement'] = (int) $cats[$d['categorie_id']]['duree_amortissement'];
        }
        if ($d['duree_amortissement'] === null || $d['duree_amortissement'] < 1 || $d['duree_amortissement'] > 100) {
            $e['duree_amortissement'] = 'Durée entre 1 et 100 ans.';
        }
        if ($d['etat'] === null)   $e['etat'] = 'Choisissez un état.';
        if ($d['statut'] === null) $e['statut'] = 'Choisissez un statut.';
        if ($d['local_id'] !== null && !in_array($d['local_id'], array_column(Ref::locaux(), 'id'))) {
            $e['local_id'] = 'Local invalide.';
        }
        if ($d['employe_id'] !== null && !in_array($d['employe_id'], array_column(Ref::employes(), 'id'))) {
            $e['employe_id'] = 'Employé invalide.';
        }
        return [$d, $e];
    }
}
