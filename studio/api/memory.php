<?php
session_start();
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$env      = fenorEnv();
$appsPath = $env['APPS_PATH'] ?? '/var/www';

function sanitizeMemoryPath(string $path): string {
    $path = str_replace('..', '', $path);
    $path = preg_replace('/[^a-zA-Z0-9\-\_\.\/]/', '', $path);
    return ltrim($path, '/');
}

function memoryPathIsValid(string $fullPath, string $memoryPath): bool {
    $real = realpath(dirname($fullPath));
    if (!$real) {
        // dir doesn't exist yet — validate the intended parent
        $real = realpath($memoryPath);
    }
    $realMem = realpath($memoryPath);
    return $realMem && strpos($real . '/', $realMem . '/') === 0;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $app  = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['app'] ?? '')));
    $file = sanitizeMemoryPath($_GET['file'] ?? '');

    if (!$app) { echo json_encode(['error' => 'missing app']); exit; }

    $memoryPath = "$appsPath/dev/$app/memory";

    if (!is_dir($memoryPath)) {
        echo json_encode(['files' => [], 'content' => '']);
        exit;
    }

    if ($file) {
        $fullPath = "$memoryPath/$file";
        $realPath = realpath($fullPath);
        $realMem  = realpath($memoryPath);
        if (!$realPath || !$realMem || strpos($realPath, $realMem . '/') !== 0) {
            echo json_encode(['error' => 'invalid path']); exit;
        }
        echo json_encode(['content' => file_get_contents($realPath)]);
        exit;
    }

    // List all .md files recursively
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($memoryPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'md') {
            $rel = str_replace($memoryPath . '/', '', $f->getPathname());
            $files[] = $rel;
        }
    }
    sort($files);
    echo json_encode(['files' => $files]);
    exit;
}

if ($method === 'POST') {
    $data    = json_decode(file_get_contents('php://input'), true) ?: [];
    $app     = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['app'] ?? '')));
    $file    = sanitizeMemoryPath($data['file'] ?? '');
    $content = $data['content'] ?? '';

    if (!$app || !$file) { echo json_encode(['error' => 'missing params']); exit; }

    $memoryPath = "$appsPath/dev/$app/memory";
    if (!is_dir($memoryPath)) { echo json_encode(['error' => 'app not found']); exit; }

    $fullPath = "$memoryPath/$file";
    $dir      = dirname($fullPath);

    if (!memoryPathIsValid($fullPath, $memoryPath)) {
        echo json_encode(['error' => 'invalid path']); exit;
    }

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true)) {
            echo json_encode(['error' => 'cannot create directory — check permissions']); exit;
        }
    }

    if (file_put_contents($fullPath, $content) === false) {
        echo json_encode(['error' => 'cannot write file — check permissions']); exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'method not allowed']);
