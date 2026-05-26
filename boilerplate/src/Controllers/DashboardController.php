<?php

class DashboardController
{
    public function index(): void
    {
        $db = Database::get();

        $totalCustomers = $db->query('SELECT COUNT(*) FROM customers WHERE active = true')->fetchColumn();

        $financial = $db->query("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount END), 0)  AS receivable,
                COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'pending' THEN amount END), 0) AS payable,
                COALESCE(SUM(CASE WHEN type = 'income' AND status = 'paid'
                    AND DATE_TRUNC('month', entry_date) = DATE_TRUNC('month', CURRENT_DATE) THEN amount END), 0) AS received_this_month,
                COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'paid'
                    AND DATE_TRUNC('month', entry_date) = DATE_TRUNC('month', CURRENT_DATE) THEN amount END), 0) AS paid_this_month
            FROM transactions
        ")->fetch();

        $recentCustomers = $db->query(
            'SELECT id, name, email, phone, created_at FROM customers WHERE active = true ORDER BY created_at DESC LIMIT 5'
        )->fetchAll();

        $recentTransactions = $db->query(
            "SELECT * FROM transactions ORDER BY created_at DESC LIMIT 8"
        )->fetchAll();

        $pageTitle  = 'Dashboard';
        $activePage = 'dashboard';
        require ROOT . '/views/dashboard.php';
    }
}
