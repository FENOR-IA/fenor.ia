<?php
/**
 * Fenor Studio — GitHub API proxy
 *
 * Works with GITHUB_TOKEN (Personal Access Token). Actions:
 *   create-repo     — create a new repo under org/user
 *   add-deploy-key  — read pubkey from disk, add to repo
 *   list-repos      — list repos (for wizard autocomplete)
 *   whoami          — return authenticated user info
 */
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

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $data['action'] ?? '';

$settings = fenorSettings();
$token    = trim($settings['GITHUB_TOKEN'] ?? '');
$org      = trim($settings['GITHUB_ORG']   ?? '');

if (!$token) {
    echo json_encode(['success' => false, 'error' => 'GitHub not connected. Go to Settings → GitHub.']);
    exit;
}

// ── GitHub API helper ──────────────────────────────────────────────────────
function ghApi(string $method, string $path, array $payload = []): array {
    global $token;
    $url = "https://api.github.com$path";
    $headers = [
        "Authorization: Bearer $token",
        "Accept: application/vnd.github+json",
        "X-GitHub-Api-Version: 2022-11-28",
        "User-Agent: Fenor-Studio/1.0",
        "Content-Type: application/json",
    ];
    $opts = [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'timeout'       => 15,
        'ignore_errors' => true,
    ];
    if ($payload) $opts['content'] = json_encode($payload);
    $ctx  = stream_context_create(['http' => $opts]);
    $raw  = @file_get_contents($url, false, $ctx);
    $code = 200;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#HTTP/[\d.]+ (\d+)#i', $h, $m)) $code = (int)$m[1];
    }
    return ['code' => $code, 'body' => json_decode($raw ?: '{}', true) ?: []];
}

// Detect whether $org is a GitHub org (vs personal account)
function ghIsOrg(string $name): bool {
    $r = ghApi('GET', "/orgs/$name");
    return $r['code'] === 200;
}

// Normalise "owner/repo" from SSH URL, HTTPS URL, or bare name
function ghRepoPath(string $repo, string $org): string {
    // git@github.com:owner/repo.git or https://github.com/owner/repo
    if (preg_match('/github\.com[:\\/]+(.+?)(?:\.git)?$/', $repo, $m)) {
        return $m[1];
    }
    // Already "owner/repo"
    if (strpos($repo, '/') !== false) return rtrim($repo, '.git');
    // Bare name — prepend org
    return $org ? "$org/$repo" : $repo;
}

// ── Dispatch ───────────────────────────────────────────────────────────────
switch ($action) {

    // ── WHOAMI ──────────────────────────────────────────────────────────────
    case 'whoami':
        $r = ghApi('GET', '/user');
        echo json_encode([
            'success' => $r['code'] === 200,
            'login'   => $r['body']['login'] ?? '',
            'name'    => $r['body']['name']  ?? '',
        ]);
        break;

    // ── LIST REPOS ──────────────────────────────────────────────────────────
    case 'list-repos':
        $user = trim($settings['GITHUB_USER'] ?? '');
        if ($org && ghIsOrg($org)) {
            // Real GitHub organization
            $r = ghApi('GET', "/orgs/$org/repos?per_page=100&sort=updated&type=all");
        } elseif ($org && $org !== $user) {
            // Another user's public repos
            $r = ghApi('GET', "/users/$org/repos?per_page=100&sort=updated&type=owner");
        } else {
            // Authenticated user's own repos (includes private) — always use /user/repos
            $r = ghApi('GET', '/user/repos?per_page=100&sort=updated&affiliation=owner');
        }
        $repos = array_map(fn($row) => [
            'name'    => $row['name'],
            'full'    => $row['full_name'],
            'ssh_url' => $row['ssh_url'],
            'private' => $row['private'],
        ], is_array($r['body']) ? $r['body'] : []);
        echo json_encode(['success' => true, 'repos' => $repos]);
        break;

    // ── CREATE REPO ─────────────────────────────────────────────────────────
    case 'create-repo':
        $name    = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['name'] ?? '')));
        $desc    = trim($data['description'] ?? '');
        $private = ($data['private'] ?? true) !== false;

        if (!$name) { echo json_encode(['success' => false, 'error' => 'Invalid repo name']); break; }

        $payload = [
            'name'        => $name,
            'description' => $desc,
            'private'     => $private,
            'auto_init'   => false,
        ];

        if ($org && ghIsOrg($org)) {
            $r = ghApi('POST', "/orgs/$org/repos", $payload);
        } else {
            $r = ghApi('POST', '/user/repos', $payload);
        }

        if ($r['code'] === 201) {
            echo json_encode([
                'success'    => true,
                'ssh_url'    => $r['body']['ssh_url']   ?? '',
                'full_name'  => $r['body']['full_name'] ?? '',
            ]);
        } elseif ($r['code'] === 422) {
            // Repo already exists — return its SSH URL
            $owner  = $org ?: ($settings['GITHUB_USER'] ?? '');
            $sshUrl = $owner ? "git@github.com:$owner/$name.git" : '';
            echo json_encode([
                'success'        => true,
                'ssh_url'        => $sshUrl,
                'full_name'      => "$owner/$name",
                'already_existed' => true,
            ]);
        } else {
            $msg = $r['body']['message'] ?? 'GitHub API error';
            echo json_encode(['success' => false, 'error' => $msg]);
        }
        break;

    // ── ADD DEPLOY KEY ──────────────────────────────────────────────────────
    case 'add-deploy-key':
        $appName = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($data['app'] ?? '')));
        $repo    = trim($data['repo'] ?? '');

        if (!$appName) { echo json_encode(['success' => false, 'error' => 'Missing app name']); break; }
        if (!$repo)    { echo json_encode(['success' => false, 'error' => 'Missing repo']); break; }

        $repoPath = ghRepoPath($repo, $org);

        // Read SSH public key from disk
        $appSafe = str_replace('-', '_', $appName);
        $keyFile = "/etc/fenor/keys/{$appSafe}.pub";

        if (!file_exists($keyFile)) {
            echo json_encode(['success' => false, 'error' => "Deploy key not found: $keyFile — provision the app first."]);
            break;
        }

        $pubKey = trim(file_get_contents($keyFile));
        if (!$pubKey) {
            echo json_encode(['success' => false, 'error' => 'Public key file is empty.']);
            break;
        }

        $r = ghApi('POST', "/repos/$repoPath/keys", [
            'title'     => "Fenor Studio — $appName",
            'key'       => $pubKey,
            'read_only' => false,
        ]);

        if ($r['code'] === 201) {
            echo json_encode(['success' => true]);
        } elseif ($r['code'] === 422) {
            // Key already on this repo — not an error
            $errs = $r['body']['errors'] ?? [];
            $alreadyAdded = false;
            foreach ($errs as $e) {
                if (($e['message'] ?? '') === 'key is already in use') { $alreadyAdded = true; break; }
            }
            echo json_encode(['success' => true, 'already_existed' => $alreadyAdded]);
        } else {
            $msg = $r['body']['message'] ?? 'GitHub API error';
            echo json_encode(['success' => false, 'error' => $msg]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
}
