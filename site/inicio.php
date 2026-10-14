<?php
require_once __DIR__ . '/includes/auth.php';

$user = exigirLogin();
$csrfToken = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Início | Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="container container-larga">
  <h1 class="font-display" style="color:var(--dourado); margin:0 0 4px;">Início</h1>
  <p class="texto-fraco mb-3">Olá, <?= htmlspecialchars(explode(' ', trim($user['nome']))[0]) ?>. Suas atividades do dia.</p>
  <div id="app-inicio"><p class="texto-fraco">Carregando...</p></div>
</main>

<script>
  window.MECANISMO_DP = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    usuario: <?= json_encode(['id' => $user['id'], 'role' => $user['role']]) ?>
  };
</script>
<script src="assets/inicio.js"></script>
</body>
</html>
