<?php require ROOT . '/views/layout.php'; ?>

<div class="page-header">
    <h1 class="page-header__title"><?= htmlspecialchars($cliente['name']) ?></h1>
    <div class="page-header__actions">
        <a href="/clientes/<?= $cliente['id'] ?>/editar" class="btn btn--secondary">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="/clientes" class="btn btn--ghost">← Voltar</a>
    </div>
</div>

<?php if ($sucesso ?? false): ?>
    <div class="alert alert--success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<div class="detail-grid">

    <!-- Dados do cliente -->
    <div class="card">
        <div class="card__header"><h2 class="card__title">Dados</h2></div>
        <div class="card__body">
            <dl class="detail-list">
                <div class="detail-item">
                    <dt class="detail-item__label">Nome</dt>
                    <dd class="detail-item__value"><?= htmlspecialchars($cliente['name']) ?></dd>
                </div>
                <?php if ($cliente['email']): ?>
                <div class="detail-item">
                    <dt class="detail-item__label">E-mail</dt>
                    <dd class="detail-item__value"><?= htmlspecialchars($cliente['email']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($cliente['phone']): ?>
                <div class="detail-item">
                    <dt class="detail-item__label">Telefone</dt>
                    <dd class="detail-item__value"><?= htmlspecialchars($cliente['phone']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($cliente['document']): ?>
                <div class="detail-item">
                    <dt class="detail-item__label">CPF / CNPJ</dt>
                    <dd class="detail-item__value"><?= htmlspecialchars($cliente['document']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($cliente['address']): ?>
                <div class="detail-item">
                    <dt class="detail-item__label">Endereço</dt>
                    <dd class="detail-item__value"><?= htmlspecialchars($cliente['address']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($cliente['notes']): ?>
                <div class="detail-item">
                    <dt class="detail-item__label">Observações</dt>
                    <dd class="detail-item__value"><?= nl2br(htmlspecialchars($cliente['notes'])) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- Lançamentos do cliente -->
    <div class="card">
        <div class="card__header"><h2 class="card__title">Lançamentos</h2></div>
        <div class="card__body">
            <?php if (empty($lancamentos)): ?>
                <div class="empty">
                    <i class="bi bi-cash-stack"></i>
                    <div class="empty__title">Nenhum lançamento</div>
                </div>
            <?php else: ?>
            <div class="mobile-list">
                <?php foreach ($lancamentos as $t): ?>
                <div class="mobile-list__item">
                    <div class="mobile-list__main"><?= htmlspecialchars($t['description']) ?></div>
                    <div class="mobile-list__sub">
                        <span class="badge badge--<?= $t['status'] ?>">
                            <?= match($t['status']) {
                                'paid'      => 'Pago',
                                'pending'   => 'Pendente',
                                'cancelled' => 'Cancelado',
                                default     => $t['status']
                            } ?>
                        </span>
                        R$ <?= number_format((float) $t['amount'], 2, ',', '.') ?>
                        · <?= date('d/m/Y', strtotime($t['entry_date'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require ROOT . '/views/layout_fim.php'; ?>
