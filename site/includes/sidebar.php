<?php
// Espera que $user (usuário logado) já esteja definido antes do include.
$rotulos = ['GESTOR' => 'Gestor', 'CONSULTOR' => 'Consultor', 'OPERACIONAL' => 'Operacional'];
?>
<div class="sidebar" id="sidebar">
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" onclick="document.getElementById('sidebar').classList.toggle('aberta')">
    <span></span><span></span><span></span>
  </button>

  <div class="sidebar-painel">
    <img src="assets/img/logo.jpg" alt="Mecanismo DP" class="sidebar-logo" width="220">

    <nav class="sidebar-nav">
      <a href="inicio.php">Início</a>
      <a href="dashboard.php">Calendário</a>
      <a href="controle13.php">Controle 13º</a>
      <a href="dashboard13.php">Dashboard 13º</a>
      <?php /* Conferência de Folha oculta do menu até ficar pronta: <a href="conferencia.php">Conferência de Folha</a> */ ?>
      <?php if ($user['role'] === 'GESTOR'): ?>
        <a href="admin.php">Administração</a>
      <?php endif; ?>
      <a href="opcoes.php">Opções</a>
    </nav>

    <div class="sidebar-usuario">
      <p class="nome"><?= htmlspecialchars($user['nome']) ?></p>
      <p class="papel"><?= $rotulos[$user['role']] ?? $user['role'] ?></p>
    </div>
    <a href="logout.php" class="btn btn-ghost btn-sm" style="width:100%; text-align:center;">Sair</a>
  </div>
</div>

<div class="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('aberta')"></div>
