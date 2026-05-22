<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config/db.php';
$config  = require  __DIR__ . '/config/config.php';
$success = '';
$error   = '';

// Campos editáveis pelo painel
$fields = [
    'BASE_DOMAIN'  => ['label' => 'Domínio base',      'type' => 'text',     'placeholder' => 'fenor.ia.br'],
    'ADMIN_EMAIL'  => ['label' => 'Email do admin',    'type' => 'email',    'placeholder' => 'seu@email.com'],
    'TERMINAL_URL' => ['label' => 'URL do terminal',   'type' => 'url',      'placeholder' => 'https://terminal.fenor.ia.br'],
    'CF_TOKEN'     => ['label' => 'CF API Token',      'type' => 'password', 'placeholder' => ''],
    'CF_ZONE_ID'   => ['label' => 'CF Zone ID',        'type' => 'text',     'placeholder' => ''],
    'CF_TUNNEL_ID' => ['label' => 'CF Tunnel ID',      'type' => 'text',     'placeholder' => ''],
    'APPS_PATH'    => ['label' => 'Diretório de apps', 'type' => 'text',     'placeholder' => '/var/www'],
    'GITHUB_TOKEN'        => ['label' => 'GitHub Token',        'type' => 'password', 'placeholder' => 'ghp_...'],
    'GITHUB_ORG'          => ['label' => 'GitHub Org/User',     'type' => 'text',     'placeholder' => 'minha-org'],
    'ANTHROPIC_API_KEY'   => ['label' => 'Anthropic API Key',   'type' => 'password', 'placeholder' => 'sk-ant-...'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'settings') {
        try {
            foreach ($fields as $key => $meta) {
                if (in_array($key, ['CF_TOKEN', 'GITHUB_TOKEN', 'ANTHROPIC_API_KEY']) && empty($_POST[$key])) continue;
                $val = trim($_POST[$key] ?? '');
                if ($val !== '') saveSetting($key, $val);
            }
            $success = 'Configurações salvas.';
            $config  = config(); // recarrega

            // Atualiza /etc/fenor/ttyd.env e reinicia serviços ttyd
            if (!empty($_POST['ANTHROPIC_API_KEY'])) {
                $apiKey = trim($_POST['ANTHROPIC_API_KEY']);
                @file_put_contents('/etc/fenor/ttyd.env', "ANTHROPIC_API_KEY=$apiKey\n");
                shell_exec('sudo systemctl restart "ttyd-*.service" 2>/dev/null || sudo systemctl restart $(systemctl list-units --type=service --plain --no-legend | grep "ttyd-" | awk \'{print $1}\') 2>/dev/null &');
            }
        } catch (Throwable $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }

    if ($action === 'password') {
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 8) {
            $error = 'Senha deve ter pelo menos 8 caracteres.';
        } elseif ($new !== $conf) {
            $error = 'As senhas não coincidem.';
        } else {
            saveSetting('ADMIN_PASSWORD_HASH', password_hash($new, PASSWORD_BCRYPT));
            $success = 'Senha atualizada.';
        }
    }
}

$settings = fenorSettings();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configurações — Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body>
<div class="layout">
  <?php $pageTitle = 'Configurações'; include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>

    <div class="content">

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- PLATAFORMA -->
      <div class="table-wrap" style="margin-bottom:1.25rem;">
        <div class="table-head">
          <h2>Plataforma</h2>
          <span style="font-size:.75rem;color:var(--muted);">
            Banco: <?= htmlspecialchars($config['db_driver']) ?>
          </span>
        </div>
        <div style="padding:1.25rem;">
          <form method="POST">
            <input type="hidden" name="_action" value="settings">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
              <?php foreach ($fields as $key => $meta):
                $val = $settings[$key] ?? '';
                $isPass = $meta['type'] === 'password';
              ?>
              <div class="field">
                <label><?= $meta['label'] ?></label>
                <input
                  type="<?= $meta['type'] ?>"
                  name="<?= $key ?>"
                  placeholder="<?= htmlspecialchars($isPass ? '(não alterado)' : $meta['placeholder']) ?>"
                  value="<?= $isPass ? '' : htmlspecialchars($val) ?>"
                  <?= $isPass ? '' : '' ?>
                  autocomplete="off">
                <?php if ($isPass && $val): ?>
                  <small>Token configurado — deixe em branco para manter</small>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:1rem;">
              <button type="submit" class="btn btn-primary">Salvar configurações</button>
            </div>
          </form>
        </div>
      </div>

      <!-- STATUS DO AMBIENTE -->
      <div class="table-wrap" style="margin-bottom:1.25rem;">
        <div class="table-head"><h2>Status</h2></div>
        <table>
          <?php
          $checks = [
              'Domínio base'      => !empty($settings['BASE_DOMAIN']),
              'Cloudflare Token'  => !empty($settings['CF_TOKEN']),
              'Cloudflare Zone'   => !empty($settings['CF_ZONE_ID']),
              'Cloudflare Tunnel' => !empty($settings['CF_TUNNEL_ID']),
              'Terminal URL'      => !empty($settings['TERMINAL_URL']),
              'GitHub Token'      => !empty($settings['GITHUB_TOKEN']),
              'GitHub Org/User'   => !empty($settings['GITHUB_ORG']),
              'Anthropic API Key' => !empty($settings['ANTHROPIC_API_KEY']),
          ];
          foreach ($checks as $label => $ok): ?>
          <tr>
            <td style="width:200px;"><?= $label ?></td>
            <td>
              <span class="badge <?= $ok ? 'badge-ok' : 'badge-off' ?>">
                <?= $ok ? 'Configurado' : 'Pendente' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <!-- ALTERAR SENHA -->
      <div class="table-wrap">
        <div class="table-head"><h2>Alterar senha</h2></div>
        <div style="padding:1.25rem;max-width:380px;">
          <form method="POST">
            <input type="hidden" name="_action" value="password">
            <div class="field">
              <label>Nova senha</label>
              <input type="password" name="new_password" required minlength="8" placeholder="Mínimo 8 caracteres">
            </div>
            <div class="field">
              <label>Confirmar senha</label>
              <input type="password" name="confirm_password" required placeholder="Repita a senha">
            </div>
            <button type="submit" class="btn btn-primary">Atualizar senha</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
