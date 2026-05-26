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

// Fetch app metadata from database
try {
    $stmt = fenorDB()->prepare('SELECT description, language FROM fenor_apps WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    $description = $row ? $row['description'] : '';
    $language    = ($row && in_array($row['language'], ['pt', 'en'])) ? $row['language'] : 'pt';
} catch (Throwable $e) {
    $description = '';
    $language    = 'pt';
}

$cmd    = 'sudo /usr/local/bin/newapp ' . escapeshellarg($name) . ' ' . escapeshellarg($description) . ' ' . escapeshellarg($language) . ' 2>&1';
$output = shell_exec($cmd);

$success = strpos($output ?? '', 'App ready!') !== false || strpos($output ?? '', 'URL:') !== false;

if ($success) {
    try {
        fenorDB()
            ->prepare("UPDATE fenor_apps SET status = 'provisioned' WHERE name = ?")
            ->execute([$name]);
    } catch (Throwable $e) { /* ignora */ }
}

echo json_encode([
    'success' => $success,
    'output'  => $output ?? 'Script não encontrado em /usr/local/bin/newapp',
]);
