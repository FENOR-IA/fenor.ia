<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title">Users</h1>
    <a href="/users/new" class="btn btn--primary">
        <i class="bi bi-plus-lg"></i> New User
    </a>
</div>

<?php if ($success ?? false): ?>
    <div class="alert alert--success" data-auto-close>
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="5"><div class="empty"><i class="bi bi-person"></i><div class="empty__title">No users</div></div></td></tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:var(--space-sm)">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">
                                <?= mb_strtoupper(mb_substr($u['name'], 0, 1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($u['name']) ?></strong>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge--<?= $u['role'] === 'admin' ? 'admin' : 'inactive' ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><span class="badge badge--<?= $u['active'] ? 'active' : 'inactive' ?>"><?= $u['active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="td-actions">
                            <a href="/users/<?= $u['id'] ?>/edit" class="btn btn--secondary btn--sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
