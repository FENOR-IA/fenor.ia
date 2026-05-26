<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title">Clientes</h1>
    <a href="/clientes/novo" class="btn btn--primary">
        <i class="bi bi-plus-circle"></i> Novo cliente
    </a>
</div>

<?php if ($sucesso = Session::getFlash('sucesso')): ?>
    <div class="alert alert--success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>
<?php if ($erro = Session::getFlash('erro')): ?>
    <div class="alert alert--error"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Busca -->
<form method="GET" class="search-bar">
    <input type="text" name="q" class="field__input" placeholder="Buscar por nome, e-mail, telefone..."
           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <button type="submit" class="btn btn--secondary">
        <i class="bi bi-search"></i>
    </button>
    <?php if (!empty($_GET['q'])): ?>
        <a href="/clientes" class="btn btn--ghost">Limpar</a>
    <?php endif; ?>
</form>

<!-- Lista mobile -->
<div class="mobile-list desktop-hide">
    <?php if (empty($clientes)): ?>
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="empty__title">Nenhum cliente encontrado</div>
        </div>
    <?php else: ?>
        <?php foreach ($clientes as $c): ?>
        <a href="/clientes/<?= $c['id'] ?>" class="mobile-list__item">
            <div class="mobile-list__main"><?= htmlspecialchars($c['name']) ?></div>
            <div class="mobile-list__sub">
                <?= htmlspecialchars($c['email'] ?? '') ?>
                <?= $c['phone'] ? ' · ' . htmlspecialchars($c['phone']) : '' ?>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Tabela desktop -->
<div class="table-wrap mobile-hide">
    <?php if (empty($clientes)): ?>
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="empty__title">Nenhum cliente encontrado</div>
            <a href="/clientes/novo" class="btn btn--primary btn--sm">Cadastrar cliente</a>
        </div>
    <?php else: ?>
    <table data-search-table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Documento</th>
                <th class="td-actions">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr data-search-row>
                <td><a href="/clientes/<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></td>
                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['document'] ?? '') ?></td>
                <td class="td-actions">
                    <a href="/clientes/<?= $c['id'] ?>/editar" class="btn btn--secondary btn--sm">Editar</a>
                    <form action="/clientes/<?= $c['id'] ?>/excluir" method="POST" style="display:inline">
                        <button type="submit" class="btn btn--danger btn--sm"
                                data-confirm="Remover <?= htmlspecialchars($c['name']) ?>?">Remover</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
