<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-card__logo">
        <div style="font-size:2rem;color:var(--primary)"><i class="bi bi-grid-3x3-gap-fill"></i></div>
    </div>
    <h1 class="login-card__title"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?></h1>
    <p class="login-card__subtitle">Sign in to your account</p>

    <?php if ($error ?? false): ?>
        <div class="alert alert--error">
            <i class="bi bi-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="/login/submit" method="POST" class="form">
        <div class="field">
            <label class="field__label" for="email">Email</label>
            <input class="field__input" type="email" id="email" name="email" required
                   placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="field">
            <label class="field__label" for="password">Password</label>
            <input class="field__input" type="password" id="password" name="password" required
                   placeholder="••••••••" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn--primary btn--lg">
            <i class="bi bi-arrow-right-circle"></i> Sign in
        </button>
    </form>

    <p class="login-card__footer">
        <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?> — powered by Fenor
    </p>
</div>

<script src="/assets/js/app.js"></script>
</body>
</html>
