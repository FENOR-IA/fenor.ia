<?php
$email = htmlspecialchars($_SESSION['user']['email'] ?? '');
?>
<div class="topbar">
  <h1><?= $pageTitle ?? 'Studio' ?></h1>
  <div class="user">
    <span><?= $email ?></span>
    <a href="<?= $baseUrl ?? '' ?>logout.php">Sair</a>
  </div>
</div>
