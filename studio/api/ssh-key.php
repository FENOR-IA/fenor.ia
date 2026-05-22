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

$data   = json_decode(file_get_contents('php://input'), true);
$name   = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));
$action = $data['action'] ?? 'get'; // get | test | push

if (!$name) {
    echo json_encode(['success' => false, 'error' => 'Nome inválido']);
    exit;
}

$appSafe = str_replace('-', '_', $name);
$keyFile = "/etc/fenor/keys/{$appSafe}.pub";
$pubKey  = file_exists($keyFile) ? trim(file_get_contents($keyFile)) : '';

if ($action === 'get') {
    echo json_encode(['success' => true, 'public_key' => $pubKey]);
    exit;
}

if ($action === 'test') {
    $result = shell_exec("ssh -T -o ConnectTimeout=5 git@github-$name 2>&1");
    $ok     = strpos($result ?? '', 'successfully authenticated') !== false;
    echo json_encode(['success' => $ok, 'output' => trim($result ?? 'Sem resposta')]);
    exit;
}

if ($action === 'push') {
    require_once dirname(__DIR__) . '/config/db.php';
    $env      = fenorEnv();
    $appsPath = $env['APPS_PATH'] ?? '/var/www';
    $appPath  = "$appsPath/dev/$name";

    if (!is_dir($appPath)) {
        echo json_encode(['success' => false, 'error' => 'App não provisionado']);
        exit;
    }

    $output = shell_exec("cd " . escapeshellarg($appPath) . " && git push origin main 2>&1");
    $ok     = strpos($output ?? '', 'error') === false && strpos($output ?? '', 'fatal') === false;
    echo json_encode(['success' => $ok, 'output' => trim($output ?? '')]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Ação inválida']);
