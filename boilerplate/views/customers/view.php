<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:var(--space-sm)">
        <a href="/customers" class="btn btn--ghost btn--sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-header__title"><?= htmlspecialchars($customer['name']) ?></h1>
    </div>
    <a href="/customers/<?= $customer['id'] ?>/edit" class="btn btn--secondary btn--sm">
        <i class="bi bi-pencil"></i> Edit
    </a>
</div>

<?php if ($success ?? false): ?>
    <div class="alert alert--success" data-auto-close>
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- Customer header -->
<div class="detail-header">
    <div class="detail-avatar"><?= mb_strtoupper(mb_substr($customer['name'], 0, 1)) ?></div>
    <div class="detail-info">
        <div class="detail-name"><?= htmlspecialchars($customer['name']) ?></div>
        <div class="detail-sub">
            Customer since <?= date('d/m/Y', strtotime($customer['created_at'])) ?>
        </div>
    </div>
</div>

<!-- Customer data -->
<div class="card">
    <div class="card__header">
        <span class="card__title">Details</span>
    </div>
    <div class="card__body">
        <dl class="dl-grid">
            <div class="dl-item">
                <dt>Email</dt>
                <dd><?= htmlspecialchars($customer['email'] ?? '—') ?></dd>
            </div>
            <div class="dl-item">
                <dt>Phone</dt>
                <dd><?= htmlspecialchars($customer['phone'] ?? '—') ?></dd>
            </div>
            <div class="dl-item">
                <dt>CPF / CNPJ</dt>
                <dd><?= htmlspecialchars($customer['document'] ?? '—') ?></dd>
            </div>
            <div class="dl-item">
                <dt>Address</dt>
                <dd><?= htmlspecialchars($customer['address'] ?? '—') ?></dd>
            </div>
            <?php if (!empty($customer['notes'])): ?>
            <div class="dl-item" style="grid-column:1/-1">
                <dt>Notes</dt>
                <dd><?= nl2br(htmlspecialchars($customer['notes'])) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>
</div>

<!-- Customer transactions -->
<div class="card">
    <div class="card__header">
        <span class="card__title"><i class="bi bi-cash-stack"></i> Transactions</span>
        <a href="/finance/new?customer_id=<?= $customer['id'] ?>" class="btn btn--primary btn--sm">
            <i class="bi bi-plus-lg"></i> Add transaction
        </a>
    </div>
    <?php if (empty($transactions)): ?>
        <div class="empty">
            <i class="bi bi-cash-stack"></i>
            <div class="empty__title">No transactions</div>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($t['entry_date'])) ?></td>
                    <td><?= htmlspecialchars($t['description']) ?></td>
                    <td><span class="badge badge--<?= $t['type'] === 'income' ? 'active' : 'cancelled' ?>">
                        <?= ucfirst($t['type']) ?>
                    </span></td>
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
