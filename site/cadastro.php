<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';

if (usuarioLogado()) {
    header('Location: inicio.php');
    exit;
}

$erro = null;
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada, atualize a página e tente novamente.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

        if (!$username || !$senha || !$email || !$cpf) {
            $erro = 'Preencha todos os campos.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha precisa ter pelo menos 6 caracteres.';
        } elseif ($senha !== $confirmarSenha) {
            $erro = 'As senhas não coincidem.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif (strlen($cpf) !== 11) {
            $erro = 'Informe um CPF válido (11 números).';
        } elseif (buscarUsuarioPorUsername($username)) {
            $erro = 'Esse nome de usuário já está em uso.';
        } elseif (buscarUsuarioPorEmail($email)) {
            $erro = 'Esse e-mail já está cadastrado.';
        } elseif (buscarUsuarioPorCpf($cpf)) {
            $erro = 'Esse CPF já está cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            registrarUsuarioPublico($username, $hash, $email, $cpf);
            $sucesso = true;
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
<title>Criar conta — Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="tela-login">
  <div class="login-caixa">
    <div class="login-marca">
      <img src="assets/img/logo.jpg" alt="Mecanismo DP" class="logo-imagem" width="340">
      <p class="subtitulo">Criar conta</p>
    </div>

    <div class="login-card">
      <?php if ($sucesso): ?>
        <p style="color:#fff; margin:0 0 8px;">Conta criada com sucesso!</p>
        <p class="texto-fraco">Seu acesso ainda não está vinculado a nenhuma dupla — fale com seu gestor para liberar o que você precisa ver.</p>
        <p class="texto-fraco mt-3"><a href="login.php" style="color: var(--dourado);">Ir para a tela de login →</a></p>
      <?php else: ?>
        <?php if ($erro): ?><p class="erro-msg"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="campo">
            <label>Nome de usuário</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
          <div class="campo">
            <label>Senha</label>
            <input type="password" name="senha" required minlength="6">
          </div>
          <div class="campo">
            <label>Confirmar senha</label>
            <input type="password" name="confirmar_senha" required minlength="6">
          </div>
          <div class="campo">
            <label>E-mail</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="campo">
            <label>CPF</label>
            <input type="text" name="cpf" required placeholder="Somente números" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;">Criar conta</button>
        </form>
        <p class="texto-fraco mt-3"><a href="login.php" style="color: var(--dourado);">Já tenho conta — entrar →</a></p>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
