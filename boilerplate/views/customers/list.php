<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title">Customers</h1>
    <a href="/customers/new" class="btn btn--primary desktop-only">
        <i class="bi bi-plus-lg"></i> New Customer
    </a>
</div>

<?php if ($success = Session::getFlash('success')): ?>
    <div class="alert alert--success" data-auto-close>
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="search-bar">
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Search customers..." data-search-table="#customer-list">
    </div>
</div>

<!-- Desktop: table -->
<div class="card desktop-only">
    <div class="table-wrap">
        <table id="customer-list">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Document</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="5">
                    <div class="empty">
                        <i class="bi bi-people"></i>
                        <div class="empty__title">No customers found</div>
                    </div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr data-search-row="<?= htmlspecialchars($c['name'] . ' ' . $c['email'] . ' ' . $c['phone']) ?>">
                    <td>
                        <a href="/customers/<?= $c['id'] ?>" style="font-weight:600;color:var(--primary)">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['document'] ?? '') ?></td>
                    <td>
                        <div class="td-actions">
                            <a href="/customers/<?= $c['id'] ?>/edit" class="btn btn--secondary btn--sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="/customers/<?= $c['id'] ?>/delete" method="POST" style="display:inline">
                                <button type="submit" class="btn btn--danger btn--sm"
                                    data-confirm="Remove <?= htmlspecialchars($c['name']) ?>?">
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

<!-- Mobile: card list -->
<div class="mobile-list mobile-only" id="customer-list">
    <?php if (empty($customers)): ?>
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="empty__title">No customers</div>
            <a href="/customers/new" class="btn btn--primary btn--sm" style="margin-top:8px">Add customer</a>
        </div>
    <?php else: ?>
        <?php foreach ($customers as $c): ?>
        <a href="/customers/<?= $c['id'] ?>" class="mobile-list__item"
           data-search-row="<?= htmlspecialchars($c['name'] . ' ' . $c['email'] . ' ' . $c['phone']) ?>">
            <div class="mobile-list__avatar"><?= mb_strtoupper(mb_substr($c['name'], 0, 1)) ?></div>
            <div class="mobile-list__info">
                <div class="mobile-list__name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="mobile-list__sub"><?= htmlspecialchars($c['email'] ?? $c['phone'] ?? '') ?></div>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--gray)"></i>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB mobile -->
<a href="/customers/new" class="fab">
    <i class="bi bi-plus-lg"></i>
</a>

<?php require ROOT . '/views/layout_fim.php'; ?>
