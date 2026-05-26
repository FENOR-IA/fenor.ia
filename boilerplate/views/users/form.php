<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:var(--space-sm)">
        <a href="/users" class="btn btn--ghost btn--sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-header__title"><?= isset($user['id']) ? 'Edit User' : 'New User' ?></h1>
    </div>
</div>

<?php if ($error ?? false): ?>
    <div class="alert alert--error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <form action="<?= isset($user['id']) ? "/users/{$user['id']}" : '/users' ?>" method="POST" class="form">

            <div class="form-grid">
                <div class="field field--full">
                    <label class="field__label">Name *</label>
                    <input class="field__input" type="text" name="name" required
                           value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Full name">
                </div>
                <div class="field">
                    <label class="field__label">Email *</label>
                    <input class="field__input" type="email" name="email" required
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@example.com">
                </div>
                <div class="field">
                    <label class="field__label">Role</label>
                    <select class="field__select" name="role">
                        <option value="user"  <?= ($user['role'] ?? '') === 'user'  ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label">Password <?= isset($user['id']) ? '(leave blank to keep current)' : '*' ?></label>
                    <input class="field__input" type="password" name="password" <?= isset($user['id']) ? '' : 'required' ?>
                           placeholder="Minimum 6 characters" autocomplete="new-password">
                </div>
                <?php if (isset($user['id'])): ?>
                <div class="field">
                    <label class="field__label">Status</label>
                    <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer">
                        <input type="checkbox" name="active" value="1" <?= ($user['active'] ?? true) ? 'checked' : '' ?>>
                        Active user
                    </label>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <a href="/users" class="btn btn--ghost">Cancel</a>
                <button type="submit" class="btn btn--primary">
                    <i class="bi bi-check-lg"></i>
                    <?= isset($user['id']) ? 'Save' : 'Create user' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
