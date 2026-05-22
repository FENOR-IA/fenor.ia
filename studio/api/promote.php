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

$data = json_decode(file_get_contents('php://input'), true);
$name = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));
$to   = in_array($data['to'] ?? '', ['hml', 'prd']) ? $data['to'] : '';

if (!$name || !$to) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$cmd    = "sudo /usr/local/bin/fenor-promote " . escapeshellarg($name) . " 2>&1";
$output = shell_exec($cmd);

$success = strpos($output ?? '', 'Promoção concluída') !== false;

echo json_encode([
    'success' => $success,
    'output'  => $output,
]);
