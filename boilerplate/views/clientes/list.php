<?php require ROOT . '/views/layout.php'; ?>

<div class="pagina-topo">
    <h1 class="pagina-topo__titulo">Clientes</h1>
    <a href="/clientes/novo" class="btn btn--primario desktop-only">
        <i class="bi bi-plus-lg"></i> Novo Cliente
    </a>
</div>

<?php if ($sucesso = Session::getFlash('sucesso')): ?>
    <div class="alerta alerta--sucesso" data-auto-fechar>
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
    </div>
<?php endif; ?>

<div class="barra-busca">
    <div class="campo-busca">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Buscar clientes..." data-busca-tabela="#lista-clientes">
    </div>
</div>

<!-- Desktop: tabela -->
<div class="card desktop-only">
    <div class="tabela-wrap">
        <table id="lista-clientes">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Telefone</th>
                    <th>Documento</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                <tr><td colspan="5">
                    <div class="vazio">
                        <i class="bi bi-people"></i>
                        <div class="vazio__titulo">Nenhum cliente encontrado</div>
                    </div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr data-busca-linha="<?= htmlspecialchars($c['nome'] . ' ' . $c['email'] . ' ' . $c['telefone']) ?>">
                    <td>
                        <a href="/clientes/<?= $c['id'] ?>" style="font-weight:600;color:var(--primary)">
                            <?= htmlspecialchars($c['nome']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['telefone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['documento'] ?? '') ?></td>
                    <td>
                        <div class="td-acoes">
                            <a href="/clientes/<?= $c['id'] ?>/editar" class="btn btn--secundario btn--sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="/clientes/<?= $c['id'] ?>/excluir" method="POST" style="display:inline">
                                <button type="submit" class="btn btn--perigo btn--sm"
                                    data-confirmar="Remover <?= htmlspecialchars($c['nome']) ?>?">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile: lista de cards -->
<div class="lista-mobile mobile-only" id="lista-clientes">
    <?php if (empty($clientes)): ?>
        <div class="vazio">
            <i class="bi bi-people"></i>
            <div class="vazio__titulo">Nenhum cliente</div>
            <a href="/clientes/novo" class="btn btn--primario btn--sm" style="margin-top:8px">Cadastrar</a>
        </div>
    <?php else: ?>
        <?php foreach ($clientes as $c): ?>
        <a href="/clientes/<?= $c['id'] ?>" class="lista-mobile__item"
           data-busca-linha="<?= htmlspecialchars($c['nome'] . ' ' . $c['email'] . ' ' . $c['telefone']) ?>">
            <div class="lista-mobile__avatar"><?= mb_strtoupper(mb_substr($c['nome'], 0, 1)) ?></div>
            <div class="lista-mobile__info">
                <div class="lista-mobile__nome"><?= htmlspecialchars($c['nome']) ?></div>
                <div class="lista-mobile__sub"><?= htmlspecialchars($c['email'] ?? $c['telefone'] ?? '') ?></div>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--gray)"></i>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB mobile -->
<a href="/clientes/novo" class="fab">
    <i class="bi bi-plus-lg"></i>
</a>

<?php require ROOT . '/views/layout_fim.php'; ?>
