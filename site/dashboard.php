<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';

$user = exigirLogin();
$duplas = duplasVisiveis($user);
$csrfToken = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="container">
  <?php if (empty($duplas)): ?>
    <div class="card" style="text-align:center; color: rgba(255,255,255,.6);">
    <p>Você ainda não está associado a nenhuma célula.</p>
    <p class="texto-fraco" style="margin-top:4px;">Peça ao gestor para te vincular a uma célula na Administração.</p>
    </div>
  <?php else: ?>
    <div id="app-calendario"></div>
  <?php endif; ?>
</main>

<div style="text-align:center; padding: 24px 0; color: var(--dourado);">S</div>

<script>
  window.MECANISMO_DP = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    duplas: <?= json_encode($duplas) ?>,
    usuario: <?= json_encode(['id' => $user['id'], 'role' => $user['role']]) ?>
  };
</script>
<script src="assets/app.js"></script>
</body>
</html>
