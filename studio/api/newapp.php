<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$data        = json_decode(file_get_contents('php://input'), true);
$name        = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));
$description = trim($data['description']  ?? '');
$github_repo = trim($data['github_repo']  ?? '');
$template    = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['template'] ?? 'base')));
$config      = $data['config'] ?? [];
$config['template'] = $template;
$config_json = json_encode($config);

if (!$name) {
    echo json_encode(['success' => false, 'error' => 'Invalid name']);
    exit;
}

try {
    fenorDB()
        ->prepare('INSERT INTO fenor_apps (name, description, github_repo, status, config) VALUES (?, ?, ?, \'registered\', ?)')
        ->execute([$name, $description, $github_repo, $config_json]);
    echo json_encode(['success' => true, 'message' => "App \"$name\" registered."]);
} catch (Throwable $e) {
    $msg = strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false
        ? "An app named \"$name\" already exists."
        : $e->getMessage();
    echo json_encode(['success' => false, 'error' => $msg]);
}
