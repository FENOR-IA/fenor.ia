<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title">Painel</h1>
</div>

<!-- Alertas de sessão -->
<?php if ($sucesso = Session::getFlash('sucesso')): ?>
    <div class="alert alert--success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<!-- Cards de resumo -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__label">Clientes ativos</div>
        <div class="stat-card__value"><?= number_format((int) $totalClientes) ?></div>
    </div>
    <div class="stat-card stat-card--income">
        <div class="stat-card__label">A receber</div>
        <div class="stat-card__value">R$ <?= number_format((float) ($financeiro['a_receber'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="stat-card stat-card--expense">
        <div class="stat-card__label">A pagar</div>
        <div class="stat-card__value">R$ <?= number_format((float) ($financeiro['a_pagar'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Recebido no mês</div>
        <div class="stat-card__value">R$ <?= number_format((float) ($financeiro['recebido_mes'] ?? 0), 2, ',', '.') ?></div>
    </div>
</div>

<!-- Tabelas lado a lado -->
<div class="dashboard-grid">

    <!-- Clientes recentes -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Clientes recentes</h2>
            <a href="/clientes/novo" class="btn btn--primary btn--sm">
                <i class="bi bi-plus"></i> Novo
            </a>
        </div>
        <div class="card__body">
            <?php if (empty($clientesRecentes)): ?>
                <div class="empty">
                    <i class="bi bi-people"></i>
                    <div class="empty__title">Nenhum cliente ainda</div>
                    <a href="/clientes/novo" class="btn btn--primary btn--sm">Cadastrar primeiro cliente</a>
                </div>
            <?php else: ?>
                <div class="mobile-list">
                    <?php foreach ($clientesRecentes as $c): ?>
                    <a href="/clientes/<?= $c['id'] ?>" class="mobile-list__item">
                        <div class="mobile-list__main"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="mobile-list__sub"><?= htmlspecialchars($c['email'] ?? '') ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="card__footer">
                    <a href="/clientes" class="btn btn--ghost btn--sm">Ver todos →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lançamentos recentes -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Lançamentos recentes</h2>
        </div>
        <div class="card__body">
            <?php if (empty($lancamentosRecentes)): ?>
                <div class="empty">
                    <i class="bi bi-cash-stack"></i>
                    <div class="empty__title">Nenhum lançamento ainda</div>
                </div>
            <?php else: ?>
                <div class="mobile-list">
                    <?php foreach ($lancamentosRecentes as $t): ?>
                    <div class="mobile-list__item">
                        <div class="mobile-list__main"><?= htmlspecialchars($t['description']) ?></div>
                        <div class="mobile-list__sub">
                            <span class="badge badge--<?= $t['status'] ?>">
                                <?= match($t['status']) {
                                    'paid'      => 'Pago',
                                    'pending'   => 'Pendente',
                                    'cancelled' => 'Cancelado',
                                    default     => $t['status']
                                } ?>
                            </span>
                            &nbsp;R$ <?= number_format((float) $t['amount'], 2, ',', '.') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
