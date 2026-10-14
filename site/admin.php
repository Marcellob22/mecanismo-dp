<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';

$user = exigirGestorPagina();
$usuarios = listarUsuarios();
$duplas = listarDuplas();
$csrfToken = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administração — Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="container">
  <div id="app-admin"></div>
</main>

<script>
  window.MECANISMO_DP = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    usuarios: <?= json_encode($usuarios) ?>,
    duplas: <?= json_encode($duplas) ?>
  };
</script>
<script src="assets/admin.js"></script>
</body>
</html>
