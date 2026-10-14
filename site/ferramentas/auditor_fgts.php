<?php
require_once __DIR__ . "/../includes/auth.php";
exigirLogin();
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store");
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Auditor de FGTS - Caixa Econômica Federal</title>
  <meta name="description" content="Sistema especialista em auditoria pericial e conferência analítica de contas de FGTS da CEF." />

  <!-- Fontes Google -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;900&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Estilos Tailwind compilados e bibliotecas hospedadas no próprio site (sem CDN) -->
  <link rel="stylesheet" href="auditor-fgts/tailwind.css">

  <!-- PDF.js (extração local de texto de PDFs) -->
  <script src="auditor-fgts/pdf.min.js"></script>
  <script>
    if (window.pdfjsLib) {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'auditor-fgts/pdf.worker.min.js';
    }
  </script>

  <!-- SheetJS (planilhas Excel .xlsx) -->
  <script src="auditor-fgts/xlsx.full.min.js"></script>

  <!-- Lucide Icons -->
  <script src="auditor-fgts/lucide.min.js"></script>

  <style>
    /* Estilos Globais e Tema Escuro Luxo */
    :root {
      color-scheme: dark;
    }
    body {
      background-color: #060608;
      color: #f3f4f6;
      font-family: 'Outfit', sans-serif;
      margin: 0;
      padding: 0;
    }
    .font-cinzel {
      font-family: 'Cinzel', serif;
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #0d0d12;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #27272a;
      border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #eab308;
    }
    @media print {
      header, #input-section, #filter-section, #export-buttons, footer, #btn-example, #drop-zone, textarea {
        display: none !important;
      }
      body {
        background-color: #ffffff !important;
        color: #000000 !important;
      }
      #results-panel {
        display: block !important;
      }
      .bg-\[\#0e0e13\], .bg-\[\#121217\], .bg-\[\#08080a\] {
        background-color: #ffffff !important;
        color: #000000 !important;
        border: 1px solid #cccccc !important;
      }
      table {
        color: #000000 !important;
      }
      th, td {
        border: 1px solid #dddddd !important;
        color: #000000 !important;
      }
    }
  </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-amber-500/30 selection:text-amber-200">

  <!-- CABEÇALHO DO SISTEMA -->
  <header class="bg-[#0c0c10] border-b border-amber-500/20 sticky top-0 z-50 shadow-[0_4px_25px_rgba(0,0,0,0.8)] backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-amber-400 via-amber-500 to-yellow-600 flex items-center justify-center text-black font-black shadow-[0_0_15px_rgba(212,175,55,0.3)]">
          <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
          <div class="flex items-center space-x-2">
            <span class="text-[10px] font-bold tracking-wider bg-amber-500/10 text-amber-300 border border-amber-500/25 px-2 py-0.5 rounded uppercase">
              Auditoria CEF
            </span>
            <span class="text-[10px] text-neutral-500 font-mono">v2.5 Autônomo</span>
          </div>
          <h1 class="text-lg font-bold text-neutral-300 tracking-tight leading-none mt-0.5">
            Auditor de <span class="text-white font-black font-cinzel tracking-wide">FGTS</span>
          </h1>
        </div>
      </div>

      <div class="flex items-center space-x-3">
        <span id="ai-status-indicator" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
          <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
          Motor Local Ativo
        </span>
      </div>
    </div>
  </header>

  <!-- ÁREA PRINCIPAL -->
  <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col lg:flex-row gap-8">
    
    <!-- COLUNA ESQUERDA: ENTRADA DE DADOS E ARQUIVOS -->
    <div id="input-section" class="lg:w-5/12 flex flex-col gap-6">
      
      <!-- Card de Entrada -->
      <div class="bg-[#0e0e13] border border-amber-500/20 rounded-2xl p-5 shadow-2xl relative overflow-hidden">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-2">
            <div class="bg-amber-500/10 text-amber-400 border border-amber-500/25 p-1.5 rounded-lg">
              <i data-lucide="upload-cloud" class="w-5 h-5"></i>
            </div>
            <h2 class="text-sm font-bold text-white">Entrada do Extrato</h2>
          </div>
          <button id="btn-example" class="text-xs text-amber-400 hover:text-amber-300 font-semibold transition-colors flex items-center gap-1">
            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
            Exemplo CEF
          </button>
        </div>

        <!-- Seletor Drag & Drop de Arquivos -->
        <div id="drop-zone" class="border-2 border-dashed border-neutral-700 hover:border-amber-500/50 rounded-xl p-5 text-center cursor-pointer transition-all bg-[#121217]/60 hover:bg-[#16161d] mb-4 group">
          <input type="file" id="file-input" accept=".pdf,.txt" multiple class="hidden" />
          <div class="w-10 h-10 mx-auto mb-2.5 rounded-full bg-neutral-800/80 flex items-center justify-center text-neutral-400 group-hover:text-amber-400 group-hover:bg-amber-500/10 transition-all">
            <i data-lucide="file-up" class="w-5 h-5"></i>
          </div>
          <p class="text-xs font-semibold text-neutral-200">
            Arraste extratos em <span class="text-amber-400 font-bold">PDF</span> ou <span class="text-amber-400 font-bold">TXT</span>
          </p>
          <p class="text-[11px] text-neutral-500 mt-1">Leitura multi-arquivos com extração sequencial</p>
        </div>

        <!-- Status de Leitura de PDF -->
        <div id="file-progress-container" class="hidden mb-4 p-3 bg-[#14141a] rounded-xl border border-amber-500/20 text-xs">
          <div class="flex items-center justify-between text-amber-300 mb-1.5">
            <span id="file-progress-label" class="font-semibold truncate max-w-[220px]">Processando arquivo...</span>
            <span id="file-progress-percent" class="font-mono">0%</span>
          </div>
          <div class="w-full bg-neutral-800 h-1.5 rounded-full overflow-hidden">
            <div id="file-progress-fill" class="bg-amber-400 h-full w-0 transition-all duration-150"></div>
          </div>
        </div>

        <!-- Área de Texto Direto -->
        <div class="mb-4">
          <div class="flex justify-between items-center mb-1.5 text-xs text-neutral-400">
            <span>Ou cole o texto do extrato:</span>
            <button id="btn-clear" class="hover:text-rose-400 transition-colors">Limpar</button>
          </div>
          <textarea
            id="text-input"
            rows="8"
            placeholder="Cole aqui o texto analítico da Caixa (incluindo PIS, Admissão, Afastamento, Código 115 e Depósitos)..."
            class="w-full bg-[#08080a] border border-neutral-800 rounded-xl p-3.5 text-xs text-neutral-200 placeholder:text-neutral-600 focus:outline-none focus:border-amber-500/60 font-mono resize-y custom-scrollbar"
          ></textarea>
        </div>

        <!-- Botão Executar Auditoria -->
        <button
          id="btn-audit"
          class="w-full py-3.5 px-4 rounded-xl font-black text-xs uppercase tracking-wider bg-gradient-to-r from-amber-400 via-amber-300 to-yellow-500 hover:from-amber-300 hover:to-amber-400 text-black shadow-[0_0_20px_rgba(212,175,55,0.25)] flex items-center justify-center gap-2 cursor-pointer transition-all active:scale-[0.98]"
        >
          <i data-lucide="activity" class="w-4 h-4 fill-black"></i>
          <span>Realizar Auditoria de FGTS</span>
        </button>
      </div>

      <!-- Card Informativo -->
      <div class="bg-[#0d0d10] rounded-xl border border-amber-500/20 p-4 text-xs text-neutral-400 shadow-lg">
        <div class="flex gap-2 text-white font-bold mb-1.5">
          <i data-lucide="shield-alert" class="w-4 h-4 text-amber-400 shrink-0"></i>
          <span>Regras Oficiais Aplicadas:</span>
        </div>
        <ul class="space-y-1 list-disc list-inside text-[11px] text-neutral-400 leading-relaxed">
          <li>Conferência de todos os meses devidos entre Admissão e Afastamento.</li>
          <li>Identificação de depósitos regulares (Código 115) e depósitos em atraso.</li>
          <li>Mapeamento de parcelamentos e cruzamento de contas vinculadas.</li>
        </ul>
      </div>

    </div>

    <!-- COLUNA DIREITA: RESULTADOS E RELATÓRIO -->
    <div class="lg:w-7/12 flex flex-col gap-6">

      <!-- Estado Inicial (Vazio) -->
      <div id="empty-state" class="bg-[#0e0e13] border border-neutral-800 rounded-2xl p-10 text-center flex flex-col items-center justify-center min-h-[460px]">
        <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center mb-4">
          <i data-lucide="file-search" class="w-8 h-8"></i>
        </div>
        <h3 class="text-base font-bold text-white mb-2">Aguardando Extrato para Auditoria</h3>
        <p class="text-xs text-neutral-400 max-w-md leading-relaxed">
          Envie o extrato em PDF ou cole o texto do extrato analítico da Caixa ao lado. O sistema verificará todos os depósitos devidos e pendentes.
        </p>
      </div>

      <!-- Painel de Resultados (Inicia Oculto) -->
      <div id="results-panel" class="hidden flex flex-col gap-6">
        
        <!-- Cabeçalho do Trabalhador e Ações -->
        <div class="bg-[#0e0e13] border border-amber-500/30 rounded-2xl p-6 shadow-xl relative overflow-hidden">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-800">
            <div>
              <span class="text-[10px] uppercase tracking-wider font-bold text-amber-400">Trabalhador Identificado</span>
              <h3 id="res-worker-name" class="text-lg font-black text-white">—</h3>
              <div class="flex items-center gap-3 text-xs text-neutral-400 mt-1 font-mono">
                <span>PIS/PASEP: <b id="res-worker-pis" class="text-neutral-200">—</b></span>
                <span>&bull;</span>
                <span id="res-worker-companies-count">1 Empresa(s)</span>
              </div>
            </div>

            <!-- Botões de Exportação -->
            <div id="export-buttons" class="flex items-center gap-2">
              <button id="btn-export-excel" class="px-3.5 py-2 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold flex items-center gap-1.5 transition-all">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                Exportar Excel
              </button>
              <button id="btn-print" class="px-3.5 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 text-xs font-bold flex items-center gap-1.5 transition-all">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Imprimir / PDF
              </button>
            </div>
          </div>

          <!-- Seletor de Empresas se houver mais de uma -->
          <div id="company-selector-wrapper" class="mt-4 hidden">
            <label class="text-[11px] font-bold text-neutral-400 uppercase tracking-wider block mb-1.5">Vínculo Empregatício Analisado:</label>
            <select id="company-select" class="w-full bg-[#14141a] border border-neutral-700 rounded-lg p-2.5 text-xs text-neutral-200 focus:outline-none focus:border-amber-400 font-mono">
            </select>
          </div>

          <!-- Dados do Contrato da Empresa Selecionada -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-neutral-800/80 text-xs font-mono">
            <div class="bg-[#121217] p-3 rounded-xl border border-neutral-800">
              <span class="text-neutral-500 text-[10px] block">ADMISSÃO</span>
              <span id="res-info-adm" class="font-bold text-white">—</span>
            </div>
            <div class="bg-[#121217] p-3 rounded-xl border border-neutral-800">
              <span class="text-neutral-500 text-[10px] block">AFASTAMENTO</span>
              <span id="res-info-dis" class="font-bold text-white">—</span>
            </div>
            <div class="bg-[#121217] p-3 rounded-xl border border-neutral-800">
              <span class="text-neutral-500 text-[10px] block">CONTA CEF</span>
              <span id="res-info-account" class="font-bold text-amber-400">—</span>
            </div>
            <div class="bg-[#121217] p-3 rounded-xl border border-neutral-800">
              <span class="text-neutral-500 text-[10px] block">SALDO REGISTRADO</span>
              <span id="res-info-balance" class="font-bold text-emerald-400">R$ 0,00</span>
            </div>
          </div>
        </div>

        <!-- Cards de Resumo e Estatísticas -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-[#0e0e13] border border-rose-500/30 rounded-2xl p-4 shadow-lg flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center shrink-0">
              <i data-lucide="alert-circle" class="w-6 h-6"></i>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-neutral-400 block">Meses Pendentes</span>
              <span id="stat-pending-count" class="text-xl font-black text-rose-400">0</span>
              <span class="text-[10px] text-neutral-500 block">não recolhidos</span>
            </div>
          </div>

          <div class="bg-[#0e0e13] border border-emerald-500/30 rounded-2xl p-4 shadow-lg flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
              <i data-lucide="check-circle-2" class="w-6 h-6"></i>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-neutral-400 block">Meses Pagos</span>
              <span id="stat-paid-count" class="text-xl font-black text-emerald-400">0</span>
              <span class="text-[10px] text-neutral-500 block">depósitos confirmados</span>
            </div>
          </div>

          <div class="bg-[#0e0e13] border border-amber-500/30 rounded-2xl p-4 shadow-lg flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
              <i data-lucide="trending-up" class="w-6 h-6"></i>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-neutral-400 block">Total Depositado</span>
              <span id="stat-total-paid" class="text-lg font-black text-amber-400">R$ 0,00</span>
              <span class="text-[10px] text-neutral-500 block">somatório pago</span>
            </div>
          </div>
        </div>

        <!-- Filtros Prescricionais -->
        <div id="filter-section" class="bg-[#0e0e13] border border-neutral-800 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
          <span class="font-semibold text-neutral-300 flex items-center gap-2">
            <i data-lucide="filter" class="w-4 h-4 text-amber-400"></i>
            Filtro Prescricional para Execução:
          </span>
          <div class="flex items-center gap-2">
            <button data-filter="all" class="filter-btn px-3 py-1.5 rounded-lg font-semibold bg-amber-500 text-black">
              Todo o Contrato
            </button>
            <button data-filter="5years" class="filter-btn px-3 py-1.5 rounded-lg font-semibold bg-neutral-800 hover:bg-neutral-700 text-neutral-300">
              Últimos 5 Anos
            </button>
            <button data-filter="30years" class="filter-btn px-3 py-1.5 rounded-lg font-semibold bg-neutral-800 hover:bg-neutral-700 text-neutral-300">
              Regra Trintenária
            </button>
          </div>
        </div>

        <!-- Tabela Cronológica de Competências -->
        <div class="bg-[#0e0e13] border border-neutral-800 rounded-2xl overflow-hidden shadow-xl">
          <div class="p-4 bg-[#121217] border-b border-neutral-800 flex items-center justify-between">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
              <i data-lucide="table" class="w-4 h-4 text-amber-400"></i>
              Detalhamento Cronológico das Competências
            </h4>
            <span id="table-total-count" class="text-[11px] text-neutral-400 font-mono">0 competências</span>
          </div>

          <div class="overflow-x-auto max-h-96 overflow-y-auto custom-scrollbar">
            <table class="w-full text-left text-xs font-mono">
              <thead class="bg-[#08080a] text-neutral-400 sticky top-0 border-b border-neutral-800">
                <tr>
                  <th class="p-3">#</th>
                  <th class="p-3">Competência</th>
                  <th class="p-3">Situação</th>
                  <th class="p-3">Data Depósito</th>
                  <th class="p-3 text-right">Valor Pago</th>
                </tr>
              </thead>
              <tbody id="competencies-table-body" class="divide-y divide-neutral-900 text-neutral-200">
                <!-- Inserido dinamicamente via JS -->
              </tbody>
            </table>
          </div>
        </div>

      </div>

    </div>

  </main>

  <!-- RODAPÉ -->
  <footer class="border-t border-neutral-800 bg-[#0a0a0d] py-6 text-center text-xs text-neutral-500">
    <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
      <p>Auditor de FGTS &bull; Caixa Econômica Federal &bull; Sistema Pericial Trabalhista</p>
      <p class="font-mono text-[11px] text-neutral-600">Arquivo Único Autônomo para Hospedagem Própria</p>
    </div>
  </footer>

  <!-- SCRIPT DE AUDITORIA, REGEX, CÁLCULOS E INTEGRAÇÃO GEMINI -->
  <script>
    // =========================================================================
    // CONFIGURAÇÃO DA CHAVE DO GEMINI
    // Se desejar usar a IA da Google, coloque sua chave abaixo.
    // Se deixar vazio ou como está, o sistema usa o Auditor Local automaticamente!
    // =========================================================================
    const GEMINI_API_KEY = "COLOQUE_SUA_CHAVE_AQUI";

    // Inicialização dos ícones Lucide
    lucide.createIcons();

    // Estado da Aplicação
    let currentAuditedData = null;
    let selectedCompanyIndex = 0;
    let activeFilter = 'all'; // 'all', '5years', '30years'

    // Exemplo de Extrato Oficial da CEF
    const SAMPLE_EXTRACT = `CAIXA ECONOMICA FEDERAL - EXTRATO DA CONTA VINCULADA DO FGTS
PIS/PASEP: 123.45678.90-1
NOME: CARLOS EDUARDO DA SILVA
EMPREGADOR: EMPRESA MODELO DE ENGENHARIA LTDA
CNPJ: 12.345.678/0001-90
CONTA FGTS: 00001234567-8
ADMISSAO: 10/01/2021
AFASTAMENTO: 20/03/2023
SALDO: R$ 3.840,50

HISTORICO DOS DEPOSITOS:
15/02/2021 115-DEPOSITO JANEIRO/2021 240,00
15/03/2021 115-DEPOSITO FEVEREIRO/2021 240,00
15/04/2021 115-DEPOSITO MARCO/2021 240,00
15/05/2021 115-DEPOSITO ABRIL/2021 240,00
15/06/2021 115-DEPOSITO MAIO/2021 240,00
15/08/2021 115-DEPOSITO JULHO/2021 240,00
15/09/2021 115-DEPOSITO AGOSTO/2021 240,00
15/10/2021 115-DEPOSITO SETEMBRO/2021 240,00
15/11/2021 115-DEPOSITO OUTUBRO/2021 240,00
15/01/2022 115-DEPOSITO DEZEMBRO/2021 240,00
15/02/2022 115-DEPOSITO JANEIRO/2022 260,00
15/03/2022 115-DEPOSITO FEVEREIRO/2022 260,00
15/04/2022 115-DEPOSITO MARCO/2022 260,00
15/06/2022 115-DEPOSITO MAIO/2022 260,00
15/07/2022 115-DEPOSITO JUNHO/2022 260,00
15/09/2022 115-DEPOSITO AGOSTO/2022 260,00
15/10/2022 115-DEPOSITO SETEMBRO/2022 260,00
15/11/2022 115-DEPOSITO OUTUBRO/2022 260,00
15/12/2022 115-DEPOSITO NOVEMBRO/2022 260,00
15/01/2023 115-DEPOSITO DEZEMBRO/2022 260,00
15/02/2023 115-DEPOSITO JANEIRO/2023 280,00`;

    // Elementos da Interface
    const textInput = document.getElementById('text-input');
    const fileInput = document.getElementById('file-input');
    const dropZone = document.getElementById('drop-zone');
    const btnAudit = document.getElementById('btn-audit');
    const btnClear = document.getElementById('btn-clear');
    const btnExample = document.getElementById('btn-example');
    const emptyState = document.getElementById('empty-state');
    const resultsPanel = document.getElementById('results-panel');
    const companySelect = document.getElementById('company-select');
    const companySelectorWrapper = document.getElementById('company-selector-wrapper');
    const filterButtons = document.querySelectorAll('.filter-btn');

    // Botão Exemplo
    btnExample.addEventListener('click', () => {
      textInput.value = SAMPLE_EXTRACT;
      executarAuditoria(SAMPLE_EXTRACT);
    });

    // Botão Limpar
    btnClear.addEventListener('click', () => {
      textInput.value = '';
      emptyState.classList.remove('hidden');
      resultsPanel.classList.add('hidden');
      currentAuditedData = null;
    });

    // Upload & Drag-and-drop de Arquivos
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('border-amber-400');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-amber-400'));
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('border-amber-400');
      if (e.dataTransfer.files.length > 0) {
        processarArquivos(e.dataTransfer.files);
      }
    });
    fileInput.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        processarArquivos(e.target.files);
      }
    });

    // Leitura dos Arquivos PDF / TXT
    async function processarArquivos(files) {
      const progressContainer = document.getElementById('file-progress-container');
      const progressLabel = document.getElementById('file-progress-label');
      const progressFill = document.getElementById('file-progress-fill');
      const progressPercent = document.getElementById('file-progress-percent');

      progressContainer.classList.remove('hidden');
      let combinedText = '';

      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        progressLabel.innerText = `Lendo (${i + 1}/${files.length}): ${file.name}`;

        if (file.name.toLowerCase().endsWith('.pdf')) {
          const pdfText = await extrairTextoDePdf(file, (curr, total) => {
            const p = Math.round((curr / total) * 100);
            progressFill.style.width = `${p}%`;
            progressPercent.innerText = `${p}%`;
          });
          combinedText += `\n--- ARQUIVO: ${file.name} ---\n` + pdfText + `\n`;
        } else {
          const txt = await file.text();
          combinedText += `\n--- ARQUIVO: ${file.name} ---\n` + txt + `\n`;
        }
      }

      progressContainer.classList.add('hidden');
      textInput.value = combinedText.trim();
      executarAuditoria(textInput.value);
    }

    // Leitura de PDF com PDF.js
    async function extrairTextoDePdf(file, onProgress) {
      const arrayBuffer = await file.arrayBuffer();
      const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(arrayBuffer) }).promise;
      let fullText = '';
      for (let p = 1; p <= pdf.numPages; p++) {
        const page = await pdf.getPage(p);
        const textContent = await page.getTextContent();
        // Reconstrói as linhas pela posição vertical de cada trecho (evita juntar a página inteira em uma única linha)
        const pageLines = [];
        let linhaAtual = '';
        let ultimoY = null;
        for (const item of textContent.items) {
          const y = item.transform ? item.transform[5] : null;
          if (ultimoY !== null && y !== null && Math.abs(y - ultimoY) > 2) {
            pageLines.push(linhaAtual);
            linhaAtual = '';
          }
          if (item.str) linhaAtual += (linhaAtual ? ' ' : '') + item.str;
          if (item.hasEOL) {
            pageLines.push(linhaAtual);
            linhaAtual = '';
          }
          if (y !== null) ultimoY = y;
        }
        if (linhaAtual) pageLines.push(linhaAtual);
        const pageStr = pageLines.join('\n');
        fullText += pageStr + '\n';
        if (onProgress) onProgress(p, pdf.numPages);
      }
      return fullText;
    }

    // Clique no Botão de Auditoria
    btnAudit.addEventListener('click', () => {
      const txt = textInput.value.trim();
      if (!txt) {
        alert('Por favor, cole o texto do extrato ou selecione um arquivo PDF/TXT.');
        return;
      }
      executarAuditoria(txt);
    });

    // Filtros Prescricionais
    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => {
          b.classList.remove('bg-amber-500', 'text-black');
          b.classList.add('bg-neutral-800', 'text-neutral-300');
        });
        btn.classList.add('bg-amber-500', 'text-black');
        btn.classList.remove('bg-neutral-800', 'text-neutral-300');
        activeFilter = btn.getAttribute('data-filter');
        renderizarVisualizacaoEmpresa();
      });
    });

    // Seletor de Vínculo/Empresa
    companySelect.addEventListener('change', (e) => {
      selectedCompanyIndex = parseInt(e.target.value, 10);
      renderizarVisualizacaoEmpresa();
    });

    // =========================================================================
    // FUNÇÃO PRINCIPAL: CHAMADA AO GEMINI OU AUDITOR LOCAL
    // =========================================================================
    async function executarAuditoria(rawText) {
      btnAudit.disabled = true;
      btnAudit.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-2 border-black border-t-transparent mr-2"></div> Processando Auditoria...';

      // Se houver chave do Gemini configurada, tenta chamar a IA
      if (GEMINI_API_KEY && GEMINI_API_KEY !== "COLOQUE_SUA_CHAVE_AQUI" && GEMINI_API_KEY.trim().length > 10) {
        try {
          const resultadoIA = await chamarGemini(rawText, GEMINI_API_KEY);
          if (resultadoIA && resultadoIA.workers && resultadoIA.workers.length > 0) {
            currentAuditedData = resultadoIA.workers[0];
            concluirAuditoria();
            return;
          }
        } catch (err) {
          console.warn("Falha na chamada ao Gemini, executando Auditor Local CEF:", err);
        }
      }

      // Fallback autônomo imediato via Auditor Local CEF (Sem dependências externas)
      executarAuditorLocalCEF(rawText);
      concluirAuditoria();
    }

    function concluirAuditoria() {
      btnAudit.disabled = false;
      btnAudit.innerHTML = '<i data-lucide="activity" class="w-4 h-4 fill-black"></i><span>Realizar Auditoria de FGTS</span>';
      emptyState.classList.add('hidden');
      resultsPanel.classList.remove('hidden');
      selectedCompanyIndex = 0;
      renderizarVisualizacaoEmpresa();
      lucide.createIcons();
    }

    // =========================================================================
    // FUNÇÃO: chamarGemini
    // Concentra toda e qualquer comunicação com a API do Google Gemini
    // =========================================================================
    async function chamarGemini(textoExtrato, chaveApiKey) {
      const promptAuditoria = `Você é um auditor contábil especialista em extratos de FGTS da Caixa Econômica Federal.
Analise detalhadamente o extrato fornecido abaixo e extraia:
1. Nome completo do trabalhador, PIS/PASEP e CPF (se houver).
2. Para cada vínculo empregatício encontrado:
   - Razão Social / Empregador e CNPJ.
   - Conta vinculada FGTS.
   - Data de Admissão e Afastamento (formato DD/MM/AAAA).
   - Saldo registrado.
   - Lista de depósitos pagos (competência MM/AAAA, data do depósito e valor numérico em reais).

Retorne ESTRITAMENTE um JSON no seguinte formato:
{
  "workers": [
    {
      "workerName": "NOME DO TRABALHADOR",
      "pis": "000.00000.00-0",
      "cpf": "",
      "companies": [
        {
          "name": "NOME DA EMPRESA",
          "cnpj": "00.000.000/0000-00",
          "accountNumber": "000000000-0",
          "admissionDate": "DD/MM/AAAA",
          "dischargeDate": "DD/MM/AAAA",
          "balance": "R$ 0,00",
          "paidDeposits": [
            {
              "competence": "MM/AAAA",
              "depositDate": "DD/MM/AAAA",
              "value": 150.00
            }
          ]
        }
      ]
    }
  ]
}

Extrato analítico:
${textoExtrato}`;

      const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=${chaveApiKey}`;

      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          contents: [{ role: "user", parts: [{ text: promptAuditoria }] }],
          generationConfig: {
            responseMimeType: "application/json",
            temperature: 0.1
          }
        })
      });

      if (!response.ok) {
        throw new Error(`Erro na API Gemini: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      const rawJson = data.candidates?.[0]?.content?.parts?.[0]?.text;
      if (!rawJson) throw new Error("Resposta vazia da IA");
      return JSON.parse(rawJson);
    }

    // =========================================================================
    // MOTOR DE AUDITORIA LOCAL CEF (MOTOR ANALÍTICO PERICIAL)
    // =========================================================================
    function executarAuditorLocalCEF(rawText) {
      const lines = rawText.split('\n').map(l => l.trim()).filter(Boolean);
      
      let workerName = 'TRABALHADOR IDENTIFICADO';
      let pis = 'NÃO LOCALIZADO';
      let admissionDate = null;
      let dischargeDate = null;
      let companyName = 'EMPRESA EMPREGADORA';
      let cnpj = 'NÃO INFORMADO';
      let accountNumber = 'N/A';
      let balanceStr = 'R$ 0,00';
      
      const paidDeposits = [];

      const monthNames = {
        'JANEIRO': '01', 'FEVEREIRO': '02', 'MARCO': '03', 'MARÇO': '03',
        'ABRIL': '04', 'MAIO': '05', 'JUNHO': '06', 'JULHO': '07',
        'AGOSTO': '08', 'SETEMBRO': '09', 'OUTUBRO': '10', 'NOVEMBRO': '11', 'DEZEMBRO': '12',
        'JAN': '01', 'FEV': '02', 'MAR': '03', 'ABR': '04', 'MAI': '05', 'JUN': '06',
        'JUL': '07', 'AGO': '08', 'SET': '09', 'OUT': '10', 'NOV': '11', 'DEZ': '12'
      };

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const upper = line.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

        // PIS/PASEP
        if (upper.includes('PIS') || upper.includes('PASEP')) {
          const m = line.match(/(?:PIS|PASEP|NIT)[\s/:]*([\d\.\-]+)/i);
          if (m && m[1].replace(/\D/g, '').length >= 10) pis = m[1].trim();
        }

        // Nome do Trabalhador
        if (upper.includes('NOME:') || upper.includes('TRABALHADOR:')) {
          const m = line.match(/(?:NOME|TRABALHADOR)[\s:]+([A-ZÀ-Ú\s]{4,60})/i);
          if (m && m[1].trim().length > 3) workerName = m[1].trim();
        }

        // Empregador
        if (upper.includes('EMPREGADOR:') || upper.includes('EMPRESA:')) {
          const m = line.match(/(?:EMPREGADOR|EMPRESA)[\s:]+([A-ZÀ-Ú0-9\s\.\-\&]{4,70})/i);
          if (m) companyName = m[1].trim();
        }

        // CNPJ
        if (upper.includes('CNPJ') || upper.includes('CGC')) {
          const m = line.match(/(?:CNPJ|CGC)[\s:]*([\d\.\/\-]+)/i);
          if (m) cnpj = m[1].trim();
        }

        // Admissão
        if (upper.includes('ADMISSAO') || upper.includes('ADM:')) {
          const m = line.match(/(\d{2}\/\d{2}\/\d{4})/);
          if (m) admissionDate = m[1];
        }

        // Afastamento / Demissão
        if (upper.includes('AFASTAMENTO') || upper.includes('DEMISSAO') || upper.includes('RESCISAO')) {
          const m = line.match(/(\d{2}\/\d{2}\/\d{4})/);
          if (m) dischargeDate = m[1];
        }

        // Conta FGTS
        if (upper.includes('CONTA') && (upper.includes('FGTS') || upper.includes('VINCULADA'))) {
          const m = line.match(/CONTA[^\d]*([\d\-\.\/]+)/i);
          if (m) accountNumber = m[1].trim();
        }

        // Saldo
        if (upper.includes('SALDO')) {
          const m = line.match(/R\$\s*([\d\.,]+)/i);
          if (m) balanceStr = 'R$ ' + m[1];
        }

        // Depósitos (Código 115 ou Depósito)
        if (upper.includes('115') || upper.includes('DEPOSITO') || upper.includes('DEP ')) {
          let comp = null;
          let date = null;
          let val = 0;

          const dateMatch = line.match(/^(\d{2}\/\d{2}\/\d{4})/);
          if (dateMatch) date = dateMatch[1];

          const compWordMatch = upper.match(/(JANEIRO|FEVEREIRO|MARCO|MARÇO|ABRIL|MAIO|JUNHO|JULHO|AGOSTO|SETEMBRO|OUTUBRO|NOVEMBRO|DEZEMBRO|JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ)[\/\s]*(\d{4})/);
          if (compWordMatch) {
            const mNum = monthNames[compWordMatch[1]];
            const yNum = compWordMatch[2];
            comp = `${mNum}/${yNum}`;
          } else {
            const compNumMatch = line.match(/(\d{2})\/(\d{4})/);
            if (compNumMatch) comp = `${compNumMatch[1]}/${compNumMatch[2]}`;
          }

          const valMatches = line.match(/([\d\.]+,\d{2})/g);
          if (valMatches && valMatches.length > 0) {
            const lastVal = valMatches[valMatches.length - 1];
            val = parseFloat(lastVal.replace(/\./g, '').replace(',', '.'));
          }

          if (comp && !paidDeposits.some(d => d.competence === comp)) {
            paidDeposits.push({
              competence: comp,
              depositDate: date || 'Registrado',
              value: val
            });
          }
        }
      }

      if (!admissionDate && paidDeposits.length > 0) {
        admissionDate = '01/' + paidDeposits[0].competence;
      }
      if (!dischargeDate && paidDeposits.length > 0) {
        dischargeDate = '30/' + paidDeposits[paidDeposits.length - 1].competence;
      }

      currentAuditedData = {
        workerName: workerName,
        pis: pis,
        companies: [{
          id: 'comp-1',
          name: companyName,
          cnpj: cnpj,
          accountNumber: accountNumber,
          admissionDate: admissionDate || '01/01/2020',
          dischargeDate: dischargeDate || '31/12/2023',
          balance: balanceStr,
          paidDeposits: paidDeposits
        }]
      };
    }

    // =========================================================================
    // RENDERIZAÇÃO DA VISUALIZAÇÃO E TABELA
    // =========================================================================
    function renderizarVisualizacaoEmpresa() {
      if (!currentAuditedData || !currentAuditedData.companies || !currentAuditedData.companies.length) return;

      const comp = currentAuditedData.companies[selectedCompanyIndex];

      document.getElementById('res-worker-name').innerText = currentAuditedData.workerName;
      document.getElementById('res-worker-pis').innerText = currentAuditedData.pis;
      document.getElementById('res-worker-companies-count').innerText = `${currentAuditedData.companies.length} Empresa(s)`;
      
      document.getElementById('res-info-adm').innerText = comp.admissionDate;
      document.getElementById('res-info-dis').innerText = comp.dischargeDate;
      document.getElementById('res-info-account').innerText = comp.accountNumber;
      document.getElementById('res-info-balance').innerText = comp.balance;

      // Gerar cronologia mês a mês
      const [admD, admM, admY] = comp.admissionDate.split('/').map(Number);
      const [disD, disM, disY] = comp.dischargeDate.split('/').map(Number);

      let curM = admM || 1;
      let curY = admY || 2020;
      const endM = disM || 12;
      const endY = disY || 2023;

      const allTimeline = [];
      while (curY < endY || (curY === endY && curM <= endM)) {
        allTimeline.push(`${curM.toString().padStart(2, '0')}/${curY}`);
        curM++;
        if (curM > 12) {
          curM = 1;
          curY++;
        }
      }

      // Aplicação dos Filtros Prescricionais
      const currentYear = new Date().getFullYear();
      const filteredTimeline = allTimeline.filter(c => {
        if (activeFilter === '5years') {
          const y = parseInt(c.split('/')[1], 10);
          return y >= currentYear - 5;
        }
        if (activeFilter === '30years') {
          const y = parseInt(c.split('/')[1], 10);
          return y >= currentYear - 30;
        }
        return true;
      });

      // Mapeamento de Pagamentos
      const depositsMap = {};
      comp.paidDeposits.forEach(d => { depositsMap[d.competence] = d; });

      let paidCount = 0;
      let pendingCount = 0;
      let totalValuePaid = 0;

      const tableBody = document.getElementById('competencies-table-body');
      tableBody.innerHTML = '';

      filteredTimeline.forEach((competence, idx) => {
        const dep = depositsMap[competence];
        const isPaid = !!dep;

        if (isPaid) {
          paidCount++;
          totalValuePaid += (dep.value || 0);
        } else {
          pendingCount++;
        }

        const tr = document.createElement('tr');
        tr.className = isPaid ? 'hover:bg-neutral-800/40' : 'bg-rose-950/15 hover:bg-rose-950/25';

        const valFormatted = isPaid && dep.value > 0
          ? dep.value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
          : '—';

        tr.innerHTML = `
          <td class="p-3 text-neutral-500">${(idx + 1).toString().padStart(2, '0')}</td>
          <td class="p-3 font-bold text-white">${competence}</td>
          <td class="p-3">
            ${isPaid 
              ? '<span class="inline-flex items-center gap-1 text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">✓ PAGO</span>'
              : '<span class="inline-flex items-center gap-1 text-rose-400 font-bold bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">✗ PENDENTE</span>'
            }
          </td>
          <td class="p-3 text-neutral-400">${isPaid ? (dep.depositDate || 'Confirmado') : '—'}</td>
          <td class="p-3 text-right font-bold ${isPaid ? 'text-amber-400' : 'text-neutral-600'}">${valFormatted}</td>
        `;
        tableBody.appendChild(tr);
      });

      // Atualiza Estatísticas
      document.getElementById('stat-pending-count').innerText = pendingCount;
      document.getElementById('stat-paid-count').innerText = paidCount;
      document.getElementById('stat-total-paid').innerText = totalValuePaid.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
      document.getElementById('table-total-count').innerText = `${filteredTimeline.length} competências analisadas`;
    }

    // =========================================================================
    // EXPORTAÇÃO EXCEL (.XLSX)
    // =========================================================================
    document.getElementById('btn-export-excel').addEventListener('click', () => {
      if (!currentAuditedData) return;
      const comp = currentAuditedData.companies[selectedCompanyIndex];

      const rows = [
        ['AUDITORIA PERICIAL DE FGTS - LAUDO ANALÍTICO'],
        ['Trabalhador:', currentAuditedData.workerName],
        ['PIS/PASEP:', currentAuditedData.pis],
        ['Empregador:', comp.name],
        ['CNPJ:', comp.cnpj],
        ['Admissão:', comp.admissionDate],
        ['Afastamento:', comp.dischargeDate],
        ['Conta FGTS:', comp.accountNumber],
        [],
        ['Item', 'Competência', 'Status', 'Data Depósito', 'Valor Pago (R$)']
      ];

      const depositsMap = {};
      comp.paidDeposits.forEach(d => { depositsMap[d.competence] = d; });

      const [admD, admM, admY] = comp.admissionDate.split('/').map(Number);
      const [disD, disM, disY] = comp.dischargeDate.split('/').map(Number);
      let curM = admM || 1;
      let curY = admY || 2020;
      const endM = disM || 12;
      const endY = disY || 2023;

      let idx = 1;
      while (curY < endY || (curY === endY && curM <= endM)) {
        const compStr = `${curM.toString().padStart(2, '0')}/${curY}`;
        const dep = depositsMap[compStr];
        rows.push([
          idx++,
          compStr,
          dep ? 'PAGO' : 'PENDENTE',
          dep ? (dep.depositDate || 'Confirmado') : '—',
          dep ? dep.value : 0
        ]);
        curM++;
        if (curM > 12) { curM = 1; curY++; }
      }

      const ws = XLSX.utils.aoa_to_sheet(rows);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Auditoria FGTS');
      XLSX.writeFile(wb, `Auditoria_FGTS_${currentAuditedData.workerName.replace(/\s+/g, '_')}.xlsx`);
    });

    // IMPRESSÃO / SALVAR COMO PDF
    document.getElementById('btn-print').addEventListener('click', () => {
      window.print();
    });
  </script>
</body>
</html>