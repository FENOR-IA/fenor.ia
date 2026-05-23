<?php

class ClientesController
{
    public function index(): void
    {
        $db   = Database::get();
        $busca = trim($_GET['q'] ?? '');

        if ($busca) {
            $stmt = $db->prepare("
                SELECT * FROM clientes
                WHERE ativo = true AND (nome ILIKE ? OR email ILIKE ? OR telefone ILIKE ? OR documento ILIKE ?)
                ORDER BY nome
            ");
            $like = "%$busca%";
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $db->query('SELECT * FROM clientes WHERE ativo = true ORDER BY nome');
        }
        $clientes = $stmt->fetchAll();

        $tituloPagina = 'Clientes';
        $paginaAtiva  = 'clientes';
        require ROOT . '/views/clientes/list.php';
    }

    public function novo(): void
    {
        $tituloPagina = 'Novo Cliente';
        $paginaAtiva  = 'clientes';
        $cliente      = [];
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/clientes/form.php';
    }

    public function salvar(): void
    {
        $dados = $this->dadosForm();
        $erros = $this->validar($dados);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header('Location: /clientes/novo');
            exit;
        }

        $db = Database::get();
        $db->prepare("
            INSERT INTO clientes (nome, email, telefone, documento, endereco, observacoes)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $dados['nome'], $dados['email'], $dados['telefone'],
            $dados['documento'], $dados['endereco'], $dados['observacoes'],
        ]);

        $id = $db->lastInsertId() ?: $db->query("SELECT currval(pg_get_serial_sequence('clientes','id'))")->fetchColumn();
        Session::flash('sucesso', 'Cliente cadastrado com sucesso.');
        header("Location: /clientes/$id");
        exit;
    }

    public function ver(int $id): void
    {
        $db      = Database::get();
        $cliente = $db->prepare('SELECT * FROM clientes WHERE id = ?')->execute([$id]) ? null : null;
        $stmt    = $db->prepare('SELECT * FROM clientes WHERE id = ?');
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            http_response_code(404);
            echo '<h1>Cliente não encontrado</h1>';
            exit;
        }

        $lancamentos = $db->prepare(
            'SELECT * FROM lancamentos WHERE cliente_id = ? ORDER BY data_lancamento DESC LIMIT 20'
        );
        $lancamentos->execute([$id]);
        $lancamentos = $lancamentos->fetchAll();

        $sucesso = Session::getFlash('sucesso');
        $tituloPagina = $cliente['nome'];
        $paginaAtiva  = 'clientes';
        require ROOT . '/views/clientes/view.php';
    }

    public function editar(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM clientes WHERE id = ?');
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();

        if (!$cliente) { http_response_code(404); echo '<h1>Não encontrado</h1>'; exit; }

        $tituloPagina = 'Editar Cliente';
        $paginaAtiva  = 'clientes';
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/clientes/form.php';
    }

    public function atualizar(int $id): void
    {
        $dados = $this->dadosForm();
        $erros = $this->validar($dados);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header("Location: /clientes/$id/editar");
            exit;
        }

        $db = Database::get();
        $db->prepare("
            UPDATE clientes SET nome=?, email=?, telefone=?, documento=?, endereco=?, observacoes=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            $dados['nome'], $dados['email'], $dados['telefone'],
            $dados['documento'], $dados['endereco'], $dados['observacoes'], $id,
        ]);

        Session::flash('sucesso', 'Cliente atualizado.');
        header("Location: /clientes/$id");
        exit;
    }

    public function excluir(int $id): void
    {
        $db = Database::get();
        $db->prepare('UPDATE clientes SET ativo = false WHERE id = ?')->execute([$id]);
        Session::flash('sucesso', 'Cliente removido.');
        header('Location: /clientes');
        exit;
    }

    private function dadosForm(): array
    {
        return [
            'nome'        => trim($_POST['nome']        ?? ''),
            'email'       => trim($_POST['email']       ?? ''),
            'telefone'    => trim($_POST['telefone']    ?? ''),
            'documento'   => trim($_POST['documento']   ?? ''),
            'endereco'    => trim($_POST['endereco']    ?? ''),
            'observacoes' => trim($_POST['observacoes'] ?? ''),
        ];
    }

    private function validar(array $dados): array
    {
        $erros = [];
        if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório.';
        return $erros;
    }
}
