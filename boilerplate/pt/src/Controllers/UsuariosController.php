<?php

class UsuariosController
{
    // Lista todos os usuários do sistema
    public function listar(): void
    {
        if (!Session::isAdmin()) {
            header('Location: /');
            exit;
        }

        $db       = Database::get();
        $usuarios = $db->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY name')->fetchAll();

        $tituloPagina = 'Usuários';
        $paginaAtiva  = 'usuarios';
        require ROOT . '/views/usuarios/lista.php';
    }

    // Formulário de novo usuário
    public function criar(): void
    {
        if (!Session::isAdmin()) { header('Location: /'); exit; }

        $tituloPagina = 'Novo Usuário';
        $paginaAtiva  = 'usuarios';
        $usuario      = [];
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/usuarios/formulario.php';
    }

    // Salva novo usuário
    public function salvar(): void
    {
        if (!Session::isAdmin()) { header('Location: /'); exit; }

        $dados  = $this->dadosFormulario();
        $erros  = $this->validar($dados, true);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header('Location: /usuarios/novo');
            exit;
        }

        $hash = password_hash($dados['password'], PASSWORD_BCRYPT);
        $db   = Database::get();
        $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)")
           ->execute([$dados['name'], $dados['email'], $hash, $dados['role']]);

        Session::flash('sucesso', 'Usuário criado com sucesso.');
        header('Location: /usuarios');
        exit;
    }

    // Formulário de edição
    public function editar(int $id): void
    {
        if (!Session::isAdmin()) { header('Location: /'); exit; }

        $db   = Database::get();
        $stmt = $db->prepare('SELECT id, name, email, role, active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if (!$usuario) { http_response_code(404); echo '<h1>Não encontrado</h1>'; exit; }

        $tituloPagina = 'Editar Usuário';
        $paginaAtiva  = 'usuarios';
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/usuarios/formulario.php';
    }

    // Atualiza usuário existente
    public function atualizar(int $id): void
    {
        if (!Session::isAdmin()) { header('Location: /'); exit; }

        $dados = $this->dadosFormulario();
        $erros = $this->validar($dados, false);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header("Location: /usuarios/$id/editar");
            exit;
        }

        $db = Database::get();
        if (!empty($dados['password'])) {
            $hash = password_hash($dados['password'], PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET name=?, email=?, role=?, active=?, password_hash=?, updated_at=NOW() WHERE id=?")
               ->execute([$dados['name'], $dados['email'], $dados['role'], isset($_POST['active']) ? 1 : 0, $hash, $id]);
        } else {
            $db->prepare("UPDATE users SET name=?, email=?, role=?, active=?, updated_at=NOW() WHERE id=?")
               ->execute([$dados['name'], $dados['email'], $dados['role'], isset($_POST['active']) ? 1 : 0, $id]);
        }

        Session::flash('sucesso', 'Usuário atualizado.');
        header('Location: /usuarios');
        exit;
    }

    private function dadosFormulario(): array
    {
        return [
            'name'     => trim($_POST['name']     ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'role'     => in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user',
            'password' => $_POST['password'] ?? '',
        ];
    }

    private function validar(array $dados, bool $novo): array
    {
        $erros = [];
        if (empty($dados['name']))  $erros[] = 'O nome é obrigatório.';
        if (empty($dados['email'])) $erros[] = 'O e-mail é obrigatório.';
        if ($novo && empty($dados['password'])) $erros[] = 'A senha é obrigatória.';
        return $erros;
    }
}
