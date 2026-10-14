<?php
require_once __DIR__ . '/includes/auth.php';

$user = exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simulador de Rescisão | Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="pagina-ferramenta">
  <iframe src="ferramentas/simulador_rescisao.php" title="Simulador de Rescisão Trabalhista" class="ferramenta-frame"></iframe>
</main>
</body>
</html>
