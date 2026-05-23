<?php require ROOT . '/views/layout.php'; ?>

<div class="pagina-topo">
    <h1 class="pagina-topo__titulo">Usuários</h1>
    <a href="/usuarios/novo" class="btn btn--primario">
        <i class="bi bi-plus-lg"></i> Novo Usuário
    </a>
</div>

<?php if ($sucesso ?? false): ?>
    <div class="alerta alerta--sucesso" data-auto-fechar>
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="tabela-wrap">
        <table>
            <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                <tr><td colspan="5"><div class="vazio"><i class="bi bi-person"></i><div class="vazio__titulo">Nenhum usuário</div></div></td></tr>
                <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:var(--space-sm)">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">
                                <?= mb_strtoupper(mb_substr($u['nome'], 0, 1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($u['nome']) ?></strong>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge--<?= $u['role'] === 'admin' ? 'admin' : 'inativo' ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><span class="badge badge--<?= $u['ativo'] ? 'ativo' : 'inativo' ?>"><?= $u['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <div class="td-acoes">
                            <a href="/usuarios/<?= $u['id'] ?>/editar" class="btn btn--secundario btn--sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
