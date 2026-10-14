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

<main class="container container-larga pagina-inicio">
  <header class="marcas">
    <img src="assets/img/logo.jpg" alt="Mecanismo DP, gestão de departamento pessoal" class="marca-mecanismo">
    <img src="assets/img/pretorian.png" alt="Pretorian, gestão de alta performance e contabilidade" class="marca-pretorian">
  </header>
  <div id="app-inicio"><p class="texto-fraco">Carregando...</p></div>
</main>

<script>
  window.MECANISMO_DP = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    usuario: <?= json_encode(['id' => $user['id'], 'role' => $user['role'], 'nome' => explode(' ', trim($user['nome']))[0]]) ?>
  };
</script>
<script src="assets/inicio.js"></script>
</body>
</html>
