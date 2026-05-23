<?php

class UsuariosController
{
    public function __construct()
    {
        if (!Session::isAdmin()) {
            http_response_code(403);
            echo '<h1>Acesso negado</h1>';
            exit;
        }
    }

    public function index(): void
    {
        $db      = Database::get();
        $usuarios = $db->query('SELECT id, nome, email, role, ativo, created_at FROM users ORDER BY nome')->fetchAll();

        $tituloPagina = 'Usuários';
        $paginaAtiva  = 'usuarios';
        $sucesso = Session::getFlash('sucesso');
        require ROOT . '/views/usuarios/list.php';
    }

    public function novo(): void
    {
        $tituloPagina = 'Novo Usuário';
        $paginaAtiva  = 'usuarios';
        $usuario      = [];
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/usuarios/form.php';
    }

    public function salvar(): void
    {
        $dados = $this->dadosForm();
        $erros = $this->validar($dados, true);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header('Location: /usuarios/novo');
            exit;
        }

        $db = Database::get();
        $db->prepare("
            INSERT INTO users (nome, email, senha_hash, role)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $dados['nome'],
            $dados['email'],
            password_hash($dados['senha'], PASSWORD_BCRYPT),
            $dados['role'],
        ]);

        Session::flash('sucesso', 'Usuário criado com sucesso.');
        header('Location: /usuarios');
        exit;
    }

    public function editar(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT id, nome, email, role, ativo FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if (!$usuario) { http_response_code(404); echo '<h1>Não encontrado</h1>'; exit; }

        $tituloPagina = 'Editar Usuário';
        $paginaAtiva  = 'usuarios';
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/usuarios/form.php';
    }

    public function atualizar(int $id): void
    {
        $dados = $this->dadosForm();
        $erros = $this->validar($dados, false);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header("Location: /usuarios/$id/editar");
            exit;
        }

        $db = Database::get();

        if (!empty($dados['senha'])) {
            $db->prepare("UPDATE users SET nome=?, email=?, role=?, ativo=?, senha_hash=?, updated_at=NOW() WHERE id=?")
               ->execute([$dados['nome'], $dados['email'], $dados['role'], $dados['ativo'], password_hash($dados['senha'], PASSWORD_BCRYPT), $id]);
        } else {
            $db->prepare("UPDATE users SET nome=?, email=?, role=?, ativo=?, updated_at=NOW() WHERE id=?")
               ->execute([$dados['nome'], $dados['email'], $dados['role'], $dados['ativo'], $id]);
        }

        Session::flash('sucesso', 'Usuário atualizado.');
        header('Location: /usuarios');
        exit;
    }

    private function dadosForm(): array
    {
        return [
            'nome'  => trim($_POST['nome']  ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'senha' => $_POST['senha'] ?? '',
            'role'  => in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user',
            'ativo' => isset($_POST['ativo']) ? 'true' : 'false',
        ];
    }

    private function validar(array $dados, bool $novo): array
    {
        $erros = [];
        if (empty($dados['nome']))  $erros[] = 'Nome é obrigatório.';
        if (empty($dados['email'])) $erros[] = 'E-mail é obrigatório.';
        if ($novo && empty($dados['senha'])) $erros[] = 'Senha é obrigatória.';
        if (!empty($dados['senha']) && strlen($dados['senha']) < 6) $erros[] = 'Senha deve ter ao menos 6 caracteres.';
        return $erros;
    }
}
