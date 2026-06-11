<?php
session_start();
if (empty($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$name = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['name'] ?? '')));
$env  = preg_replace('/[^a-z]/', '', strtolower(trim($_GET['env'] ?? 'dev')));

if (!$name || !in_array($env, ['dev', 'hml', 'prd'], true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$cmd    = 'sudo /usr/local/bin/export-app ' . escapeshellarg($name) . ' ' . escapeshellarg($env) . ' 2>&1';
$output = trim((string) shell_exec($cmd));
$lines  = explode("\n", $output);
$path   = trim(end($lines));

$exportDir = '/var/fenor/exports';
$real      = realpath($path);

if ($path === '' || strpos($path, '!') === 0 || !$real || strpos($real, $exportDir . '/') !== 0 || !is_file($real)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $output ?: 'Falha ao gerar pacote']);
    exit;
}

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . basename($real) . '"');
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');

readfile($real);
unlink($real);
