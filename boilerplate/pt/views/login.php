<?php
$tituloPagina = 'Entrar';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'App') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="login-body">

<div class="login-box">
    <div class="login-box__header">
        <h1 class="login-box__title"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'App') ?></h1>
        <p class="login-box__sub">Acesse sua conta</p>
    </div>

    <?php if ($erro ?? false): ?>
        <div class="alert alert--error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form action="/entrar/salvar" method="POST" class="login-box__form">
        <div class="field">
            <label for="email" class="field__label">E-mail</label>
            <input type="email" id="email" name="email" class="field__input"
                   required autofocus autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="seu@email.com">
        </div>
        <div class="field">
            <label for="senha" class="field__label">Senha</label>
            <input type="password" id="senha" name="senha" class="field__input"
                   required autocomplete="current-password"
                   placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn--primary btn--full">
            Entrar <i class="bi bi-arrow-right"></i>
        </button>
    </form>
</div>

</body>
</html>
