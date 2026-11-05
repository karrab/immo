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
use App\Core\View;

/**
 * Transferts de biens d'un local (bureau) vers un autre.
 * Cycle : demande (en_attente) -> validation (biens déplacés + historique) | rejet | annulation.
 */
final class TransfertsController extends Controller
{
    private const SELECT = "SELECT t.*, ls.code AS source_code, ls.nom AS source_nom, ld.code AS dest_code, ld.nom AS dest_nom,
            CONCAT(ed.nom, ' ', ed.prenom) AS employe_dest, ud.nom_complet AS demandeur, ut.nom_complet AS traitant,
            (SELECT COUNT(*) FROM transfert_lignes tl WHERE tl.transfert_id = t.id) AS nb_biens
       FROM transferts t
       LEFT JOIN locaux ls ON ls.id = t.local_source_id
       LEFT JOIN locaux ld ON ld.id = t.local_destination_id
       LEFT JOIN employes ed ON ed.id = t.employe_destination_id
       LEFT JOIN users ud ON ud.id = t.demande_par
       LEFT JOIN users ut ON ut.id = t.traite_par";

    private function filters(): array
    {
        $w = [];
        $p = [];
        $f = [
            'q'        => $this->q('q', 30),
            'statut'   => $this->enum('statut', STATUTS_TRANSFERT, $_GET),
            'local_id' => $this->qint('local_id'),
            'date_du'  => $this->date('date_du', $_GET),
            'date_au'  => $this->date('date_au', $_GET),
            'bien'     => $this->q('bien', 40),
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
        return [$w, $p, $f];
    }

    public function index(): void
    {
        $this->authorize('transferts.view');
        [$w, $p, $f] = $this->filters();
        $page = (new Keyset(self::SELECT, $w, $p, 't.id', 'id', 't.id', 'DESC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $total = $this->cachedCount('transferts', 'SELECT COUNT(*) FROM transferts t' . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p);
        $this->render('transferts/index', ['title' => 'Transferts de biens', 'page' => $page, 'filters' => $f, 'total' => $total]);
    }

    public function export(): void
    {
        $this->authorize('transferts.export');
        [$w, $p] = $this->filters();
        $rows = Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY t.id DESC LIMIT ' . (int) config('app.export_max'), $p);
        Audit::log('export', 'transferts', null, 'Export de la liste des transferts (' . count($rows) . ' lignes)');
        if (($_GET['format'] ?? 'pdf') === 'csv') {
            Csv::stream('transferts_' . date('Ymd') . '.csv', ['Numéro', 'Date', 'Source', 'Destination', 'Employé destinataire', 'Nb biens', 'Motif', 'Statut', 'Demandé par', 'Traité par'],
                array_map(static fn($r) => [$r['numero'], date_fr($r['date_transfert']), $r['source_code'], $r['dest_code'], $r['employe_dest'],
                    $r['nb_biens'], $r['motif'], label(STATUTS_TRANSFERT, $r['statut']), $r['demandeur'], $r['traitant']], $rows));
        }
        $html = View::render('pdf/transferts_liste', ['title' => 'Liste des transferts', 'rows' => $rows], 'layout/pdf');
        Pdf::stream($html, 'transferts_' . date('Ymd') . '.pdf', 'landscape', !empty($_GET['inline']));
    }

    /** JSON : biens présents dans un local et non engagés dans un transfert en attente. */
    public function biens(): void
    {
        $this->authorize('transferts.create');
        $localId = (int) $this->qint('local_id');
        $rows = Database::all(
            "SELECT b.id, b.code_inventaire, b.designation, b.etat, CONCAT(e.nom, ' ', e.prenom) AS employe
               FROM biens b LEFT JOIN employes e ON e.id = b.employe_id
              WHERE b.local_id = ?
                AND NOT EXISTS (SELECT 1 FROM transfert_lignes tl JOIN transferts t ON t.id = tl.transfert_id
                                 WHERE tl.bien_id = b.id AND t.statut = 'en_attente')
              ORDER BY b.code_inventaire LIMIT 1000",
            [$localId]
        );
        $this->json(array_map(static fn($r) => $r + ['etat_label' => label(ETATS_BIEN, $r['etat'])], $rows));
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

    public function store(): void
    {
        $this->authorize('transferts.create');
        $this->requirePost();
        $d = [
            'local_source_id'        => $this->int('local_source_id'),
            'local_destination_id'   => $this->int('local_destination_id'),
            'employe_destination_id' => $this->int('employe_destination_id'),
            'date_transfert'         => $this->date('date_transfert'),
            'motif'                  => $this->str('motif', 255),
        ];
        $biens = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['biens'] ?? [])))));
        $locaux = array_column(Ref::locaux(), 'id');
        $e = [];
        if (!in_array($d['local_source_id'], $locaux)) $e['local_source_id'] = 'Choisissez le local source.';
        if (!in_array($d['local_destination_id'], $locaux)) $e['local_destination_id'] = 'Choisissez le local de destination.';
        if ($d['local_source_id'] && $d['local_source_id'] === $d['local_destination_id']) $e['local_destination_id'] = 'La destination doit être différente de la source.';
        if ($d['employe_destination_id'] && !in_array($d['employe_destination_id'], array_column(Ref::employes(), 'id'))) $e['employe_destination_id'] = 'Employé invalide.';
        if (!$d['date_transfert']) $e['date_transfert'] = 'Date invalide.';
        if (!$d['motif']) $e['motif'] = 'Le motif est obligatoire.';
        if (!$biens) $e['biens'] = 'Sélectionnez au moins un bien à transférer.';

        if (!$e) {
            try {
                $id = Database::transaction(function () use ($d, $biens) {
                    $in = implode(',', array_fill(0, count($biens), '?'));
                    $rows = Database::all("SELECT id, code_inventaire, local_id, employe_id FROM biens WHERE id IN ($in) FOR UPDATE", $biens);
                    if (count($rows) !== count($biens)) {
                        throw new BusinessException('Certains biens sélectionnés n\'existent plus.');
                    }
                    foreach ($rows as $r) {
                        if ((int) $r['local_id'] !== $d['local_source_id']) {
                            throw new BusinessException("Le bien {$r['code_inventaire']} n'est pas dans le local source.");
                        }
                    }
                    $pending = Database::value(
                        "SELECT b.code_inventaire FROM transfert_lignes tl JOIN transferts t ON t.id = tl.transfert_id
                           JOIN biens b ON b.id = tl.bien_id WHERE t.statut = 'en_attente' AND tl.bien_id IN ($in) LIMIT 1", $biens);
                    if ($pending) {
                        throw new BusinessException("Le bien $pending fait déjà l'objet d'un transfert en attente.");
                    }
                    $d['numero'] = sprintf('TR-%s-%05d', date('Y'), Database::nextSequence('transfert:' . date('Y')));
                    $d['demande_par'] = Auth::id();
                    $d['statut'] = 'en_attente';
                    $id = Database::insert('transferts', $d);
                    foreach ($rows as $r) {
                        Database::insert('transfert_lignes', ['transfert_id' => $id, 'bien_id' => $r['id'], 'employe_source_id' => $r['employe_id']]);
                    }
                    return $id;
                });
                $numero = Database::value('SELECT numero FROM transferts WHERE id = ?', [$id]);
                Audit::log('creation', 'transferts', $id, "Demande de transfert $numero (" . count($biens) . ' biens)', $d + ['biens' => $biens]);
                Cache::invalidate('transferts');

                if (!empty($_POST['valider']) && can('transferts.validate')) {
                    $this->apply($id, 'Validation immédiate');
                    flash('success', "Transfert $numero créé et validé : les biens ont été déplacés.");
                } else {
                    flash('success', "Demande de transfert $numero enregistrée, en attente de validation.");
                }
                $this->redirect('transferts.show', ['id' => $id]);
            } catch (BusinessException $ex) {
                $e['biens'] = $ex->getMessage();
            }
        }
        $this->render('transferts/form', ['title' => 'Nouveau transfert', 'data' => $d + ['biens' => $biens], 'errors' => $e]);
    }

    private function find(int $id): array
    {
        return Database::one(self::SELECT . ' WHERE t.id = ?', [$id]) ?? $this->notFound('Transfert introuvable.');
    }

    private function lignes(int $id): array
    {
        return Database::all(
            "SELECT b.id, b.code_inventaire, b.designation, b.marque, b.modele, b.numero_serie, b.etat, b.valeur_acquisition,
                    CONCAT(es.nom, ' ', es.prenom) AS employe_source
               FROM transfert_lignes tl JOIN biens b ON b.id = tl.bien_id
               LEFT JOIN employes es ON es.id = tl.employe_source_id
              WHERE tl.transfert_id = ? ORDER BY b.code_inventaire",
            [$id]
        );
    }

    public function show(): void
    {
        $this->authorize('transferts.view');
        $t = $this->find((int) $this->qint('id'));
        $this->render('transferts/show', ['title' => 'Transfert ' . $t['numero'], 't' => $t, 'lignes' => $this->lignes((int) $t['id'])]);
    }

    public function pdf(): void
    {
        $this->authorize('transferts.export');
        $t = $this->find((int) $this->qint('id'));
        Audit::log('impression', 'transferts', (int) $t['id'], 'Impression du bon de transfert ' . $t['numero']);
        $html = View::render('pdf/transfert_bon', ['title' => 'Bon de transfert ' . $t['numero'], 't' => $t, 'lignes' => $this->lignes((int) $t['id'])], 'layout/pdf');
        Pdf::stream($html, 'bon_' . $t['numero'] . '.pdf', 'portrait', !empty($_GET['inline']));
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
        $this->authorize('transferts.validate');
        $this->requirePost();
        $id = (int) $this->int('id');
        try {
            $this->apply($id, $this->str('commentaire', 255));
            flash('success', 'Transfert validé : les biens ont été déplacés.');
        } catch (BusinessException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('transferts.show', ['id' => $id]);
    }

    public function rejeter(): void
    {
        $this->authorize('transferts.validate');
        $this->changeStatut('rejete', 'rejet', 'Transfert rejeté.');
    }

    public function annuler(): void
    {
        $this->authorize('transferts.create');
        $t = $this->find((int) $this->int('id'));
        if ((int) $t['demande_par'] !== Auth::id() && !can('transferts.validate')) {
            $this->authorize('transferts.validate');
        }
        $this->changeStatut('annule', 'annulation', 'Transfert annulé.');
    }

    private function changeStatut(string $statut, string $action, string $msg): never
    {
        $this->requirePost();
        $id = (int) $this->int('id');
        $commentaire = $this->str('commentaire', 255);
        if ($statut === 'rejete' && !$commentaire) {
            flash('danger', 'Indiquez le motif du rejet.');
            $this->redirect('transferts.show', ['id' => $id]);
        }
        $n = Database::update('transferts', [
            'statut' => $statut, 'traite_par' => Auth::id(), 'date_traitement' => date('Y-m-d H:i:s'), 'commentaire' => $commentaire,
        ], "id = ? AND statut = 'en_attente'", [$id]);
        if ($n) {
            $numero = Database::value('SELECT numero FROM transferts WHERE id = ?', [$id]);
            Audit::log($action, 'transferts', $id, ucfirst($action) . " du transfert $numero", ['commentaire' => $commentaire]);
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
