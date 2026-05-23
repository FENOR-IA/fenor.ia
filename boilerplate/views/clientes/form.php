<?php require ROOT . '/views/layout.php'; ?>

<div class="pagina-topo">
    <div style="display:flex;align-items:center;gap:var(--space-sm)">
        <a href="/clientes" class="btn btn--ghost btn--sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="pagina-topo__titulo"><?= isset($cliente['id']) ? 'Editar Cliente' : 'Novo Cliente' ?></h1>
    </div>
</div>

<?php if ($erro ?? false): ?>
    <div class="alerta alerta--erro"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__corpo">
        <form action="<?= isset($cliente['id']) ? "/clientes/{$cliente['id']}" : '/clientes' ?>" method="POST" class="formulario">

            <div class="form-grid">
                <div class="campo campo--full">
                    <label class="campo__label" for="nome">Nome *</label>
                    <input class="campo__input" type="text" id="nome" name="nome" required
                           value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" placeholder="Nome completo">
                </div>
                <div class="campo">
                    <label class="campo__label" for="email">E-mail</label>
                    <input class="campo__input" type="email" id="email" name="email"
                           value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" placeholder="email@exemplo.com">
                </div>
                <div class="campo">
                    <label class="campo__label" for="telefone">Telefone</label>
                    <input class="campo__input" type="tel" id="telefone" name="telefone"
                           value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>"
                           placeholder="(00) 00000-0000" data-mask="telefone">
                </div>
                <div class="campo">
                    <label class="campo__label" for="documento">CPF / CNPJ</label>
                    <input class="campo__input" type="text" id="documento" name="documento"
                           value="<?= htmlspecialchars($cliente['documento'] ?? '') ?>"
                           placeholder="000.000.000-00" data-mask="cpfcnpj">
                </div>
                <div class="campo campo--full">
                    <label class="campo__label" for="endereco">Endereço</label>
                    <input class="campo__input" type="text" id="endereco" name="endereco"
                           value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>"
                           placeholder="Rua, número, bairro, cidade">
                </div>
                <div class="campo campo--full">
                    <label class="campo__label" for="observacoes">Observações</label>
                    <textarea class="campo__input campo__textarea" id="observacoes" name="observacoes"
                              placeholder="Anotações sobre o cliente..."><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-acoes">
                <a href="<?= isset($cliente['id']) ? "/clientes/{$cliente['id']}" : '/clientes' ?>"
                   class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primario">
                    <i class="bi bi-check-lg"></i>
                    <?= isset($cliente['id']) ? 'Salvar alterações' : 'Cadastrar cliente' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
