<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard.index');
        }
        $error = null;
        $username = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $username = $this->str('username', 60) ?? '';
            $password = (string) ($_POST['password'] ?? '');
            if (!Csrf::verify()) {
                $error = 'Session expirée, veuillez réessayer.';
            } elseif ($username === '' || $password === '') {
                $error = 'Veuillez saisir votre identifiant et votre mot de passe.';
            } else {
                $error = Auth::attempt($username, $password);
                if ($error === null) {
                    $this->redirect('dashboard.index');
                }
            }
        }
        echo View::render('auth/login', ['title' => 'Connexion', 'error' => $error, 'username' => $username], 'layout/guest');
    }

    public function logout(): void
    {
        $this->requirePost();
        Audit::log('logout', 'auth', Auth::id(), 'Déconnexion');
        Auth::logout();
        session_start();
        flash('info', 'Vous êtes déconnecté.');
        $this->redirect('auth.login');
    }

    /** Changement de son propre mot de passe. */
    public function password(): void
    {
        if (!Auth::check()) {
            $this->redirect('auth.login');
        }
        $errors = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->requirePost();
            $current = (string) ($_POST['current'] ?? '');
            $new     = (string) ($_POST['new'] ?? '');
            $confirm = (string) ($_POST['confirm'] ?? '');
            $hash = Database::value('SELECT password_hash FROM users WHERE id = ?', [Auth::id()]);
            if (!password_verify($current, (string) $hash)) {
                $errors['current'] = 'Mot de passe actuel incorrect.';
            }
            if ($msg = password_policy_error($new)) {
                $errors['new'] = $msg;
            }
            if ($new !== $confirm) {
                $errors['confirm'] = 'La confirmation ne correspond pas.';
            }
            if (!$errors) {
                Database::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [Auth::id()]);
                Audit::log('mot_de_passe', 'auth', Auth::id(), 'Changement de mot de passe');
                flash('success', 'Mot de passe modifié.');
                $this->redirect('dashboard.index');
            }
        }
        $this->render('auth/password', ['title' => 'Changer mon mot de passe', 'errors' => $errors]);
    }
}
