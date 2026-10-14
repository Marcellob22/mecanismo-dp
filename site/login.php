<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';

// Se ninguém foi cadastrado ainda, manda para a configuração inicial
if (!existeAlgumUsuario()) {
    header('Location: setup.php');
    exit;
}

if (usuarioLogado()) {
    header('Location: inicio.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada, tente novamente.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $senha = $_POST['password'] ?? '';
        $user = $username ? buscarUsuarioPorUsername($username) : null;

        if (!$user || !$user['ativo'] || !password_verify($senha, $user['password_hash'])) {
            $erro = 'Usuário ou senha inválidos.';
        } else {
            fazerLogin($user);
            header('Location: inicio.php');
            exit;
        }
    }
}

$csrfToken = gerarCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="tela-login">
  <div class="login-caixa">
    <div class="login-marca">
      <img src="assets/img/logo.jpg" alt="Mecanismo DP" class="logo-imagem" width="340">
      <p class="subtitulo">Departamento Pessoal</p>
    </div>

    <div class="login-card">
      <?php if ($erro): ?><p class="erro-msg"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="campo">
          <label for="username">Usuário</label>
          <input id="username" type="text" name="username" autocomplete="username" required placeholder="seu.usuario">
        </div>
        <div class="campo">
          <label for="password">Senha</label>
          <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Entrar</button>
      </form>
      <p class="texto-fraco mt-3" style="text-align:center;">
        <a href="cadastro.php" style="color: var(--dourado);">Criar uma conta →</a>
      </p>
    </div>

    <div class="hierarquia-legenda">
      <span class="item"><span class="bolinha" style="background: var(--dourado);"></span> Gestor</span>
      <span class="linha"></span>
      <span class="item"><span class="bolinha" style="background: rgba(201,162,39,.6);"></span> Consultor</span>
      <span class="linha"></span>
      <span class="item"><span class="bolinha" style="background: rgba(201,162,39,.3);"></span> Operacional</span>
    </div>
  </div>
</main>
</body>
</html>
