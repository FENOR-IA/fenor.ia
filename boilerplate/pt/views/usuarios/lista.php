<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title">Usuários</h1>
    <a href="/usuarios/novo" class="btn btn--primary">
        <i class="bi bi-plus-circle"></i> Novo usuário
    </a>
</div>

<?php if ($sucesso = Session::getFlash('sucesso')): ?>
    <div class="alert alert--success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>
<?php if ($erro = Session::getFlash('erro')): ?>
    <div class="alert alert--error"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="table-wrap">
    <?php if (empty($usuarios)): ?>
        <div class="empty">
            <i class="bi bi-person-gear"></i>
            <div class="empty__title">Nenhum usuário</div>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Status</th>
                <th class="td-actions">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'badge--active' : 'badge--inactive' ?>">
                        <?= $u['role'] === 'admin' ? 'Admin' : 'Usuário' ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $u['active'] ? 'badge--active' : 'badge--inactive' ?>">
                        <?= $u['active'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td class="td-actions">
                    <a href="/usuarios/<?= $u['id'] ?>/editar" class="btn btn--secondary btn--sm">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
