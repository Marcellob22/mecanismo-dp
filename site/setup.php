<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';

// Esta página só funciona enquanto não existir nenhum usuário cadastrado.
// Depois que o primeiro gestor for criado, ela se desativa sozinha.
$jaTemUsuario = existeAlgumUsuario();

$erro = null;
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$jaTemUsuario) {
    $nome = trim($_POST['nome'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$nome || !$username || !$senha) {
        $erro = 'Preencha todos os campos.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } else {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        criarUsuario($nome, $username, $hash, 'GESTOR', null);
        $sucesso = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuração inicial — Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="tela-login">
  <div class="login-caixa">
    <div class="login-marca">
      <img src="assets/img/logo.jpg" alt="Mecanismo DP" class="logo-imagem" width="340">
      <p class="subtitulo">Configuração inicial</p>
    </div>

    <div class="login-card">
      <?php if ($jaTemUsuario): ?>
        <p style="color:#fff; margin:0 0 8px;">A configuração inicial já foi concluída.</p>
        <p class="texto-fraco">Já existe pelo menos um usuário cadastrado neste sistema.</p>
        <p class="texto-fraco mt-3"><a href="login.php" style="color: var(--dourado);">Ir para a tela de login →</a></p>
      <?php elseif ($sucesso): ?>
        <p style="color:#fff; margin:0 0 8px;">Usuário gestor criado com sucesso!</p>
        <p class="texto-fraco mt-3"><a href="login.php" style="color: var(--dourado);">Ir para a tela de login →</a></p>
      <?php else: ?>
        <p style="color:#fff; margin:0 0 16px;">Crie o primeiro usuário (gestor) para começar a usar o sistema.</p>
        <?php if ($erro): ?><p class="erro-msg"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <form method="post">
          <div class="campo">
            <label>Nome completo</label>
            <input type="text" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
          </div>
          <div class="campo">
            <label>Nome de usuário (login)</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
          <div class="campo">
            <label>Senha</label>
            <input type="password" name="senha" required minlength="6">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;">Criar usuário gestor</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
