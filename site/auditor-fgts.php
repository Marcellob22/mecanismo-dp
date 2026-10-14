<?php
require_once __DIR__ . '/includes/auth.php';

$user = exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auditor de FGTS | Mecanismo DP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="pagina-ferramenta">
<!-- AUDITOR DE FGTS (CEF) - EMBED RESPONSIVO -->
<div class="auditor-fgts-container" id="auditor-fgts-wrapper">
  <div class="auditor-fgts-header">
    <div class="auditor-fgts-brand">
      <div class="auditor-fgts-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
      </div>
      <div>
        <h3 class="auditor-fgts-title">Auditor de FGTS</h3>
        <p class="auditor-fgts-subtitle">Caixa Econômica Federal &bull; Sistema Pericial</p>
      </div>
    </div>
    <div class="auditor-fgts-actions">
      <a href="https://auditor-de-fgts.ai.studio" target="_blank" rel="noopener noreferrer" class="auditor-fgts-btn-external">
        <span>Abrir em Nova Aba</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
          <polyline points="15 3 21 3 21 9"/>
          <line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
      </a>
    </div>
  </div>

  <iframe
    id="auditor-fgts-frame"
    src="https://auditor-de-fgts.ai.studio"
    title="Auditor de FGTS CEF"
    allow="clipboard-write; clipboard-read"
    loading="lazy"
  ></iframe>
</div>
</main>
</body>
</html>
