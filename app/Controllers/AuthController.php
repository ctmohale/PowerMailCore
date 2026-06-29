<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

class AuthController
{
    public function showLogin(): void
    {
        if (current_user()) {
            redirect('/dashboard');
        }

        view('auth.login');
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = Database::fetch('select * from users where email = ? limit 1', [$email]);

        if (! $user || ! password_verify($password, $user['password'])) {
            flash('error', 'These credentials do not match our records.');
            keep_old(['email' => $email]);
            redirect('/login');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        clear_old();
        redirect('/dashboard');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        redirect('/login');
    }
}
