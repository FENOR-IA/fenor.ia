<?php require ROOT . '/views/layout.php'; ?>

<div class="pagina-topo">
    <div>
        <h1 class="pagina-topo__titulo">Dashboard</h1>
        <p class="pagina-topo__subtitulo">Bem-vindo, <?= htmlspecialchars(Session::usuario()['nome'] ?? '') ?></p>
    </div>
</div>

<!-- KPIs -->
<div class="grid-cards">
    <div class="card-kpi card-kpi--primario">
        <i class="bi bi-people card-kpi__icone"></i>
        <span class="card-kpi__label">Clientes ativos</span>
        <span class="card-kpi__valor"><?= number_format($totalClientes) ?></span>
    </div>
    <div class="card-kpi" style="border-left:3px solid var(--success)">
        <i class="bi bi-arrow-down-circle card-kpi__icone" style="color:var(--success)"></i>
        <span class="card-kpi__label">A receber</span>
        <span class="card-kpi__valor">R$ <?= number_format($financeiro['a_receber'], 2, ',', '.') ?></span>
    </div>
    <div class="card-kpi" style="border-left:3px solid var(--error)">
        <i class="bi bi-arrow-up-circle card-kpi__icone" style="color:var(--error)"></i>
        <span class="card-kpi__label">A pagar</span>
        <span class="card-kpi__valor">R$ <?= number_format($financeiro['a_pagar'], 2, ',', '.') ?></span>
    </div>
    <div class="card-kpi" style="border-left:3px solid var(--accent)">
        <i class="bi bi-calendar-check card-kpi__icone" style="color:var(--accent)"></i>
        <span class="card-kpi__label">Recebido no mês</span>
        <span class="card-kpi__valor">R$ <?= number_format($financeiro['recebido_mes'], 2, ',', '.') ?></span>
    </div>
</div>

<!-- Últimos clientes -->
<div class="card">
    <div class="card__cabecalho">
        <span class="card__titulo"><i class="bi bi-people"></i> Últimos clientes</span>
        <a href="/clientes" class="btn btn--secundario btn--sm">Ver todos</a>
    </div>
    <?php if (empty($ultimosClientes)): ?>
        <div class="vazio">
            <i class="bi bi-people"></i>
            <div class="vazio__titulo">Nenhum cliente cadastrado</div>
            <a href="/clientes/novo" class="btn btn--primario btn--sm" style="margin-top:8px">Cadastrar primeiro cliente</a>
        </div>
    <?php else: ?>
    <div class="tabela-wrap desktop-only">
        <table>
            <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Cadastrado</th></tr></thead>
            <tbody>
                <?php foreach ($ultimosClientes as $c): ?>
                <tr onclick="location.href='/clientes/<?= $c['id'] ?>'" style="cursor:pointer">
                    <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                    <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['telefone'] ?? '') ?></td>
                    <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="lista-mobile mobile-only" style="padding:var(--space-sm)">
        <?php foreach ($ultimosClientes as $c): ?>
        <a href="/clientes/<?= $c['id'] ?>" class="lista-mobile__item">
            <div class="lista-mobile__avatar"><?= mb_strtoupper(mb_substr($c['nome'], 0, 1)) ?></div>
            <div class="lista-mobile__info">
                <div class="lista-mobile__nome"><?= htmlspecialchars($c['nome']) ?></div>
                <div class="lista-mobile__sub"><?= htmlspecialchars($c['email'] ?? $c['telefone'] ?? '') ?></div>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--gray)"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Últimos lançamentos -->
<div class="card">
    <div class="card__cabecalho">
        <span class="card__titulo"><i class="bi bi-cash-stack"></i> Últimos lançamentos</span>
        <a href="/financeiro" class="btn btn--secundario btn--sm">Ver todos</a>
    </div>
    <?php if (empty($ultimosLancamentos)): ?>
        <div class="vazio">
            <i class="bi bi-cash-stack"></i>
            <div class="vazio__titulo">Nenhum lançamento</div>
        </div>
    <?php else: ?>
    <div class="tabela-wrap">
        <table>
            <thead><tr><th>Data</th><th>Descrição</th><th>Tipo</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($ultimosLancamentos as $l): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
                    <td><?= htmlspecialchars($l['descricao']) ?></td>
                    <td>
                        <span class="badge <?= $l['tipo'] === 'receita' ? 'badge--ativo' : 'badge--cancelado' ?>">
                            <?= $l['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?>
                        </span>
                    </td>
                    <td><strong>R$ <?= number_format($l['valor'], 2, ',', '.') ?></strong></td>
                    <td><span class="badge badge--<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
