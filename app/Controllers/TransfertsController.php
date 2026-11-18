<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\Keyset;
use App\Core\Pdf;
use App\Core\Ref;
use App\Core\Scope;
use App\Core\View;

/**
 * Transferts de biens d'un local (bureau) vers un autre.
 * Cycle : demande (en_attente) -> validation | rejet | annulation,
 * TOUJOURS par le chef du service auquel appartient le local source.
 * (Si ce service n'a pas de chef, seul le super-administrateur peut suppléer.)
 */
final class TransfertsController extends Controller
{
    private const SELECT = "SELECT t.*, ls.code AS source_code, ls.nom AS source_nom, ld.code AS dest_code, ld.nom AS dest_nom,
            ls.service_id AS source_service_id, ss.nom AS source_service,
            ch.id AS chef_source_id, CONCAT(ch.nom, ' ', ch.prenom) AS chef_source,
            ld.service_id AS dest_service_id, sd.nom AS dest_service,
            chd.id AS chef_dest_id, CONCAT(chd.nom, ' ', chd.prenom) AS chef_dest,
            CONCAT(rp.nom, ' ', rp.prenom) AS remis_par, rp.fonction AS remis_par_fonction,
            CONCAT(rc.nom, ' ', rc.prenom) AS recu_par, rc.fonction AS recu_par_fonction,
            CONCAT(ed.nom, ' ', ed.prenom) AS employe_dest, ud.nom_complet AS demandeur, ut.nom_complet AS traitant,
            (SELECT COUNT(*) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS nb_biens
       FROM transferts t
       LEFT JOIN locaux ls ON ls.id = t.local_source_id
       LEFT JOIN locaux ld ON ld.id = t.local_destination_id
       LEFT JOIN services ss ON ss.id = ls.service_id
       LEFT JOIN employes ch ON ch.chef_service_id = ls.service_id
       LEFT JOIN services sd ON sd.id = ld.service_id
       LEFT JOIN employes chd ON chd.chef_service_id = ld.service_id
       LEFT JOIN employes rp ON rp.id = t.remis_par_id
       LEFT JOIN employes rc ON rc.id = t.recu_par_id
       LEFT JOIN employes ed ON ed.id = t.employe_destination_id
       LEFT JOIN users ud ON ud.id = t.demande_par
       LEFT JOIN users ut ON ut.id = t.traite_par";

    private const FROM_COUNT = 'SELECT COUNT(*) FROM transferts t
       LEFT JOIN locaux ls ON ls.id = t.local_source_id LEFT JOIN locaux ld ON ld.id = t.local_destination_id';

    private function filters(): array
    {
        $w = [];
        $p = [];
        $f = [
            'q'          => $this->q('q', 30),
            'statut'     => $this->enum('statut', STATUTS_TRANSFERT, $_GET),
            'local_id'   => $this->qint('local_id'),
            'date_du'    => $this->date('date_du', $_GET),
            'date_au'    => $this->date('date_au', $_GET),
            'bien'       => $this->q('bien', 40),
            'a_valider'  => ($_GET['a_valider'] ?? '') === '1',
        ];
        if ($f['q'])        { $w[] = 't.numero LIKE ?'; $p[] = '%' . $f['q'] . '%'; }
        if ($f['statut'])   { $w[] = 't.statut = ?'; $p[] = $f['statut']; }
        if ($f['local_id']) { $w[] = '(t.local_source_id = ? OR t.local_destination_id = ?)'; array_push($p, $f['local_id'], $f['local_id']); }
        if ($f['date_du'])  { $w[] = 't.date_transfert >= ?'; $p[] = $f['date_du']; }
        if ($f['date_au'])  { $w[] = 't.date_transfert <= ?'; $p[] = $f['date_au']; }
        if ($f['bien']) {
            $w[] = 'EXISTS (SELECT 1 FROM transfert_lignes tl JOIN biens b ON b.id = tl.bien_id WHERE tl.transfert_id = t.id AND b.code_inventaire LIKE ?)';
            $p[] = $f['bien'] . '%';
        }
        if ($f['a_valider']) {
            // Transferts en attente que l'utilisateur connecté doit traiter
            $sid = Scope::chefOfService();
            $w[] = "t.statut = 'en_attente'";
            $w[] = $sid ? 'ls.service_id = ?' : '1 = 0';
            if ($sid) $p[] = $sid;
        }
        Scope::applyTransferts($w, $p);
        return [$w, $p, $f];
    }

    public function index(): void
    {
        $this->authorize('transferts.view');
        [$w, $p, $f] = $this->filters();
        $page = $this->keyset(self::SELECT, $w, $p, 't.id', 'id', 't.id', 'DESC');
        foreach ($page['rows'] as &$row) {
            $row['can_edit'] = $this->canEdit($row);
        }
        unset($row);
        $total = $this->cachedCount('transferts', self::FROM_COUNT . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p);
        $this->render('transferts/index', ['title' => $f['a_valider'] ? 'Transferts à valider' : 'Transferts de biens',
            'page' => $page, 'filters' => $f, 'total' => $total]);
    }

    public function export(): void
    {
        $this->authorize('transferts.export');
        [$w, $p] = $this->filters();
        $rows = Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY t.id DESC LIMIT ' . (int) config('app.export_max'), $p);
        Audit::log('export', 'transferts', null, 'Export de la liste des transferts (' . count($rows) . ' lignes)');
        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('transferts_' . date('Ymd') . '.csv', ['Numéro', 'Date', 'Source', 'Service source', 'Destination', 'Employé destinataire', 'Nb biens', 'Motif', 'Statut', 'Demandé par', 'Traité par'],
                array_map(static fn($r) => [$r['numero'], date_fr($r['date_transfert']), $r['source_code'], $r['source_service'], $r['dest_code'], $r['employe_dest'],
                    $r['nb_biens'], $r['motif'], label(STATUTS_TRANSFERT, $r['statut']), $r['demandeur'], $r['traitant']], $rows));
        }
        $html = View::render('pdf/transferts_liste', ['title' => 'Liste des transferts', 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'transferts_' . date('Ymd') . '.pdf', 'landscape', !empty($_GET['inline']));
    }

    /**
     * JSON paginé (keyset) : biens d'un local, visibles par l'utilisateur et non engagés
     * dans un transfert en attente. Paramètres : local_id, q, after.
     */
    public function biens(): void
    {
        $this->authorize('transferts.create');
        $except = (int) ($this->qint('transfert_id') ?? 0); // modification : ses propres biens restent sélectionnables
        $w = ['b.local_id = ?', "NOT EXISTS (SELECT 1 FROM transfert_lignes tl JOIN transferts t ON t.id = tl.transfert_id
                                     WHERE tl.bien_id = b.id AND t.statut = 'en_attente' AND t.id <> ?)"];
        $p = [(int) $this->qint('local_id'), $except];
        if ($q = $this->q('q', 60)) {
            $w[] = '(b.code_inventaire LIKE ? OR b.designation LIKE ? OR b.numero_serie LIKE ?)';
            array_push($p, $q . '%', '%' . $q . '%', $q . '%');
        }
        Scope::applyBiens($w, $p);
        $page = (new Keyset(
            "SELECT b.id, b.code_inventaire, b.designation, b.etat, CONCAT(e.nom, ' ', e.prenom) AS employe
               FROM biens b LEFT JOIN locaux l ON l.id = b.local_id LEFT JOIN employes e ON e.id = b.employe_id",
            $w, $p, 'b.code_inventaire', 'code_inventaire', 'b.id', 'ASC', 100
        ))->paginate($this->q('after', 200));
        $this->json([
            'rows' => array_map(static fn($r) => $r + ['etat_label' => label(ETATS_BIEN, $r['etat'])], $page['rows']),
            'next' => $page['next'],
        ]);
    }

    public function create(): void
    {
        $this->authorize('transferts.create');
        $data = ['date_transfert' => date('Y-m-d'), 'local_source_id' => $this->qint('local_source_id'), 'biens' => []];
        if ($bienId = $this->qint('bien_id')) {
            $b = Database::one('SELECT id, local_id FROM biens WHERE id = ?', [$bienId]);
            if ($b && $b['local_id']) {
                $data['local_source_id'] = $b['local_id'];
                $data['biens'] = [$b['id']];
            }
        }
        $this->render('transferts/form', ['title' => 'Nouveau transfert', 'data' => $data, 'errors' => []]);
    }

    /** Lit et valide le formulaire. @return array{0: array, 1: list<int>, 2: array} [données, biens, erreurs] */
    private function input(): array
    {
        $d = [
            'local_source_id'        => $this->int('local_source_id'),
            'local_destination_id'   => $this->int('local_destination_id'),
            'employe_destination_id' => $this->int('employe_destination_id'),
            'remis_par_id'           => $this->int('remis_par_id'),
            'recu_par_id'            => $this->int('recu_par_id'),
            'date_transfert'         => $this->date('date_transfert'),
            'motif'                  => $this->str('motif', 255),
        ];
        // Par défaut, le nouveau détenteur réceptionne les biens
        $d['recu_par_id'] ??= $d['employe_destination_id'];
        $biens = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['biens'] ?? [])))));
        $locaux = array_column(Ref::locaux(), 'id');
        $e = [];
        $empIds = array_column(Ref::employes(), 'id');
        if ($d['remis_par_id'] && !in_array($d['remis_par_id'], $empIds)) $e['remis_par_id'] = 'Employé invalide.';
        if ($d['recu_par_id'] && !in_array($d['recu_par_id'], $empIds)) $e['recu_par_id'] = 'Employé invalide.';
        if (!in_array($d['local_source_id'], $locaux)) $e['local_source_id'] = 'Choisissez le local source.';
        if (!in_array($d['local_destination_id'], $locaux)) $e['local_destination_id'] = 'Choisissez le local de destination.';
        if ($d['local_source_id'] && $d['local_source_id'] === $d['local_destination_id']) $e['local_destination_id'] = 'La destination doit être différente de la source.';
        if ($d['employe_destination_id'] && !in_array($d['employe_destination_id'], array_column(Ref::employes(), 'id'))) $e['employe_destination_id'] = 'Employé invalide.';
        if (!$d['date_transfert']) $e['date_transfert'] = 'Date invalide.';
        if (!$d['motif']) $e['motif'] = 'Le motif est obligatoire.';
        if (!$biens) $e['biens'] = 'Sélectionnez au moins un bien à transférer.';
        if (count($biens) > 1000) $e['biens'] = 'Maximum 1000 biens par transfert.';
        return [$d, $biens, $e];
    }

    /**
     * Verrouille et contrôle les biens (dans une transaction) : existence, périmètre,
     * présence dans le local source, absence d'un autre transfert en attente.
     */
    private function lockBiens(array $d, array $biens, ?int $exceptTransfertId = null): array
    {
        $in = implode(',', array_fill(0, count($biens), '?'));
        $rows = Database::all("SELECT b.id, b.code_inventaire, b.local_id, b.employe_id, e.service_id AS employe_service_id, l.service_id AS local_service_id
                                 FROM biens b LEFT JOIN employes e ON e.id = b.employe_id LEFT JOIN locaux l ON l.id = b.local_id
                                WHERE b.id IN ($in) FOR UPDATE", $biens);
        if (count($rows) !== count($biens)) {
            throw new BusinessException('Certains biens sélectionnés n\'existent plus.');
        }
        foreach ($rows as $r) {
            if (!Scope::canSeeBien($r)) {
                throw new BusinessException("Le bien {$r['code_inventaire']} ne fait pas partie de votre périmètre.");
            }
            if ((int) $r['local_id'] !== $d['local_source_id']) {
                throw new BusinessException("Le bien {$r['code_inventaire']} n'est pas dans le local source.");
            }
        }
        $pending = Database::value(
            "SELECT b.code_inventaire FROM transfert_lignes tl JOIN transferts t ON t.id = tl.transfert_id
               JOIN biens b ON b.id = tl.bien_id WHERE t.statut = 'en_attente' AND tl.bien_id IN ($in)"
            . ($exceptTransfertId ? ' AND t.id <> ?' : '') . ' LIMIT 1',
            $exceptTransfertId ? [...$biens, $exceptTransfertId] : $biens);
        if ($pending) {
            throw new BusinessException("Le bien $pending fait déjà l'objet d'un autre transfert en attente.");
        }
        return $rows;
    }

    public function store(): void
    {
        $this->authorize('transferts.create');
        $this->requirePost();
        [$d, $biens, $e] = $this->input();

        if (!$e) {
            try {
                $id = Database::transaction(function () use ($d, $biens) {
                    $rows = $this->lockBiens($d, $biens);
                    $d['numero'] = sprintf('TR-%s-%05d', date('Y'), Database::nextSequence('transfert:' . date('Y')));
                    $d['demande_par'] = Auth::id();
                    $d['statut'] = 'en_attente';
                    $id = Database::insert('transferts', $d);
                    foreach ($rows as $r) {
                        Database::insert('transfert_lignes', ['transfert_id' => $id, 'bien_id' => $r['id'], 'employe_source_id' => $r['employe_id']]);
                    }
                    return $id;
                });
                $t = Database::one(self::SELECT . ' WHERE t.id = ?', [$id]);
                Audit::log('creation', 'transferts', $id, "Demande de transfert {$t['numero']} (" . count($biens) . ' biens)', $d + ['biens' => $biens]);
                Cache::invalidate('transferts');

                if (!empty($_POST['valider']) && $this->canProcess($t)) {
                    $this->apply($id, 'Validation immédiate par le chef de service');
                    flash('success', "Transfert {$t['numero']} créé et validé : les biens ont été déplacés.");
                } else {
                    $qui = $t['chef_source'] ? 'du chef de service ' . $t['chef_source'] : "de l'administrateur (le service source n'a pas de chef)";
                    flash('success', "Demande de transfert {$t['numero']} enregistrée, en attente de validation $qui.");
                }
                $this->redirect('transferts.show', ['id' => $id]);
            } catch (BusinessException $ex) {
                $e['biens'] = $ex->getMessage();
            }
        }
        $this->render('transferts/form', ['title' => 'Nouveau transfert', 'data' => $d + ['biens' => $biens], 'errors' => $e]);
    }

    /**
     * Modification possible tant que le transfert est en attente, par le demandeur
     * ou par la personne habilitée à le traiter (chef du service source / admin).
     */
    private function canEdit(array $t): bool
    {
        return $t['statut'] === 'en_attente' && can('transferts.create')
            && ((int) $t['demande_par'] === Auth::id() || $this->canProcess($t) || (int) (user()['is_super'] ?? 0) === 1);
    }

    /** Charge le transfert à modifier ou refuse avec un message explicite. */
    private function forEdit(int $id): array
    {
        $t = $this->find($id);
        if ($t['statut'] !== 'en_attente') {
            flash('warning', 'Seul un transfert en attente peut être modifié (celui-ci est « ' . label(STATUTS_TRANSFERT, $t['statut']) . ' »).');
            $this->redirect('transferts.show', ['id' => $id]);
        }
        if (!$this->canEdit($t)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'Accès refusé',
                'message' => 'Seuls le demandeur et le chef du service source peuvent modifier ce transfert.']);
            exit;
        }
        return $t;
    }

    public function edit(): void
    {
        $t = $this->forEdit((int) $this->qint('id'));
        $data = $t + ['biens' => array_map('intval', array_column(
            Database::all('SELECT bien_id FROM transfert_lignes WHERE transfert_id = ?', [$t['id']]), 'bien_id'))];
        $this->render('transferts/form', ['title' => 'Modifier le transfert ' . $t['numero'], 'data' => $data, 'errors' => []]);
    }

    public function update(): void
    {
        $this->requirePost();
        $t = $this->forEdit((int) $this->int('id'));
        $id = (int) $t['id'];
        [$d, $biens, $e] = $this->input();

        if (!$e) {
            try {
                $changes = Database::transaction(function () use ($id, $d, $biens) {
                    $cur = Database::one('SELECT * FROM transferts WHERE id = ? FOR UPDATE', [$id]);
                    if (!$cur || $cur['statut'] !== 'en_attente') {
                        throw new BusinessException('Ce transfert vient d\'être traité : il ne peut plus être modifié.');
                    }
                    $rows = $this->lockBiens($d, $biens, $id);
                    $avant = array_map('intval', array_column(Database::all('SELECT bien_id FROM transfert_lignes WHERE transfert_id = ?', [$id]), 'bien_id'));

                    Database::update('transferts', $d, 'id = ?', [$id]);
                    // Lignes : on retire les biens décochés et on ajoute les nouveaux
                    $retires = array_values(array_diff($avant, $biens));
                    $ajoutes = array_values(array_diff($biens, $avant));
                    if ($retires) {
                        Database::run('DELETE FROM transfert_lignes WHERE transfert_id = ? AND bien_id IN (' . implode(',', array_fill(0, count($retires), '?')) . ')', [$id, ...$retires]);
                    }
                    foreach ($rows as $r) {
                        if (in_array((int) $r['id'], $ajoutes, true)) {
                            Database::insert('transfert_lignes', ['transfert_id' => $id, 'bien_id' => $r['id'], 'employe_source_id' => $r['employe_id']]);
                        }
                    }
                    $changes = Audit::diff($cur, $d);
                    if ($retires || $ajoutes) {
                        $code = static fn(array $ids) => $ids ? array_column(Database::all(
                            'SELECT code_inventaire FROM biens WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids), 'code_inventaire') : [];
                        $changes['biens'] = ['retirés' => $code($retires), 'ajoutés' => $code($ajoutes)];
                    }
                    return $changes;
                });
                if ($changes) {
                    Audit::log('modification', 'transferts', $id, "Modification du transfert {$t['numero']}", $changes);
                    Cache::invalidate('transferts');
                    flash('success', "Transfert {$t['numero']} modifié.");
                } else {
                    flash('info', 'Aucune modification.');
                }
                $this->redirect('transferts.show', ['id' => $id]);
            } catch (BusinessException $ex) {
                $e['biens'] = $ex->getMessage();
            }
        }
        $this->render('transferts/form', ['title' => 'Modifier le transfert ' . $t['numero'],
            'data' => $d + ['biens' => $biens, 'id' => $id, 'numero' => $t['numero']], 'errors' => $e]);
    }

    private function find(int $id): array
    {
        $t = Database::one(self::SELECT . ' WHERE t.id = ?', [$id]);
        if (!$t || !Scope::canSeeTransfert($t)) {
            $this->notFound('Transfert introuvable.');
        }
        return $t;
    }

    /**
     * Seul le chef du service du local source traite (valide / rejette / annule) un transfert.
     * Si ce service n'a pas de chef, le super-administrateur peut suppléer.
     */
    private function canProcess(array $t): bool
    {
        return $t['statut'] === 'en_attente' && $this->isSourceChef($t);
    }

    /** Dévalidation : même autorité que la validation (chef du service source). */
    private function canUnvalidate(array $t): bool
    {
        return $t['statut'] === 'valide' && $this->isSourceChef($t);
    }

    /** L'utilisateur est-il le chef du service du local source (ou l'admin si ce service n'a pas de chef) ? */
    private function isSourceChef(array $t): bool
    {
        if (!can('transferts.validate')) {
            return false;
        }
        $u = Auth::user();
        if ($t['chef_source_id']) {
            return (int) $u['employe_id'] === (int) $t['chef_source_id'] && (int) $u['employe_actif'] === 1;
        }
        return (int) $u['is_super'] === 1;
    }

    private function lignesSelect(): string
    {
        return "SELECT b.id, b.code_inventaire, b.designation, b.marque, b.modele, b.numero_serie, b.etat, b.valeur_acquisition,
                       CONCAT(es.nom, ' ', es.prenom) AS employe_source
                  FROM transfert_lignes tl JOIN biens b ON b.id = tl.bien_id
                  LEFT JOIN employes es ON es.id = tl.employe_source_id";
    }

    public function show(): void
    {
        $this->authorize('transferts.view');
        $t = $this->find((int) $this->qint('id'));
        $lignes = $this->keyset($this->lignesSelect(), ['tl.transfert_id = ?'], [$t['id']], 'b.code_inventaire', 'code_inventaire', 'b.id', 'ASC');
        $valeur = (float) Database::value('SELECT COALESCE(SUM(b.valeur_acquisition),0) FROM transfert_lignes tl JOIN biens b ON b.id = tl.bien_id WHERE tl.transfert_id = ?', [$t['id']]);
        $this->render('transferts/show', ['title' => 'Transfert ' . $t['numero'], 't' => $t, 'lignes' => $lignes, 'valeur' => $valeur,
            'canProcess' => $this->canProcess($t), 'canEdit' => $this->canEdit($t), 'canUnvalidate' => $this->canUnvalidate($t)]);
    }

    public function pdf(): void
    {
        $this->authorize('transferts.export');
        $t = $this->find((int) $this->qint('id'));
        $lignes = Database::all($this->lignesSelect() . ' WHERE tl.transfert_id = ? ORDER BY b.code_inventaire', [$t['id']]);
        Audit::log('impression', 'transferts', (int) $t['id'], 'Impression du bon de transfert ' . $t['numero']);
        $html = View::render('pdf/transfert_bon', ['title' => 'Bon de transfert ' . $t['numero'], 't' => $t, 'lignes' => $lignes], 'layout/pdf');
        Pdf::stream($html, 'bon_' . $t['numero'] . '.pdf', 'portrait', !empty($_GET['inline']));
    }

    /** Charge le transfert et vérifie que l'utilisateur est le chef habilité à le traiter. */
    private function forProcessing(): array
    {
        $t = $this->find((int) $this->int('id'));
        if ($t['statut'] !== 'en_attente') {
            flash('warning', 'Ce transfert a déjà été traité.');
            $this->redirect('transferts.show', ['id' => $t['id']]);
        }
        if (!$this->canProcess($t)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'Accès refusé', 'permission' => null,
                'message' => $t['chef_source']
                    ? "Seul le chef du service {$t['source_service']} ({$t['chef_source']}) peut valider, rejeter ou annuler ce transfert."
                    : "Le service du local source n'a pas de chef : seul l'administrateur peut traiter ce transfert."]);
            exit;
        }
        return $t;
    }

    /** Applique le transfert : déplace les biens, journalise l'historique. */
    private function apply(int $id, ?string $commentaire): void
    {
        Database::transaction(function () use ($id, $commentaire) {
            $t = Database::one('SELECT * FROM transferts WHERE id = ? FOR UPDATE', [$id]);
            if (!$t || $t['statut'] !== 'en_attente') {
                throw new BusinessException('Ce transfert a déjà été traité.');
            }
            $src  = Database::one('SELECT code, nom FROM locaux WHERE id = ?', [$t['local_source_id']]);
            $dest = Database::one('SELECT code, nom FROM locaux WHERE id = ?', [$t['local_destination_id']]);
            $rows = Database::all(
                'SELECT b.id, b.code_inventaire, b.local_id, b.employe_id FROM transfert_lignes tl
                   JOIN biens b ON b.id = tl.bien_id WHERE tl.transfert_id = ? FOR UPDATE',
                [$id]
            );
            foreach ($rows as $b) {
                if ((int) $b['local_id'] !== (int) $t['local_source_id']) {
                    throw new BusinessException("Le bien {$b['code_inventaire']} n'est plus dans le local source : transfert impossible.");
                }
                Database::update('biens', [
                    'local_id'   => $t['local_destination_id'],
                    'employe_id' => $t['employe_destination_id'],
                    'updated_by' => Auth::id(),
                ], 'id = ?', [$b['id']]);
                Audit::bien((int) $b['id'], 'transfert', "Transfert {$t['numero']} : {$src['code']} → {$dest['code']}", [
                    'transfert_id' => $id,
                    'local_id'     => [$b['local_id'], $t['local_destination_id']],
                    'employe_id'   => [$b['employe_id'], $t['employe_destination_id']],
                ]);
            }
            Database::update('transferts', [
                'statut' => 'valide', 'traite_par' => Auth::id(), 'date_traitement' => date('Y-m-d H:i:s'), 'commentaire' => $commentaire,
            ], 'id = ?', [$id]);
            Audit::log('validation', 'transferts', $id, "Validation du transfert {$t['numero']} ({$src['code']} → {$dest['code']}, " . count($rows) . ' biens)');
        });
        Cache::invalidate('transferts', 'biens', 'locaux', 'employes');
    }

    public function valider(): void
    {
        $this->requirePost();
        $t = $this->forProcessing();
        try {
            $this->apply((int) $t['id'], $this->str('commentaire', 255));
            flash('success', 'Transfert validé : les biens ont été déplacés.');
        } catch (BusinessException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('transferts.show', ['id' => $t['id']]);
    }

    /**
     * Dévalide un transfert validé : les biens reviennent dans le local source avec
     * leur ancien détenteur, et le transfert repasse « en attente » (modifiable,
     * revalidable ou rejetable). Refusé si un bien a bougé depuis la validation.
     */
    public function devalider(): void
    {
        $this->requirePost();
        $t = $this->find((int) $this->int('id'));
        $id = (int) $t['id'];
        if ($t['statut'] !== 'valide') {
            flash('warning', 'Seul un transfert validé peut être dévalidé.');
            $this->redirect('transferts.show', ['id' => $id]);
        }
        if (!$this->canUnvalidate($t)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'Accès refusé', 'message' => $t['chef_source']
                ? "Seul le chef du service {$t['source_service']} ({$t['chef_source']}) peut dévalider ce transfert."
                : "Le service du local source n'a pas de chef : seul l'administrateur peut dévalider ce transfert."]);
            exit;
        }
        $motif = $this->str('commentaire', 200);
        if (!$motif) {
            flash('danger', 'Indiquez le motif de la dévalidation.');
            $this->redirect('transferts.show', ['id' => $id]);
        }
        try {
            $nb = Database::transaction(function () use ($id, $motif) {
                $cur = Database::one('SELECT * FROM transferts WHERE id = ? FOR UPDATE', [$id]);
                if (!$cur || $cur['statut'] !== 'valide') {
                    throw new BusinessException('Ce transfert n\'est plus validé.');
                }
                $rows = Database::all(
                    'SELECT b.id, b.code_inventaire, b.local_id, b.employe_id, tl.employe_source_id, l.code AS local_code
                       FROM transfert_lignes tl JOIN biens b ON b.id = tl.bien_id LEFT JOIN locaux l ON l.id = b.local_id
                      WHERE tl.transfert_id = ? FOR UPDATE', [$id]);
                foreach ($rows as $b) {
                    // Le bien doit être resté tel que le transfert l'a laissé
                    if ((int) $b['local_id'] !== (int) $cur['local_destination_id']
                        || (int) ($b['employe_id'] ?? 0) !== (int) ($cur['employe_destination_id'] ?? 0)) {
                        throw new BusinessException("Le bien {$b['code_inventaire']} a été déplacé ou réaffecté depuis la validation"
                            . ($b['local_code'] ? " (actuellement en {$b['local_code']})" : '') . ' : dévalidation impossible.');
                    }
                    $autre = Database::value(
                        "SELECT t.numero FROM transfert_lignes tl JOIN transferts t ON t.id = tl.transfert_id
                          WHERE tl.bien_id = ? AND t.id <> ? AND t.statut = 'en_attente' LIMIT 1", [$b['id'], $id]);
                    if ($autre) {
                        throw new BusinessException("Le bien {$b['code_inventaire']} fait partie du transfert en attente $autre : dévalidation impossible.");
                    }
                }
                $src  = Database::value('SELECT code FROM locaux WHERE id = ?', [$cur['local_source_id']]);
                $dest = Database::value('SELECT code FROM locaux WHERE id = ?', [$cur['local_destination_id']]);
                foreach ($rows as $b) {
                    Database::update('biens', [
                        'local_id'   => $cur['local_source_id'],
                        'employe_id' => $b['employe_source_id'],
                        'updated_by' => Auth::id(),
                    ], 'id = ?', [$b['id']]);
                    Audit::bien((int) $b['id'], 'transfert', "Dévalidation du transfert {$cur['numero']} : retour $dest → $src", [
                        'transfert_id' => $id, 'motif' => $motif,
                        'local_id'     => [$cur['local_destination_id'], $cur['local_source_id']],
                        'employe_id'   => [$b['employe_id'], $b['employe_source_id']],
                    ]);
                }
                Database::update('transferts', [
                    'statut' => 'en_attente', 'traite_par' => null, 'date_traitement' => null,
                    'commentaire' => mb_substr('Dévalidé le ' . date('d/m/Y H:i') . ' par ' . (user()['nom_complet'] ?? '') . ' : ' . $motif, 0, 255),
                ], 'id = ?', [$id]);
                return count($rows);
            });
            Audit::log('devalidation', 'transferts', $id, "Dévalidation du transfert {$t['numero']} ($nb biens remis en {$t['source_code']})",
                ['motif' => $motif, 'traite_par_precedent' => $t['traitant'], 'date_validation' => $t['date_traitement']]);
            Cache::invalidate('transferts', 'biens', 'locaux', 'employes');
            flash('success', "Transfert dévalidé : $nb bien(s) remis dans {$t['source_code']}. La demande est de nouveau en attente.");
        } catch (BusinessException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('transferts.show', ['id' => $id]);
    }

    public function rejeter(): void
    {
        $this->changeStatut('rejete', 'rejet', 'Transfert rejeté.');
    }

    public function annuler(): void
    {
        $this->changeStatut('annule', 'annulation', 'Transfert annulé.');
    }

    private function changeStatut(string $statut, string $action, string $msg): never
    {
        $this->requirePost();
        $t = $this->forProcessing();
        $id = (int) $t['id'];
        $commentaire = $this->str('commentaire', 255);
        if ($statut === 'rejete' && !$commentaire) {
            flash('danger', 'Indiquez le motif du rejet.');
            $this->redirect('transferts.show', ['id' => $id]);
        }
        $n = Database::update('transferts', [
            'statut' => $statut, 'traite_par' => Auth::id(), 'date_traitement' => date('Y-m-d H:i:s'), 'commentaire' => $commentaire,
        ], "id = ? AND statut = 'en_attente'", [$id]);
        if ($n) {
            Audit::log($action, 'transferts', $id, ucfirst($action) . " du transfert {$t['numero']}", ['commentaire' => $commentaire]);
            Cache::invalidate('transferts');
            flash('success', $msg);
        } else {
            flash('warning', 'Ce transfert a déjà été traité.');
        }
        $this->redirect('transferts.show', ['id' => $id]);
    }

    public function delete(): void
    {
        $this->authorize('transferts.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $t = $this->find($id);
        if ($t['statut'] === 'valide') {
            flash('danger', 'Un transfert validé fait partie de l\'historique et ne peut pas être supprimé.');
            $this->redirect('transferts.show', ['id' => $id]);
        }
        $this->safeDelete('transferts', $id, 'transferts', $t['numero'], 'transferts.index', ['transferts']);
    }
}
