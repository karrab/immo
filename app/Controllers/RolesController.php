<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Database;

final class RolesController extends Controller
{
    public function index(): void
    {
        $this->authorize('roles.view');
        $w = [];
        $p = [];
        if ($q = $this->q('q')) {
            $w[] = 'r.nom LIKE ?';
            $p[] = '%' . $q . '%';
        }
        $page = $this->keyset(
            'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS nb_users,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS nb_perms
               FROM roles r', $w, $p, 'r.nom', 'nom', 'r.id', 'ASC');
        $total = (int) Database::value('SELECT COUNT(*) FROM roles r' . ($w ? ' WHERE ' . implode(' AND ', $w) : ''), $p);
        $this->render('roles/index', ['title' => 'Rôles & permissions', 'page' => $page, 'total' => $total,
            'nbPerms' => (int) Database::value('SELECT COUNT(*) FROM permissions')]);
    }

    private function permissionsByModule(): array
    {
        $out = [];
        foreach (Database::all('SELECT id, code, module, libelle FROM permissions ORDER BY module, id') as $p) {
            $out[$p['module']][] = $p;
        }
        return $out;
    }

    public function create(): void
    {
        $this->authorize('roles.create');
        $this->render('roles/form', ['title' => 'Nouveau rôle', 'item' => [], 'selected' => [], 'modules' => $this->permissionsByModule(), 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('roles.edit');
        $item = Database::one('SELECT * FROM roles WHERE id = ?', [(int) $this->qint('id')]) ?? $this->notFound();
        $selected = array_map('intval', array_column(Database::all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$item['id']]), 'permission_id'));
        $this->render('roles/form', ['title' => 'Rôle ' . $item['nom'], 'item' => $item, 'selected' => $selected, 'modules' => $this->permissionsByModule(), 'errors' => []]);
    }

    public function store(): void
    {
        $this->authorize('roles.create');
        $this->save(null);
    }

    public function update(): void
    {
        $this->authorize('roles.edit');
        $this->save((int) $this->int('id'));
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        $before = $id ? (Database::one('SELECT * FROM roles WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $d = ['nom' => $this->str('nom', 80), 'description' => $this->str('description', 255)];
        $valid = array_map('intval', array_column(Database::all('SELECT id FROM permissions'), 'id'));
        $perms = array_values(array_intersect($valid, array_map('intval', (array) ($_POST['permissions'] ?? []))));
        $e = [];
        if (!$d['nom']) $e['nom'] = 'Nom obligatoire.';
        if (!$e) {
            try {
                $id = Database::transaction(function () use ($id, $d, $perms) {
                    if ($id) {
                        Database::update('roles', $d, 'id = ?', [$id]);
                    } else {
                        $id = Database::insert('roles', $d);
                    }
                    Database::delete('role_permissions', 'role_id = ?', [$id]);
                    foreach ($perms as $pid) {
                        Database::insert('role_permissions', ['role_id' => $id, 'permission_id' => $pid]);
                    }
                    return $id;
                });
                $codes = $perms ? array_column(Database::all('SELECT code FROM permissions WHERE id IN (' . implode(',', $perms) . ')'), 'code') : [];
                Audit::log($before ? 'modification' : 'creation', 'roles', $id, ($before ? 'Modification' : 'Création') . ' du rôle ' . $d['nom'], ['permissions' => $codes]);
                Cache::invalidate('roles');
                flash('success', 'Rôle enregistré.');
                $this->redirect('roles.index');
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e['nom'] = 'Ce nom existe déjà.';
            }
        }
        $this->render('roles/form', ['title' => 'Rôle', 'item' => $d + ['id' => $id, 'is_super' => $before['is_super'] ?? 0], 'selected' => $perms,
            'modules' => $this->permissionsByModule(), 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('roles.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        $r = Database::one('SELECT nom, is_super FROM roles WHERE id = ?', [$id]) ?? $this->notFound();
        if ($r['is_super']) {
            flash('danger', 'Le rôle administrateur ne peut pas être supprimé.');
            $this->redirect('roles.index');
        }
        $this->safeDelete('roles', $id, 'roles', $r['nom'], 'roles.index', ['roles']);
    }
}
