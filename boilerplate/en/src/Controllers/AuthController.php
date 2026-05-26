<?php

class AuthController
{
    public function login(): void
    {
        if (Session::isLoggedIn()) {
            header('Location: /');
            exit;
        }
        $error = Session::getFlash('error');
        require ROOT . '/views/login.php';
    }

    public function loginPost(): void
    {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            Session::flash('error', 'Please enter your email and password.');
            header('Location: /login');
            exit;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND active = true LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Session::flash('error', 'Invalid email or password.');
            header('Location: /login');
            exit;
        }

        Session::set('user_id', $user['id']);
        Session::set('user', [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        Session::destroy();
        header('Location: /login');
        exit;
    }
}
