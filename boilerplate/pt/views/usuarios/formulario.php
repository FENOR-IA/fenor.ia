<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title"><?= htmlspecialchars($tituloPagina) ?></h1>
    <a href="/usuarios" class="btn btn--ghost">← Voltar</a>
</div>

<?php if ($erro ?? false): ?>
    <div class="alert alert--error"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <form action="<?= isset($usuario['id']) ? '/usuarios/' . $usuario['id'] : '/usuarios' ?>" method="POST">

            <div class="field-row">
                <div class="field">
                    <label class="field__label">Nome <span class="required">*</span></label>
                    <input type="text" name="name" class="field__input"
                           value="<?= htmlspecialchars($usuario['name'] ?? '') ?>"
                           required autofocus>
                </div>
                <div class="field">
                    <label class="field__label">E-mail <span class="required">*</span></label>
                    <input type="email" name="email" class="field__input"
                           value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                           required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label">Perfil</label>
                    <select name="role" class="field__input">
                        <option value="user"  <?= ($usuario['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>Usuário</option>
                        <option value="admin" <?= ($usuario['role'] ?? '')      === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label">
                        <?= isset($usuario['id']) ? 'Nova senha (deixe em branco para manter)' : 'Senha *' ?>
                    </label>
                    <input type="password" name="password" class="field__input"
                           <?= isset($usuario['id']) ? '' : 'required' ?>
                           placeholder="Mínimo 8 caracteres">
                </div>
            </div>

            <?php if (isset($usuario['id'])): ?>
            <div class="field">
                <label class="field__label field__label--inline">
                    <input type="checkbox" name="active" value="1"
                           <?= ($usuario['active'] ?? true) ? 'checked' : '' ?>>
                    Usuário ativo
                </label>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <a href="/usuarios" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">
                    <?= isset($usuario['id']) ? 'Salvar alterações' : 'Criar usuário' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
