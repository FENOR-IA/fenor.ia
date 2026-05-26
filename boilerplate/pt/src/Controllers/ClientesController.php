<?php

class ClientesController
{
    // Lista clientes com busca opcional
    public function listar(): void
    {
        $db     = Database::get();
        $busca  = trim($_GET['q'] ?? '');

        if ($busca) {
            $stmt = $db->prepare("
                SELECT * FROM customers
                WHERE active = true AND (name ILIKE ? OR email ILIKE ? OR phone ILIKE ? OR document ILIKE ?)
                ORDER BY name
            ");
            $like = "%$busca%";
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $db->query('SELECT * FROM customers WHERE active = true ORDER BY name');
        }
        $clientes = $stmt->fetchAll();

        $tituloPagina = 'Clientes';
        $paginaAtiva  = 'clientes';
        require ROOT . '/views/clientes/lista.php';
    }

    // Formulário de novo cliente
    public function criar(): void
    {
        $tituloPagina = 'Novo Cliente';
        $paginaAtiva  = 'clientes';
        $cliente      = [];
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/clientes/formulario.php';
    }

    // Salva novo cliente
    public function salvar(): void
    {
        $dados  = $this->dadosFormulario();
        $erros  = $this->validar($dados);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header('Location: /clientes/novo');
            exit;
        }

        $db = Database::get();
        $db->prepare("
            INSERT INTO customers (name, email, phone, document, address, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $dados['name'], $dados['email'], $dados['phone'],
            $dados['document'], $dados['address'], $dados['notes'],
        ]);

        $id = $db->lastInsertId() ?: $db->query("SELECT currval(pg_get_serial_sequence('customers','id'))")->fetchColumn();
        Session::flash('sucesso', 'Cliente salvo com sucesso.');
        header("Location: /clientes/$id");
        exit;
    }

    // Exibe detalhes do cliente
    public function ver(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            http_response_code(404);
            echo '<h1>Cliente não encontrado</h1>';
            exit;
        }

        $stmt = $db->prepare(
            'SELECT * FROM transactions WHERE customer_id = ? ORDER BY entry_date DESC LIMIT 20'
        );
        $stmt->execute([$id]);
        $lancamentos = $stmt->fetchAll();

        $sucesso      = Session::getFlash('sucesso');
        $tituloPagina = $cliente['name'];
        $paginaAtiva  = 'clientes';
        require ROOT . '/views/clientes/detalhe.php';
    }

    // Formulário de edição
    public function editar(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();

        if (!$cliente) { http_response_code(404); echo '<h1>Não encontrado</h1>'; exit; }

        $tituloPagina = 'Editar Cliente';
        $paginaAtiva  = 'clientes';
        $erro         = Session::getFlash('erro');
        require ROOT . '/views/clientes/formulario.php';
    }

    // Atualiza cliente existente
    public function atualizar(int $id): void
    {
        $dados  = $this->dadosFormulario();
        $erros  = $this->validar($dados);

        if ($erros) {
            Session::flash('erro', implode(' ', $erros));
            header("Location: /clientes/$id/editar");
            exit;
        }

        $db = Database::get();
        $db->prepare("
            UPDATE customers SET name=?, email=?, phone=?, document=?, address=?, notes=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            $dados['name'], $dados['email'], $dados['phone'],
            $dados['document'], $dados['address'], $dados['notes'], $id,
        ]);

        Session::flash('sucesso', 'Cliente atualizado.');
        header("Location: /clientes/$id");
        exit;
    }

    // Desativa cliente (soft delete)
    public function excluir(int $id): void
    {
        $db = Database::get();
        $db->prepare('UPDATE customers SET active = false WHERE id = ?')->execute([$id]);
        Session::flash('sucesso', 'Cliente removido.');
        header('Location: /clientes');
        exit;
    }

    private function dadosFormulario(): array
    {
        return [
            'name'     => trim($_POST['name']     ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'phone'    => trim($_POST['phone']    ?? ''),
            'document' => trim($_POST['document'] ?? ''),
            'address'  => trim($_POST['address']  ?? ''),
            'notes'    => trim($_POST['notes']    ?? ''),
        ];
    }

    private function validar(array $dados): array
    {
        $erros = [];
        if (empty($dados['name'])) $erros[] = 'O nome é obrigatório.';
        return $erros;
    }
}
