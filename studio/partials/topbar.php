<?php
$email = htmlspecialchars($_SESSION['user']['email'] ?? '');
?>
<div class="topbar">
  <h1 id="topbar-title"><?= $pageTitle ?? 'Studio' ?></h1>
  <div class="user">
    <span><?= $email ?></span>
    <button class="lang-toggle-btn" onclick="studioToggleLang()" title="Switch language / Alternar idioma">
      <span id="lang-label">EN</span>
    </button>
    <a href="<?= $baseUrl ?? '' ?>logout.php" data-pt="Sair" data-en="Sign out">Sair</a>
  </div>
</div>
