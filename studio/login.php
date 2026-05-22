<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (!empty($_SESSION['user'])) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $config = require __DIR__ . '/config/config.php';

    if ($email === $config['admin_email'] && password_verify($pass, $config['admin_password_hash'])) {
        $_SESSION['user'] = ['email' => $email, 'role' => 'admin'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Email ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fenor Studio</title>
  <link rel="icon" type="image/png" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&display=swap">
  <style>
    :root {
      --ink:  #1A1613;
      --cream:#F6F1EA;
      --paper:#FBF7F1;
      --warm: #D9633A;
      --rule: #E5DCCF;
      --muted:#8C7C6B;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Geist', system-ui, sans-serif;
      background: var(--paper);
      color: var(--ink);
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
      -webkit-font-smoothing: antialiased;
    }

    /* Lado esquerdo — brand */
    .brand-side {
      background: var(--cream);
      border-right: 1px solid var(--rule);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
    }
    .brand-side img {
      width: 100%;
      max-width: 320px;
    }

    /* Lado direito — form */
    .form-side {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem 2.5rem;
    }
    .form-wrap {
      width: 100%;
      max-width: 340px;
    }
    .form-wrap h1 {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: .35rem;
    }
    .form-wrap .sub {
      font-size: .8rem;
      color: var(--muted);
      margin-bottom: 2rem;
    }

    .field { margin-bottom: .875rem; }
    .field label {
      display: block;
      font-size: .78rem;
      font-weight: 500;
      margin-bottom: .3rem;
      color: var(--ink);
    }
    .field input {
      width: 100%;
      padding: .5rem .75rem;
      border: 1px solid var(--rule);
      border-radius: 6px;
      background: #fff;
      color: var(--ink);
      font-size: .875rem;
      font-family: inherit;
      transition: border-color .15s, box-shadow .15s;
    }
    .field input:focus {
      outline: none;
      border-color: var(--warm);
      box-shadow: 0 0 0 3px rgba(217,99,58,.1);
    }

    .alert {
      padding: .6rem .85rem;
      border-radius: 6px;
      font-size: .8rem;
      margin-bottom: .875rem;
      background: #fdecea;
      color: #C62828;
      border: 1px solid #f5c6c6;
    }

    .btn-submit {
      width: 100%;
      padding: .55rem 1rem;
      background: var(--warm);
      color: #fff;
      border: none;
      border-radius: 6px;
      font-family: inherit;
      font-size: .875rem;
      font-weight: 500;
      cursor: pointer;
      margin-top: .25rem;
      transition: opacity .15s;
    }
    .btn-submit:hover { opacity: .88; }

    .form-foot {
      margin-top: 1.5rem;
      font-size: .75rem;
      color: var(--muted);
      text-align: center;
    }

    @media (max-width: 640px) {
      body { grid-template-columns: 1fr; }
      .brand-side { display: none; }
    }
  </style>
</head>
<body>

  <!-- BRAND -->
  <div class="brand-side">
    <img src="assets/img/fenor-ia-stacked-transparent-ink.svg" alt="Fenor — Build · Test · Ship">
  </div>

  <!-- FORM -->
  <div class="form-side">
    <div class="form-wrap">
      <h1>Entrar no Studio</h1>
      <p class="sub">Painel de controle da plataforma</p>

      <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autofocus
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="seu@email.com">
        </div>
        <div class="field">
          <label for="password">Senha</label>
          <input type="password" id="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn-submit">Entrar →</button>
      </form>

      <p class="form-foot">fenor.ia.br · Studio v1.0</p>
    </div>
  </div>

</body>
</html>
