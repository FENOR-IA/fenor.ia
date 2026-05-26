<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title"><?= htmlspecialchars($tituloPagina) ?></h1>
    <a href="/clientes" class="btn btn--ghost">← Voltar</a>
</div>

<?php if ($erro ?? false): ?>
    <div class="alert alert--error"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <form action="<?= isset($cliente['id']) ? '/clientes/' . $cliente['id'] : '/clientes' ?>" method="POST">

            <div class="field">
                <label class="field__label">Nome <span class="required">*</span></label>
                <input type="text" name="name" class="field__input"
                       value="<?= htmlspecialchars($cliente['name'] ?? '') ?>"
                       required autofocus placeholder="Nome completo">
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label">E-mail</label>
                    <input type="email" name="email" class="field__input"
                           value="<?= htmlspecialchars($cliente['email'] ?? '') ?>"
                           placeholder="email@exemplo.com">
                </div>
                <div class="field">
                    <label class="field__label">Telefone</label>
                    <input type="text" name="phone" class="field__input"
                           value="<?= htmlspecialchars($cliente['phone'] ?? '') ?>"
                           placeholder="(00) 00000-0000" data-mask="phone">
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label">CPF / CNPJ</label>
                    <input type="text" name="document" class="field__input"
                           value="<?= htmlspecialchars($cliente['document'] ?? '') ?>"
                           placeholder="000.000.000-00">
                </div>
                <div class="field">
                    <label class="field__label">Endereço</label>
                    <input type="text" name="address" class="field__input"
                           value="<?= htmlspecialchars($cliente['address'] ?? '') ?>"
                           placeholder="Rua, número, bairro">
                </div>
            </div>

            <div class="field">
                <label class="field__label">Observações</label>
                <textarea name="notes" class="field__input" rows="3"
                          placeholder="Informações adicionais..."><?= htmlspecialchars($cliente['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a href="/clientes" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">
                    <?= isset($cliente['id']) ? 'Salvar alterações' : 'Cadastrar cliente' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
