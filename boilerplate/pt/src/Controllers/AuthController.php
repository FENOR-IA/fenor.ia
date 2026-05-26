<?php

class AuthController
{
    public function login(): void
    {
        if (Session::isLoggedIn()) {
            header('Location: /');
            exit;
        }
        $erro = Session::getFlash('erro');
        require ROOT . '/views/login.php';
    }

    public function loginPost(): void
    {
        $email = trim($_POST['email']  ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            Session::flash('erro', 'Informe o e-mail e a senha.');
            header('Location: /entrar');
            exit;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND active = true LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['password_hash'])) {
            Session::flash('erro', 'E-mail ou senha incorretos.');
            header('Location: /entrar');
            exit;
        }

        Session::set('user_id', $usuario['id']);
        Session::set('user', [
            'id'    => $usuario['id'],
            'name'  => $usuario['name'],
            'email' => $usuario['email'],
            'role'  => $usuario['role'],
        ]);

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        Session::destroy();
        header('Location: /entrar');
        exit;
    }
}
