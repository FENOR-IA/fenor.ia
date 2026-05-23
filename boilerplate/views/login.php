<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="corpo-login">

<div class="login-card">
    <div class="login-card__logo">
        <div style="font-size:2rem;color:var(--primary)"><i class="bi bi-grid-3x3-gap-fill"></i></div>
    </div>
    <h1 class="login-card__titulo"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?></h1>
    <p class="login-card__sub">Acesse sua conta para continuar</p>

    <?php if ($erro ?? false): ?>
        <div class="alerta alerta--erro">
            <i class="bi bi-exclamation-circle"></i>
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form action="/login/entrar" method="POST" class="formulario">
        <div class="campo">
            <label class="campo__label" for="email">E-mail</label>
            <input class="campo__input" type="email" id="email" name="email" required
                   placeholder="seu@email.com" autocomplete="email">
        </div>
        <div class="campo">
            <label class="campo__label" for="senha">Senha</label>
            <input class="campo__input" type="password" id="senha" name="senha" required
                   placeholder="••••••••" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn--primario btn--lg">
            <i class="bi bi-arrow-right-circle"></i> Entrar
        </button>
    </form>

    <p class="login-card__rodape">
        <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?> — powered by Fenor
    </p>
</div>

<script src="/assets/js/app.js"></script>
</body>
</html>
