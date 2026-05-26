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

$data         = json_decode(file_get_contents('php://input'), true);
$name         = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));
$description  = trim($data['description']  ?? '');
$github_repo  = trim($data['github_repo']  ?? '');
$memory_notes = trim($data['memory_notes'] ?? '');

if (!$name) {
    echo json_encode(['success' => false, 'error' => 'Invalid name']);
    exit;
}

try {
    $db = fenorDB();
    $db->prepare('UPDATE fenor_apps SET description=?, github_repo=?, memory_notes=? WHERE name=?')
       ->execute([$description, $github_repo, $memory_notes, $name]);

    // If provisioned, update files on disk
    $row = $db->prepare('SELECT status FROM fenor_apps WHERE name=?');
    $row->execute([$name]);
    $app = $row->fetch();

    if ($app && $app['status'] === 'provisioned') {
        $env    = fenorEnv();
        $appsPath = $env['APPS_PATH'] ?? '/var/www';
        $appPath  = "$appsPath/dev/$name";

        if (is_dir($appPath)) {
            // Update CLAUDE.md
            $claudeFile = "$appPath/CLAUDE.md";
            if (file_exists($claudeFile)) {
                $content = file_get_contents($claudeFile);
                // Replace Description block
                $content = preg_replace(
                    '/## Description\n.*?(?=\n##|\z)/s',
                    "## Description\n$description\n",
                    $content
                );
                // Replace or add Notes block
                if (strpos($content, '## Notes') !== false) {
                    $content = preg_replace(
                        '/## Notes\n.*?(?=\n##|\z)/s',
                        "## Notes\n$memory_notes\n",
                        $content
                    );
                } elseif ($memory_notes) {
                    $content .= "\n## Notes\n$memory_notes\n";
                }
                file_put_contents($claudeFile, $content);
            }

            // Update memory/INDEX.md
            $indexFile = "$appPath/memory/INDEX.md";
            if (file_exists($indexFile)) {
                $content = file_get_contents($indexFile);
                $content = preg_replace(
                    '/## What it is\n.*?(?=\n##|\z)/s',
                    "## What it is\n$description\n",
                    $content
                );
                file_put_contents($indexFile, $content);
            }

            // Configure git remote with SSH alias and update .env
            if ($github_repo) {
                $appSafe  = preg_replace('/[^a-z0-9_]/', '_', $name);
                // Convert URL to SSH alias: git@github-{app}:user/repo.git
                $aliasUrl = preg_replace('/^git@github\.com:/', "git@github-$name:", $github_repo);
                $aliasUrl = preg_replace('#^https://github\.com/#', "git@github-$name:", $aliasUrl);
                if (substr($aliasUrl, -4) !== '.git') $aliasUrl .= '.git';

                $gitCmd = "cd " . escapeshellarg($appPath) . " && "
                    . "(git remote get-url origin 2>/dev/null "
                    . "&& git remote set-url origin " . escapeshellarg($aliasUrl)
                    . " || git remote add origin " . escapeshellarg($aliasUrl) . ") 2>&1";
                shell_exec($gitCmd);

                $envFile = "$appPath/.env";
                if (file_exists($envFile)) {
                    $envContent = file_get_contents($envFile);
                    if (strpos($envContent, 'GITHUB_REPO=') !== false) {
                        $envContent = preg_replace('/^GITHUB_REPO=.*/m', "GITHUB_REPO=$github_repo", $envContent);
                    } else {
                        $envContent .= "\nGITHUB_REPO=$github_repo";
                    }
                    file_put_contents($envFile, $envContent);
                }
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
