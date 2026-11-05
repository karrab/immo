<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Keyset;

final class ServicesController extends Controller
{
    public function index(): void
    {
        $this->authorize('services.view');
        $where = [];
        $params = [];
        if ($q = $this->q('q')) {
            $where[] = '(s.code LIKE ? OR s.nom LIKE ?)';
            array_push($params, $q . '%', '%' . $q . '%');
        }
        $select = 'SELECT s.*,
                   (SELECT COUNT(*) FROM locaux l WHERE l.service_id = s.id) AS nb_locaux,
                   (SELECT COUNT(*) FROM employes e WHERE e.service_id = s.id) AS nb_employes
              FROM services s';
        $page = (new Keyset($select, $where, $params, 's.nom', 'nom', 's.id', 'ASC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $this->render('services/index', ['title' => 'Services / Directions', 'page' => $page]);
    }

    public function create(): void
    {
        $this->authorize('services.create');
        $this->render('services/form', ['title' => 'Nouveau service', 'item' => [], 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('services.edit');
        $item = Database::one('SELECT * FROM services WHERE id = ?', [(int) $this->qint('id')]) ?? $this->notFound();
        $this->render('services/form', ['title' => 'Modifier le service', 'item' => $item, 'errors' => []]);
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
        $before = $id ? (Database::one('SELECT * FROM services WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $d = [
            'code'        => strtoupper((string) $this->str('code', 20)),
            'nom'         => $this->str('nom', 150),
            'description' => $this->str('description', 255),
        ];
        $e = [];
        if (!preg_match('/^[A-Z0-9_\-]{1,20}$/', $d['code'])) $e['code'] = 'Code obligatoire (lettres majuscules, chiffres).';
        if (!$d['nom']) $e['nom'] = 'Nom obligatoire.';
        if (!$e) {
            try {
                if ($id) {
                    Database::update('services', $d, 'id = ?', [$id]);
                    Audit::log('modification', 'services', $id, 'Modification service ' . $d['code'], Audit::diff($before, $d));
                } else {
                    $id = Database::insert('services', $d);
                    Audit::log('creation', 'services', $id, 'Création service ' . $d['code'], $d);
                }
                Cache::invalidate('services');
                flash('success', 'Service enregistré.');
                $this->redirect('services.index');
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e['code'] = 'Ce code existe déjà.';
            }
        }
        $this->render('services/form', ['title' => $id ? 'Modifier le service' : 'Nouveau service', 'item' => $d + ['id' => $id], 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('services.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $item = Database::one('SELECT code, nom FROM services WHERE id = ?', [$id]) ?? $this->notFound();
        $this->safeDelete('services', $id, 'services', $item['code'] . ' ' . $item['nom'], 'services.index', ['services', 'locaux', 'employes']);
    }
}
