<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Dashboard</h1>
        <p class="page-header__subtitle">Welcome, <?= htmlspecialchars(Session::user()['name'] ?? '') ?></p>
    </div>
</div>

<!-- Summary cards — replace these with your real data -->
<div class="grid-cards">
    <div class="card-kpi card-kpi--primary">
        <i class="bi bi-bar-chart card-kpi__icon"></i>
        <span class="card-kpi__label">Metric 1</span>
        <span class="card-kpi__value">—</span>
    </div>
    <div class="card-kpi">
        <i class="bi bi-graph-up card-kpi__icon"></i>
        <span class="card-kpi__label">Metric 2</span>
        <span class="card-kpi__value">—</span>
    </div>
    <div class="card-kpi">
        <i class="bi bi-activity card-kpi__icon"></i>
        <span class="card-kpi__label">Metric 3</span>
        <span class="card-kpi__value">—</span>
    </div>
    <div class="card-kpi">
        <i class="bi bi-check-circle card-kpi__icon"></i>
        <span class="card-kpi__label">Metric 4</span>
        <span class="card-kpi__value">—</span>
    </div>
</div>

<!-- Main content area — build your modules here -->
<div class="card">
    <div class="card__header">
        <span class="card__title"><i class="bi bi-grid"></i> Your first module</span>
    </div>
    <div class="empty" style="padding: 3rem;">
        <i class="bi bi-terminal" style="font-size:2.5rem;color:var(--primary);"></i>
        <div class="empty__title" style="margin-top:.75rem;">Ready to build</div>
        <p style="color:var(--muted);font-size:.875rem;margin-top:.5rem;max-width:320px;text-align:center;">
            Ask Claude to build your first feature.<br>
            Type <code>claude</code> in the terminal to get started.
        </p>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
