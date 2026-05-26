<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Dashboard</h1>
        <p class="page-header__subtitle">Welcome, <?= htmlspecialchars(Session::user()['name'] ?? '') ?></p>
    </div>
</div>

<!-- KPIs -->
<div class="grid-cards">
    <div class="card-kpi card-kpi--primary">
        <i class="bi bi-people card-kpi__icon"></i>
        <span class="card-kpi__label">Active customers</span>
        <span class="card-kpi__value"><?= number_format($totalCustomers) ?></span>
    </div>
    <div class="card-kpi" style="border-left:3px solid var(--success)">
        <i class="bi bi-arrow-down-circle card-kpi__icon" style="color:var(--success)"></i>
        <span class="card-kpi__label">Receivable</span>
        <span class="card-kpi__value">R$ <?= number_format($financial['receivable'], 2, ',', '.') ?></span>
    </div>
    <div class="card-kpi" style="border-left:3px solid var(--error)">
        <i class="bi bi-arrow-up-circle card-kpi__icon" style="color:var(--error)"></i>
        <span class="card-kpi__label">Payable</span>
        <span class="card-kpi__value">R$ <?= number_format($financial['payable'], 2, ',', '.') ?></span>
    </div>
    <div class="card-kpi" style="border-left:3px solid var(--accent)">
        <i class="bi bi-calendar-check card-kpi__icon" style="color:var(--accent)"></i>
        <span class="card-kpi__label">Received this month</span>
        <span class="card-kpi__value">R$ <?= number_format($financial['received_this_month'], 2, ',', '.') ?></span>
    </div>
</div>

<!-- Recent customers -->
<div class="card">
    <div class="card__header">
        <span class="card__title"><i class="bi bi-people"></i> Recent customers</span>
        <a href="/customers" class="btn btn--secondary btn--sm">View all</a>
    </div>
    <?php if (empty($recentCustomers)): ?>
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="empty__title">No customers yet</div>
            <a href="/customers/new" class="btn btn--primary btn--sm" style="margin-top:8px">Add first customer</a>
        </div>
    <?php else: ?>
    <div class="table-wrap desktop-only">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th></tr></thead>
            <tbody>
                <?php foreach ($recentCustomers as $c): ?>
                <tr onclick="location.href='/customers/<?= $c['id'] ?>'" style="cursor:pointer">
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                    <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mobile-list mobile-only" style="padding:var(--space-sm)">
        <?php foreach ($recentCustomers as $c): ?>
        <a href="/customers/<?= $c['id'] ?>" class="mobile-list__item">
            <div class="mobile-list__avatar"><?= mb_strtoupper(mb_substr($c['name'], 0, 1)) ?></div>
            <div class="mobile-list__info">
                <div class="mobile-list__name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="mobile-list__sub"><?= htmlspecialchars($c['email'] ?? $c['phone'] ?? '') ?></div>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--gray)"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Recent transactions -->
<div class="card">
    <div class="card__header">
        <span class="card__title"><i class="bi bi-cash-stack"></i> Recent transactions</span>
        <a href="/finance" class="btn btn--secondary btn--sm">View all</a>
    </div>
    <?php if (empty($recentTransactions)): ?>
        <div class="empty">
            <i class="bi bi-cash-stack"></i>
            <div class="empty__title">No transactions yet</div>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recentTransactions as $t): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($t['entry_date'])) ?></td>
                    <td><?= htmlspecialchars($t['description']) ?></td>
                    <td>
                        <span class="badge <?= $t['type'] === 'income' ? 'badge--active' : 'badge--cancelled' ?>">
                            <?= $t['type'] === 'income' ? 'Income' : 'Expense' ?>
                        </span>
                    </td>
                    <td><strong>R$ <?= number_format($t['amount'], 2, ',', '.') ?></strong></td>
                    <td><span class="badge badge--<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
