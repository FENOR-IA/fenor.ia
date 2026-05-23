<?php require ROOT . '/views/layout.php'; ?>

<div class="pagina-topo">
    <div style="display:flex;align-items:center;gap:var(--space-sm)">
        <a href="/usuarios" class="btn btn--ghost btn--sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="pagina-topo__titulo"><?= isset($usuario['id']) ? 'Editar Usuário' : 'Novo Usuário' ?></h1>
    </div>
</div>

<?php if ($erro ?? false): ?>
    <div class="alerta alerta--erro"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__corpo">
        <form action="<?= isset($usuario['id']) ? "/usuarios/{$usuario['id']}" : '/usuarios' ?>" method="POST" class="formulario">

            <div class="form-grid">
                <div class="campo campo--full">
                    <label class="campo__label">Nome *</label>
                    <input class="campo__input" type="text" name="nome" required
                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" placeholder="Nome completo">
                </div>
                <div class="campo">
                    <label class="campo__label">E-mail *</label>
                    <input class="campo__input" type="email" name="email" required
                           value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" placeholder="email@exemplo.com">
                </div>
                <div class="campo">
                    <label class="campo__label">Perfil</label>
                    <select class="campo__select" name="role">
                        <option value="user"  <?= ($usuario['role'] ?? '') === 'user'  ? 'selected' : '' ?>>Usuário</option>
                        <option value="admin" <?= ($usuario['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                <div class="campo">
                    <label class="campo__label">Senha <?= isset($usuario['id']) ? '(deixe em branco para manter)' : '*' ?></label>
                    <input class="campo__input" type="password" name="senha" <?= isset($usuario['id']) ? '' : 'required' ?>
                           placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                </div>
                <?php if (isset($usuario['id'])): ?>
                <div class="campo">
                    <label class="campo__label">Status</label>
                    <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer">
                        <input type="checkbox" name="ativo" value="1" <?= ($usuario['ativo'] ?? true) ? 'checked' : '' ?>>
                        Usuário ativo
                    </label>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-acoes">
                <a href="/usuarios" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primario">
                    <i class="bi bi-check-lg"></i>
                    <?= isset($usuario['id']) ? 'Salvar' : 'Criar usuário' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
