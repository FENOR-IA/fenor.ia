<?php

class DashboardController
{
    public function index(): void
    {
        $db = Database::get();

        $totalClientes = $db->query('SELECT COUNT(*) FROM clientes WHERE ativo = true')->fetchColumn();

        $financeiro = $db->query("
            SELECT
                COALESCE(SUM(CASE WHEN tipo = 'receita' AND status = 'pendente' THEN valor END), 0) AS a_receber,
                COALESCE(SUM(CASE WHEN tipo = 'despesa' AND status = 'pendente' THEN valor END), 0) AS a_pagar,
                COALESCE(SUM(CASE WHEN tipo = 'receita' AND status = 'pago'
                    AND DATE_TRUNC('month', data_lancamento) = DATE_TRUNC('month', CURRENT_DATE) THEN valor END), 0) AS recebido_mes,
                COALESCE(SUM(CASE WHEN tipo = 'despesa' AND status = 'pago'
                    AND DATE_TRUNC('month', data_lancamento) = DATE_TRUNC('month', CURRENT_DATE) THEN valor END), 0) AS pago_mes
            FROM lancamentos
        ")->fetch();

        $ultimosClientes = $db->query(
            'SELECT id, nome, email, telefone, created_at FROM clientes WHERE ativo = true ORDER BY created_at DESC LIMIT 5'
        )->fetchAll();

        $ultimosLancamentos = $db->query(
            "SELECT * FROM lancamentos ORDER BY created_at DESC LIMIT 8"
        )->fetchAll();

        $tituloPagina = 'Dashboard';
        $paginaAtiva  = 'dashboard';
        require ROOT . '/views/dashboard.php';
    }
}
