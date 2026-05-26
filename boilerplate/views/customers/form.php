<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:var(--space-sm)">
        <a href="/customers" class="btn btn--ghost btn--sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-header__title"><?= isset($customer['id']) ? 'Edit Customer' : 'New Customer' ?></h1>
    </div>
</div>

<?php if ($error ?? false): ?>
    <div class="alert alert--error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <form action="<?= isset($customer['id']) ? "/customers/{$customer['id']}" : '/customers' ?>" method="POST" class="form">

            <div class="form-grid">
                <div class="field field--full">
                    <label class="field__label" for="name">Name *</label>
                    <input class="field__input" type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($customer['name'] ?? '') ?>" placeholder="Full name">
                </div>
                <div class="field">
                    <label class="field__label" for="email">Email</label>
                    <input class="field__input" type="email" id="email" name="email"
                           value="<?= htmlspecialchars($customer['email'] ?? '') ?>" placeholder="email@example.com">
                </div>
                <div class="field">
                    <label class="field__label" for="phone">Phone</label>
                    <input class="field__input" type="tel" id="phone" name="phone"
                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                           placeholder="(00) 00000-0000" data-mask="phone">
                </div>
                <div class="field">
                    <label class="field__label" for="document">CPF / CNPJ</label>
                    <input class="field__input" type="text" id="document" name="document"
                           value="<?= htmlspecialchars($customer['document'] ?? '') ?>"
                           placeholder="000.000.000-00" data-mask="cpfcnpj">
                </div>
                <div class="field field--full">
                    <label class="field__label" for="address">Address</label>
                    <input class="field__input" type="text" id="address" name="address"
                           value="<?= htmlspecialchars($customer['address'] ?? '') ?>"
                           placeholder="Street, number, neighborhood, city">
                </div>
                <div class="field field--full">
                    <label class="field__label" for="notes">Notes</label>
                    <textarea class="field__input field__textarea" id="notes" name="notes"
                              placeholder="Notes about this customer..."><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= isset($customer['id']) ? "/customers/{$customer['id']}" : '/customers' ?>"
                   class="btn btn--ghost">Cancel</a>
                <button type="submit" class="btn btn--primary">
                    <i class="bi bi-check-lg"></i>
                    <?= isset($customer['id']) ? 'Save changes' : 'Add customer' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
