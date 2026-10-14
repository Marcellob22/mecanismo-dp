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
<title>Opções | Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="container">
  <h1 class="font-display" style="color:var(--dourado); margin:0 0 4px;">Opções</h1>
  <p class="texto-fraco mb-3">Conta de <?= htmlspecialchars($user['username']) ?></p>

  <section class="card opcoes-card">
    <h2 class="font-display">Alterar senha</h2>
    <form id="form-senha" autocomplete="on" novalidate>
      <label class="opcoes-campo">
        <span>Senha atual</span>
        <input type="password" name="senhaAtual" autocomplete="current-password" required>
      </label>
      <label class="opcoes-campo">
        <span>Nova senha</span>
        <input type="password" name="novaSenha" autocomplete="new-password" minlength="6" required>
        <small class="texto-fraco">Mínimo de 6 caracteres.</small>
      </label>
      <label class="opcoes-campo">
        <span>Repita a nova senha</span>
        <input type="password" name="confirmarSenha" autocomplete="new-password" required>
      </label>
      <label class="opcoes-mostrar"><input type="checkbox" id="mostrar-senhas"> Mostrar senhas</label>
      <p id="senha-msg" class="opcoes-msg" role="status"></p>
      <button type="submit" class="btn btn-primary">Salvar nova senha</button>
    </form>
  </section>
</main>

<script>
  window.MECANISMO_DP = { csrfToken: <?= json_encode($csrfToken) ?> };
</script>
<script src="assets/opcoes.js"></script>
</body>
</html>
