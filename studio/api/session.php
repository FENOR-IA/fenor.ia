<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthenticated']);
    exit;
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$app  = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['app'] ?? '')));
$mode = in_array($data['mode'] ?? '', ['planner', 'executor', 'reviewer'])
    ? $data['mode']
    : 'executor';

if (!$app) {
    echo json_encode(['success' => false, 'error' => 'app required']);
    exit;
}

$appSafe  = str_replace('-', '_', $app);
$modeFile = "/tmp/fenor-flags/session-{$app}";
$svcName  = "ttyd-{$appSafe}-dev";

// /tmp/fenor-flags não tem sticky bit: o terminal (rodando como usuário do
// app) consegue remover o arquivo depois de ler o modo — em /tmp puro
// (sticky), o rm -f falharia silenciosamente (dono diferente).
@mkdir('/tmp/fenor-flags', 0777);
@chmod('/tmp/fenor-flags', 0777);
file_put_contents($modeFile, $mode);
chmod($modeFile, 0666);

shell_exec("sudo /bin/systemctl restart {$svcName} 2>&1");

echo json_encode(['success' => true, 'mode' => $mode]);
