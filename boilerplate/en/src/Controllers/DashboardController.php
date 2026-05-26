<?php

class DashboardController
{
    public function index(): void
    {
        // TODO: add your dashboard queries here
        // Example:
        //   $db = Database::get();
        //   $totalItems = $db->query('SELECT COUNT(*) FROM your_table')->fetchColumn();

        $pageTitle  = 'Dashboard';
        $activePage = 'dashboard';
        require ROOT . '/views/dashboard.php';
    }
}
