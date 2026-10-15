<?php
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auditor de FGTS — Caixa Econômica Federal</title>
  <meta name="description" content="Sistema especialista em auditoria pericial de extratos de FGTS da CEF. Reconhecimento de depósitos código 115, consolidação de vínculos, filtros prescricionais e exportação em Excel e PDF.">

  <!-- Fontes Oficiais -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;900&family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- Estilos do app e bibliotecas hospedadas no próprio site (sem CDN) -->
  <link rel="stylesheet" href="auditor-fgts/style.css">

  <!-- PDF.js para leitura direta de PDFs no navegador -->
  <script src="auditor-fgts/pdf.min.js"></script>
  <script>
    if (window.pdfjsLib) {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'auditor-fgts/pdf.worker.min.js';
    }
  </script>

  <!-- ExcelJS e SheetJS para planilhas Excel -->
  <script src="auditor-fgts/exceljs.min.js"></script>
  <script src="auditor-fgts/xlsx.full.min.js"></script>

  <!-- jsPDF e jsPDF-AutoTable para laudos em PDF -->
  <script src="auditor-fgts/jspdf.umd.min.js"></script>
  <script src="auditor-fgts/jspdf.plugin.autotable.min.js"></script>

  <!-- Ícones Lucide -->
  <script src="auditor-fgts/lucide.min.js"></script>
</head>
<body>

  <!-- Header Institucional -->
  <header class="app-header">
    <div class="header-container">
      <div class="header-brand">
        <div class="brand-icon-box">
          <i data-lucide="building-2"></i>
        </div>
        <div class="brand-titles">
          <div class="brand-tagline">
            <span class="badge-tag">Auditoria Pericial CEF</span>
            <span class="version-tag">v3.0 Vanilla CDN</span>
          </div>
          <h1 class="brand-h1">
            Auditor de <strong>FGTS</strong>
          </h1>
        </div>
      </div>

      <div class="header-badges">
        <div class="status-indicator">
          <span class="pulse-dot"></span>
          <span>Motor Pericial Local Ativo (100% Offline / CDN)</span>
        </div>
      </div>
    </div>
  </header>

  <!-- Container Principal -->
  <main class="main-wrapper">
    
    <!-- ====================================================================
         COLUNA ESQUERDA: ENTRADA DE EXTRATOS E CONFIGURAÇÃO PERICIAL
         ==================================================================== -->
    <aside class="col-left">
      
      <!-- Card: Entrada do Extrato -->
      <section class="card-panel">
        <div class="card-header">
          <div class="card-title-group">
            <div class="card-title-icon">
              <i data-lucide="upload-cloud"></i>
            </div>
            <h2 class="card-title">Entrada de Extratos</h2>
          </div>
          <button id="btnClearInput" class="btn-link" title="Limpar dados carregados">
            <i data-lucide="rotate-ccw"></i>
            <span>Limpar</span>
          </button>
        </div>

        <!-- Zona de Drag and Drop -->
        <div id="dropzone" class="dropzone">
          <input type="file" id="fileInput" multiple accept=".pdf,.txt" style="display: none;">
          <div class="dropzone-icon">
            <i data-lucide="file-up" style="width: 32px; height: 32px;"></i>
          </div>
          <p class="dropzone-text">
            Arraste um ou múltiplos extratos (PDF/TXT) aqui ou
            <button type="button" id="btnBrowseFiles" class="dropzone-btn-link">procure no computador</button>
          </p>
          <span class="dropzone-hint">
            Processa extratos analíticos, rescisórios e contas vinculadas da Caixa em lote.
          </span>
        </div>

        <!-- Fila de Arquivos Carregados -->
        <div id="fileQueueContainer" class="file-queue" style="display: none;">
          <div class="file-queue-header">
            <span>Arquivos Selecionados (<span id="fileCountSpan">0</span>)</span>
            <span id="fileQueueStatus" class="file-queue-status" style="display: none;">Processando...</span>
          </div>
          <div id="fileQueueList" class="file-queue-items"></div>
        </div>

        <!-- Barra de Exemplo Rápido -->
        <div class="sample-bar">
          <span>Deseja testar rapidamente?</span>
          <button type="button" id="btnLoadSample" class="btn-link" style="color: var(--gold-light); font-weight: 700;">
            <i data-lucide="sparkles"></i>
            <span>Carregar Extrato de Exemplo</span>
          </button>
        </div>

        <!-- Textarea para colar texto ou visualizar conteúdo extraído -->
        <div class="textarea-wrapper">
          <textarea 
            id="rawTextarea" 
            class="code-textarea" 
            placeholder="Cole aqui o texto copiado do PDF de extrato do FGTS ou adicione arquivos acima para leitura automática..."
          ></textarea>
          <div id="charCounter" class="char-counter">0 caracteres</div>
        </div>

        <!-- Alerta de Erro -->
        <div id="errorAlert" class="alert-box alert-error" style="display: none;">
          <i data-lucide="alert-triangle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
          <span id="errorMessage">Erro no processamento do documento.</span>
        </div>

        <!-- Botão Executar Auditoria -->
        <button type="button" id="btnRunAudit" class="btn btn-primary-gold btn-run-audit">
          <i data-lucide="activity"></i>
          <span>Realizar Auditoria de FGTS</span>
        </button>
      </section>

      <!-- Card: Filtros Prescricionais & Marco Temporal -->
      <section class="prescription-card">
        <div class="card-title-group">
          <div class="card-title-icon">
            <i data-lucide="scale"></i>
          </div>
          <h3 class="card-title">Filtros Prescricionais</h3>
        </div>

        <div class="prescription-grid">
          <div class="form-group">
            <label class="form-label" for="selectPrescriptionRule">Regra Prescricional Aplicável</label>
            <select id="selectPrescriptionRule" class="form-select">
              <option value="NONE">Integral (Sem Prescrição — Todo o Histórico)</option>
              <option value="QUINQUENAL" selected>Prescrição Quinquenal (Últimos 5 anos do Marco)</option>
              <option value="STF_709212">Regra STF ARE 709.212 / Súmula 362 TST (Transição 30/5 anos)</option>
              <option value="YEAR_START">A Partir de Ano Específico</option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <div class="form-group">
              <label class="form-label" for="inputCutoffDate">Data Marco / Ajuizamento</label>
              <input type="text" id="inputCutoffDate" class="form-input font-mono" placeholder="DD/MM/AAAA" maxlength="10">
            </div>

            <div class="form-group">
              <label class="form-label" for="inputStartYear">Ano Inicial (Opcional)</label>
              <input type="number" id="inputStartYear" class="form-input font-mono" placeholder="Ex: 2021" min="1980" max="2035">
            </div>
          </div>
        </div>

        <div id="prescriptionRuleDesc" class="prescription-summary-badge">
          <strong>Regra Ativa: Prescrição Quinquenal</strong>
          Apenas competências devidas nos últimos 5 anos anteriores à data do marco serão computadas como passivo exigível.
        </div>
      </section>

      <!-- Card: Regras Periciais Oficiais da CEF -->
      <section class="rules-info-card">
        <h4>
          <i data-lucide="info" style="color: var(--gold-primary); width: 16px; height: 16px;"></i>
          Regras de Auditoria Pericial (CEF):
        </h4>
        <ul class="rules-list">
          <li>Apenas depósitos código <strong>115-DEPOSITO</strong> e <strong>115-DEPOSITO EM ATRASO</strong> constituem quitação oficial de competência.</li>
          <li>Créditos de JAM (Juros e Atualização Monetária) <strong>NÃO</strong> quitam competências mensais de 8%.</li>
          <li>Depósitos realizados em atraso ou listados em contas secundárias são <strong>reconciliados de forma cruzada</strong> entre os vínculos da empresa.</li>
          <li>Se a data de afastamento for <em>00/00/0000</em> ou igual à data de admissão, o vínculo é considerado <strong>ATIVO</strong> e apurado até o presente mês.</li>
        </ul>
      </section>

    </aside>

    <!-- ====================================================================
         COLUNA DIREITA: RESULTADOS, ABAS, DASHBOARD E EXPORTAÇÕES
         ==================================================================== -->
    <section class="col-right">

      <!-- Navegação das Abas -->
      <nav class="tabs-nav">
        <button type="button" id="tabDashboardBtn" class="tab-btn active">
          <i data-lucide="layout-dashboard"></i>
          <span>Painel</span>
        </button>
        <button type="button" id="tabReportBtn" class="tab-btn">
          <i data-lucide="file-check-2"></i>
          <span>Relatório</span>
        </button>
        <button type="button" id="tabTimelineBtn" class="tab-btn">
          <i data-lucide="calendar"></i>
          <span>Histórico</span>
        </button>
        <button type="button" id="tabInspectorBtn" class="tab-btn">
          <i data-lucide="layers"></i>
          <span>Inspetor</span>
        </button>
      </nav>

      <!-- Estado: Aguardando Importação (Empty State) -->
      <div id="emptyState" class="empty-state-box">
        <div class="empty-state-icon">
          <i data-lucide="file-spreadsheet" style="width: 64px; height: 64px;"></i>
        </div>
        <h3 class="empty-state-title">Aguardando Importação do Extrato</h3>
        <p class="empty-state-desc">
          Carregue um ou mais extratos de FGTS da CEF na lateral esquerda ou clique em 
          <strong style="color: var(--gold-light);">Carregar Extrato de Exemplo</strong> 
          para processar instantaneamente e visualizar a auditoria pericial.
        </p>
      </div>

      <!-- Estado: Carregando Análise (Loading State) -->
      <div id="loadingState" class="empty-state-box" style="display: none;">
        <div class="spinner-ring" style="width: 48px; height: 48px; border-width: 3px;"></div>
        <h3 class="empty-state-title" style="margin-top: 1rem;">Processando Auditoria Pericial...</h3>
        <p id="loadingStateDesc" class="empty-state-desc">
          Mapeando coordenadas físicas do extrato, cruzando contas vinculadas e reconciliando depósitos de competências código 115.
        </p>
      </div>

      <!-- Estado: Resultados da Auditoria (Results Container) -->
      <div id="resultsContainer" style="display: none; display: flex; flex-direction: column; gap: 1.5rem;">

        <!-- Banner de Múltiplos Trabalhadores (quando houver mais de 1 no lote) -->
        <div id="multiWorkersBanner" class="multi-workers-banner" style="display: none;">
          <div class="multi-workers-header">
            <span style="font-size: 11px; font-weight: 800; color: var(--gold-light); text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="users" style="width: 16px; height: 16px;"></i>
              Múltiplos Colaboradores Detectados (<span id="multiWorkersCountSpan">0</span>)
            </span>
            <span class="badge-tag">Análise Individualizada</span>
          </div>
          <p style="font-size: 11px; color: var(--text-muted);">
            Selecione o colaborador abaixo para alternar e explorar a análise pericial individual:
          </p>
          <div id="workersListButtons" class="multi-workers-btns"></div>
        </div>

        <!-- Header do Trabalhador Identificado -->
        <div class="worker-header-card">
          <div class="worker-main-info">
            <div class="worker-identity">
              <div class="worker-avatar">
                <i data-lucide="user-check" style="width: 24px; height: 24px;"></i>
              </div>
              <div class="worker-details">
                <span style="font-size: 10px; font-weight: 700; color: var(--text-dim); text-transform: uppercase;">Trabalhador Identificado</span>
                <h2 id="workerNameDisplay">LEONARDO SANTOS PIRES</h2>
                <div class="worker-meta-row">
                  <span class="meta-item">
                    <strong>CPF:</strong> <span id="workerCpfDisplay">—</span>
                  </span>
                  <span class="meta-item">
                    <strong>PIS/PASEP:</strong> <span id="workerPisDisplay">—</span>
                  </span>
                  <span id="calcDateDisplay" class="badge-calc-date">
                    Apuração: 15/10/2026
                  </span>
                </div>
              </div>
            </div>

            <!-- Ações Rápidas Individuais do Colaborador Selecionado -->
            <div class="worker-header-actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
              <button type="button" id="btnQuickExcelWorker" class="btn btn-primary-gold" style="font-size: 11px; padding: 6px 12px;" title="Baixar planilha Excel (.xlsx) apenas deste colaborador">
                <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px;"></i>
                <span id="btnQuickExcelWorkerText">Excel Individual</span>
              </button>
              <button type="button" id="btnQuickPdfWorker" class="btn btn-secondary-dark" style="font-size: 11px; padding: 6px 12px;" title="Baixar laudo PDF pericial apenas deste colaborador">
                <i data-lucide="file-text" style="width: 14px; height: 14px; color: var(--gold-light);"></i>
                <span id="btnQuickPdfWorkerText">PDF Individual</span>
              </button>
            </div>
          </div>

          <!-- Seletor de Vínculos / Empresas Empregadoras -->
          <div class="companies-switcher">
            <span class="companies-label">Empresas / Vínculos:</span>
            <div id="companiesButtonsList" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
          </div>
        </div>

        <!-- Barra de Ações Rápidas de Exportação -->
        <div class="export-bar">
          <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 6px;">
              <i data-lucide="filter" style="width: 16px; height: 16px; color: var(--gold-primary);"></i>
              <label for="exportYearFilterInput" style="font-size: 11px; font-weight: 700; color: #ffffff;">Filtro de Ano:</label>
            </div>
            <div style="position: relative; display: inline-block;">
              <input 
                type="number" 
                id="exportYearFilterInput" 
                class="form-input font-mono" 
                placeholder="Todos os anos" 
                style="width: 140px; padding: 4px 8px; font-size: 11px;"
                min="1980" 
                max="2035"
              >
              <button 
                type="button" 
                id="btnClearYearFilter" 
                class="btn-link" 
                style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); font-size: 10px; display: none;"
                title="Limpar ano"
              >✕</button>
            </div>
          </div>

          <div class="export-actions">
            <!-- 1. Botão Excel Individual (Deste Colaborador) -->
            <button type="button" id="btnExportWorkerExcel" class="btn btn-primary-gold" title="Baixar planilha Excel (.xlsx) apenas do colaborador selecionado">
              <i data-lucide="file-spreadsheet"></i>
              <span id="btnExportWorkerExcelText">Excel Individual</span>
            </button>

            <!-- 2. Botão PDF Individual (Deste Colaborador) -->
            <button type="button" id="btnExportWorkerPdf" class="btn btn-secondary-dark" title="Baixar laudo PDF pericial apenas do colaborador selecionado">
              <i data-lucide="file-text" style="color: var(--gold-light);"></i>
              <span id="btnExportWorkerPdfText">PDF Individual</span>
            </button>

            <!-- 3. Botão Excel de Todos (Lote Completo) -->
            <button type="button" id="btnExportAllExcel" class="btn btn-secondary-dark" title="Baixar planilha Excel (.xlsx) consolidada de todos os colaboradores do lote" style="display: none;">
              <i data-lucide="folder-archive" style="color: var(--gold-light);"></i>
              <span id="btnExportAllExcelText">Excel de Todos</span>
            </button>

            <!-- 4. Botão PDF de Todos (Lote Completo) -->
            <button type="button" id="btnExportAllPdf" class="btn btn-secondary-dark" title="Baixar laudo PDF com todos os colaboradores do lote" style="display: none;">
              <i data-lucide="files" style="color: var(--gold-light);"></i>
              <span id="btnExportAllPdfText">PDF de Todos</span>
            </button>

            <!-- Exportar TXT -->
            <button type="button" id="btnExportTxt" class="btn btn-secondary-dark" title="Baixar texto formatado para anexar a peças">
              <i data-lucide="download" style="color: var(--gold-light);"></i>
              <span>Baixar TXT</span>
            </button>

            <!-- Copiar Relatório -->
            <button type="button" id="btnCopyReport" class="btn btn-secondary-dark" title="Copiar relatório formatado para a área de transferência">
              <i data-lucide="copy" style="color: var(--gold-light);"></i>
              <span id="btnCopyReportText">Copiar</span>
            </button>
          </div>
        </div>

        <!-- ================================================================
             CONTEÚDO DA ABA 1: PAINEL GERAL (DASHBOARD)
             ================================================================ -->
        <div id="tabDashboard" class="tab-pane">
          
          <!-- Card de Quitação e Métricas -->
          <div class="card-panel" style="gap: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
              <div>
                <h3 style="font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                  <i data-lucide="activity" style="width: 16px; height: 16px; color: var(--gold-primary);"></i>
                  Quitação Consolidada de FGTS
                </h3>
                <p style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">
                  Auditoria cruzada e consolidação de recolhimentos entre contas do trabalhador.
                </p>
              </div>
              <span id="unifiedCompaniesBadge" class="badge-tag">Vínculos Analisados: 1</span>
            </div>

            <!-- Grid de Indicadores -->
            <div class="metrics-grid">
              <!-- Total Analisado -->
              <div class="metric-card">
                <span class="metric-label">Total do Período</span>
                <div class="metric-value-row">
                  <div class="metric-val">
                    <span id="valTotalChecked">0</span>
                    <span class="metric-unit">meses</span>
                  </div>
                  <span class="metric-badge badge-neutral">PERÍODO</span>
                </div>
              </div>

              <!-- Competências Pagas -->
              <div class="metric-card">
                <span class="metric-label" style="color: var(--gold-light);">Competências Pagas</span>
                <div class="metric-value-row">
                  <div class="metric-val gold">
                    <span id="valTotalPaid">0</span>
                    <span class="metric-unit">meses</span>
                  </div>
                  <span id="valPaidPercentBadge" class="metric-badge badge-gold">0%</span>
                </div>
              </div>

              <!-- Competências em Aberto -->
              <div class="metric-card">
                <span class="metric-label" style="color: #ffffff;">Competências em Aberto</span>
                <div class="metric-value-row">
                  <div class="metric-val danger">
                    <span id="valTotalPending">0</span>
                    <span class="metric-unit">meses</span>
                  </div>
                  <span id="valPendingPercentBadge" class="metric-badge badge-gold">0%</span>
                </div>
              </div>

              <!-- Valor Total Depositado -->
              <div class="metric-card">
                <span class="metric-label">Total Recolhido</span>
                <div class="metric-value-row">
                  <div class="metric-val" style="font-size: 1.25rem;">
                    <span id="valTotalDeposited">R$ 0,00</span>
                  </div>
                  <span class="metric-badge badge-neutral">115</span>
                </div>
              </div>

              <!-- Prejuízo Estimado Faltante -->
              <div class="metric-card">
                <span class="metric-label" style="color: var(--gold-light);">Passivo Estimado em Aberto</span>
                <div class="metric-value-row">
                  <div class="metric-val gold" style="font-size: 1.25rem;">
                    <span id="valTotalEstimatedLoss">R$ 0,00</span>
                  </div>
                  <span class="metric-badge badge-gold">ESTIMADO</span>
                </div>
              </div>
            </div>

            <!-- Barra de Progresso Visual -->
            <div class="progress-container">
              <div class="progress-track">
                <div id="progressBarPaid" class="progress-bar-paid" style="width: 0%;"></div>
                <div id="progressBarPending" class="progress-bar-pending" style="width: 100%;"></div>
              </div>
              <div class="progress-labels">
                <span style="color: var(--gold-light); font-weight: 700;">
                  Pagas: <span id="progressLabelPaid">0</span>
                </span>
                <span style="color: #ffffff; font-weight: 700;">
                  Em Aberto: <span id="progressLabelPending">0</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Card: Competências Efetivamente em Aberto -->
          <div class="open-competencies-card" style="margin-top: 1.5rem;">
            <div>
              <h3 style="font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="alert-circle" style="width: 16px; height: 16px; color: var(--gold-primary);"></i>
                COMPETÊNCIAS REALMENTE EM ABERTO (PENDÊNCIAS)
              </h3>
              <p style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">
                Meses em que não houve recolhimento código 115 ou cujo depósito em atraso não foi localizado em nenhuma conta.
              </p>
            </div>

            <div id="openCompetenciesContainer" style="display: flex; flex-direction: column; gap: 1rem;"></div>
          </div>

        </div>

        <!-- ================================================================
             CONTEÚDO DA ABA 2: RELATÓRIO OFICIAL FORMATADO
             ================================================================ -->
        <div id="tabReport" class="tab-pane" style="display: none;">
          <div class="report-view-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
              <div>
                <h3 style="font-size: 13px; font-weight: 800; color: #ffffff;">Relatório Pericial Oficial (Texto Estruturado)</h3>
                <p style="font-size: 11px; color: var(--text-dim);">Pronto para ser colado em petições iniciais, impugnações ou laudos periciais.</p>
              </div>
              <div style="display: flex; gap: 0.5rem;">
                <button type="button" id="btnReportDownloadTxt" class="btn btn-secondary-dark">
                  <i data-lucide="download" style="color: var(--gold-light);"></i>
                  <span>Baixar TXT</span>
                </button>
                <button type="button" id="btnReportCopy" class="btn btn-primary-gold">
                  <i data-lucide="copy"></i>
                  <span>Copiar Relatório</span>
                </button>
              </div>
            </div>

            <pre id="reportPreBox" class="report-pre-box"></pre>
          </div>
        </div>

        <!-- ================================================================
             CONTEÚDO DA ABA 3: HISTÓRICO ANUAL / CALENDÁRIO
             ================================================================ -->
        <div id="tabTimeline" class="tab-pane" style="display: none;">
          <div class="card-panel" style="gap: 1rem;">
            <div>
              <h3 style="font-size: 13px; font-weight: 800; color: #ffffff;">Histórico Mensal por Exercício</h3>
              <p style="font-size: 11px; color: var(--text-dim);">
                Grade cronológica de depósitos mês a mês. Passe o cursor sobre o mês para detalhes do lançamento.
              </p>
            </div>

            <div id="calendarYearsContainer" class="calendar-years-list"></div>
          </div>
        </div>

        <!-- ================================================================
             CONTEÚDO DA ABA 4: INSPETOR PERICIAL LINHA A LINHA
             ================================================================ -->
        <div id="tabInspector" class="tab-pane" style="display: none;">
          <div class="inspector-card">
            <div class="inspector-filters-row">
              <div>
                <h3 style="font-size: 13px; font-weight: 800; color: #ffffff;">Match Inspector (Auditoria de Linhas)</h3>
                <p style="font-size: 11px; color: var(--text-dim);">Classificação técnica aplicada pelo motor pericial a cada linha do documento.</p>
              </div>

              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <select id="selectInspectorFilter" class="form-select" style="width: auto;">
                  <option value="ALL">Todas as linhas</option>
                  <option value="PAYMENT">Apenas Depósitos 115</option>
                  <option value="IGNORED_JAM">JAM Ignorado</option>
                  <option value="ELSE">Outros / Cabeçalhos</option>
                </select>

                <input 
                  type="search" 
                  id="searchInspectorInput" 
                  class="form-input font-mono" 
                  placeholder="Filtrar por texto..." 
                  style="width: 180px;"
                >
              </div>
            </div>

            <div id="inspectorLinesList" class="inspector-lines-list"></div>
          </div>
        </div>

      </div>

    </section>

  </main>

  <!-- Rodapé Institucional -->
  <footer class="app-footer">
    <div class="footer-container">
      <p>© 2026 Auditor de FGTS — Caixa Econômica Federal. Conformidade técnica com Código 115 da CEF e jurisprudência do STF (ARE 709.212).</p>
      <span class="footer-badge">ENGINE_VANILLA_STANDALONE_CEF</span>
    </div>
  </footer>

  <!-- Lógica Principal da Aplicação (Vanilla JS) -->
  <script src="auditor-fgts/app.js"></script>
</body>
</html>
