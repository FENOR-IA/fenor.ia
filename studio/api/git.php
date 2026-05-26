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

$data    = json_decode(file_get_contents('php://input'), true) ?: [];
$name    = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name']    ?? '')));
$action  = preg_replace('/[^a-z\-]/',    '', strtolower(trim($data['action']  ?? '')));
$message = trim($data['message'] ?? 'Update ' . date('Y-m-d H:i'));

if (!$name || !in_array($action, ['status', 'pull', 'push', 'set-remote'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// ── set-remote — link a repo by name (or owner/name), no full URL needed ──
if ($action === 'set-remote') {
    $repo = trim($data['repo'] ?? '');
    if (!$repo) {
        echo json_encode(['success' => false, 'error' => 'Missing repo name']);
        exit;
    }

    $settings   = fenorSettings();
    $token      = trim($settings['GITHUB_TOKEN']       ?? '');
    $authMethod = trim($settings['GITHUB_AUTH_METHOD'] ?? '');
    $org        = trim($settings['GITHUB_ORG']         ?? '');
    $user       = trim($settings['GITHUB_USER']        ?? '');
    $owner      = $org ?: $user;
    $isPat      = $token && $authMethod === 'pat';

    // Normalize: strip host / .git suffix, extract just the repo slug
    // Accepts: "my-project", "owner/my-project", "https://github.com/owner/repo.git"
    $repoClean = preg_replace('#^.*github\.com[:/]+#i', '', $repo);  // strip host
    $repoClean = rtrim($repoClean, '.git');                            // strip .git
    $repoClean = trim($repoClean, '/');

    // If "owner/repo" already given use it; else prepend configured owner
    if (strpos($repoClean, '/') !== false) {
        [$owner, $repoSlug] = explode('/', $repoClean, 2);
    } else {
        $repoSlug = $repoClean;
        // owner stays as $org ?: $user (set above)
    }

    $repoSlug = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $repoSlug);
    if (!$repoSlug) {
        echo json_encode(['success' => false, 'error' => 'Invalid repo name']);
        exit;
    }
    if (!$owner) {
        echo json_encode(['success' => false, 'error' => 'GitHub user/org not configured. Go to Settings → GitHub.']);
        exit;
    }

    // Build the remote URL for .git/config
    if ($isPat) {
        // HTTPS with embedded token — no SSH key required
        $remoteUrl = "https://{$token}@github.com/{$owner}/{$repoSlug}.git";
    } else {
        // Standard SSH (deploy key must exist separately)
        $appSafe   = str_replace('-', '_', $name);
        $remoteUrl = "git@github-{$name}:{$owner}/{$repoSlug}.git";
    }

    // URL stored in DB (clean, no token)
    $cleanUrl = "https://github.com/{$owner}/{$repoSlug}";

    // Set remote via fenor-git
    $cmd    = 'sudo /usr/local/bin/fenor-git ' . escapeshellarg($name)
            . ' set-remote ' . escapeshellarg($remoteUrl) . ' 2>&1';
    $output = shell_exec($cmd) ?? '';
    $ok     = strpos($output, '✓') !== false && strpos($output, '!') === false;

    if ($ok) {
        // Persist clean URL to DB
        try {
            $db   = fenorDB();
            $stmt = $db->prepare('UPDATE fenor_apps SET github_repo = ? WHERE name = ?');
            $stmt->execute([$cleanUrl, $name]);
        } catch (Throwable $e) { /* non-fatal */ }

        echo json_encode([
            'success'     => true,
            'browser_url' => "https://github.com/{$owner}/{$repoSlug}",
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => trim($output) ?: 'Failed to set remote']);
    }
    exit;
}

// ── status / pull / push ───────────────────────────────────────────────────
$cmd    = 'sudo /usr/local/bin/fenor-git '
        . escapeshellarg($name) . ' '
        . escapeshellarg($action) . ' '
        . escapeshellarg($message) . ' 2>&1';
$output = shell_exec($cmd) ?? '';

if ($action === 'status') {
    $parsed = [];
    foreach (explode("\n", trim($output)) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $parsed[trim($k)] = trim($v);
        }
    }
    echo json_encode(['success' => true, 'status' => $parsed]);
    exit;
}

// Detecta sucesso pelo marcador final — independente do idioma das mensagens
if ($action === 'push') {
    $success = strpos($output, '✓ Enviado para o GitHub com sucesso') !== false
            || strpos($output, '✓ O código já está atualizado no GitHub') !== false
            // legado (mensagens antigas em inglês)
            || strpos($output, '✓ Pushed to GitHub successfully') !== false
            || strpos($output, '✓ Code is already up to date') !== false;
} else {
    // pull: sucesso se tiver ✓ final e nenhum ! de erro
    $success = (strpos($output, '✓ Código atualizado com sucesso') !== false
             || strpos($output, '✓ Seu código já está atualizado') !== false)
            && strpos($output, '!') === false;
}

echo json_encode(['success' => $success, 'output' => $output]);
