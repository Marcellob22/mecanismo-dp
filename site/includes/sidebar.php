<?php
// Espera que $user (usuário logado) já esteja definido antes do include.
$rotulos = ['GESTOR' => 'Gestor', 'CONSULTOR' => 'Consultor', 'OPERACIONAL' => 'Operacional'];
$paginaAtual = basename($_SERVER['SCRIPT_NAME'] ?? '');
$grupo13Aberto = in_array($paginaAtual, ['controle13.php', 'dashboard13.php'], true);
$grupoFerramentasAberto = in_array($paginaAtual, ['simulador-rescisao.php', 'auditor-fgts.php'], true);
$ativo = fn(string $arquivo) => $paginaAtual === $arquivo ? ' class="ativo" aria-current="page"' : '';
?>
<div class="sidebar" id="sidebar">
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" onclick="document.getElementById('sidebar').classList.toggle('aberta')">
    <span></span><span></span><span></span>
  </button>

  <div class="sidebar-painel">
    <img src="assets/img/logo.jpg" alt="Mecanismo DP" class="sidebar-logo" width="220">

    <nav class="sidebar-nav">
      <a href="inicio.php"<?= $ativo('inicio.php') ?>>Início</a>
      <a href="dashboard.php"<?= $ativo('dashboard.php') ?>>Calendário</a>
      <details class="nav-grupo"<?= $grupo13Aberto ? ' open' : '' ?>>
        <summary>13º Salário</summary>
        <div class="nav-subitens">
          <a href="controle13.php"<?= $ativo('controle13.php') ?>>Controle 13º</a>
          <a href="dashboard13.php"<?= $ativo('dashboard13.php') ?>>Dashboard 13º</a>
        </div>
      </details>
      <details class="nav-grupo"<?= $grupoFerramentasAberto ? ' open' : '' ?>>
        <summary>Ferramentas</summary>
        <div class="nav-subitens">
          <a href="simulador-rescisao.php"<?= $ativo('simulador-rescisao.php') ?>>Simulador de Rescisão</a>
          <a href="auditor-fgts.php"<?= $ativo('auditor-fgts.php') ?>>Auditor de FGTS</a>
        </div>
      </details>
      <?php /* Conferência de Folha oculta do menu até ficar pronta: <a href="conferencia.php">Conferência de Folha</a> */ ?>
      <?php if ($user['role'] === 'GESTOR'): ?>
        <a href="admin.php"<?= $ativo('admin.php') ?>>Administração</a>
      <?php endif; ?>
      <a href="opcoes.php"<?= $ativo('opcoes.php') ?>>Opções</a>
    </nav>

    <div class="sidebar-usuario">
      <p class="nome"><?= htmlspecialchars($user['nome']) ?></p>
      <p class="papel"><?= $rotulos[$user['role']] ?? $user['role'] ?></p>
    </div>
    <a href="logout.php" class="btn btn-ghost btn-sm" style="width:100%; text-align:center;">Sair</a>
  </div>
</div>

<div class="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('aberta')"></div>
