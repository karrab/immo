<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Keyset;

final class CategoriesController extends Controller
{
    public function index(): void
    {
        $this->authorize('categories.view');
        $where = [];
        $params = [];
        if ($q = $this->q('q')) {
            $where[] = '(c.code LIKE ? OR c.libelle LIKE ?)';
            array_push($params, $q . '%', '%' . $q . '%');
        }
        $select = 'SELECT c.*, (SELECT COUNT(*) FROM biens b WHERE b.categorie_id = c.id) AS nb_biens FROM categories c';
        $page = (new Keyset($select, $where, $params, 'c.libelle', 'libelle', 'c.id', 'ASC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $this->render('categories/index', ['title' => 'Catégories comptables', 'page' => $page]);
    }

    public function create(): void
    {
        $this->authorize('categories.create');
        $this->render('categories/form', ['title' => 'Nouvelle catégorie', 'item' => ['duree_amortissement' => 5], 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('categories.edit');
        $item = Database::one('SELECT * FROM categories WHERE id = ?', [(int) $this->qint('id')]) ?? $this->notFound();
        $this->render('categories/form', ['title' => 'Modifier la catégorie', 'item' => $item, 'errors' => []]);
    }

    public function store(): void
    {
        $this->authorize('categories.create');
        $this->save(null);
    }

    public function update(): void
    {
        $this->authorize('categories.edit');
        $this->save((int) $this->int('id'));
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        $before = $id ? (Database::one('SELECT * FROM categories WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $d = [
            'code'                 => strtoupper((string) $this->str('code', 20)),
            'libelle'              => $this->str('libelle', 150),
            'compte_comptable'     => $this->str('compte_comptable', 20),
            'compte_amortissement' => $this->str('compte_amortissement', 20),
            'duree_amortissement'  => $this->int('duree_amortissement'),
        ];
        $e = [];
        if (!preg_match('/^[A-Z0-9_]{1,20}$/', $d['code'])) $e['code'] = 'Code obligatoire (lettres majuscules, chiffres).';
        if (!$d['libelle']) $e['libelle'] = 'Libellé obligatoire.';
        if (!$d['duree_amortissement'] || $d['duree_amortissement'] < 1 || $d['duree_amortissement'] > 100) $e['duree_amortissement'] = 'Entre 1 et 100 ans.';
        if (!$e) {
            try {
                if ($id) {
                    Database::update('categories', $d, 'id = ?', [$id]);
                    Audit::log('modification', 'categories', $id, 'Modification catégorie ' . $d['code'], Audit::diff($before, $d));
                } else {
                    $id = Database::insert('categories', $d);
                    Audit::log('creation', 'categories', $id, 'Création catégorie ' . $d['code'], $d);
                }
                Cache::invalidate('categories', 'biens');
                flash('success', 'Catégorie enregistrée.');
                $this->redirect('categories.index');
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e['code'] = 'Ce code existe déjà.';
            }
        }
        $this->render('categories/form', ['title' => $id ? 'Modifier la catégorie' : 'Nouvelle catégorie', 'item' => $d + ['id' => $id], 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('categories.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $item = Database::one('SELECT code, libelle FROM categories WHERE id = ?', [$id]) ?? $this->notFound();
        $this->safeDelete('categories', $id, 'categories', $item['code'] . ' ' . $item['libelle'], 'categories.index', ['categories']);
    }
}
