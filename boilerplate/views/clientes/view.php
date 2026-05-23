<?php require ROOT . '/views/layout.php'; ?>

<div class="pagina-topo">
    <div style="display:flex;align-items:center;gap:var(--space-sm)">
        <a href="/clientes" class="btn btn--ghost btn--sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="pagina-topo__titulo"><?= htmlspecialchars($cliente['nome']) ?></h1>
    </div>
    <a href="/clientes/<?= $cliente['id'] ?>/editar" class="btn btn--secundario btn--sm">
        <i class="bi bi-pencil"></i> Editar
    </a>
</div>

<?php if ($sucesso ?? false): ?>
    <div class="alerta alerta--sucesso" data-auto-fechar>
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
    </div>
<?php endif; ?>

<!-- Header do cliente -->
<div class="detalhe-header">
    <div class="detalhe-avatar"><?= mb_strtoupper(mb_substr($cliente['nome'], 0, 1)) ?></div>
    <div class="detalhe-info">
        <div class="detalhe-nome"><?= htmlspecialchars($cliente['nome']) ?></div>
        <div class="detalhe-sub">
            Cliente desde <?= date('d/m/Y', strtotime($cliente['created_at'])) ?>
        </div>
    </div>
</div>

<!-- Dados do cliente -->
<div class="card">
    <div class="card__cabecalho">
        <span class="card__titulo">Dados</span>
    </div>
    <div class="card__corpo">
        <dl class="dl-grid">
            <div class="dl-item">
                <dt>E-mail</dt>
                <dd><?= htmlspecialchars($cliente['email'] ?? '—') ?></dd>
            </div>
            <div class="dl-item">
                <dt>Telefone</dt>
                <dd><?= htmlspecialchars($cliente['telefone'] ?? '—') ?></dd>
            </div>
            <div class="dl-item">
                <dt>CPF / CNPJ</dt>
                <dd><?= htmlspecialchars($cliente['documento'] ?? '—') ?></dd>
            </div>
            <div class="dl-item">
                <dt>Endereço</dt>
                <dd><?= htmlspecialchars($cliente['endereco'] ?? '—') ?></dd>
            </div>
            <?php if (!empty($cliente['observacoes'])): ?>
            <div class="dl-item" style="grid-column:1/-1">
                <dt>Observações</dt>
                <dd><?= nl2br(htmlspecialchars($cliente['observacoes'])) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>
</div>

<!-- Lançamentos do cliente -->
<div class="card">
    <div class="card__cabecalho">
        <span class="card__titulo"><i class="bi bi-cash-stack"></i> Financeiro</span>
        <a href="/financeiro/novo?cliente_id=<?= $cliente['id'] ?>" class="btn btn--primario btn--sm">
            <i class="bi bi-plus-lg"></i> Lançamento
        </a>
    </div>
    <?php if (empty($lancamentos)): ?>
        <div class="vazio">
            <i class="bi bi-cash-stack"></i>
            <div class="vazio__titulo">Sem lançamentos</div>
        </div>
    <?php else: ?>
    <div class="tabela-wrap">
        <table>
            <thead><tr><th>Data</th><th>Descrição</th><th>Tipo</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($lancamentos as $l): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
                    <td><?= htmlspecialchars($l['descricao']) ?></td>
                    <td><span class="badge badge--<?= $l['tipo'] === 'receita' ? 'ativo' : 'cancelado' ?>">
                        <?= ucfirst($l['tipo']) ?>
                    </span></td>
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
