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

$VALID_TIPOS = ['review', 'security', 'performance', 'suggestions'];

function loadConfig(PDO $db, string $name): array
{
    $stmt = $db->prepare('SELECT config FROM fenor_apps WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $config = json_decode($row['config'] ?? '{}', true);
    return is_array($config) ? $config : [];
}

$db = fenorDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['name'] ?? '')));

    if (!$name) {
        echo json_encode(['success' => false, 'error' => 'Invalid name']);
        exit;
    }

    try {
        $config = loadConfig($db, $name);
        if ($config === null) {
            echo json_encode(['success' => false, 'error' => 'App not found']);
            exit;
        }

        $schedule = $config['agent_schedule'] ?? [];
        echo json_encode([
            'success'  => true,
            'schedule' => [
                'enabled'        => !empty($schedule['enabled']),
                'tipos'          => array_values(array_intersect((array) ($schedule['tipos'] ?? []), $VALID_TIPOS)),
                'frequency_days' => (int) ($schedule['frequency_days'] ?? 7),
            ],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));

    if (!$name) {
        echo json_encode(['success' => false, 'error' => 'Invalid name']);
        exit;
    }

    $enabled       = !empty($data['enabled']);
    $tipos         = array_values(array_intersect((array) ($data['tipos'] ?? []), $VALID_TIPOS));
    $frequencyDays = (int) ($data['frequency_days'] ?? 7);
    if ($frequencyDays < 1) {
        $frequencyDays = 1;
    }

    try {
        $config = loadConfig($db, $name);
        if ($config === null) {
            echo json_encode(['success' => false, 'error' => 'App not found']);
            exit;
        }

        $config['agent_schedule'] = [
            'enabled'        => $enabled,
            'tipos'          => $tipos,
            'frequency_days' => $frequencyDays,
        ];

        $db->prepare('UPDATE fenor_apps SET config = ? WHERE name = ?')
           ->execute([json_encode($config), $name]);

        echo json_encode(['success' => true, 'schedule' => $config['agent_schedule']]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
