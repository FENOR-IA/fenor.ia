<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$name = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));

if (!$name) {
    echo json_encode(['success' => false, 'error' => 'Invalid name']);
    exit;
}

$cmd    = 'sudo /usr/local/bin/delete-app ' . escapeshellarg($name) . ' 2>&1';
$output = shell_exec($cmd);

try {
    fenorDB()
        ->prepare('DELETE FROM fenor_apps WHERE name = ?')
        ->execute([$name]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'output' => $output]);
    exit;
}

echo json_encode([
    'success' => true,
    'output'  => $output ?? 'Script não encontrado em /usr/local/bin/delete-app',
]);
