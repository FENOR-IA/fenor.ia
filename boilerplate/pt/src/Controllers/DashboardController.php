<?php

class DashboardController
{
    public function index(): void
    {
        $db = Database::get();

        // Total de clientes ativos
        $totalClientes = $db->query('SELECT COUNT(*) FROM customers WHERE active = true')->fetchColumn();

        // Resumo financeiro
        $financeiro = $db->query("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'income'  AND status = 'pending' THEN amount END), 0)  AS a_receber,
                COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'pending' THEN amount END), 0)  AS a_pagar,
                COALESCE(SUM(CASE WHEN type = 'income'  AND status = 'paid'
                    AND DATE_TRUNC('month', entry_date) = DATE_TRUNC('month', CURRENT_DATE) THEN amount END), 0) AS recebido_mes,
                COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'paid'
                    AND DATE_TRUNC('month', entry_date) = DATE_TRUNC('month', CURRENT_DATE) THEN amount END), 0) AS pago_mes
            FROM transactions
        ")->fetch();

        // Clientes recentes
        $clientesRecentes = $db->query(
            'SELECT id, name, email, phone, created_at FROM customers WHERE active = true ORDER BY created_at DESC LIMIT 5'
        )->fetchAll();

        // Lançamentos recentes
        $lancamentosRecentes = $db->query(
            "SELECT * FROM transactions ORDER BY created_at DESC LIMIT 8"
        )->fetchAll();

        $tituloPagina = 'Painel';
        $paginaAtiva  = 'painel';
        require ROOT . '/views/dashboard.php';
    }
}
