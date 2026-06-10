<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$config = require __DIR__ . '/config/config.php';
require  __DIR__ . '/config/helpers.php';

// Apps in database
$dbApps = [];
try {
    foreach (fenorDB()->query('SELECT * FROM fenor_apps ORDER BY created_at DESC')->fetchAll() as $row) {
        $dbApps[$row['name']] = $row;
    }
} catch (Throwable $e) { $dbApps = []; }

$appsJson = json_encode($dbApps, JSON_HEX_TAG | JSON_HEX_AMP);

// Apps in filesystem
$fsApps = [];
foreach (loadApps($config['apps_path']) as $app) {
    $fsApps[$app['name']] = $app;
}

// Merge DB + filesystem
$apps = [];
foreach ($dbApps as $name => $meta) {
    $apps[] = [
        'name'        => $name,
        'description' => $meta['description'] ?? '',
        'github_repo' => $meta['github_repo'] ?? '',
        'language'    => $meta['language']    ?? 'pt',
        'status'      => $meta['status']      ?? 'registered',
        'envs'        => $fsApps[$name]['envs'] ?? [],
    ];
}
foreach ($fsApps as $name => $fsApp) {
    if (!isset($dbApps[$name])) {
        $apps[] = array_merge($fsApp, ['description' => '', 'github_repo' => '', 'language' => 'pt', 'status' => 'provisioned']);
    }
}

$total  = count($apps);
$devCnt = count(array_filter($apps, fn($a) => isset($a['envs']['dev'])));
$hmlCnt = count(array_filter($apps, fn($a) => isset($a['envs']['hml'])));
$prdCnt = count(array_filter($apps, fn($a) => isset($a['envs']['prd'])));

require_once __DIR__ . '/config/db.php';
$_ghSettings      = fenorSettings();
$_hasGithubToken  = !empty(trim($_ghSettings['GITHUB_TOKEN'] ?? ''));
$_ghOwner         = trim($_ghSettings['GITHUB_ORG'] ?? '') ?: trim($_ghSettings['GITHUB_USER'] ?? '');

// Templates disponíveis
$_templatesIndex = '/etc/fenor/templates/index.json';
$_templates = file_exists($_templatesIndex)
    ? (json_decode(file_get_contents($_templatesIndex), true) ?: [])
    : [['name'=>'base','label'=>'Base','description'=>'Login e dashboard.'],['name'=>'crm','label'=>'CRM','description'=>'Clientes e financeiro.']];
?>
<!DOCTYPE html>
<html lang="en" data-lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    /* Template cards */
    .tpl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:.6rem; margin-top:.5rem; }
    .tpl-card {
      display:flex; flex-direction:column; gap:.3rem;
      padding:.875rem; border:2px solid var(--rule); border-radius:10px;
      cursor:pointer; background:#fff; transition:all .15s; user-select:none;
    }
    .tpl-card input { display:none; }
    .tpl-card strong { font-size:.875rem; color:var(--ink); display:flex; align-items:center; gap:.4rem; }
    .tpl-card strong svg { width:15px; height:15px; stroke:var(--muted); flex-shrink:0; transition:stroke .15s; }
    .tpl-card small { font-size:.75rem; color:var(--muted); line-height:1.5; }
    .tpl-card:hover { border-color:var(--ember); }
    .tpl-card:hover strong svg { stroke:var(--ember); }
    .tpl-card.sel { border-color:var(--warm); background:#fff8f5; }
    .tpl-card.sel strong svg { stroke:var(--warm); }

    /* Wizard progress */
    .wiz-progress { display:flex; align-items:center; gap:0; margin-bottom:1.5rem; }
    .wiz-step { display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:var(--muted); }
    .wiz-step .wiz-num {
      width:26px; height:26px; border-radius:50%;
      background:var(--rule); color:var(--muted);
      display:flex; align-items:center; justify-content:center;
      font-size:.75rem; font-weight:700; flex-shrink:0; transition:background .2s, color .2s;
    }
    .wiz-step.active .wiz-num  { background:var(--warm); color:#fff; }
    .wiz-step.done  .wiz-num   { background:#d4edda; color:var(--success); }
    .wiz-step.active .wiz-label { color:var(--ink); font-weight:500; }
    .wiz-connector { flex:1; height:2px; background:var(--rule); margin:0 .5rem; transition:background .2s; }
    .wiz-connector.done { background:var(--warm); }

    /* Creation progress */
    @keyframes spin    { to { transform: rotate(360deg); } }
    @keyframes piIn    { to { opacity:1; transform:translateY(0); } }
    @keyframes scaleIn { to { opacity:1; transform:scale(1); } }
    #modal-progress { margin-top:1rem; }
    .prog-spinner { display:flex; flex-direction:column; align-items:center; gap:.875rem; padding:2rem 0; color:var(--muted); }
    .prog-spinner svg { animation: spin .9s linear infinite; }
    .prog-spinner span { font-size:.875rem; }
    #progress-items { padding:.25rem 0; }
    .pi-item {
      display:flex; align-items:center; gap:.75rem; padding:.45rem 0;
      border-bottom:1px solid var(--rule); font-size:.8125rem;
      opacity:0; transform:translateY(5px); animation: piIn .22s ease forwards;
    }
    .pi-item:last-child { border-bottom:none; }
    .pi-icon { width:28px; height:28px; border-radius:7px; flex-shrink:0; background:var(--cream); display:flex; align-items:center; justify-content:center; }
    .pi-icon svg { width:14px; height:14px; stroke:var(--muted); }
    .pi-label { flex:1; color:var(--ink); }
    .pi-check svg { width:16px; height:16px; stroke:var(--success); }
    .prog-success { text-align:center; padding:1.25rem 0 .5rem; opacity:0; transform:scale(.94); animation: scaleIn .3s .1s ease forwards; }
    .prog-success .check-circle { width:52px; height:52px; border-radius:50%; background:#e8f5e9; margin:0 auto .875rem; display:flex; align-items:center; justify-content:center; }
    .prog-success .check-circle svg { width:26px; height:26px; stroke:var(--success); }
    .prog-success strong { display:block; font-size:.95rem; margin-bottom:.3rem; }
    .prog-success .prog-url { font-size:.8rem; color:var(--warm); text-decoration:none; display:inline-flex; align-items:center; gap:.3rem; }
    .prog-success .prog-url:hover { text-decoration:underline; }
  </style>
</head>
<body>
<div class="layout">
  <?php $pageTitle = 'Apps'; include __DIR__ . '/partials/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <div class="content">

      <!-- Stats -->
      <div class="stats-grid" style="margin-bottom:1.5rem;">
        <div class="stat-card">
          <div class="label">Total</div>
          <div class="value"><?= $total ?></div>
          <div class="sub" data-pt="apps cadastrados" data-en="registered apps">apps cadastrados</div>
        </div>
        <div class="stat-card">
          <div class="label">DEV</div>
          <div class="value"><?= $devCnt ?></div>
          <div class="sub" data-pt="em desenvolvimento" data-en="in development">em desenvolvimento</div>
        </div>
        <div class="stat-card">
          <div class="label">HML</div>
          <div class="value"><?= $hmlCnt ?></div>
          <div class="sub" data-pt="em homologação" data-en="in staging">em homologação</div>
        </div>
        <div class="stat-card">
          <div class="label">PRD</div>
          <div class="value"><?= $prdCnt ?></div>
          <div class="sub" data-pt="em produção" data-en="in production">em produção</div>
        </div>
      </div>

      <!-- Apps table -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <p style="color:var(--muted);font-size:.875rem;"><?= $total ?> app(s)</p>
        <button class="btn btn-primary" onclick="openModal('new')" style="display:inline-flex;align-items:center;gap:.4rem;">
          <i data-lucide="plus-circle" style="width:16px;height:16px;"></i>
          <span data-pt="Novo app" data-en="New app">Novo app</span>
        </button>
      </div>

      <?php if (empty($apps)): ?>
        <div style="text-align:center;padding:4rem;color:var(--muted);">
          <p style="font-size:1.1rem;margin-bottom:1rem;" data-pt="Nenhum app criado ainda." data-en="No apps created yet.">Nenhum app criado ainda.</p>
          <button class="btn btn-primary" onclick="openModal('new')"
                  data-pt="Criar primeiro app →" data-en="Create first app →">Criar primeiro app →</button>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>App</th>
              <th style="text-align:center;width:110px;" data-pt="Desenvolvimento" data-en="Development">Desenvolvimento</th>
              <th style="text-align:center;width:110px;" data-pt="Homologação" data-en="Staging">Homologação</th>
              <th style="text-align:center;width:110px;" data-pt="Produção" data-en="Production">Produção</th>
              <th style="text-align:center;width:70px;">Terminal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($apps as $app):
              $name        = $app['name'];
              $desc        = $app['description'] ?? '';
              $ghRepo      = $app['github_repo'] ?: ($app['envs']['dev']['github_repo'] ?? '');
              $ghUrl       = $ghRepo ? 'https://github.com/' . preg_replace('/^git@github\.com:|\.git$/', '', str_replace(':', '/', $ghRepo)) : '';
              $provisioned = ($app['status'] ?? 'registered') === 'provisioned';
              $devUrl      = $app['envs']['dev']['url']      ?? '';
              $hmlUrl      = $app['envs']['hml']['url']      ?? '';
              $prdUrl      = $app['envs']['prd']['url']      ?? '';
              $termUrl     = $app['envs']['dev']['terminal'] ?? '';
              $hasDev      = !empty($devUrl);
              $hasHml      = !empty($hmlUrl);
              $hasPrd      = !empty($prdUrl);
              $appLang     = $app['language'] ?? 'pt';
              $n           = htmlspecialchars($name);
            ?>
            <tr>
              <td>
                <span onclick="openModal('edit','<?= $n ?>')"
                      style="cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;padding:.2rem .4rem .2rem 0;border-radius:6px;transition:background .15s;"
                      onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background='none'">
                  <strong style="font-size:.95rem;"><?= $n ?></strong>
                  <i data-lucide="pencil" style="width:12px;height:12px;color:var(--warm);flex-shrink:0;"></i>
                </span>
                <span style="display:inline-block;font-size:.65rem;padding:.1rem .4rem;border-radius:4px;background:var(--cream);color:var(--muted);margin-left:.2rem;font-weight:600;letter-spacing:.04em;"><?= strtoupper($appLang) ?></span>
                <?php if ($desc): ?>
                  <br><span style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($desc) ?></span>
                <?php endif; ?>
                <?php if ($ghUrl): ?>
                  <br><a href="<?= htmlspecialchars($ghUrl) ?>" target="_blank" style="font-size:.78rem;color:var(--muted);">GitHub ↗</a>
                <?php endif; ?>
              </td>

              <!-- DEV -->
              <td style="text-align:center;">
                <?php if (!$provisioned): ?>
                  <button onclick="openModal('provision','<?= $n ?>')" class="env-btn env-btn-action">
                    <i data-lucide="zap" style="width:14px;height:14px;"></i>
                    <span data-pt="Criar" data-en="Create">Criar</span>
                  </button>
                <?php elseif ($hasDev): ?>
                  <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank" class="env-btn env-btn-dev">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                    <span data-pt="Abrir" data-en="Open">Abrir</span>
                  </a>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- HML -->
              <td style="text-align:center;">
                <?php if ($hasHml): ?>
                  <a href="<?= htmlspecialchars($hmlUrl) ?>" target="_blank" class="env-btn env-btn-hml">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                    <span data-pt="Abrir" data-en="Open">Abrir</span>
                  </a>
                <?php elseif ($provisioned && $hasDev): ?>
                  <button onclick="openModal('publish','<?= $n ?>','hml')" class="env-btn env-btn-publish">
                    <i data-lucide="upload-cloud" style="width:14px;height:14px;"></i>
                    <span data-pt="Publicar" data-en="Publish">Publicar</span>
                  </button>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- PRD -->
              <td style="text-align:center;">
                <?php if ($hasPrd): ?>
                  <a href="<?= htmlspecialchars($prdUrl) ?>" target="_blank" class="env-btn env-btn-prd">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                    <span data-pt="Abrir" data-en="Open">Abrir</span>
                  </a>
                <?php elseif ($provisioned && $hasDev): ?>
                  <button onclick="openModal('publish','<?= $n ?>','prd')" class="env-btn env-btn-publish">
                    <i data-lucide="rocket" style="width:14px;height:14px;"></i>
                    <span data-pt="Publicar" data-en="Deploy">Publicar</span>
                  </button>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- Terminal -->
              <td style="text-align:center;">
                <?php if ($termUrl && $provisioned): ?>
                  <a href="workspace.php?app=<?= urlencode($name) ?>" class="env-btn env-btn-term">
                    <i data-lucide="terminal" style="width:16px;height:16px;"></i>
                  </a>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
      <h2 id="modal-title" style="margin:0;" data-pt="Novo app" data-en="New app">Novo app</h2>
    </div>

    <!-- Wizard progress -->
    <div id="wiz-progress" style="display:none;" class="wiz-progress">
      <div class="wiz-step active" id="wp-s1">
        <span class="wiz-num">1</span>
        <span class="wiz-label">Template</span>
      </div>
      <div class="wiz-connector" id="wp-line1"></div>
      <div class="wiz-step" id="wp-s2">
        <span class="wiz-num">2</span>
        <span class="wiz-label">Nome</span>
      </div>
    </div>

    <!-- STEP 1: Template selection -->
    <div id="form-template">
      <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin-bottom:.75rem;">
        Escolha o template. Ele define o que o app já vem com — telas, banco e código prontos.
      </p>
      <div class="tpl-grid" id="tpl-opts">
        <?php foreach ($_templates as $tpl): ?>
        <label class="tpl-card<?= $tpl === reset($_templates) ? ' sel' : '' ?>"
               onclick="selectTemplate(this, '<?= htmlspecialchars($tpl['name']) ?>')">
          <input type="radio" name="app-template" value="<?= htmlspecialchars($tpl['name']) ?>"
                 <?= $tpl === reset($_templates) ? 'checked' : '' ?>>
          <strong>
            <i data-lucide="layers"></i>
            <?= htmlspecialchars($tpl['label'] ?? $tpl['name']) ?>
          </strong>
          <small><?= htmlspecialchars($tpl['description'] ?? '') ?></small>
        </label>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:.875rem;font-size:.75rem;color:var(--muted);">
        Mais templates em <a href="templates.php" style="color:var(--warm);">Templates</a>.
      </p>
    </div>

    <!-- STEP 2: App name -->
    <div id="form-new" style="display:none;">
      <div class="field" style="margin-bottom:1rem;">
        <label>Nome do app</label>
        <input type="text" id="app-name" placeholder="meu-app" autofocus>
        <small>Letras minúsculas, números e hífens. Ex: <strong>clinica-central</strong>, <strong>meu-crm</strong></small>
      </div>
      <?php if ($_hasGithubToken): ?>
      <div class="field" style="margin-bottom:0;">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="github" style="width:14px;height:14px;"></i>
          Repositório GitHub <small style="color:var(--muted);font-weight:400;">(opcional)</small>
        </label>
        <div style="display:flex;gap:.4rem;align-items:center;">
          <select id="app-github"
            style="flex:1;padding:.45rem .65rem;border:1px solid var(--rule);border-radius:8px;
                   font-family:'Geist Mono',monospace;font-size:.78rem;background:var(--paper);color:var(--ink);">
            <option value="">— <?= $_ghOwner ? htmlspecialchars($_ghOwner).'/' : '' ?>...</option>
          </select>
          <button type="button" onclick="loadGhRepos('app-github')" title="Atualizar"
            style="padding:.4rem .55rem;border:1px solid var(--rule);border-radius:7px;
                   background:var(--paper);cursor:pointer;color:var(--muted);flex-shrink:0;">
            <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i>
          </button>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" id="app-github" value="">
      <?php endif; ?>
    </div>

    <!-- Publish / Provision / Edit forms -->
    <div id="form-publish"   style="display:none;"><p id="publish-msg"   style="color:var(--muted);font-size:.875rem;"></p></div>
    <div id="form-provision" style="display:none;"><p id="provision-msg" style="color:var(--muted);font-size:.875rem;margin-bottom:1rem;"></p></div>

    <div id="form-edit" style="display:none;">
      <div style="background:var(--cream);border-radius:8px;padding:.6rem .875rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;">
        <i data-lucide="info" style="width:14px;height:14px;stroke:var(--muted);flex-shrink:0;"></i>
        <span style="font-size:.78rem;color:var(--muted);"
              data-pt="O nome do app não pode ser alterado após a criação."
              data-en="The app name cannot be changed after creation.">O nome do app não pode ser alterado após a criação.</span>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="github" style="width:14px;height:14px;"></i>
          GitHub Repository
        </label>
        <?php if ($_hasGithubToken): ?>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <select id="edit-github"
              style="flex:1;padding:.45rem .65rem;border:1px solid var(--rule);border-radius:7px;
                     font-family:'Geist Mono',monospace;font-size:.78rem;background:var(--paper);
                     color:var(--ink);">
              <option value="">— selecionar —</option>
            </select>
            <button type="button" onclick="loadGhRepos('edit-github')" title="Refresh list"
              style="padding:.4rem .55rem;border:1px solid var(--rule);border-radius:7px;
                     background:var(--paper);cursor:pointer;color:var(--muted);flex-shrink:0;">
              <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i>
            </button>
          </div>
        <?php else: ?>
          <input type="text" id="edit-github"
            placeholder="owner/nome-do-repo"
            style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);
                   border-radius:6px;font-family:'Geist Mono',monospace;font-size:.8rem;">
        <?php endif; ?>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="bot" style="width:14px;height:14px;stroke:var(--warm);"></i>
          <span data-pt="Instruções para o Claude" data-en="Instructions for Claude">Instruções para o Claude</span>
        </label>
        <small style="margin-bottom:.4rem;display:block;"
               data-pt="Contexto, regras de negócio e decisões técnicas."
               data-en="Context, business rules and technical decisions.">Contexto, regras de negócio e decisões técnicas.</small>
        <textarea id="edit-notes" rows="7"
          data-pt-placeholder="Ex: Usar UUID como PK. Relatórios em PDF. Cliente prefere layout minimalista..."
          data-en-placeholder="E.g.: Use UUID as PK. PDF reports. Client prefers minimal layout..."
          placeholder="Ex: Usar UUID como PK. Relatórios em PDF. Cliente prefere layout minimalista..."
          style="width:100%;padding:.75rem;border:1px solid var(--rule);border-radius:8px;font-family:'Geist Mono',monospace;font-size:.78rem;line-height:1.7;resize:vertical;background:var(--paper);color:var(--ink);"></textarea>
      </div>
      <div id="ssh-section" style="display:none;margin-top:1rem;padding:1rem;background:var(--cream);border-radius:8px;border:1px solid var(--rule);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
          <label style="font-size:.8rem;font-weight:600;">Deploy Key (SSH)</label>
          <div style="display:flex;gap:.5rem;">
            <button type="button" class="btn btn-secondary btn-xs" onclick="testSshConnection()"
                    data-pt="Testar" data-en="Test">Testar</button>
            <button type="button" class="btn btn-secondary btn-xs" onclick="gitPush()">Git push</button>
          </div>
        </div>
        <textarea id="ssh-pubkey" readonly rows="3"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:monospace;font-size:.72rem;background:#fff;resize:none;"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;">
          <small style="color:var(--muted);">GitHub → Settings → Deploy Keys → Add key (write access)</small>
          <button type="button" class="btn btn-secondary btn-xs" onclick="copyKey()"
                  data-pt="Copiar" data-en="Copy">Copiar</button>
        </div>
        <div id="ssh-status" style="margin-top:.5rem;font-size:.8rem;"></div>
      </div>

      <div style="margin-top:1.5rem;padding:1rem;border:1px solid var(--danger);border-radius:8px;background:#fdecea;">
        <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.5rem;">
          <i data-lucide="alert-triangle" style="width:14px;height:14px;color:var(--danger);"></i>
          <strong style="font-size:.8rem;color:var(--danger);" data-pt="Zona de perigo" data-en="Danger zone">Zona de perigo</strong>
        </div>
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:.6rem;"
           data-pt="Remove permanentemente todos os ambientes (DEV/HML/PRD), banco de dados, terminal e arquivos deste app. Esta ação não pode ser desfeita."
           data-en="Permanently removes all environments (DEV/HML/PRD), database, terminal and files for this app. This action cannot be undone.">
          Remove permanentemente todos os ambientes (DEV/HML/PRD), banco de dados, terminal e arquivos deste app. Esta ação não pode ser desfeita.
        </p>
        <div style="display:flex;gap:.5rem;align-items:center;">
          <input type="text" id="delete-confirm-input" autocomplete="off"
                 style="flex:1;padding:.45rem .65rem;border:1px solid var(--danger);border-radius:7px;
                        font-family:'Geist Mono',monospace;font-size:.78rem;background:var(--paper);color:var(--ink);">
          <button type="button" class="btn btn-danger btn-xs" id="btn-delete-app" onclick="deleteApp()" disabled
                  style="white-space:nowrap;"
                  data-pt="Excluir app" data-en="Delete app">Excluir app</button>
        </div>
        <div id="delete-status" style="margin-top:.5rem;font-size:.8rem;"></div>
      </div>
    </div>

    <!-- Output log -->
    <div id="modal-output" style="display:none;margin-top:1rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
        <span style="font-size:.78rem;color:var(--muted);">Output</span>
        <button type="button" onclick="copyLog()" class="btn btn-secondary btn-xs"
                data-pt="Copiar" data-en="Copy">Copiar</button>
      </div>
      <pre id="modal-log" style="background:var(--ink);color:#c9d1d9;padding:1rem;border-radius:8px;font-family:monospace;font-size:.78rem;height:260px;overflow-y:auto;white-space:pre-wrap;margin:0;"></pre>
    </div>

    <!-- Creation progress -->
    <div id="modal-progress" style="display:none;">
      <div class="prog-spinner" id="prog-spinner">
        <i data-lucide="loader-2" style="width:32px;height:32px;stroke:var(--warm);"></i>
        <span id="prog-spinner-label" data-pt="Criando app..." data-en="Creating app...">Criando app...</span>
      </div>
      <div id="progress-items" style="display:none;"></div>
      <div id="prog-success" style="display:none;" class="prog-success">
        <div class="check-circle"><i data-lucide="check" style="width:26px;height:26px;"></i></div>
        <strong data-pt="App criado com sucesso!" data-en="App created successfully!">App criado com sucesso!</strong>
        <a id="prog-app-url" href="#" target="_blank" class="prog-url">
          <i data-lucide="external-link" style="width:13px;height:13px;"></i>
          <span id="prog-app-url-label"></span>
        </a>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()"
              data-pt="Cancelar" data-en="Cancel">Cancelar</button>
      <button class="btn btn-secondary" id="btn-back" style="display:none;" onclick="backStep()"
              data-pt="← Voltar" data-en="← Back">← Voltar</button>
      <button class="btn btn-primary" id="btn-action" onclick="runAction()"
              data-pt="Próximo →" data-en="Next →">Próximo →</button>
    </div>
  </div>
</div>

<script src="assets/js/studio.js"></script>
<script>
const _appsData       = <?= $appsJson ?>;
const _hasGithubToken = <?= $_hasGithubToken ? 'true' : 'false' ?>;
const _ghOwner        = '<?= addslashes($_ghOwner) ?>';
let _mode = 'new', _app = '', _to = '', _step = 1;
let _ghRepos = [];

function t(pt, en) { return (window.studioLang ? studioLang() : 'pt') === 'en' ? en : pt; }

function selectTemplate(card, name) {
  document.querySelectorAll('#tpl-opts .tpl-card').forEach(c => c.classList.remove('sel'));
  card.classList.add('sel');
  card.querySelector('input').checked = true;
}

function getSelectedTemplate() {
  const radio = document.querySelector('#tpl-opts input[name="app-template"]:checked');
  return radio ? radio.value : 'base';
}

function setWizStep(n) {
  _step = n;
  ['wp-s1','wp-s2'].forEach((id, i) => {
    const el = document.getElementById(id);
    if (!el) return;
    const step = i + 1;
    el.className = 'wiz-step ' + (n > step ? 'done' : n === step ? 'active' : '');
  });
  const line = document.getElementById('wp-line1');
  if (line) line.className = 'wiz-connector' + (n > 1 ? ' done' : '');
}

function openModal(mode, app = '', to = '') {
  _mode = mode; _app = app; _to = to;
  document.getElementById('modal').classList.add('open');
  document.getElementById('modal-output').style.display = 'none';
  document.getElementById('modal-log').textContent = '';
  document.getElementById('btn-action').disabled = false;
  ['form-template','form-new','form-publish','form-provision','form-edit'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  document.getElementById('modal-progress').style.display = 'none';
  document.getElementById('btn-back').style.display = 'none';
  document.getElementById('wiz-progress').style.display = 'none';

  if (mode === 'new') {
    document.getElementById('modal-title').textContent = 'Novo app';
    document.getElementById('form-template').style.display = '';
    document.getElementById('btn-action').textContent = 'Próximo →';
    document.getElementById('wiz-progress').style.display = '';
    setWizStep(1);
    setTimeout(() => lucide.createIcons(), 30);

  } else if (mode === 'edit') {
    const meta = _appsData[app] || {};
    document.getElementById('modal-title').textContent = t('Editar — ', 'Edit — ') + app;
    document.getElementById('form-edit').style.display = '';
    document.getElementById('edit-notes').value   = meta.memory_notes || '';
    document.getElementById('btn-action').textContent = t('Salvar', 'Save');
    setGhRepoSelect('edit-github', meta.github_repo || '');
    loadSshKey(app);
    resetDeleteZone(app);

  } else if (mode === 'provision') {
    document.getElementById('modal-title').textContent = t('Provisionar ', 'Provision ') + app;
    document.getElementById('form-provision').style.display = '';
    document.getElementById('provision-msg').textContent =
      t(`Criar ambiente DEV para "${app}"? Isso cria pasta, banco, terminal e repositório.`,
        `Create DEV environment for "${app}"? This creates the folder, database, terminal and repository.`);
    document.getElementById('btn-action').textContent = t('⚡ Provisionar DEV', '⚡ Provision DEV');

  } else if (mode === 'publish') {
    const envLabel = to === 'hml' ? t('Homologação', 'Staging') : t('Produção', 'Production');
    document.getElementById('modal-title').textContent = t('Publicar em ', 'Publish to ') + envLabel;
    document.getElementById('form-publish').style.display = '';
    document.getElementById('publish-msg').textContent =
      t(`Copiar código de DEV para ${envLabel}? Um novo banco isolado será criado.`,
        `Copy code from DEV to ${envLabel}? A new isolated database will be created.`);
    document.getElementById('btn-action').textContent = t(`🚀 Publicar em ${envLabel}`, `🚀 Deploy to ${envLabel}`);
  }
}

const _piMap = [
  { re: /✓ Directory/,   icon: 'folder',       label: () => 'Diretório criado' },
  { re: /✓ System user/, icon: 'user',          label: () => 'Usuário isolado' },
  { re: /✓ Database/,    icon: 'database',      label: () => 'Banco configurado' },
  { re: /✓ Tables/,      icon: 'table-2',       label: () => 'Tabelas criadas' },
  { re: /✓ Terminal/,    icon: 'terminal',      label: () => 'Terminal configurado' },
  { re: /✓ \.env/,       icon: 'file-cog',      label: () => 'Variáveis configuradas' },
  { re: /✓ Template/,    icon: 'layers',        label: () => 'Template instalado' },
  { re: /✓ Memory/,      icon: 'brain-circuit', label: () => 'Contexto inicializado' },
  { re: /✓ Git/,         icon: 'git-branch',    label: () => 'Git inicializado' },
  { re: /DNS/,           icon: 'globe',         label: () => 'DNS configurado' },
  { re: /✓ Permissions/, icon: 'shield-check',  label: () => 'Permissões ajustadas' },
];

function animateProgress(output, appName, success) {
  const lines = output.split('\n');
  const items = [];
  for (const line of lines) {
    for (const map of _piMap) {
      if (map.re.test(line) && !items.find(i => i === map)) {
        items.push(map); break;
      }
    }
  }
  const container = document.getElementById('progress-items');
  if (!container) return;
  container.innerHTML = '';
  container.style.display = 'block';
  items.forEach((item, i) => {
    setTimeout(() => {
      try {
        const el = document.createElement('div');
        el.className = 'pi-item';
        el.innerHTML =
          `<span class="pi-icon"><i data-lucide="${item.icon}"></i></span>` +
          `<span class="pi-label">${item.label()}</span>` +
          `<span class="pi-check"><i data-lucide="check-circle-2"></i></span>`;
        container.appendChild(el);
        lucide.createIcons();
      } catch(e) { /* animação cosmética — ignora erros */ }
    }, i * 150);
  });
}

function closeModal() {
  document.getElementById('modal').classList.remove('open');
  document.getElementById('wiz-progress').style.display = 'none';
}

function backStep() {
  if (_mode !== 'new') return;
  if (_step === 2) {
    document.getElementById('form-new').style.display = 'none';
    document.getElementById('form-template').style.display = '';
    document.getElementById('modal-title').textContent = 'Novo app';
    document.getElementById('btn-action').textContent = 'Próximo →';
    document.getElementById('btn-back').style.display = 'none';
    setWizStep(1);
    setTimeout(() => lucide.createIcons(), 30);
  }
}

async function runAction() {
  const btn = document.getElementById('btn-action');
  const out = document.getElementById('modal-output');
  const log = document.getElementById('modal-log');
  btn.disabled = true;

  let endpoint, body;

  if (_mode === 'new') {
    const template = getSelectedTemplate();

    if (_step === 1) {
      // Template selected → go to name (sem mostrar output ainda)
      document.getElementById('form-template').style.display = 'none';
      document.getElementById('form-new').style.display = '';
      document.getElementById('btn-action').textContent = 'Criar app →';
      document.getElementById('btn-back').style.display = '';
      btn.disabled = false;
      setWizStep(2);
      if (_hasGithubToken) loadGhRepos('app-github');
      setTimeout(() => { document.getElementById('app-name').focus(); lucide.createIcons(); }, 50);
      return;
    }

    // Step 2 → register + provision
    const name = document.getElementById('app-name').value.trim();
    if (!name) { alert('Informe o nome do app'); btn.disabled = false; return; }
    if (!/^[a-z0-9][a-z0-9-]*$/.test(name)) { alert('Use apenas letras minúsculas, números e hífens'); btn.disabled = false; return; }

    out.style.display = 'none';
    document.getElementById('modal-progress').style.display = 'block';
    document.getElementById('prog-spinner').style.display = 'flex';
    document.getElementById('progress-items').style.display = 'none';
    document.getElementById('prog-success').style.display = 'none';
    document.getElementById('prog-spinner-label').textContent = 'Registrando app...';
    btn.textContent = 'Criando...';
    setTimeout(() => lucide.createIcons(), 30);

    try {
      const r1 = await fetch('api/newapp.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          name, description: name, template,
          github_repo: document.getElementById('app-github').value.trim()
        })
      });
      if (r1.status === 401) { location.href = 'login.php'; return; }
      const d1 = await r1.json();
      if (!d1.success) {
        document.getElementById('prog-spinner-label').textContent = 'Erro: ' + (d1.error || 'falha ao registrar.');
        btn.disabled = false; btn.textContent = 'Tentar novamente'; return;
      }

      document.getElementById('prog-spinner-label').textContent = 'Provisionando ambiente DEV...';

      const r2 = await fetch('api/provision.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ name })
      });
      if (r2.status === 401) { location.href = 'login.php'; return; }
      const d2 = await r2.json();

      const ghRepo = document.getElementById('app-github').value.trim();
      if (d2.success && ghRepo && _hasGithubToken) {
        document.getElementById('prog-spinner-label').textContent = 'Configurando GitHub...';
        try {
          await fetch('api/git.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({name, action: 'set-remote', repo: ghRepo})
          });
        } catch(e) { /* non-fatal */ }
      }

      // Atualiza botão imediatamente — sem depender da animação
      const urlMatch = (d2.output || '').match(/URL:\s+(https?:\/\/\S+)/);
      const appUrl   = urlMatch ? urlMatch[1] : '';
      document.getElementById('prog-spinner').style.display = 'none';
      document.getElementById('btn-back').style.display = 'none';
      if (d2.success) {
        document.getElementById('prog-success').style.display = 'block';
        if (appUrl) {
          document.getElementById('prog-app-url').href = appUrl;
          document.getElementById('prog-app-url-label').textContent = appUrl;
        } else {
          document.getElementById('prog-app-url').style.display = 'none';
        }
        lucide.createIcons();
      }
      btn.textContent = d2.success ? 'Abrir workspace →' : 'Tentar novamente';
      btn.disabled = false;
      if (d2.success) btn.onclick = () => { window.location.href = 'workspace.php?app=' + encodeURIComponent(name); };
      const cancelBtn = document.querySelector('.modal-footer .btn-secondary');
      if (cancelBtn) { cancelBtn.textContent = 'Ficar no dashboard'; cancelBtn.onclick = () => location.reload(); }

      // Animação de items é cosmética — roda em paralelo
      animateProgress(d2.output || '', name, d2.success);

    } catch(e) {
      document.getElementById('prog-spinner').style.display = 'none';
      document.getElementById('prog-spinner-label').textContent = 'Erro: ' + e.message;
      btn.disabled = false; btn.textContent = 'Tentar novamente';
    }
    return;

  } else if (_mode === 'edit') {
    endpoint = 'api/update-app.php';
    body = { name: _app, github_repo: document.getElementById('edit-github').value.trim(), memory_notes: document.getElementById('edit-notes').value.trim() };
    btn.textContent = t('Salvando...', 'Saving...');
  } else if (_mode === 'provision') {
    endpoint = 'api/provision.php';
    body = { name: _app };
    btn.textContent = t('Provisionando...', 'Provisioning...');
  } else if (_mode === 'publish') {
    endpoint = 'api/promote.php';
    body = { name: _app, to: _to };
    btn.textContent = t(`Publicando em ${_to.toUpperCase()}...`, `Deploying to ${_to.toUpperCase()}...`);
  }

  out.style.display = 'block';
  log.textContent = 'Processando...\n';

  try {
    const resp = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    if (resp.status === 401) { location.href = 'login.php'; return; }
    const data = await resp.json();
    log.textContent = data.output || data.error || data.message || t('Concluído.', 'Done.');
    if (data.success) {
      btn.textContent = t('✓ Concluído!', '✓ Done!');
      setTimeout(() => location.reload(), 2000);
    } else {
      btn.disabled = false;
      btn.textContent = t('Tentar novamente', 'Try again');
    }
  } catch(e) {
    log.textContent = t('Erro: ', 'Error: ') + e.message;
    btn.disabled = false;
    btn.textContent = t('Tentar novamente', 'Try again');
  }
}

document.getElementById('modal').addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});

// ── GitHub repo list ────────────────────────────────────────────────────────
async function loadGhRepos(selectId) {
  const sel = document.getElementById(selectId);
  if (!sel || !_hasGithubToken) return;

  const prevVal = sel.value;
  sel.disabled = true;

  // Use cache if already loaded
  if (_ghRepos.length === 0) {
    try {
      const r = await fetch('api/github.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'list-repos'})
      });
      const d = await r.json();
      _ghRepos = d.repos || [];
    } catch(e) { _ghRepos = []; }
  }

  // Populate select
  sel.innerHTML = '<option value="">— ' + (_ghOwner ? _ghOwner + '/' : '') + t('selecionar', 'select') + ' —</option>';
  _ghRepos.forEach(repo => {
    const opt = document.createElement('option');
    opt.value = repo.full;   // e.g. "owner/repo-name"
    opt.textContent = repo.name + (repo.private ? ' 🔒' : '');
    if (repo.full === prevVal) opt.selected = true;
    sel.appendChild(opt);
  });

  if (_ghRepos.length === 0) {
    sel.innerHTML += '<option disabled>' + t('Nenhum repo encontrado', 'No repos found') + '</option>';
  }

  sel.disabled = false;
}

// Normalize any GitHub URL/SSH format → "owner/repo"
function ghNormalizeRepo(raw) {
  if (!raw) return '';
  // SSH alias: git@github-app:owner/repo.git or git@github.com:owner/repo.git
  let m = raw.match(/github[^:]*:([^/\s]+\/[^\s]+?)(?:\.git)?$/i);
  if (m) return m[1];
  // HTTPS: https://github.com/owner/repo or https://token@github.com/owner/repo
  m = raw.match(/github\.com\/([^/\s]+\/[^\s.]+?)(?:\.git)?$/i);
  if (m) return m[1];
  // Already "owner/repo"
  if (raw.includes('/')) return raw.replace(/\.git$/, '');
  return raw;
}

// Open a select + pre-select the current value (loads list if needed)
async function setGhRepoSelect(selectId, rawValue) {
  const el = document.getElementById(selectId);
  if (!el) return;
  if (el.tagName === 'INPUT') { el.value = rawValue; return; }

  await loadGhRepos(selectId);

  const normalized = ghNormalizeRepo(rawValue);
  if (normalized) {
    // Try to select matching option
    for (const opt of el.options) {
      if (opt.value === normalized) { el.value = normalized; return; }
    }
    // Not found — add it so the current value is preserved
    const opt = document.createElement('option');
    opt.value = normalized;
    opt.textContent = normalized;
    opt.selected = true;
    el.appendChild(opt);
  }
}

async function loadSshKey(name) {
  const section = document.getElementById('ssh-section');
  section.style.display = 'none';
  document.getElementById('ssh-status').textContent = '';
  const resp = await fetch('api/ssh-key.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({name, action:'get'}) });
  const data = await resp.json();
  if (data.public_key) { document.getElementById('ssh-pubkey').value = data.public_key; section.style.display = 'block'; }
}

async function testSshConnection() {
  const status = document.getElementById('ssh-status');
  status.textContent = 'Testando...';
  const resp = await fetch('api/ssh-key.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({name: _app, action:'test'}) });
  const data = await resp.json();
  status.innerHTML = data.success ? '<span style="color:#2e7d32;">✓ Conectado!</span>' : `<span style="color:#c62828;">✗ ${data.output}</span>`;
}

async function gitPush() {
  const status = document.getElementById('ssh-status');
  status.textContent = 'Enviando...';
  const resp = await fetch('api/ssh-key.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({name: _app, action:'push'}) });
  const data = await resp.json();
  status.innerHTML = data.success ? '<span style="color:#2e7d32;">✓ Push feito!</span>' : `<span style="color:#c62828;">✗ ${data.output}</span>`;
}

// ── Excluir app ──────────────────────────────────────────────────────────────
function resetDeleteZone(app) {
  const input = document.getElementById('delete-confirm-input');
  const btn   = document.getElementById('btn-delete-app');
  input.value = '';
  input.disabled = false;
  input.placeholder = t(`Digite "${app}" para confirmar`, `Type "${app}" to confirm`);
  btn.disabled = true;
  document.getElementById('delete-status').textContent = '';
  input.oninput = () => { btn.disabled = (input.value !== app); };
}

async function deleteApp() {
  const input  = document.getElementById('delete-confirm-input');
  const btn    = document.getElementById('btn-delete-app');
  const status = document.getElementById('delete-status');
  if (input.value !== _app) return;

  btn.disabled = true;
  input.disabled = true;
  status.textContent = t('Removendo...', 'Removing...');

  try {
    const resp = await fetch('api/delete-app.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ name: _app })
    });
    if (resp.status === 401) { location.href = 'login.php'; return; }
    const data = await resp.json();
    if (data.success) {
      status.innerHTML = '<span style="color:#2e7d32;">' + t('✓ App removido.', '✓ App removed.') + '</span>';
      setTimeout(() => location.reload(), 1200);
    } else {
      status.innerHTML = `<span style="color:#c62828;">✗ ${data.error || data.output || t('Erro ao remover.', 'Error removing.')}</span>`;
      btn.disabled = false; input.disabled = false;
    }
  } catch(e) {
    status.innerHTML = `<span style="color:#c62828;">✗ ${e.message}</span>`;
    btn.disabled = false; input.disabled = false;
  }
}

function copyKey() {
  const key = document.getElementById('ssh-pubkey').value;
  const done = () => { document.getElementById('ssh-status').innerHTML = '<span style="color:#2e7d32;">✓ Copiado!</span>'; };
  navigator.clipboard ? navigator.clipboard.writeText(key).then(done) : (fallbackCopy(key), done());
}

function copyLog() {
  const btn = event.currentTarget;
  const done = () => { btn.textContent = '✓ Copiado!'; btn.style.background = 'var(--success)'; btn.style.color = '#fff'; setTimeout(() => { btn.textContent = 'Copiar'; btn.style.background = ''; btn.style.color = ''; }, 2000); };
  navigator.clipboard ? navigator.clipboard.writeText(document.getElementById('modal-log').textContent).then(done) : (fallbackCopy(document.getElementById('modal-log').textContent), done());
}

function fallbackCopy(text) {
  const ta = Object.assign(document.createElement('textarea'), {value: text, style: 'position:fixed;opacity:0'});
  document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
}

lucide.createIcons();

const _origOpen = openModal;
window.openModal = function(...args) { _origOpen(...args); setTimeout(() => lucide.createIcons(), 50); };
</script>
</body>
</html>
