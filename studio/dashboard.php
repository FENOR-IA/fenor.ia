<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$config = require __DIR__ . '/config/config.php';
require  __DIR__ . '/config/helpers.php';

// Apps no banco
$dbApps = [];
try {
    foreach (fenorDB()->query('SELECT * FROM fenor_apps ORDER BY created_at DESC')->fetchAll() as $row) {
        $dbApps[$row['name']] = $row;
    }
} catch (Throwable $e) { $dbApps = []; }

$appsJson = json_encode($dbApps, JSON_HEX_TAG | JSON_HEX_AMP);

// Apps no filesystem
$fsApps = [];
foreach (loadApps($config['apps_path']) as $app) {
    $fsApps[$app['name']] = $app;
}

// Merge
$apps = [];
foreach ($dbApps as $name => $meta) {
    $apps[] = [
        'name'        => $name,
        'description' => $meta['description'] ?? '',
        'github_repo' => $meta['github_repo'] ?? '',
        'status'      => $meta['status'] ?? 'registered',
        'envs'        => $fsApps[$name]['envs'] ?? [],
    ];
}
foreach ($fsApps as $name => $fsApp) {
    if (!isset($dbApps[$name])) {
        $apps[] = array_merge($fsApp, ['description' => '', 'github_repo' => '', 'status' => 'provisioned']);
    }
}

$total  = count($apps);
$devCnt = count(array_filter($apps, fn($a) => isset($a['envs']['dev'])));
$hmlCnt = count(array_filter($apps, fn($a) => isset($a['envs']['hml'])));
$prdCnt = count(array_filter($apps, fn($a) => isset($a['envs']['prd'])));
$regCnt = count(array_filter($apps, fn($a) => ($a['status'] ?? '') === 'registered'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
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
          <div class="sub">apps cadastrados</div>
        </div>
        <div class="stat-card">
          <div class="label">DEV</div>
          <div class="value"><?= $devCnt ?></div>
          <div class="sub">em desenvolvimento</div>
        </div>
        <div class="stat-card">
          <div class="label">HML</div>
          <div class="value"><?= $hmlCnt ?></div>
          <div class="sub">em homologação</div>
        </div>
        <div class="stat-card">
          <div class="label">PRD</div>
          <div class="value"><?= $prdCnt ?></div>
          <div class="sub">em produção</div>
        </div>
      </div>

      <!-- Tabela de apps -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <p style="color:var(--muted);font-size:.875rem;"><?= $total ?> app(s)</p>
        <button class="btn btn-primary" onclick="openModal('new')" style="display:inline-flex;align-items:center;gap:.4rem;">
          <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Novo app
        </button>
      </div>

      <?php if (empty($apps)): ?>
        <div style="text-align:center;padding:4rem;color:var(--muted);">
          <p style="font-size:1.1rem;margin-bottom:1rem;">Nenhum app criado ainda.</p>
          <button class="btn btn-primary" onclick="openModal('new')">Criar primeiro app →</button>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>App</th>
              <th style="text-align:center;width:110px;">Desenvolvimento</th>
              <th style="text-align:center;width:110px;">Homologação</th>
              <th style="text-align:center;width:110px;">Produção</th>
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
              $n           = htmlspecialchars($name);
            ?>
            <tr>
              <td>
                <span onclick="openModal('edit','<?= $n ?>')"
                      title="Clique para editar"
                      style="cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;padding:.2rem .4rem .2rem 0;border-radius:6px;transition:background .15s;"
                      onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background='none'">
                  <strong style="font-size:.95rem;"><?= $n ?></strong>
                  <i data-lucide="pencil" style="width:12px;height:12px;color:var(--warm);flex-shrink:0;"></i>
                </span>
                <?php if ($desc): ?>
                  <br><span style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($desc) ?></span>
                <?php endif; ?>
                <?php if ($ghUrl): ?>
                  <br><a href="<?= htmlspecialchars($ghUrl) ?>" target="_blank"
                     style="font-size:.78rem;color:var(--muted);">GitHub ↗</a>
                <?php endif; ?>
              </td>

              <!-- DEV -->
              <td style="text-align:center;">
                <?php if (!$provisioned): ?>
                  <button onclick="openModal('provision','<?= $n ?>')"
                          title="Criar ambiente de desenvolvimento"
                          class="env-btn env-btn-action">
                    <i data-lucide="zap" style="width:14px;height:14px;"></i> Criar
                  </button>
                <?php elseif ($hasDev): ?>
                  <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank"
                     class="env-btn env-btn-dev">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i> Abrir
                  </a>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- HML -->
              <td style="text-align:center;">
                <?php if ($hasHml): ?>
                  <a href="<?= htmlspecialchars($hmlUrl) ?>" target="_blank"
                     class="env-btn env-btn-hml">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i> Abrir
                  </a>
                <?php elseif ($provisioned && $hasDev): ?>
                  <button onclick="openModal('publish','<?= $n ?>','hml')"
                          title="Publicar para o cliente testar"
                          class="env-btn env-btn-publish">
                    <i data-lucide="upload-cloud" style="width:14px;height:14px;"></i> Publicar
                  </button>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- PRD -->
              <td style="text-align:center;">
                <?php if ($hasPrd): ?>
                  <a href="<?= htmlspecialchars($prdUrl) ?>" target="_blank"
                     class="env-btn env-btn-prd">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i> Abrir
                  </a>
                <?php elseif ($provisioned && $hasDev): ?>
                  <button onclick="openModal('publish','<?= $n ?>','prd')"
                          title="Colocar em produção"
                          class="env-btn env-btn-publish">
                    <i data-lucide="rocket" style="width:14px;height:14px;"></i> Publicar
                  </button>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- Terminal -->
              <td style="text-align:center;">
                <?php if ($termUrl && $provisioned): ?>
                  <a href="workspace.php?app=<?= urlencode($name) ?>"
                     title="Abrir terminal e workspace do app" class="env-btn env-btn-term">
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
    <h2 id="modal-title">Novo app</h2>

    <div id="form-new">
      <div class="field">
        <label>Nome do app</label>
        <input type="text" id="app-name" placeholder="meu-crm" autofocus>
        <small style="color:var(--muted);font-size:.8rem;">Apenas letras minúsculas, números e hífens</small>
      </div>
      <div class="field">
        <label>Descrição</label>
        <textarea id="app-desc" placeholder="Descreva o que este app faz..." rows="2"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:inherit;font-size:.875rem;resize:vertical;"></textarea>
      </div>
      <div class="field">
        <label>Repositório GitHub <small style="color:var(--muted);">(opcional)</small></label>
        <input type="text" id="app-github" placeholder="git@github.com:usuario/repo.git"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:monospace;font-size:.8rem;">
        <small style="color:var(--muted);font-size:.78rem;">
          Se informado, o Fenor fará o primeiro push na branch <strong>dev</strong> após adicionar a Deploy Key.
        </small>
      </div>
    </div>

    <div id="form-publish" style="display:none;">
      <p id="publish-msg" style="color:var(--muted);font-size:.875rem;"></p>
    </div>

    <div id="form-provision" style="display:none;">
      <p id="provision-msg" style="color:var(--muted);font-size:.875rem;margin-bottom:1rem;"></p>
    </div>

    <div id="form-edit" style="display:none;">
      <div class="field">
        <label>Descrição</label>
        <textarea id="edit-desc" rows="2"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:inherit;font-size:.875rem;resize:vertical;"></textarea>
      </div>
      <div class="field">
        <label>Repositório GitHub <small style="color:var(--muted);">(SSH)</small></label>
        <input type="text" id="edit-github" placeholder="git@github.com:usuario/repo.git"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:monospace;font-size:.8rem;">
      </div>
      <div class="field">
        <label>Notas para o Claude <small style="color:var(--muted);">(contexto, regras, decisões)</small></label>
        <textarea id="edit-notes" rows="4" placeholder="Ex: Usar sempre UUID como PK. Cliente prefere layout minimalista..."
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:inherit;font-size:.875rem;resize:vertical;"></textarea>
      </div>
      <div id="ssh-section" style="display:none;margin-top:1rem;padding:1rem;background:var(--cream);border-radius:8px;border:1px solid var(--rule);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
          <label style="font-size:.8rem;font-weight:600;">Deploy Key (SSH)</label>
          <div style="display:flex;gap:.5rem;">
            <button type="button" class="btn btn-secondary btn-xs" onclick="testSshConnection()">Testar</button>
            <button type="button" class="btn btn-secondary btn-xs" onclick="gitPush()">Git push</button>
          </div>
        </div>
        <textarea id="ssh-pubkey" readonly rows="3"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:monospace;font-size:.72rem;background:#fff;resize:none;"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;">
          <small style="color:var(--muted);">GitHub → Settings → Deploy Keys → Add key (write access)</small>
          <button type="button" class="btn btn-secondary btn-xs" onclick="copyKey()">Copiar</button>
        </div>
        <div id="ssh-status" style="margin-top:.5rem;font-size:.8rem;"></div>
      </div>
    </div>

    <div id="modal-output" style="display:none;margin-top:1rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
        <span style="font-size:.78rem;color:var(--muted);">Output</span>
        <button type="button" onclick="copyLog()" class="btn btn-secondary btn-xs">Copiar</button>
      </div>
      <pre id="modal-log" style="background:var(--ink);color:#c9d1d9;padding:1rem;border-radius:8px;
        font-family:monospace;font-size:.78rem;height:340px;overflow-y:auto;white-space:pre-wrap;margin:0;"></pre>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="btn-action" onclick="runAction()">Cadastrar →</button>
    </div>
  </div>
</div>

<script>
const _appsData = <?= $appsJson ?>;
let _mode = 'new', _app = '', _to = '';

function openModal(mode, app = '', to = '') {
  _mode = mode; _app = app; _to = to;
  document.getElementById('modal').classList.add('open');
  document.getElementById('modal-output').style.display = 'none';
  document.getElementById('modal-log').textContent = '';
  document.getElementById('btn-action').disabled = false;
  ['form-new','form-publish','form-provision','form-edit'].forEach(id =>
    document.getElementById(id).style.display = 'none'
  );

  if (mode === 'new') {
    document.getElementById('modal-title').textContent = 'Novo app';
    document.getElementById('form-new').style.display = '';
    document.getElementById('btn-action').textContent = 'Cadastrar →';
    setTimeout(() => document.getElementById('app-name').focus(), 100);

  } else if (mode === 'edit') {
    const meta = _appsData[app] || {};
    document.getElementById('modal-title').textContent = `Editar — ${app}`;
    document.getElementById('form-edit').style.display = '';
    document.getElementById('edit-desc').value    = meta.description  || '';
    document.getElementById('edit-github').value  = meta.github_repo  || '';
    document.getElementById('edit-notes').value   = meta.memory_notes || '';
    document.getElementById('btn-action').textContent = 'Salvar';
    loadSshKey(app);

  } else if (mode === 'provision') {
    document.getElementById('modal-title').textContent = `Provisionar ${app}`;
    document.getElementById('form-provision').style.display = '';
    document.getElementById('provision-msg').textContent =
      `Criar ambiente DEV para "${app}"? Isso cria pasta, banco, terminal e repositório.`;
    document.getElementById('btn-action').textContent = '⚡ Provisionar DEV';

  } else if (mode === 'publish') {
    const envLabel = to === 'hml' ? 'Homologação' : 'Produção';
    document.getElementById('modal-title').textContent = `Publicar em ${envLabel}`;
    document.getElementById('form-publish').style.display = '';
    document.getElementById('publish-msg').textContent =
      `Copiar código de DEV para ${envLabel}? Um novo banco isolado será criado.`;
    document.getElementById('btn-action').textContent = `🚀 Publicar em ${envLabel}`;
  }
}

function closeModal() { document.getElementById('modal').classList.remove('open'); }

async function runAction() {
  const btn = document.getElementById('btn-action');
  const out = document.getElementById('modal-output');
  const log = document.getElementById('modal-log');
  btn.disabled = true;
  out.style.display = 'block';
  log.textContent = 'Processando...\n';

  let endpoint, body;
  if (_mode === 'new') {
    const name = document.getElementById('app-name').value.trim();
    if (!name) { alert('Informe o nome do app'); btn.disabled = false; return; }
    endpoint = 'api/newapp.php';
    body = { name, description: document.getElementById('app-desc').value.trim(), github_repo: document.getElementById('app-github').value.trim() };
    btn.textContent = 'Cadastrando...';
  } else if (_mode === 'edit') {
    endpoint = 'api/update-app.php';
    body = { name: _app, description: document.getElementById('edit-desc').value.trim(), github_repo: document.getElementById('edit-github').value.trim(), memory_notes: document.getElementById('edit-notes').value.trim() };
    btn.textContent = 'Salvando...';
  } else if (_mode === 'provision') {
    endpoint = 'api/provision.php';
    body = { name: _app };
    btn.textContent = 'Provisionando...';
  } else if (_mode === 'publish') {
    endpoint = 'api/promote.php';
    body = { name: _app, to: _to };
    btn.textContent = `Publicando em ${_to.toUpperCase()}...`;
  }

  try {
    const resp = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    if (resp.status === 401) { location.href = 'login.php'; return; }
    const data = await resp.json();
    log.textContent = data.output || data.error || data.message || 'Concluído.';
    if (data.success) {
      btn.textContent = '✓ Concluído!';
      setTimeout(() => location.reload(), 2000);
    } else {
      btn.disabled = false;
      btn.textContent = 'Tentar novamente';
    }
  } catch(e) {
    log.textContent = 'Erro: ' + e.message;
    btn.disabled = false;
    btn.textContent = 'Tentar novamente';
  }
}

document.getElementById('modal').addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});


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

function copyKey() {
  const key = document.getElementById('ssh-pubkey').value;
  const done = () => document.getElementById('ssh-status').innerHTML = '<span style="color:#2e7d32;">✓ Copiado!</span>';
  navigator.clipboard ? navigator.clipboard.writeText(key).then(done) : (fallbackCopy(key), done());
}

function copyLog() {
  const btn = event.currentTarget;
  const done = () => { btn.textContent='✓ Copiado!'; btn.style.background='var(--success)'; btn.style.color='#fff'; setTimeout(()=>{btn.textContent='Copiar';btn.style.background='';btn.style.color='';},2000); };
  navigator.clipboard ? navigator.clipboard.writeText(document.getElementById('modal-log').textContent).then(done) : (fallbackCopy(document.getElementById('modal-log').textContent), done());
}

function fallbackCopy(text) {
  const ta = Object.assign(document.createElement('textarea'), {value: text, style: 'position:fixed;opacity:0'});
  document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
}

lucide.createIcons();

// Reinicializa ícones após reload do modal
const _origOpen = openModal;
window.openModal = function(...args) { _origOpen(...args); setTimeout(() => lucide.createIcons(), 50); };
</script>
</body>
</html>
