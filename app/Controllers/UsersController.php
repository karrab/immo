<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Keyset;
use App\Core\Ref;

final class UsersController extends Controller
{
    public function index(): void
    {
        $this->authorize('users.view');
        $w = [];
        $p = [];
        $f = ['q' => $this->q('q'), 'role_id' => $this->qint('role_id'),
              'actif' => isset($_GET['actif']) && $_GET['actif'] !== '' ? (int) (bool) $_GET['actif'] : null];
        if ($f['q'])       { $w[] = '(u.username LIKE ? OR u.nom_complet LIKE ? OR u.email LIKE ?)'; array_push($p, $f['q'] . '%', '%' . $f['q'] . '%', $f['q'] . '%'); }
        if ($f['role_id']) { $w[] = 'u.role_id = ?'; $p[] = $f['role_id']; }
        if ($f['actif'] !== null) { $w[] = 'u.actif = ?'; $p[] = $f['actif']; }
        $select = "SELECT u.id, u.username, u.nom_complet, u.email, u.actif, u.last_login_at, r.nom AS role,
                          CONCAT(e.nom, ' ', e.prenom) AS employe
                     FROM users u LEFT JOIN roles r ON r.id = u.role_id LEFT JOIN employes e ON e.id = u.employe_id";
        $page = (new Keyset($select, $w, $p, 'u.username', 'username', 'u.id', 'ASC', $this->perPage()))
            ->paginate($this->q('after', 200), $this->q('before', 200));
        $this->render('users/index', ['title' => 'Utilisateurs', 'page' => $page, 'filters' => $f]);
    }

    public function create(): void
    {
        $this->authorize('users.create');
        $this->render('users/form', ['title' => 'Nouvel utilisateur', 'item' => ['actif' => 1], 'errors' => []]);
    }

    public function edit(): void
    {
        $this->authorize('users.edit');
        $item = Database::one('SELECT id, username, nom_complet, email, role_id, employe_id, actif FROM users WHERE id = ?', [(int) $this->qint('id')]) ?? $this->notFound();
        $this->render('users/form', ['title' => 'Modifier l\'utilisateur', 'item' => $item, 'errors' => []]);
    }

    public function store(): void
    {
        $this->authorize('users.create');
        $this->save(null);
    }

    public function update(): void
    {
        $this->authorize('users.edit');
        $this->save((int) $this->int('id'));
    }

    private function save(?int $id): void
    {
        $this->requirePost();
        $before = $id ? (Database::one('SELECT id, username, nom_complet, email, role_id, employe_id, actif FROM users WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $d = [
            'username'    => mb_strtolower((string) $this->str('username', 60)),
            'nom_complet' => $this->str('nom_complet', 150),
            'email'       => $this->str('email', 150),
            'role_id'     => $this->int('role_id'),
            'employe_id'  => $this->int('employe_id'),
            'actif'       => empty($_POST['actif']) ? 0 : 1,
        ];
        $password = (string) ($_POST['password'] ?? '');
        $e = [];
        if (!preg_match('/^[a-z0-9._\-]{3,60}$/', $d['username'])) $e['username'] = '3 à 60 caractères : lettres, chiffres, . _ -';
        if (!$d['nom_complet']) $e['nom_complet'] = 'Nom obligatoire.';
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Email invalide.';
        if (!in_array($d['role_id'], array_column(Ref::roles(), 'id'))) $e['role_id'] = 'Choisissez un rôle.';
        if ($d['employe_id'] && !in_array($d['employe_id'], array_column(Ref::employes(), 'id'))) $e['employe_id'] = 'Employé invalide.';
        if (!$id || $password !== '') {
            if ($msg = password_policy_error($password)) $e['password'] = $msg;
            elseif ($password !== ($_POST['password_confirm'] ?? '')) $e['password'] = 'La confirmation ne correspond pas.';
        }
        if ($id === Auth::id()) {
            // Empêche de se verrouiller soi-même
            if (!$d['actif']) $e['actif'] = 'Vous ne pouvez pas désactiver votre propre compte.';
            if ($d['role_id'] !== (int) $before['role_id']) $e['role_id'] = 'Vous ne pouvez pas modifier votre propre rôle.';
        }
        if (!$e) {
            try {
                $row = $d;
                if ($password !== '') {
                    $row['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                if ($id) {
                    Database::update('users', $row, 'id = ?', [$id]);
                    $changes = Audit::diff($before, $d);
                    if ($password !== '') $changes['mot_de_passe'] = ['***', '***'];
                    Audit::log('modification', 'users', $id, 'Modification utilisateur ' . $d['username'], $changes);
                } else {
                    $id = Database::insert('users', $row);
                    Audit::log('creation', 'users', $id, 'Création utilisateur ' . $d['username'], $d);
                }
                Cache::invalidate('users');
                flash('success', 'Utilisateur enregistré.');
                $this->redirect('users.index');
            } catch (\PDOException $ex) {
                if (!Database::isDuplicate($ex)) throw $ex;
                $e[str_contains($ex->getMessage(), 'email') ? 'email' : 'username'] = 'Déjà utilisé par un autre compte.';
            }
        }
        $this->render('users/form', ['title' => $id ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur', 'item' => $d + ['id' => $id], 'errors' => $e]);
    }

    public function delete(): void
    {
        $this->authorize('users.delete');
        $this->requirePost();
        $id = (int) $this->int('id');
        if ($id === Auth::id()) {
            flash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            $this->redirect('users.index');
        }
        $u = Database::one('SELECT username FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        $this->safeDelete('users', $id, 'users', $u['username'], 'users.index', ['users']);
    }
}
