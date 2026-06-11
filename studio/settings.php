<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config/db.php';
$config  = require  __DIR__ . '/config/config.php';
$success = '';
$error   = '';

$_fenorVersion     = trim(@file_get_contents('/etc/fenor/version') ?: '—');
$_templatesIndex   = '/etc/fenor/templates/index.json';
$_templates        = file_exists($_templatesIndex)
    ? (json_decode(file_get_contents($_templatesIndex), true) ?: [])
    : [];

// Platform settings (excludes GitHub — managed in dedicated section)
$fields = [
    'BASE_DOMAIN'        => ['label' => 'Base domain',       'type' => 'text',     'placeholder' => 'fenor.ia.br'],
    'ADMIN_EMAIL'        => ['label' => 'Admin email',       'type' => 'email',    'placeholder' => 'you@email.com'],
    'TERMINAL_URL'       => ['label' => 'Terminal URL',      'type' => 'url',      'placeholder' => 'https://terminal.fenor.ia.br'],
    'CF_TOKEN'           => ['label' => 'CF API Token',      'type' => 'password', 'placeholder' => ''],
    'CF_ZONE_ID'         => ['label' => 'CF Zone ID',        'type' => 'text',     'placeholder' => ''],
    'CF_TUNNEL_ID'       => ['label' => 'CF Tunnel ID',      'type' => 'text',     'placeholder' => ''],
    'APPS_PATH'          => ['label' => 'Apps directory',    'type' => 'text',     'placeholder' => '/var/www'],
    'ANTHROPIC_API_KEY'  => ['label' => 'Anthropic API Key', 'type' => 'password', 'placeholder' => 'sk-ant-...'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'settings') {
        try {
            foreach ($fields as $key => $meta) {
                if ($meta['type'] === 'password' && empty($_POST[$key])) continue;
                $val = trim($_POST[$key] ?? '');
                if ($val !== '') saveSetting($key, $val);
            }
            $success = 'Settings saved.';
            $config  = config();
            // Update /etc/fenor/ttyd.env and restart ttyd services
            if (!empty($_POST['ANTHROPIC_API_KEY'])) {
                $apiKey = trim($_POST['ANTHROPIC_API_KEY']);
                @file_put_contents('/etc/fenor/ttyd.env', "ANTHROPIC_API_KEY=$apiKey\n");
                shell_exec('sudo systemctl restart "ttyd-*.service" 2>/dev/null &');
            }
        } catch (Throwable $e) {
            $error = 'Error saving: ' . $e->getMessage();
        }
    }

    if ($action === 'github-pat') {
        // Personal Access Token — validate and save; username fetched automatically
        try {
            $pat = trim($_POST['GITHUB_TOKEN_PAT'] ?? '');
            if (!$pat) { $error = 'Token cannot be empty.'; }
            else {
                // Verify token and fetch username via GitHub API
                $ctx = stream_context_create(['http' => [
                    'method'        => 'GET',
                    'header'        => implode("\r\n", [
                        "Authorization: Bearer $pat",
                        "Accept: application/vnd.github+json",
                        "X-GitHub-Api-Version: 2022-11-28",
                        "User-Agent: Fenor-Studio/1.0",
                    ]),
                    'timeout'       => 8,
                    'ignore_errors' => true,
                ]]);
                $raw  = @file_get_contents('https://api.github.com/user', false, $ctx);
                $info = json_decode($raw ?: '{}', true) ?: [];
                $code = 200;
                foreach ($http_response_header ?? [] as $h) {
                    if (preg_match('#HTTP/[\d.]+ (\d+)#i', $h, $m)) $code = (int)$m[1];
                }
                if ($code !== 200) {
                    $error = 'Invalid token or GitHub unreachable (HTTP ' . $code . ').';
                } else {
                    $login = $info['login'] ?? '';
                    saveSetting('GITHUB_TOKEN',       $pat);
                    saveSetting('GITHUB_AUTH_METHOD', 'pat');
                    if ($login) {
                        saveSetting('GITHUB_USER', $login);
                        saveSetting('GITHUB_ORG',  $login); // default owner = personal account
                    }
                    $success = 'GitHub connected' . ($login ? " as @$login" : '') . ' via Personal Access Token.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    if ($action === 'github-disconnect') {
        try {
            saveSetting('GITHUB_TOKEN',       '');
            saveSetting('GITHUB_USER',        '');
            saveSetting('GITHUB_AUTH_METHOD', '');
            $success = 'GitHub disconnected.';
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    if ($action === 'password') {
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($new !== $conf) {
            $error = 'Passwords do not match.';
        } else {
            saveSetting('ADMIN_PASSWORD_HASH', password_hash($new, PASSWORD_BCRYPT));
            $success = 'Password updated.';
        }
    }
}

$settings    = fenorSettings();

$githubUser  = trim($settings['GITHUB_USER']  ?? '');
$githubToken = trim($settings['GITHUB_TOKEN'] ?? '');
$githubOrg   = trim($settings['GITHUB_ORG']   ?? '');
$isConnected = !empty($githubToken) && !empty($githubUser);

$groups = [
    [
        'title'  => 'Geral',
        'icon'   => 'settings-2',
        'keys'   => ['BASE_DOMAIN', 'ADMIN_EMAIL', 'TERMINAL_URL', 'APPS_PATH'],
        'checks' => ['BASE_DOMAIN', 'TERMINAL_URL'],
    ],
    [
        'title'  => 'Cloudflare',
        'icon'   => 'cloud',
        'keys'   => ['CF_TOKEN', 'CF_ZONE_ID', 'CF_TUNNEL_ID'],
        'checks' => ['CF_TOKEN', 'CF_ZONE_ID', 'CF_TUNNEL_ID'],
    ],
    [
        'title'  => 'Claude',
        'icon'   => 'bot',
        'keys'   => ['ANTHROPIC_API_KEY'],
        'checks' => ['ANTHROPIC_API_KEY'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en" data-lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings — Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="assets/js/studio.js"></script>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    .gh-connected-badge {
      display: inline-flex; align-items: center; gap: .6rem;
      background: #e8f5e9; color: #2e7d32;
      border: 1px solid #c8e6c9; border-radius: 8px;
      padding: .55rem 1rem; font-size: .875rem; font-weight: 600;
    }
    .gh-connected-badge svg { width: 16px; height: 16px; stroke: #2e7d32; }

    .gh-section-row {
      display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
      padding: 1.25rem;
    }

  </style>
</head>
<body>
<div class="layout">
  <?php $pageTitle = 'Settings'; include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>

    <div class="content">

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php foreach ($groups as $group): ?>
      <!-- <?= strtoupper($group['title']) ?> -->
      <div class="table-wrap" style="margin-bottom:1.25rem;">
        <div class="table-head">
          <h2 style="display:flex;align-items:center;gap:.45rem;">
            <i data-lucide="<?= $group['icon'] ?>" style="width:15px;height:15px;"></i>
            <?= $group['title'] ?>
          </h2>
          <?php if ($group['title'] === 'Geral'): ?>
          <span style="font-size:.75rem;color:var(--muted);">
            DB: <?= htmlspecialchars($config['db_driver']) ?>
          </span>
          <?php endif; ?>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="settings">
          <table>
            <thead>
              <tr>
                <th style="width:170px;">Campo</th>
                <th>Valor</th>
                <th style="width:120px;text-align:right;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($group['keys'] as $key):
                $meta      = $fields[$key];
                $val       = $settings[$key] ?? '';
                $isPass    = $meta['type'] === 'password';
                $hasStatus = in_array($key, $group['checks']);
                $ok        = $hasStatus ? !empty($val) : null;
              ?>
              <tr>
                <td style="font-weight:500;"><?= $meta['label'] ?></td>
                <td style="padding-top:.45rem;padding-bottom:.45rem;">
                  <input
                    type="<?= $meta['type'] ?>"
                    name="<?= $key ?>"
                    placeholder="<?= htmlspecialchars($isPass ? '(unchanged)' : $meta['placeholder']) ?>"
                    value="<?= $isPass ? '' : htmlspecialchars($val) ?>"
                    autocomplete="off"
                    style="width:100%;box-sizing:border-box;border:1px solid var(--rule);border-radius:6px;padding:.45rem .65rem;font-size:.8125rem;font-family:inherit;background:#fff;color:var(--ink);outline:none;">
                  <?php if ($isPass && $val): ?>
                    <small style="display:block;font-size:.72rem;color:var(--muted);margin-top:.2rem;"
                           data-pt="Configurado — deixe em branco para manter"
                           data-en="Configured — leave blank to keep">
                      Configurado — deixe em branco para manter
                    </small>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;">
                  <?php if ($hasStatus): ?>
                    <span class="badge <?= $ok ? 'badge-ok' : 'badge-off' ?>">
                      <?= $ok ? 'Configurado' : 'Pendente' ?>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div style="padding:.9rem 1.25rem;border-top:1px solid var(--rule);">
            <button type="submit" class="btn btn-primary"
                    data-pt="Salvar <?= $group['title'] ?>"
                    data-en="Save <?= $group['title'] ?>">Salvar <?= $group['title'] ?></button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>

      <!-- ── GITHUB ─────────────────────────────────────────── -->
      <?php
        $authMethod  = trim($settings['GITHUB_AUTH_METHOD'] ?? '');
        $methodLabel = $authMethod === 'pat' ? 'Personal Access Token' : '';
      ?>
      <div class="table-wrap" id="github" style="margin-bottom:1.25rem;">
        <div class="table-head">
          <h2 style="display:flex;align-items:center;gap:.5rem;">
            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:var(--ink);" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577
                0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.335-1.755-1.335-1.755
                -1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305
                3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93
                0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176
                0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405
                1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23
                .645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22
                0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22
                0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57
                C20.565 21.795 24 17.295 24 12c0-6.63-5.37-12-12-12"/>
            </svg>
            GitHub
          </h2>
          <?php if ($isConnected): ?>
            <span class="badge badge-ok" style="font-size:.7rem;">
              <?= $githubUser ? '@' . htmlspecialchars($githubUser) : 'Connected' ?>
              <?= $methodLabel ? "· $methodLabel" : '' ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if ($isConnected): ?>
        <div class="gh-section-row" style="gap:.75rem;">
          <div class="gh-connected-badge">
            <i data-lucide="check-circle-2"></i>
            <?= $githubUser ? 'Connected as @' . htmlspecialchars($githubUser) : 'GitHub connected' ?>
            <?php if ($methodLabel): ?>
              <span style="font-size:.72rem;opacity:.7;font-weight:400;">via <?= $methodLabel ?></span>
            <?php endif; ?>
          </div>
          <form method="POST" style="margin:0;" onsubmit="return confirm('Disconnect GitHub?');">
            <input type="hidden" name="_action" value="github-disconnect">
            <button type="submit" class="btn btn-secondary btn-xs">
              <i data-lucide="log-out" style="width:13px;height:13px;"></i>
              Disconnect
            </button>
          </form>
        </div>
        <div style="padding:.25rem 1.25rem 1.25rem;">
          <form method="POST">
            <input type="hidden" name="_action" value="github-app">
            <div class="field" style="max-width:320px;">
              <label>Default GitHub Org / User</label>
              <input type="text" name="GITHUB_ORG"
                     value="<?= htmlspecialchars($githubOrg) ?>"
                     placeholder="my-org or my-username">
              <small>Repos will be created under this org/user.</small>
            </div>
            <button type="submit" class="btn btn-secondary btn-xs">Save</button>
          </form>
        </div>

        <?php else: ?>
        <div style="padding:1.25rem;max-width:420px;">
          <form method="POST">
            <input type="hidden" name="_action" value="github-pat">
            <div class="field">
              <label>
                Personal Access Token
                <a href="https://github.com/settings/tokens/new?scopes=repo,read:org&description=Fenor+Studio"
                   target="_blank"
                   style="color:var(--warm);font-weight:500;font-size:.72rem;margin-left:.4rem;text-decoration:none;">
                  Generate on GitHub ↗
                </a>
              </label>
              <input type="password" name="GITHUB_TOKEN_PAT"
                     placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" autocomplete="off"
                     style="font-family:'Geist Mono',monospace;font-size:.85rem;">
              <small>Scopes needed: <code>repo</code>, <code>read:org</code></small>
            </div>
            <button type="submit" class="btn btn-primary">
              Save token &amp; connect
            </button>
          </form>
        </div>
        <?php endif; ?>

      </div>

      <!-- ── CHANGE PASSWORD ────────────────────────────────── -->
      <div class="table-wrap">
        <div class="table-head">
          <h2 data-pt="Alterar senha" data-en="Change password">Alterar senha</h2>
        </div>
        <div style="padding:1.25rem;max-width:380px;">
          <form method="POST">
            <input type="hidden" name="_action" value="password">
            <div class="field">
              <label data-pt="Nova senha" data-en="New password">Nova senha</label>
              <input type="password" name="new_password" required minlength="8"
                     data-pt="Mínimo 8 caracteres" data-en="Minimum 8 characters"
                     placeholder="Mínimo 8 caracteres">
            </div>
            <div class="field">
              <label data-pt="Confirmar senha" data-en="Confirm password">Confirmar senha</label>
              <input type="password" name="confirm_password" required
                     data-pt="Repita a senha" data-en="Repeat the password"
                     placeholder="Repita a senha">
            </div>
            <button type="submit" class="btn btn-primary"
                    data-pt="Atualizar senha" data-en="Update password">Atualizar senha</button>
          </form>
        </div>
      </div>

      <!-- Sistema -->
      <div style="border:1px solid var(--rule);border-radius:12px;padding:1.25rem;margin-top:1.5rem;">
        <h2 style="font-size:.95rem;font-weight:600;margin:0 0 1rem;display:flex;align-items:center;gap:.45rem;">
          <i data-lucide="layers" style="width:16px;height:16px;stroke:var(--warm);"></i>
          Sistema
        </h2>

        <div style="display:flex;flex-direction:column;gap:.6rem;margin-bottom:1.25rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .875rem;background:var(--cream);border-radius:8px;">
            <span style="font-size:.8rem;color:var(--muted);">Fenor</span>
            <span style="font-family:'Geist Mono',monospace;font-size:.82rem;font-weight:600;">
              v<?= htmlspecialchars($_fenorVersion) ?>
            </span>
          </div>
          <?php foreach ($_templates as $tpl): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .875rem;background:var(--cream);border-radius:8px;">
            <span style="font-size:.8rem;color:var(--muted);">Template: <?= htmlspecialchars($tpl['label'] ?? $tpl['name']) ?></span>
            <span style="font-family:'Geist Mono',monospace;font-size:.82rem;font-weight:600;">
              v<?= htmlspecialchars($tpl['version'] ?? '—') ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="background:var(--ink);color:#c9d1d9;padding:.75rem 1rem;border-radius:8px;font-family:'Geist Mono',monospace;font-size:.8rem;">
          curl -fsSL https://fenor.ia.br/update.sh | bash
        </div>
        <p style="font-size:.75rem;color:var(--muted);margin:.6rem 0 0;line-height:1.6;">
          Atualiza scripts, studio e templates oficiais. Ou via CLI: <code style="background:var(--cream);padding:.1rem .35rem;border-radius:4px;">fenor update</code>
        </p>
      </div>

    </div>
  </div>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>
