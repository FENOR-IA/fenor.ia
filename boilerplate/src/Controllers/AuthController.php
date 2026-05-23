<?php

class AuthController
{
    public function login(): void
    {
        if (Session::estaLogado()) {
            header('Location: /');
            exit;
        }
        $erro = Session::getFlash('erro');
        require ROOT . '/views/login.php';
    }

    public function loginPost(): void
    {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            Session::flash('erro', 'Preencha e-mail e senha.');
            header('Location: /login');
            exit;
        }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND ativo = true LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['senha_hash'])) {
            Session::flash('erro', 'E-mail ou senha incorretos.');
            header('Location: /login');
            exit;
        }

        Session::set('usuario_id', $user['id']);
        Session::set('usuario', [
            'id'    => $user['id'],
            'nome'  => $user['nome'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        Session::destruir();
        header('Location: /login');
        exit;
    }
}
