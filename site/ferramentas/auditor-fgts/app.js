/**
 * AUDITOR DE FGTS - CAIXA ECONÔMICA FEDERAL
 * Motor Analítico Pericial 100% Vanilla JS (Navegador / CDN)
 * Lê PDFs/TXTs, agrupa múltiplos trabalhadores, reconcilia contas,
 * aplica filtros prescricionais (STF/Quinquenal) e exporta em Excel (.xlsx) e PDF (.pdf).
 */

(function () {
  "use strict";

  // ==========================================================================
  // 1. CONSTANTES E MAPEAMENTOS OFICIAIS DA CEF
  // ==========================================================================

  const MONTHS_MAP = {
    "JANEIRO": "01", "FEVEREIRO": "02", "MARCO": "03", "MARÇO": "03", "ABRIL": "04",
    "MAIO": "05", "JUNHO": "06", "JULHO": "07", "AGOSTO": "08", "SETEMBRO": "09",
    "OUTUBRO": "10", "NOVEMBRO": "11", "DEZEMBRO": "12",
    "JAN": "01", "FEV": "02", "MAR": "03", "ABR": "04", "MAI": "05", "JUN": "06",
    "JUL": "07", "AGO": "08", "SET": "09", "OUT": "10", "NOV": "11", "DEZ": "12"
  };

  const PT_MONTHS = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
  const PT_MONTHS_UPPER = ["JAN", "FEV", "MAR", "ABR", "MAI", "JUN", "JUL", "AGO", "SET", "OUT", "NOV", "DEZ"];

  // ==========================================================================
  // 2. ESTADO GLOBAL DA APLICAÇÃO
  // ==========================================================================

  const state = {
    inputText: "",
    uploadedFiles: [],
    isReadingFiles: false,
    fileReadStatus: null,
    
    // Resultados de auditoria
    allWorkers: [],
    selectedWorkerIndex: 0,
    selectedCompanyId: null,
    activeTab: "dashboard",
    
    // Filtros Prescricionais
    prescriptionRule: "QUINQUENAL", // 'NONE' | 'QUINQUENAL' | 'STF_709212' | 'YEAR_START'
    prescriptionCutoffDate: getTodayDateBR(),
    prescriptionStartYear: "",
    
    // Filtro adicional de exportação por ano
    exportStartYear: "",
    
    // Filtros do Inspetor
    inspectorFilter: "ALL", // 'ALL' | 'PAYMENT' | 'IGNORED_JAM' | 'ELSE'
    inspectorSearch: "",
    
    isLoading: false,
    error: null
  };

  // ==========================================================================
  // 3. FUNÇÕES AUXILIARES DE FORMATAÇÃO E DATAS
  // ==========================================================================

  function getTodayDateBR() {
    const today = new Date();
    const d = String(today.getDate()).padStart(2, "0");
    const m = String(today.getMonth() + 1).padStart(2, "0");
    const y = today.getFullYear();
    return `${d}/${m}/${y}`;
  }

  function formatCompetencePT(compStr) {
    if (!compStr || !compStr.includes("/")) return compStr || "";
    const [mStr, yStr] = compStr.split("/");
    const m = parseInt(mStr, 10);
    if (!isNaN(m) && m >= 1 && m <= 12) {
      return `${PT_MONTHS[m - 1]}/${yStr}`;
    }
    return compStr;
  }

  function formatCompetencePTUpper(compStr) {
    if (!compStr || !compStr.includes("/")) return compStr || "";
    const [mStr, yStr] = compStr.split("/");
    const m = parseInt(mStr, 10);
    if (!isNaN(m) && m >= 1 && m <= 12) {
      return `${PT_MONTHS_UPPER[m - 1]}/${yStr}`;
    }
    return compStr;
  }

  function cleanCompanyNameKey(name) {
    if (!name) return "";
    return String(name).toUpperCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\b(LTDA|SA|S\/A|S\.A\.|LIMITADA|S A|INCORPORADORA|CONSTRUTORA|CI|CIA|COMPANHIA|LOGISTICA|TRANSPORTES|SERVICOS)\b/g, "")
      .replace(/[^A-Z0-9]/g, "")
      .trim();
  }

  function normalizeName(name) {
    if (!name) return "";
    return String(name).trim()
      .toUpperCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ");
  }

  function parseDateToMonths(dateStr, fallbackYear, fallbackMonth) {
    if (!dateStr) return fallbackYear * 12 + fallbackMonth;
    const str = String(dateStr).trim();
    const parts = str.split("/");
    if (parts.length === 3) {
      const month = parseInt(parts[1], 10);
      const year = parseInt(parts[2], 10);
      if (!isNaN(month) && !isNaN(year)) return year * 12 + month;
    } else if (parts.length === 2) {
      const month = parseInt(parts[0], 10);
      const year = parseInt(parts[1], 10);
      if (!isNaN(month) && !isNaN(year)) return year * 12 + month;
    }
    return fallbackYear * 12 + fallbackMonth;
  }

  function getCompetencyPeriod(start, end) {
    if (!start || !end) return [];
    const startParts = start.split("/");
    const endParts = end.split("/");
    const startMonth = parseInt(startParts[0], 10);
    const startYear = parseInt(startParts[1], 10);
    const endMonth = parseInt(endParts[0], 10);
    const endYear = parseInt(endParts[1], 10);

    if (isNaN(startMonth) || isNaN(startYear) || isNaN(endMonth) || isNaN(endYear)) {
      return [start];
    }
    if (startYear > endYear || (startYear === endYear && startMonth > endMonth)) {
      return [start];
    }

    const competencies = [];
    let curYear = startYear;
    let curMonth = startMonth;
    let iterations = 0;
    const maxIterations = 360;

    while ((curYear < endYear || (curYear === endYear && curMonth <= endMonth)) && iterations < maxIterations) {
      iterations++;
      const formattedMonth = curMonth.toString().padStart(2, "0");
      competencies.push(`${formattedMonth}/${curYear}`);
      curMonth++;
      if (curMonth > 12) {
        curMonth = 1;
        curYear++;
      }
    }
    return competencies;
  }

  function extractMonthAndYear(lineUpper) {
    for (const mName of Object.keys(MONTHS_MAP)) {
      const monthIdx = lineUpper.indexOf(mName);
      if (monthIdx !== -1) {
        const sub = lineUpper.substring(monthIdx);
        // Ex: JANEIRO 2022, MAIO/2021, ABR - 2023, JUNHO DE 2024
        const regex = new RegExp(`${mName}(?:\\s+|\\s*\\/\\s*|\\s*-\\s*|\\s+DE\\s+)(\\d{4})`);
        const match = sub.match(regex);
        if (match) {
          return { month: MONTHS_MAP[mName], year: match[1] };
        }
        // Ex: MAIO/22 ou MAIO 22
        const shortYearRegex = new RegExp(`${mName}(?:\\s+|\\s*\\/\\s*)(\\d{2})\\b`);
        const shortMatch = sub.match(shortYearRegex);
        if (shortMatch) {
          return { month: MONTHS_MAP[mName], year: "20" + shortMatch[1] };
        }
        // Fallback: qualquer ano de 4 dígitos após o mês
        const anyYearMatch = sub.match(/\b(20\d{2}|19\d{2})\b/);
        if (anyYearMatch) {
          return { month: MONTHS_MAP[mName], year: anyYearMatch[1] };
        }
      }
    }
    return null;
  }

  // ==========================================================================
  // 4. MOTOR DE EXTRAÇÃO DE TEXTO DE PDF VIA PDF.JS (CDN)
  // ==========================================================================

  async function extractTextFromPdf(file, onPageProgress) {
    const arrayBuffer = await file.arrayBuffer();
    const pdfjsLib = window.pdfjsLib;
    if (!pdfjsLib) {
      throw new Error("A biblioteca PDF.js não foi carregada no navegador. Verifique a conexão com a internet.");
    }

    const loadingTask = pdfjsLib.getDocument({ data: new Uint8Array(arrayBuffer) });
    const pdf = await loadingTask.promise;
    let fullText = "";

    for (let i = 1; i <= pdf.numPages; i++) {
      if (onPageProgress) {
        onPageProgress(i, pdf.numPages);
      }
      const page = await pdf.getPage(i);
      const textContent = await page.getTextContent();
      const items = textContent.items || [];

      // Agrupa itens em linhas por coordenada Y com tolerância de 5.0 pontos (clustering)
      // Essencial para organizar tabelas da Caixa Econômica Federal em leitura natural
      const rows = [];
      const tolerance = 5.0;

      for (const item of items) {
        if (typeof item.str !== "string" || !item.str.trim()) continue;
        const y = item.transform ? item.transform[5] : 0;
        let foundRow = rows.find(r => Math.abs(r.y - y) <= tolerance);
        if (foundRow) {
          foundRow.items.push(item);
        } else {
          rows.push({ y, items: [item] });
        }
      }

      // Ordena linhas de cima para baixo
      rows.sort((a, b) => b.y - a.y);

      let pageText = "";
      for (const row of rows) {
        // Ordena itens dentro da linha da esquerda para a direita
        row.items.sort((a, b) => {
          const xA = a.transform ? a.transform[4] : 0;
          const xB = b.transform ? b.transform[4] : 0;
          return xA - xB;
        });

        const rowStr = row.items.map(item => item.str.trim()).join(" ");
        if (rowStr.trim()) {
          pageText += rowStr + "\n";
        }
      }

      if (pageText.trim()) {
        fullText += pageText + "\n\n";
      }
    }

    if (!fullText.trim()) {
      throw new Error("Não foi possível ler texto deste PDF. O arquivo pode ser uma imagem digitalizada sem camada OCR. Use um extrato oficial emitido digitalmente pela Caixa.");
    }

    return fullText;
  }

  // ==========================================================================
  // 5. MOTOR PERICIAL DE RECONHECIMENTO E AUDITORIA CEF
  // ==========================================================================

  function extractWorkerPISFromText(text) {
    const lines = text.split("\n");
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      const upper = line.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

      if (upper.includes("PIS") || upper.includes("PASEP") || upper.includes("NIT")) {
        const match = line.match(/(?:PIS\/PASEP|P\.I\.S\.\/P\.A\.S\.E\.P\.|PIS|P\.I\.S\.|NIT|PASEP)\s*[:\.]?\s*([\d\.-]+)/i);
        if (match && match[1].trim()) {
          const digits = match[1].replace(/\D/g, "");
          if (digits.length >= 10 && digits.length <= 12) {
            return digits;
          }
        }
      }

      if (upper.includes("PIS/PASEP") || upper.includes("PIS / PASEP")) {
        for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
          const nextLine = lines[j].trim();
          if (nextLine && !nextLine.toUpperCase().includes("EMPRESA") && !nextLine.includes("----")) {
            const directMatch = nextLine.match(/\b\d{3}\.\d{5}\.\d{2}[-\.]\d{1}\b/) || nextLine.match(/\b\d{11}\b/);
            if (directMatch) {
              return directMatch[0].replace(/\D/g, "");
            }
          }
        }
      }
    }

    const globalMatch = text.match(/\b\d{3}\.\d{5}\.\d{2}[-\.]\d{1}\b/);
    if (globalMatch) return globalMatch[0].replace(/\D/g, "");
    return "";
  }

  function cleanWorkerName(raw) {
    if (!raw) return "TRABALHADOR NÃO IDENTIFICADO";
    let str = raw.trim();
    // Remove prefixes
    str = str.replace(/^(?:NOME(?:\s*DO\s*TRABALHADOR)?\s*[:\.]?\s*)/i, "");
    // Cut off before any metadata tokens that appear in CEF tables
    str = str.split(/\b(?:PIS|PASEP|P\.I\.S\.|P\.A\.S\.E\.P\.|NIT|CPF|C\.P\.F\.|CNPJ|CREDITO|JAM|NUM\.CONTA|CONTA|DTA|CATEGORIA|INSCRICAO|EMPRESA)\b/i)[0];
    // Cut off before any date like 21/01/2025
    str = str.split(/\b\d{2}\/\d{2}\/\d{4}\b/)[0];
    // Cut off before "R$" or currency
    str = str.split(/\bR\$\b/i)[0];
    // Remove trailing punctuation or whitespace
    str = str.replace(/[-–—:;,.\s]+$/, "").trim();
    return str || "TRABALHADOR NÃO IDENTIFICADO";
  }

  function extractWorkerNameFromText(text) {
    const lines = text.split("\n");
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      const upper = line.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

      if (upper.includes("NOME DO TRABALHADOR")) {
        if (i + 1 < lines.length) {
          const nextLine = lines[i + 1].trim();
          if (nextLine && !nextLine.includes("PIS/PASEP") && !nextLine.includes("------") && nextLine.length > 3) {
            const parts = nextLine.split(/\s{2,}/);
            const candidate = cleanWorkerName(parts[0]);
            if (candidate && candidate.length > 3 && !candidate.toUpperCase().includes("NUM.CONTA")) {
              return candidate;
            }
          }
        }
      }

      if (upper.includes("NOME:")) {
        const match = line.match(/NOME:\s*([^\n\r]+)/i);
        if (match && match[1].trim()) {
          const parts = match[1].trim().split(/\s{2,}/);
          const candidate = cleanWorkerName(parts[0]);
          if (candidate && candidate.length > 3) {
            return candidate;
          }
        }
      }
    }

    const matchDirect = text.match(/NOME DO TRABALHADOR\s*\n\s*([^\n\r]+)/i);
    if (matchDirect && matchDirect[1].trim()) {
      const parts = matchDirect[1].trim().split(/\s{2,}/);
      const candidate = cleanWorkerName(parts[0]);
      if (candidate && candidate.length > 3) return candidate;
    }

    return "TRABALHADOR NÃO IDENTIFICADO";
  }

  function groupTextByWorker(rawText) {
    const lines = rawText.split("\n");
    const subDocs = [];
    let currentDoc = [];
    let hasEncounteredFirstHeader = false;

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      const upper = line.toUpperCase().trim();

      const isNewDocHeader =
        upper.replace(/\s+/g, "") === "CAIXAECONOMICAFEDERAL" ||
        upper.includes("FGTS - EXTRATO ANALITICO") ||
        upper.includes("FGTS - EXTRATO DE CONTA VINCULADA") ||
        upper.includes("EXTRATO ANALITICO DO TRABALHADOR") ||
        upper.includes("EXTRATO DE CONTA VINCULADA PARA FINS RESCISORIOS");

      if (isNewDocHeader) {
        if (hasEncounteredFirstHeader && currentDoc.length > 0) {
          subDocs.push(currentDoc);
          currentDoc = [];
        }
        hasEncounteredFirstHeader = true;
      }
      if (hasEncounteredFirstHeader) {
        currentDoc.push(line);
      }
    }
    if (hasEncounteredFirstHeader && currentDoc.length > 0) {
      subDocs.push(currentDoc);
    }

    if (subDocs.length === 0) {
      subDocs.push(lines);
    }

    const workerGroups = {};

    for (const docLines of subDocs) {
      const docText = docLines.join("\n");
      const pisDigits = extractWorkerPISFromText(docText);
      const name = extractWorkerNameFromText(docText);
      const norm = normalizeName(name);

      let groupKey = "";
      if (pisDigits && pisDigits.length >= 10) {
        groupKey = `PIS_${pisDigits}`;
      } else {
        groupKey = `NAME_${norm || "DESCONHECIDO"}`;
      }

      if (!workerGroups[groupKey]) {
        workerGroups[groupKey] = { candidateNames: [], docs: [] };
      }
      if (name && name !== "TRABALHADOR NÃO IDENTIFICADO") {
        workerGroups[groupKey].candidateNames.push(name);
      }
      workerGroups[groupKey].docs.push(docText);
    }

    function getDocPriority(docText) {
      const upper = docText.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      if (upper.includes("FINS RESCISORIOS") || upper.includes("EXTRATO DE CONTA VINCULADA PARA FINS RESCISORIOS") || upper.includes("RESCISORIO")) {
        return 0; // Prioridade 1: Rescisório
      }
      if (upper.includes("EXTRATO ANALITICO") || upper.includes("ANALITICO")) {
        return 1; // Prioridade 2: Analítico
      }
      return 2;
    }

    const groups = Object.values(workerGroups).map(g => {
      const sortedDocs = [...g.docs].sort((a, b) => getDocPriority(a) - getDocPriority(b));

      let principalName = "TRABALHADOR NÃO IDENTIFICADO";
      if (g.candidateNames.length > 0) {
        const validNames = [...g.candidateNames].sort((a, b) => b.trim().length - a.trim().length);
        principalName = validNames[0].trim();
      }

      return {
        workerName: principalName,
        rawText: sortedDocs.join("\n\n---------------------------------------------\n\n")
      };
    });

    const identified = groups.filter(g => g.workerName !== "TRABALHADOR NÃO IDENTIFICADO");
    return identified.length > 0 ? identified : groups;
  }

  function parseSingleWorkerStatement(workerName, rawText) {
    const lines = rawText.split("\n");

    const companiesMap = {};
    let activeCompanyKey = "";
    let currentCompany = null;

    let parsedCPF = "";
    let parsedPIS = "";

    // Passo 1: Extrair dados de cabeçalho, CPF, PIS, empresas, admissões e afastamentos
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;
      const upper = line.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

      // Extração de CPF
      if (upper.includes("CPF:")) {
        const match = line.match(/CPF:\s*([\d\.-]+)/i) || line.match(/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/);
        if (match && !parsedCPF) {
          parsedCPF = match[0].replace(/CPF:\s*/i, "").trim();
        }
      } else if (!parsedCPF) {
        const directCPF = line.match(/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/);
        if (directCPF) parsedCPF = directCPF[0];
      }

      // Extração de PIS
      if (upper.includes("PIS:") || upper.includes("PIS/PASEP")) {
        const match = line.match(/PIS(?:\/PASEP)?:\s*([\d\.-]+)/i) || line.match(/\b\d{3}\.\d{5}\.\d{2}-\d{1}\b/);
        if (match && !parsedPIS) {
          parsedPIS = match[0].replace(/PIS(?:\/PASEP)?:\s*/i, "").trim();
        }
      } else if (!parsedPIS) {
        const directPIS = line.match(/\b\d{3}\.\d{5}\.\d{2}-\d{1}\b/);
        if (directPIS) parsedPIS = directPIS[0];
      }

      // Identificação da Empresa Empregadora
      if (upper.includes("EMPRESA:")) {
        const match = line.match(/EMPRESA:\s*([^\n\r]+)/i);
        if (match && match[1].trim()) {
          const rawName = match[1].trim();
          const key = cleanCompanyNameKey(rawName) || "DEFAULT_EMPRESA";
          if (!companiesMap[key]) {
            companiesMap[key] = {
              name: rawName,
              accounts: [],
              admissionDate: "",
              exitDate: null,
              deposits: []
            };
          }
          activeCompanyKey = key;
          currentCompany = companiesMap[key];
        }
      } else if (upper.includes("NOME DO EMPREGADOR")) {
        let nextLine = "";
        for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
          if (lines[j].trim()) {
            nextLine = lines[j].trim();
            break;
          }
        }
        if (nextLine) {
          const parts = nextLine.split(/\s{2,}/);
          if (parts[0] && parts[0].trim() && !parts[0].toUpperCase().includes("INSCRICAO")) {
            const rawName = parts[0].trim();
            const key = cleanCompanyNameKey(rawName) || "DEFAULT_EMPRESA";
            if (!companiesMap[key]) {
              companiesMap[key] = {
                name: rawName,
                accounts: [],
                admissionDate: "",
                exitDate: null,
                deposits: []
              };
            }
            activeCompanyKey = key;
            currentCompany = companiesMap[key];
          }
        }
      }

      if (currentCompany) {
        // Conta vinculada
        if (upper.includes("CONTA")) {
          const match = line.match(/(?:CONTA|NUMERO DA CONTA|NUMERO CONTA|CONTA FGTS):\s*([0-9A-Za-z\-]+)/i);
          if (match && match[1].trim()) {
            const acc = match[1].trim();
            if (!currentCompany.accounts.includes(acc)) {
              currentCompany.accounts.push(acc);
            }
          }
        }

        // Admissão
        if (upper.includes("ADMISSAO") || upper.includes("DTA.ADM.") || upper.includes("DTA. ADM.")) {
          const dateMatch = line.match(/\b\d{2}\/\d{2}\/\d{4}\b/);
          if (dateMatch) {
            currentCompany.admissionDate = dateMatch[0];
          } else {
            for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
              if (lines[j].trim()) {
                const dateNext = lines[j].match(/\b\d{2}\/\d{2}\/\d{4}\b/);
                if (dateNext) {
                  currentCompany.admissionDate = dateNext[0];
                  break;
                }
              }
            }
          }
        }

        // Afastamento / Desligamento / Movimentação
        // "MOVIMENTACAO DA CONTA NO PERIODO" e o titulo da lista de creditos/saques do extrato
        // para fins rescisorios: as datas abaixo dele sao lancamentos, nunca a saida do vinculo.
        if (upper.includes("MOVIMENTACAO") || upper.includes("AFASTAMENTO") || upper.includes("DATA DE AFAST.") || upper.includes("DTA. AFAST")) {
          if (!upper.includes("115-DEPOSITO") && !upper.includes("115 - DEPOSITO") && !upper.includes("MOVIMENTACAO DA CONTA")) {
            let allDates = line.match(/\b\d{2}\/\d{2}\/\d{4}\b/g);
            if (!allDates || allDates.length === 0) {
              for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
                if (lines[j].trim()) {
                  allDates = lines[j].trim().match(/\b\d{2}\/\d{2}\/\d{4}\b/g);
                  if (allDates && allDates.length > 0) break;
                }
              }
            }

            if (allDates && allDates.length > 0) {
              let candidateExit = null;
              if (allDates.length >= 3) {
                candidateExit = allDates[2]; // DTA.ADM, DTA.OPCAO, DATA DE AFAST
              } else if (allDates.length === 2) {
                candidateExit = allDates[1];
              } else {
                candidateExit = allDates[0];
              }

              if (
                candidateExit &&
                candidateExit !== "00/00/0000" &&
                candidateExit !== "00/00/00" &&
                candidateExit !== currentCompany.admissionDate
              ) {
                currentCompany.exitDate = candidateExit;
              }
            }
          }
        }
      }
    }

    // Se nenhuma empresa formal foi rotulada, cria uma genérica
    if (Object.keys(companiesMap).length === 0) {
      companiesMap["EMPRESA_DO_EXTRATO"] = {
        name: "EMPRESA DO EXTRATO",
        accounts: ["CONTA-01"],
        admissionDate: "01/01/2020",
        exitDate: null,
        deposits: []
      };
    }

    // Validação de afastamento ativo
    const hasExplicitZeroExit = /DATA\s*DE\s*AFAST[^\n\r]*00\/00\/0000/i.test(rawText) ||
      /AFASTAMENTO[^\n\r]*00\/00\/0000/i.test(rawText) ||
      /DTA\.\s*AFAST[^\n\r]*00\/00\/0000/i.test(rawText);

    Object.values(companiesMap).forEach(comp => {
      if (!comp.admissionDate) comp.admissionDate = "01/01/2020";
      if (hasExplicitZeroExit) {
        comp.exitDate = null;
      } else if (comp.exitDate) {
        const exitStr = String(comp.exitDate).trim();
        if (
          exitStr === comp.admissionDate ||
          exitStr === "00/00/0000" ||
          exitStr === "00/00/00" ||
          exitStr.toLowerCase() === "ativa" ||
          exitStr.toLowerCase() === "null" ||
          exitStr === "01/01/0001"
        ) {
          comp.exitDate = null;
        }
      }
    });

    // Passo 2: Extrair TODOS os depósitos de competência oficiais código 115
    let activeKey = Object.keys(companiesMap)[0];
    let currentAccountNum = "";

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;
      const upper = line.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

      if (upper.includes("EMPRESA:")) {
        const match = line.match(/EMPRESA:\s*([^\n\r]+)/i);
        if (match && match[1].trim()) {
          const key = cleanCompanyNameKey(match[1].trim());
          if (companiesMap[key]) activeKey = key;
        }
      }

      if (upper.includes("CONTA")) {
        const match = line.match(/(?:CONTA|NUMERO DA CONTA|NUMERO CONTA|CONTA FGTS):\s*([0-9A-Za-z\-]+)/i);
        if (match && match[1].trim()) {
          currentAccountNum = match[1].trim();
          const foundKey = Object.keys(companiesMap).find(k =>
            companiesMap[k].accounts && companiesMap[k].accounts.includes(currentAccountNum)
          );
          if (foundKey) activeKey = foundKey;
        }
      }

      // Reconhecimento de depósito oficial
      const isDepositKeyword = upper.includes("DEPOSITO") || upper.includes("DEPOSITOS") || upper.includes("DEP ") || upper.includes("DEP.") || upper.includes("115");
      const isExcludedType = upper.includes("SAQUE") || upper.includes("JAM") || upper.includes("ESTORNO") || upper.includes("SALDO ANTERIOR");

      if (isDepositKeyword && !isExcludedType) {
        let searchStr = upper;
        const depIdx = Math.max(upper.indexOf("DEPOSITO"), upper.indexOf("DEPOSITOS"), upper.indexOf("DEP"), upper.indexOf("115"));
        if (depIdx !== -1) searchStr = upper.substring(depIdx);

        let compParsed = extractMonthAndYear(searchStr) || extractMonthAndYear(upper);

        if (compParsed) {
          const { month, year } = compParsed;
          const compStr = `${month}/${year}`;
          const isLate = upper.includes("ATRASO") || upper.includes("EM ATRASO");

          const dates = line.match(/\b\d{2}\/\d{2}\/\d{4}\b/g);
          const depositDate = dates && dates[0] ? dates[0] : "—";

          let value = 150.00;
          const valRegex = /\b\d{1,3}(?:\.\d{3})*,\d{2}\b/;
          const valMatch = line.match(valRegex) || line.match(/R\$\s*([0-9.,]+)/i);
          if (valMatch) {
            const strVal = valMatch[0].replace(/R\$\s*/i, "").replace(/\./g, "").replace(",", ".");
            const parsed = parseFloat(strVal);
            if (!isNaN(parsed) && parsed > 0) value = parsed;
          }

          // Encontra a empresa correspondente por cronologia se activeKey não for compatível
          let targetKey = activeKey;
          const compVal = parseInt(year, 10) * 12 + parseInt(month, 10);

          let activeCompatible = false;
          if (targetKey && companiesMap[targetKey]) {
            const c = companiesMap[targetKey];
            const startNum = parseDateToMonths(c.admissionDate, 2020, 1);
            const today = new Date();
            const endNum = parseDateToMonths(c.exitDate, today.getFullYear(), today.getMonth() + 1);
            if (compVal >= startNum && compVal <= endNum) activeCompatible = true;
          }

          if (!activeCompatible) {
            const matchedTimelineKey = Object.keys(companiesMap).find(k => {
              const c = companiesMap[k];
              const startNum = parseDateToMonths(c.admissionDate, 2020, 1);
              const today = new Date();
              const endNum = parseDateToMonths(c.exitDate, today.getFullYear(), today.getMonth() + 1);
              return compVal >= startNum && compVal <= endNum;
            });
            if (matchedTimelineKey) targetKey = matchedTimelineKey;
          }

          const targetCompany = companiesMap[targetKey] || Object.values(companiesMap)[0];
          if (targetCompany) {
            const acc = currentAccountNum || targetCompany.accounts[0] || "CONTA-01";
            const existingDep = targetCompany.deposits.find(d => d.competence === compStr);
            if (existingDep) {
              existingDep.value += value;
            } else {
              targetCompany.deposits.push({
                competence: compStr,
                depositDate,
                value,
                type: isLate ? "LATE" : "STANDARD",
                account: acc,
                rawLine: line
              });
            }
          }
        }
      }
    }

    // Mapa global de depósitos para consolidação cruzada
    const globalDepositsMap = {};
    Object.values(companiesMap).forEach(company => {
      company.deposits.forEach(dep => {
        const comp = dep.competence;
        if (comp) {
          if (!globalDepositsMap[comp] || dep.value > globalDepositsMap[comp].value) {
            globalDepositsMap[comp] = {
              ...dep,
              originalCompany: company.name
            };
          }
        }
      });
    });

    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;
    const currentCompetency = `${currentMonth.toString().padStart(2, "0")}/${currentYear}`;
    const calculationDate = `${now.toLocaleDateString("pt-BR")} às ${now.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;

    const getMMYYYY = (dateStr, fallback) => {
      if (!dateStr) return fallback;
      const str = String(dateStr).trim();
      const parts = str.split("/");
      if (parts.length === 3) return `${parts[1].padStart(2, "0")}/${parts[2]}`;
      if (parts.length === 2) return `${parts[0].padStart(2, "0")}/${parts[1]}`;
      return fallback;
    };

    // Monta cada vínculo com a linha do tempo completa e depósitos reconciliados
    const formattedCompanies = Object.values(companiesMap).map((company, index) => {
      const ad = company.admissionDate || "01/01/2020";
      const startComp = getMMYYYY(ad, "01/2020");

      let endComp = currentCompetency;
      if (company.exitDate && company.exitDate.toLowerCase() !== "ativa") {
        endComp = getMMYYYY(company.exitDate, currentCompetency);
      }

      let timeline = [];
      try {
        timeline = getCompetencyPeriod(startComp, endComp);
      } catch (e) {
        timeline = [startComp];
      }

      const paidMap = {};
      const actualPaidDeposits = [];
      let totalValueDeposited = 0;

      const directPaidSet = new Set();
      company.deposits.forEach(dep => {
        if (dep.competence && !directPaidSet.has(dep.competence)) {
          directPaidSet.add(dep.competence);
          paidMap[dep.competence] = { ...dep, isConsolidated: false };
        }
      });

      const pendingCompetencies = [];
      timeline.forEach(c => {
        if (paidMap[c]) {
          actualPaidDeposits.push(paidMap[c]);
          totalValueDeposited += paidMap[c].value || 0;
        } else if (globalDepositsMap[c]) {
          const globalDep = {
            ...globalDepositsMap[c],
            isConsolidated: true,
            consolidatedFrom: globalDepositsMap[c].originalCompany
          };
          paidMap[c] = globalDep;
          actualPaidDeposits.push(globalDep);
          totalValueDeposited += globalDep.value || 0;
        } else {
          pendingCompetencies.push(c);
        }
      });

      // Inclui depósitos que estejam fora do período estrito da linha do tempo
      company.deposits.forEach(dep => {
        if (dep.competence && !timeline.includes(dep.competence) && !actualPaidDeposits.some(d => d.competence === dep.competence)) {
          actualPaidDeposits.push({ ...dep, isConsolidated: false });
          totalValueDeposited += dep.value || 0;
        }
      });

      // Ordena cronologicamente
      actualPaidDeposits.sort((a, b) => {
        const [am, ay] = String(a.competence || "01/2020").split("/").map(Number);
        const [bm, by] = String(b.competence || "01/2020").split("/").map(Number);
        return (ay * 12 + am) - (by * 12 + bm);
      });

      const totalCompetencies = timeline.length;
      const averageValue = actualPaidDeposits.length > 0
        ? totalValueDeposited / actualPaidDeposits.length
        : 150.00;
      const estimatedLoss = pendingCompetencies.length * averageValue;

      return {
        id: `comp-${index}-${Date.now()}`,
        name: company.name,
        accounts: company.accounts.length > 0 ? company.accounts : ["CONTA-01"],
        admissionDate: ad,
        exitDate: company.exitDate,
        totalCompetencies,
        paidDeposits: actualPaidDeposits,
        pendingCompetencies,
        totalValueDeposited,
        averageValue,
        estimatedLoss,
        fullTimeline: timeline
      };
    });

    // Classificação linha a linha para o Match Inspector
    const rawLinesLog = lines.slice(0, 400).map((lineText, idxLine) => {
      const text = lineText.trim();
      let classification = "UNKNOWN";
      let reason = "Linha informativa ou não processada";

      if (!text) {
        return { lineNumber: idxLine + 1, text, classification: "IGNORED_OTHER", reason: "Linha vazia" };
      }

      const upperLine = text.toUpperCase();
      const lineHas115 = upperLine.includes("115") && (upperLine.includes("DEPOSITO") || upperLine.includes("DEPOSITOS"));
      const lineIsLate = lineHas115 && (upperLine.includes("ATRASO") || upperLine.includes("EM ATRASO"));
      const lineIsStandard = lineHas115 && !lineIsLate;

      if (text.includes("NOME:") || text.includes("EMPRESA:") || text.includes("CONTA:") || text.includes("ADMISSÃO") || text.includes("MOVIMENTA")) {
        classification = "HEADER_INFO";
        reason = "Informação de cabeçalho do trabalhador ou empresa";
      } else if (text.includes("CREDITO DE JAM") || text.includes("JAM RECOLHIDO") || text.includes("DIST RESULTADO")) {
        classification = "IGNORED_JAM";
        reason = "Ajuste/Crédito de JAM automático (Não é depósito de competência)";
      } else if (text.includes("SAQUE") || text.includes("TRANSFERENCIA")) {
        classification = "IGNORED_OTHER";
        reason = "Movimentação de débito ou transferência de saldos";
      } else if (lineIsStandard || lineIsLate) {
        classification = "PAYMENT";
        reason = lineIsLate ? "Depósito de competência em atraso oficial (Código 115)" : "Depósito de competência oficial (Código 115)";
      }

      return { lineNumber: idxLine + 1, text, classification, reason };
    });

    let totalChecked = 0;
    let totalPaid = 0;
    let totalPending = 0;
    let totalDepositedValue = 0;
    let totalEstimatedLoss = 0;

    formattedCompanies.forEach(c => {
      totalChecked += c.totalCompetencies;
      totalPaid += c.paidDeposits.length;
      totalPending += c.pendingCompetencies.length;
      totalDepositedValue += c.totalValueDeposited;
      totalEstimatedLoss += c.estimatedLoss;
    });

    return {
      workerName: workerName || "TRABALHADOR NÃO IDENTIFICADO",
      workerCPF: parsedCPF || undefined,
      workerPIS: parsedPIS || undefined,
      calculationDate,
      companies: formattedCompanies,
      summary: {
        totalChecked,
        totalPaid,
        totalPending,
        totalDepositedValue,
        totalEstimatedLoss
      },
      rawLinesLog
    };
  }

  // ==========================================================================
  // 6. MOTOR DE FILTROS PRESCRICIONAIS (QUINQUENAL, STF ARE 709.212, ANO)
  // ==========================================================================

  function isCompetencePrescribed(compStr, rule, cutoffDateStr, startYearNum) {
    if (!compStr || !compStr.includes("/")) return false;
    const [mStr, yStr] = compStr.split("/");
    const compM = parseInt(mStr, 10);
    const compY = parseInt(yStr, 10);
    const compTotalMonths = compY * 12 + compM;

    // Regra 1: Filtro simples de ano de início
    if (startYearNum && !isNaN(startYearNum) && startYearNum > 1900) {
      if (compY < startYearNum) return true;
    }

    if (rule === "NONE") {
      return false;
    }

    if (rule === "YEAR_START") {
      if (startYearNum && !isNaN(startYearNum)) {
        return compY < startYearNum;
      }
      return false;
    }

    // Parse da Data Marco (Ajuizamento ou Data Atual)
    let cutoffY = 2026;
    let cutoffM = 10;
    if (cutoffDateStr) {
      const parts = cutoffDateStr.split("/");
      if (parts.length === 3) {
        cutoffM = parseInt(parts[1], 10) || 10;
        cutoffY = parseInt(parts[2], 10) || 2026;
      }
    }
    const cutoffTotalMonths = cutoffY * 12 + cutoffM;

    // Regra 2: Prescrição Quinquenal (CF/88 Art. 7º, XXIX - 5 anos antes do marco)
    if (rule === "QUINQUENAL") {
      const fiveYearsAgoMonths = cutoffTotalMonths - (5 * 12);
      return compTotalMonths < fiveYearsAgoMonths;
    }

    // Regra 3: Modulação STF no ARE 709.212 e Súmula 362 do TST
    // Decisão do STF: 13/11/2014 (totalMonths = 2014 * 12 + 11 = 24179)
    // Transição: Para fatos geradores anteriores a 13/11/2014, o prazo é de 30 anos
    // ou 5 anos contados de 13/11/2014 (isto é, 13/11/2019), o que ocorrer primeiro.
    if (rule === "STF_709212") {
      const stfDecisionMonths = 2014 * 12 + 11; // 11/2014
      const stfFiveYearsPost = 2019 * 12 + 11;  // 11/2019

      if (cutoffTotalMonths > stfFiveYearsPost) {
        // Ajuizada após 13/11/2019: prevalece sempre a prescrição quinquenal estrita
        const fiveYearsAgoMonths = cutoffTotalMonths - (5 * 12);
        return compTotalMonths < fiveYearsAgoMonths;
      } else {
        // Ajuizada até 13/11/2019:
        if (compTotalMonths < stfDecisionMonths) {
          // Fato anterior a 13/11/2014: 30 anos do fato gerador
          const thirtyYearsAgoMonths = cutoffTotalMonths - (30 * 12);
          return compTotalMonths < thirtyYearsAgoMonths;
        } else {
          // Fato posterior a 13/11/2014: 5 anos do fato
          const fiveYearsAgoMonths = cutoffTotalMonths - (5 * 12);
          return compTotalMonths < fiveYearsAgoMonths;
        }
      }
    }

    return false;
  }

  function getPrescriptionFilteredWorker(rawReport, rule, cutoffDateStr, startYearNum, exportStartYearStr) {
    if (!rawReport) return null;

    let effStartYear = NaN;
    if (rule === "YEAR_START" && startYearNum) {
      effStartYear = parseInt(startYearNum, 10);
    }
    if (exportStartYearStr && exportStartYearStr.trim()) {
      const expY = parseInt(exportStartYearStr.trim(), 10);
      if (!isNaN(expY)) {
        effStartYear = isNaN(effStartYear) ? expY : Math.max(effStartYear, expY);
      }
    }

    const filteredCompanies = rawReport.companies.map(c => {
      const fullTimeline = c.fullTimeline || [];
      const activeTimeline = fullTimeline.filter(comp => !isCompetencePrescribed(comp, rule, cutoffDateStr, effStartYear));

      const paidDeposits = c.paidDeposits.filter(dep => !isCompetencePrescribed(dep.competence, rule, cutoffDateStr, effStartYear));
      const pendingCompetencies = c.pendingCompetencies.filter(comp => !isCompetencePrescribed(comp, rule, cutoffDateStr, effStartYear));

      const totalValueDeposited = paidDeposits.reduce((acc, d) => acc + (d.value || 0), 0);
      const averageValue = paidDeposits.length > 0 ? totalValueDeposited / paidDeposits.length : c.averageValue || 150.00;
      const estimatedLoss = pendingCompetencies.length * averageValue;

      return {
        ...c,
        totalCompetencies: activeTimeline.length,
        paidDeposits,
        pendingCompetencies,
        totalValueDeposited,
        averageValue,
        estimatedLoss
      };
    });

    let totalChecked = 0;
    let totalPaid = 0;
    let totalPending = 0;
    let totalDepositedValue = 0;
    let totalEstimatedLoss = 0;

    filteredCompanies.forEach(c => {
      totalChecked += c.totalCompetencies;
      totalPaid += c.paidDeposits.length;
      totalPending += c.pendingCompetencies.length;
      totalDepositedValue += c.totalValueDeposited;
      totalEstimatedLoss += c.estimatedLoss;
    });

    return {
      ...rawReport,
      companies: filteredCompanies,
      summary: {
        totalChecked,
        totalPaid,
        totalPending,
        totalDepositedValue,
        totalEstimatedLoss
      }
    };
  }

  // ==========================================================================
  // ==========================================================================
  // 7. EXPORTAÇÃO EXCEL ESTILIZADA VIA EXCELJS / SHEETJS (CDN)
  // Layout oficial pericial: Blocos anuais lado a lado com cores temáticas
  // ==========================================================================

  // Definições de estilos periciais para ExcelJS
  const excelHeaderFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FF121212" } // deep pitch black
  };

  const excelCompanyFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FF1A1A1A" } // charcoal black
  };

  const excelYearFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FFCDA83E" } // rich mustard gold background
  };

  const excelColumnLabelFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FF2A2A2A" } // medium dark grey
  };

  const excelTotalLabelFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FFCDA83E" } // mustard gold fill
  };

  const excelStatusPendenteFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FFCDA83E" } // Mustard gold badge para PENDENTE
  };

  const excelStatusPagoFill = {
    type: "pattern",
    pattern: "solid",
    fgColor: { argb: "FF2D6C4D" } // Deep rich forest green para PAGO
  };

  const excelBorderThin = {
    top: { style: "thin", color: { argb: "FFCCCCCC" } },
    left: { style: "thin", color: { argb: "FFCCCCCC" } },
    right: { style: "thin", color: { argb: "FFCCCCCC" } },
    bottom: { style: "thin", color: { argb: "FFCCCCCC" } }
  };

  const excelBorderTotal = {
    top: { style: "medium", color: { argb: "FF000000" } },
    left: { style: "medium", color: { argb: "FF000000" } },
    right: { style: "medium", color: { argb: "FF000000" } },
    bottom: { style: "medium", color: { argb: "FF000000" } }
  };

  function populateWorkerSheet(worksheet, report, parsedStartYear, isYearFiltered) {
    const blocks = [];

    // Identifica empresa principal
    const latestCompany = report.companies && report.companies.length > 0
      ? report.companies[report.companies.length - 1]
      : { name: "EMPRESA IDENTIFICADA" };
    const primaryCompanyName = (latestCompany.name || "EMPRESA").toUpperCase();

    // Mapeamento de empresa por ano caso o trabalhador tenha mais de um vínculo
    const yearCompanyMap = new Map();
    (report.companies || []).forEach(company => {
      const cName = (company.name || primaryCompanyName).toUpperCase();
      (company.paidDeposits || []).forEach(d => {
        if (d.competence) {
          const y = parseInt(d.competence.split("/")[1], 10);
          if (!isNaN(y)) yearCompanyMap.set(y, cName);
        }
      });
      (company.pendingCompetencies || []).forEach(c => {
        const y = parseInt(c.split("/")[1], 10);
        if (!isNaN(y) && !yearCompanyMap.has(y)) yearCompanyMap.set(y, cName);
      });
    });

    // Consolida competências de todas as contas do trabalhador
    const compMap = new Map();

    // 1. Coleta depósitos pagos em qualquer conta
    (report.companies || []).forEach(company => {
      (company.paidDeposits || []).forEach(dep => {
        if (dep.competence) {
          const parts = dep.competence.split("/");
          const year = parts.length === 2 ? parseInt(parts[1], 10) : NaN;
          if (!isYearFiltered || (!isNaN(year) && year >= parsedStartYear)) {
            compMap.set(dep.competence, "PAGO");
          }
        }
      });
    });

    // 2. Coleta competências pendentes (somente se não paga em outra conta)
    (report.companies || []).forEach(company => {
      (company.pendingCompetencies || []).forEach(comp => {
        const parts = comp.split("/");
        const year = parts.length === 2 ? parseInt(parts[1], 10) : NaN;
        if (!isYearFiltered || (!isNaN(year) && year >= parsedStartYear)) {
          if (!compMap.has(comp)) {
            compMap.set(comp, "PENDENTE");
          }
        }
      });
    });

    // 3. Agrupa por ano (colunas anuais lado a lado)
    const yearGroups = new Map();
    compMap.forEach((status, comp) => {
      const parts = comp.split("/");
      if (parts.length === 2) {
        const year = parseInt(parts[1], 10);
        if (!isNaN(year)) {
          if (!yearGroups.has(year)) {
            yearGroups.set(year, []);
          }
          yearGroups.get(year).push({
            competence: comp,
            competenceLabel: formatCompetencePT(comp),
            status: status
          });
        }
      }
    });

    yearGroups.forEach((items, year) => {
      items.sort((a, b) => {
        const mA = parseInt(a.competence.split("/")[0], 10);
        const mB = parseInt(b.competence.split("/")[0], 10);
        return mA - mB;
      });

      const totalPending = items.filter(item => item.status === "PENDENTE").length;

      blocks.push({
        companyName: yearCompanyMap.get(year) || primaryCompanyName,
        year: year,
        items: items,
        totalPending: totalPending
      });
    });

    blocks.sort((a, b) => a.year - b.year);

    if (blocks.length === 0) {
      worksheet.getCell(1, 1).value = "Nenhuma competência apurada no período selecionado.";
      return;
    }

    const maxRows = Math.max(...blocks.map(b => b.items.length), 1);
    const numBlocks = blocks.length;
    const numColumns = numBlocks * 2;
    const maxSpreadsheetColIdx = Math.max(8, numColumns);

    for (let c = 1; c <= maxSpreadsheetColIdx; c++) {
      const col = worksheet.getColumn(c);
      col.width = 15;
    }

    // Linha 1: Título Geral "CONTROLE DE PENDÊNCIAS DE FGTS"
    worksheet.mergeCells(1, 1, 1, maxSpreadsheetColIdx);
    const titleCell = worksheet.getCell(1, 1);
    titleCell.value = isYearFiltered
      ? `CONTROLE DE PENDÊNCIAS DE FGTS (A PARTIR DE ${parsedStartYear})`
      : "CONTROLE DE PENDÊNCIAS DE FGTS";
    titleCell.fill = excelHeaderFill;
    titleCell.font = { name: "Arial", size: 14, bold: true, color: { argb: "FFFFC000" } };
    titleCell.alignment = { vertical: "middle", horizontal: "center" };
    worksheet.getRow(1).height = 36;

    // Linha 2: Nome do Trabalhador
    worksheet.mergeCells(2, 1, 2, maxSpreadsheetColIdx);
    const workerCell = worksheet.getCell(2, 1);
    workerCell.value = (report.workerName || "TRABALHADOR").toUpperCase();
    workerCell.fill = excelHeaderFill;
    workerCell.font = { name: "Arial", size: 11, bold: true, color: { argb: "FFFFFFFF" } };
    workerCell.alignment = { vertical: "middle", horizontal: "center" };
    worksheet.getRow(2).height = 24;

    // Linha 3: CPF e PIS
    worksheet.mergeCells(3, 1, 3, 2);
    const cpfLabel = worksheet.getCell(3, 1);
    cpfLabel.value = "CPF:";
    cpfLabel.fill = excelColumnLabelFill;
    cpfLabel.font = { name: "Arial", size: 10, bold: true, color: { argb: "FFFFC000" } };
    cpfLabel.alignment = { vertical: "middle", horizontal: "center" };

    worksheet.mergeCells(3, 3, 3, 4);
    const cpfValCell = worksheet.getCell(3, 3);
    cpfValCell.value = report.workerCPF || "NÃO EXTRATADO";
    cpfValCell.font = { name: "Arial", size: 10, bold: true, color: { argb: "FF000000" } };
    cpfValCell.alignment = { vertical: "middle", horizontal: "center" };
    cpfValCell.border = excelBorderThin;

    worksheet.mergeCells(3, 5, 3, 6);
    const pisLabel = worksheet.getCell(3, 5);
    pisLabel.value = "PIS:";
    pisLabel.fill = excelColumnLabelFill;
    pisLabel.font = { name: "Arial", size: 10, bold: true, color: { argb: "FFFFC000" } };
    pisLabel.alignment = { vertical: "middle", horizontal: "center" };

    worksheet.mergeCells(3, 7, 3, 8);
    const pisValCell = worksheet.getCell(3, 7);
    pisValCell.value = report.workerPIS || "NÃO EXTRATADO";
    pisValCell.font = { name: "Arial", size: 10, bold: true, color: { argb: "FF000000" } };
    pisValCell.alignment = { vertical: "middle", horizontal: "center" };
    pisValCell.border = excelBorderThin;

    if (maxSpreadsheetColIdx > 8) {
      worksheet.mergeCells(3, 9, 3, maxSpreadsheetColIdx);
      const padCell = worksheet.getCell(3, 9);
      padCell.fill = excelHeaderFill;
    }
    worksheet.getRow(3).height = 24;

    worksheet.getRow(4).height = 12;
    worksheet.getRow(5).height = 24;
    worksheet.getRow(6).height = 22;
    worksheet.getRow(7).height = 20;

    blocks.forEach((block, bIdx) => {
      const colStarts = bIdx * 2 + 1;
      const colEnds = bIdx * 2 + 2;

      // Linha 5: Nome da Empresa Empregadora
      worksheet.mergeCells(5, colStarts, 5, colEnds);
      const compCell = worksheet.getCell(5, colStarts);
      compCell.value = block.companyName;
      compCell.fill = excelCompanyFill;
      compCell.font = { name: "Arial", size: 9, bold: true, color: { argb: "FFFFC000" } };
      compCell.alignment = { vertical: "middle", horizontal: "center", wrapText: true };
      compCell.border = excelBorderThin;

      // Linha 6: Ano do Bloco
      worksheet.mergeCells(6, colStarts, 6, colEnds);
      const yrCell = worksheet.getCell(6, colStarts);
      yrCell.value = block.year;
      yrCell.fill = excelYearFill;
      yrCell.font = { name: "Arial", size: 11, bold: true, color: { argb: "FF000000" } };
      yrCell.alignment = { vertical: "middle", horizontal: "center" };
      yrCell.border = excelBorderThin;

      // Linha 7: Títulos de Coluna
      const cellHeaderComp = worksheet.getCell(7, colStarts);
      cellHeaderComp.value = "Competência";
      cellHeaderComp.fill = excelColumnLabelFill;
      cellHeaderComp.font = { name: "Arial", size: 9, bold: true, color: { argb: "FFFFFFFF" } };
      cellHeaderComp.alignment = { vertical: "middle", horizontal: "center" };
      cellHeaderComp.border = excelBorderThin;

      const cellHeaderStatus = worksheet.getCell(7, colEnds);
      cellHeaderStatus.value = "Status";
      cellHeaderStatus.fill = excelColumnLabelFill;
      cellHeaderStatus.font = { name: "Arial", size: 9, bold: true, color: { argb: "FFFFFFFF" } };
      cellHeaderStatus.alignment = { vertical: "middle", horizontal: "center" };
      cellHeaderStatus.border = excelBorderThin;

      // Linhas 8+: Dados Mensais
      for (let r = 0; r < maxRows; r++) {
        const dataRowIdx = 8 + r;
        const cellComp = worksheet.getCell(dataRowIdx, colStarts);
        const cellStatus = worksheet.getCell(dataRowIdx, colEnds);

        worksheet.getRow(dataRowIdx).height = 20;

        if (r < block.items.length) {
          const item = block.items[r];
          cellComp.value = item.competenceLabel;
          cellComp.font = { name: "Arial", size: 9, color: { argb: "FF333333" } };
          cellComp.alignment = { vertical: "middle", horizontal: "center" };
          cellComp.border = excelBorderThin;

          cellStatus.value = item.status;
          cellStatus.alignment = { vertical: "middle", horizontal: "center" };
          cellStatus.border = excelBorderThin;

          if (item.status === "PENDENTE") {
            cellStatus.fill = excelStatusPendenteFill;
            cellStatus.font = { name: "Arial", size: 9, bold: true, color: { argb: "FF000000" } };
          } else {
            cellStatus.fill = excelStatusPagoFill;
            cellStatus.font = { name: "Arial", size: 9, bold: true, color: { argb: "FFFFFFFF" } };
          }
        } else {
          cellComp.value = "";
          cellComp.alignment = { vertical: "middle", horizontal: "center" };
          cellComp.border = excelBorderThin;

          cellStatus.value = "";
          cellStatus.alignment = { vertical: "middle", horizontal: "center" };
          cellStatus.border = excelBorderThin;
        }
      }

      // Linha de Rodapé: Total Pendente
      const totalRowIdx = 8 + maxRows;
      worksheet.getRow(totalRowIdx).height = 22;

      const cellTotalLabel = worksheet.getCell(totalRowIdx, colStarts);
      cellTotalLabel.value = "Total Pendente";
      cellTotalLabel.fill = excelTotalLabelFill;
      cellTotalLabel.font = { name: "Arial", size: 9, bold: true, color: { argb: "FF000000" } };
      cellTotalLabel.alignment = { vertical: "middle", horizontal: "center" };
      cellTotalLabel.border = excelBorderTotal;

      const cellTotalVal = worksheet.getCell(totalRowIdx, colEnds);
      cellTotalVal.value = block.totalPending;
      cellTotalVal.fill = excelTotalLabelFill;
      cellTotalVal.font = { name: "Arial", size: 10, bold: true, color: { argb: "FF000000" } };
      cellTotalVal.alignment = { vertical: "middle", horizontal: "center" };
      cellTotalVal.border = excelBorderTotal;
    });

    worksheet.views = [{ showGridLines: true }];
  }

  function exportWorkerToExcelSheetSheetJS(report, ruleName) {
    const XLSX = window.XLSX;
    if (!XLSX) return null;

    const rows = [];
    rows.push(["AUDITORIA PERICIAL DE FGTS — CAIXA ECONÔMICA FEDERAL"]);
    rows.push(["TRABALHADOR:", report.workerName]);
    rows.push(["CPF:", report.workerCPF || "NÃO INFORMADO", "PIS/PASEP:", report.workerPIS || "NÃO INFORMADO"]);
    rows.push(["DATA DO CÁLCULO:", report.calculationDate || getTodayDateBR()]);
    rows.push(["REGRA PRESCRICIONAL:", ruleName || "Quinquenal"]);
    rows.push([]);

    rows.push([
      "Item",
      "Empresa",
      "Competência",
      "Mês/Ano",
      "Situação",
      "Data Depósito",
      "Valor (R$)",
      "Conta FGTS",
      "Observações Periciais"
    ]);

    let globalItem = 0;
    (report.companies || []).forEach(company => {
      const depositsMap = {};
      (company.paidDeposits || []).forEach(d => {
        depositsMap[d.competence] = d;
      });

      const allComps = [];
      (company.paidDeposits || []).forEach(d => allComps.push(d.competence));
      (company.pendingCompetencies || []).forEach(c => {
        if (!allComps.includes(c)) allComps.push(c);
      });

      allComps.sort((a, b) => {
        const [am, ay] = a.split("/").map(Number);
        const [bm, by] = b.split("/").map(Number);
        return (ay * 12 + am) - (by * 12 + bm);
      });

      allComps.forEach(comp => {
        globalItem++;
        const dep = depositsMap[comp];
        const isPaid = !!dep;
        const isConsolidated = dep && dep.isConsolidated;

        rows.push([
          globalItem,
          company.name,
          comp,
          formatCompetencePT(comp),
          isPaid ? (isConsolidated ? "PAGO (CONSOL)" : "PAGO") : "PENDENTE",
          dep ? dep.depositDate : "—",
          dep ? dep.value : 0,
          dep ? dep.account : (company.accounts?.[0] || "—"),
          isConsolidated ? `Reconciliado de: ${dep.consolidatedFrom || "Outra Conta"}` : (isPaid ? "Depósito código 115" : "Recolhimento não localizado")
        ]);
      });
    });

    rows.push([]);
    rows.push(["RESUMO CONSOLIDADO DO TRABALHADOR"]);
    rows.push(["Total Competências Auditadas:", report.summary?.totalChecked || 0]);
    rows.push(["Competências Pagas / Reconciliadas:", report.summary?.totalPaid || 0]);
    rows.push(["Competências em Aberto (Débito):", report.summary?.totalPending || 0]);
    rows.push(["Valor Total Depositado (R$):", report.summary?.totalDepositedValue || 0]);
    rows.push(["Prejuízo Estimado Faltante (R$):", report.summary?.totalEstimatedLoss || 0]);

    return XLSX.utils.aoa_to_sheet(rows);
  }

  async function handleExportExcel(isAll) {
    const rawWorkers = isAll ? state.allWorkers : [state.allWorkers[state.selectedWorkerIndex]];
    if (!rawWorkers || rawWorkers.length === 0) {
      alert("Nenhum dado auditado para exportar.");
      return;
    }

    const filteredWorkers = rawWorkers.map(rawW =>
      getPrescriptionFilteredWorker(
        rawW,
        state.prescriptionRule,
        state.prescriptionCutoffDate,
        state.prescriptionStartYear,
        state.exportStartYear
      )
    );

    const parsedStartYear = state.exportStartYear
      ? parseInt(String(state.exportStartYear), 10)
      : (state.prescriptionRule === "YEAR_START" && state.prescriptionStartYear ? parseInt(String(state.prescriptionStartYear), 10) : NaN);
    const isYearFiltered = !isNaN(parsedStartYear) && parsedStartYear > 1900;

    // 1. Prioridade: ExcelJS (Suporta estilização, cores, preenchimentos e blocos anuais lado a lado)
    if (window.ExcelJS) {
      try {
        const workbook = new window.ExcelJS.Workbook();

        if (filteredWorkers.length === 1) {
          const sheet = workbook.addWorksheet("Pendências FGTS");
          populateWorkerSheet(sheet, filteredWorkers[0], parsedStartYear, isYearFiltered);
        } else {
          // Aba 1: Resumo Geral Consolidado
          const summarySheet = workbook.addWorksheet("RESUMO GERAL");
          summarySheet.columns = [
            { header: "№", key: "num", width: 6 },
            { header: "Nome do Trabalhador", key: "name", width: 32 },
            { header: "CPF", key: "cpf", width: 16 },
            { header: "PIS", key: "pis", width: 16 },
            { header: "Qtd Empresas", key: "companies", width: 14 },
            { header: "Competências Pagas", key: "paid", width: 20 },
            { header: "Competências Pendentes", key: "pending", width: 22 },
            { header: "Situação Geral", key: "status", width: 18 }
          ];

          summarySheet.mergeCells(1, 1, 1, 8);
          const sumTitle = summarySheet.getCell(1, 1);
          sumTitle.value = "RELATÓRIO CONSOLIDADO DE AUDITORIA DE FGTS - TODOS OS TRABALHADORES";
          sumTitle.fill = excelHeaderFill;
          sumTitle.font = { name: "Arial", size: 12, bold: true, color: { argb: "FFFFC000" } };
          sumTitle.alignment = { vertical: "middle", horizontal: "center" };
          summarySheet.getRow(1).height = 32;

          const headers = ["№", "Nome do Trabalhador", "CPF", "PIS", "Empresas", "Pagas", "Pendentes", "Situação"];
          headers.forEach((h, colIdx) => {
            const cell = summarySheet.getCell(2, colIdx + 1);
            cell.value = h;
            cell.fill = excelColumnLabelFill;
            cell.font = { name: "Arial", size: 10, bold: true, color: { argb: "FFFFFFFF" } };
            cell.alignment = { vertical: "middle", horizontal: "center" };
            cell.border = excelBorderThin;
          });
          summarySheet.getRow(2).height = 24;

          let grandTotalPending = 0;
          let grandTotalPaid = 0;

          filteredWorkers.forEach((report, idx) => {
            let workerPaid = 0;
            let workerPending = 0;

            (report.companies || []).forEach(c => {
              const pCount = (c.paidDeposits || []).filter(d => {
                if (!isYearFiltered) return true;
                const parts = (d.competence || "").split("/");
                return parts.length === 2 && Number(parts[1]) >= parsedStartYear;
              }).length;

              const pendCount = (c.pendingCompetencies || []).filter(cp => {
                if (!isYearFiltered) return true;
                const parts = (cp || "").split("/");
                return parts.length === 2 && Number(parts[1]) >= parsedStartYear;
              }).length;

              workerPaid += pCount;
              workerPending += pendCount;
            });

            grandTotalPaid += workerPaid;
            grandTotalPending += workerPending;

            const rowIdx = 3 + idx;
            summarySheet.getRow(rowIdx).height = 22;

            const rowValues = [
              (idx + 1).toString(),
              (report.workerName || "").toUpperCase(),
              report.workerCPF || "—",
              report.workerPIS || "—",
              (report.companies || []).length,
              workerPaid,
              workerPending,
              workerPending > 0 ? "PENDÊNCIAS" : "REGULAR"
            ];

            rowValues.forEach((val, cIdx) => {
              const cell = summarySheet.getCell(rowIdx, cIdx + 1);
              cell.value = val;
              cell.font = { name: "Arial", size: 9.5, color: { argb: "FF111111" } };
              cell.border = excelBorderThin;
              cell.alignment = { vertical: "middle", horizontal: cIdx === 1 ? "left" : "center" };

              if (cIdx === 7) {
                if (val === "PENDÊNCIAS") {
                  cell.fill = excelStatusPendenteFill;
                  cell.font = { name: "Arial", size: 9.5, bold: true, color: { argb: "FF000000" } };
                } else {
                  cell.fill = excelStatusPagoFill;
                  cell.font = { name: "Arial", size: 9.5, bold: true, color: { argb: "FFFFFFFF" } };
                }
              }
            });
          });

          // Linha de total geral do resumo
          const totalRowIdx = 3 + filteredWorkers.length;
          summarySheet.getRow(totalRowIdx).height = 24;
          summarySheet.mergeCells(totalRowIdx, 1, totalRowIdx, 4);
          const totLabel = summarySheet.getCell(totalRowIdx, 1);
          totLabel.value = `TOTAL CONSOLIDADO (${filteredWorkers.length} TRABALHADORES)`;
          totLabel.fill = excelYearFill;
          totLabel.font = { name: "Arial", size: 10, bold: true, color: { argb: "FF000000" } };
          totLabel.alignment = { vertical: "middle", horizontal: "center" };
          totLabel.border = excelBorderTotal;

          const totCompCell = summarySheet.getCell(totalRowIdx, 5);
          totCompCell.value = filteredWorkers.reduce((acc, r) => acc + (r.companies || []).length, 0);
          totCompCell.fill = excelYearFill;
          totCompCell.font = { name: "Arial", size: 10, bold: true };
          totCompCell.alignment = { vertical: "middle", horizontal: "center" };
          totCompCell.border = excelBorderTotal;

          const totPaidCell = summarySheet.getCell(totalRowIdx, 6);
          totPaidCell.value = grandTotalPaid;
          totPaidCell.fill = excelYearFill;
          totPaidCell.font = { name: "Arial", size: 10, bold: true };
          totPaidCell.alignment = { vertical: "middle", horizontal: "center" };
          totPaidCell.border = excelBorderTotal;

          const totPendCell = summarySheet.getCell(totalRowIdx, 7);
          totPendCell.value = grandTotalPending;
          totPendCell.fill = excelYearFill;
          totPendCell.font = { name: "Arial", size: 10, bold: true };
          totPendCell.alignment = { vertical: "middle", horizontal: "center" };
          totPendCell.border = excelBorderTotal;

          const totStatusCell = summarySheet.getCell(totalRowIdx, 8);
          totStatusCell.value = grandTotalPending > 0 ? "PENDÊNCIAS" : "REGULAR";
          totStatusCell.fill = excelYearFill;
          totStatusCell.font = { name: "Arial", size: 10, bold: true };
          totStatusCell.alignment = { vertical: "middle", horizontal: "center" };
          totStatusCell.border = excelBorderTotal;

          summarySheet.views = [{ showGridLines: true }];

          // Abas individuais para cada colaborador
          filteredWorkers.forEach((report, idx) => {
            let sheetName = (report.workerName || "")
              .toUpperCase()
              .replace(/[:\\/?*\[\]]/g, "")
              .trim();
            if (!sheetName) sheetName = `Trabalhador ${idx + 1}`;
            if (sheetName.length > 25) sheetName = sheetName.substring(0, 25);
            sheetName = `${idx + 1}_${sheetName}`;

            const sheet = workbook.addWorksheet(sheetName);
            populateWorkerSheet(sheet, report, parsedStartYear, isYearFiltered);
          });
        }

        const buffer = await workbook.xlsx.writeBuffer();
        const fileBlob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
        const downloadUrl = URL.createObjectURL(fileBlob);
        const link = document.createElement("a");

        const formattedName = (filteredWorkers[0]?.workerName || "trabalhador")
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .replace(/[^a-zA-Z0-9\s]/g, "")
          .replace(/\s+/g, "_")
          .toLowerCase();

        const yearSuffix = isYearFiltered ? `_a_partir_de_${parsedStartYear}` : "";
        link.href = downloadUrl;
        link.download = filteredWorkers.length > 1
          ? `relatorio_consolidado_fgts_todos_os_trabalhadores${yearSuffix}.xlsx`
          : `relatorio_consolidado_fgts_${formattedName}${yearSuffix}.xlsx`;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(downloadUrl);
        return;
      } catch (err) {
        console.warn("Falha na geração via ExcelJS, tentando fallback SheetJS:", err);
      }
    }

    // 2. Fallback: SheetJS (caso ExcelJS CDN esteja bloqueado offline)
    const XLSX = window.XLSX;
    if (!XLSX) {
      alert("Nenhuma biblioteca de exportação Excel disponível.");
      return;
    }

    const wb = XLSX.utils.book_new();
    const ruleDesc = getPrescriptionDescriptionText(state.prescriptionRule);

    if (filteredWorkers.length > 1 && isAll) {
      const summaryRows = [
        ["AUDITORIA CONSOLIDADA DE FGTS — RESUMO GERAL DE COLABORADORES"],
        ["Data da Apuração:", getTodayDateBR()],
        ["Regra Prescricional:", ruleDesc],
        [],
        ["№", "Nome do Trabalhador", "CPF", "PIS/PASEP", "Empresas", "Comp. Totais", "Pagas", "Pendentes", "Total Depositado (R$)", "Perda Estimada (R$)", "Situação"]
      ];

      filteredWorkers.forEach((w, idx) => {
        summaryRows.push([
          idx + 1,
          w.workerName,
          w.workerCPF || "—",
          w.workerPIS || "—",
          (w.companies || []).length,
          w.summary?.totalChecked || 0,
          w.summary?.totalPaid || 0,
          w.summary?.totalPending || 0,
          w.summary?.totalDepositedValue || 0,
          w.summary?.totalEstimatedLoss || 0,
          (w.summary?.totalPending || 0) > 0 ? "PENDÊNCIAS LOCALIZADAS" : "REGULARIZADO"
        ]);
      });

      const wsSummary = XLSX.utils.aoa_to_sheet(summaryRows);
      XLSX.utils.book_append_sheet(wb, wsSummary, "Resumo Executivo");
    }

    filteredWorkers.forEach((w, idx) => {
      const wsWorker = exportWorkerToExcelSheetSheetJS(w, ruleDesc);
      if (wsWorker) {
        let cleanSheetName = (w.workerName || "").split(" ")[0].slice(0, 20);
        if (filteredWorkers.length > 1) {
          cleanSheetName = `(${idx + 1}) ${cleanSheetName}`;
        }
        XLSX.utils.book_append_sheet(wb, wsWorker, cleanSheetName || `Trabalhador ${idx + 1}`);
      }
    });

    const safeName = (filteredWorkers[0]?.workerName || "FGTS")
      .normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-zA-Z0-9]/g, "_").toLowerCase();
    const fileName = isAll && filteredWorkers.length > 1
      ? `auditoria_fgts_lote_${filteredWorkers.length}_colaboradores.xlsx`
      : `auditoria_fgts_${safeName}.xlsx`;

    XLSX.writeFile(wb, fileName);
  }

  // ==========================================================================
  // 8. EXPORTAÇÃO OFICIAL EM PDF VIA JSPDF + AUTOTABLE (CDN)
  // ==========================================================================

  function handleExportPdf(isAll) {
    const jspdfModule = window.jspdf;
    if (!jspdfModule || !jspdfModule.jsPDF) {
      alert("A biblioteca jsPDF não está carregada. Verifique sua conexão à internet.");
      return;
    }

    const workers = isAll ? state.allWorkers : [state.allWorkers[state.selectedWorkerIndex]];
    if (!workers || workers.length === 0) {
      alert("Nenhum dado auditado para exportar.");
      return;
    }

    const doc = new jspdfModule.jsPDF({
      orientation: "portrait",
      unit: "mm",
      format: "a4"
    });

    const primaryColor = [12, 12, 16];
    const goldColor = [212, 175, 55];
    const secondaryBlack = [24, 24, 28];
    const pageWidth = doc.internal.pageSize.getWidth();
    let currentY = 15;

    // Header do Documento
    doc.setFillColor(...primaryColor);
    doc.rect(0, 0, pageWidth, 24, "F");

    doc.setFillColor(...goldColor);
    doc.rect(0, 24, pageWidth, 1.2, "F");

    doc.setTextColor(...goldColor);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(13);
    doc.text("AUDITORIA CONSOLIDADA DE FGTS — CAIXA ECONÔMICA FEDERAL", pageWidth / 2, 10, { align: "center" });

    doc.setFontSize(8.5);
    doc.setTextColor(255, 255, 255);
    doc.setFont("helvetica", "normal");
    const ruleDesc = getPrescriptionDescriptionText(state.prescriptionRule);
    doc.text(`Apuração: ${getTodayDateBR()} | Regra Prescricional: ${ruleDesc}`, pageWidth / 2, 17, { align: "center" });

    currentY = 32;

    // Se múltiplos colaboradores: Tabela de Resumo Executivo
    if (workers.length > 1 && isAll) {
      doc.setFont("helvetica", "bold");
      doc.setFontSize(11);
      doc.setTextColor(...primaryColor);
      doc.text("RESUMO EXECUTIVO DOS COLABORADORES ANALISADOS", 14, currentY);
      currentY += 4;

      const summaryData = workers.map((rawW, idx) => {
        const w = getPrescriptionFilteredWorker(
          rawW,
          state.prescriptionRule,
          state.prescriptionCutoffDate,
          state.prescriptionStartYear,
          state.exportStartYear
        );
        return [
          (idx + 1).toString(),
          w.workerName.toUpperCase(),
          w.workerCPF || "—",
          w.workerPIS || "—",
          w.companies.length.toString(),
          w.summary.totalPaid.toString(),
          w.summary.totalPending.toString(),
          w.summary.totalPending > 0 ? "⚠️ DÉBITO" : "✅ REGULAR"
        ];
      });

      doc.autoTable({
        startY: currentY,
        head: [["№", "Trabalhador", "CPF", "PIS/PASEP", "Vínculos", "Pagas", "Pendentes", "Situação"]],
        body: summaryData,
        theme: "grid",
        headStyles: {
          fillColor: primaryColor,
          textColor: goldColor,
          fontSize: 8,
          fontStyle: "bold",
          halign: "center"
        },
        bodyStyles: { fontSize: 8, textColor: [30, 41, 59] },
        columnStyles: {
          0: { halign: "center", cellWidth: 8 },
          1: { fontStyle: "bold", cellWidth: 46 },
          2: { halign: "center", cellWidth: 28 },
          3: { halign: "center", cellWidth: 28 },
          4: { halign: "center", cellWidth: 16 },
          5: { halign: "center", cellWidth: 16 },
          6: { halign: "center", cellWidth: 18 },
          7: { halign: "center", fontStyle: "bold" }
        },
        margin: { left: 14, right: 14 }
      });

      currentY = doc.lastAutoTable.finalY + 10;
    }

    // Detalhamento individual
    workers.forEach(rawW => {
      const report = getPrescriptionFilteredWorker(
        rawW,
        state.prescriptionRule,
        state.prescriptionCutoffDate,
        state.prescriptionStartYear,
        state.exportStartYear
      );

      if (currentY > 230) {
        doc.addPage();
        currentY = 15;
      }

      // Banner do Trabalhador
      doc.setFillColor(...secondaryBlack);
      doc.rect(14, currentY, pageWidth - 28, 12, "F");

      doc.setFillColor(...goldColor);
      doc.rect(14, currentY, 2, 12, "F");

      doc.setFont("helvetica", "bold");
      doc.setFontSize(10);
      doc.setTextColor(...goldColor);
      doc.text(report.workerName.toUpperCase(), 18, currentY + 7);

      doc.setFont("helvetica", "normal");
      doc.setFontSize(8);
      doc.setTextColor(200, 200, 200);
      doc.text(
        `CPF: ${report.workerCPF || "—"}  |  PIS/PASEP: ${report.workerPIS || "—"}  |  Pendências: ${report.summary.totalPending}`,
        pageWidth - 16,
        currentY + 7,
        { align: "right" }
      );

      currentY += 16;

      report.companies.forEach(company => {
        if (currentY > 240) {
          doc.addPage();
          currentY = 15;
        }

        doc.setFont("helvetica", "bold");
        doc.setFontSize(9);
        doc.setTextColor(...primaryColor);
        doc.text(`EMPRESA: ${company.name.toUpperCase()} (Admissão: ${company.admissionDate} | Saída: ${company.exitDate || "Ativa"})`, 14, currentY);
        currentY += 3;

        const tableBody = [];
        const depositsMap = {};
        company.paidDeposits.forEach(d => { depositsMap[d.competence] = d; });

        const allComps = [];
        company.paidDeposits.forEach(d => allComps.push(d.competence));
        company.pendingCompetencies.forEach(c => {
          if (!allComps.includes(c)) allComps.push(c);
        });

        allComps.sort((a, b) => {
          const [am, ay] = a.split("/").map(Number);
          const [bm, by] = b.split("/").map(Number);
          return (ay * 12 + am) - (by * 12 + bm);
        });

        allComps.forEach((comp, idx) => {
          const dep = depositsMap[comp];
          const isPaid = !!dep;
          const isConsolidated = dep && dep.isConsolidated;
          tableBody.push([
            (idx + 1).toString().padStart(2, "0"),
            formatCompetencePTUpper(comp),
            isPaid ? (isConsolidated ? "PAGO (CONSOL)*" : "PAGO") : "PENDENTE",
            dep ? dep.depositDate : "—",
            dep ? (dep.value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" }) : "—",
            dep ? dep.account : (company.accounts[0] || "—")
          ]);
        });

        doc.autoTable({
          startY: currentY,
          head: [["№", "Competência", "Situação", "Data Depósito", "Valor", "Conta"]],
          body: tableBody,
          theme: "grid",
          headStyles: {
            fillColor: [30, 30, 36],
            textColor: [255, 255, 255],
            fontSize: 7.5,
            fontStyle: "bold",
            halign: "center"
          },
          bodyStyles: { fontSize: 7, textColor: [30, 41, 59] },
          columnStyles: {
            0: { halign: "center", cellWidth: 8 },
            1: { halign: "center", cellWidth: 24, fontStyle: "bold" },
            2: { halign: "center", cellWidth: 32 },
            3: { halign: "center", cellWidth: 26 },
            4: { halign: "right", cellWidth: 28 },
            5: { halign: "center" }
          },
          margin: { left: 14, right: 14 }
        });

        currentY = doc.lastAutoTable.finalY + 8;
      });
    });

    const safeName = (workers[0]?.workerName || "FGTS")
      .normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-zA-Z0-9]/g, "_").toLowerCase();
    const fileName = isAll && workers.length > 1
      ? `laudo_pericial_fgts_lote_${workers.length}_trabalhadores.pdf`
      : `laudo_pericial_fgts_${safeName}.pdf`;

    doc.save(fileName);
  }

  // ==========================================================================
  // 9. RELATÓRIO OFICIAL EM TEXTO ESTRUTURADO (TXT)
  // ==========================================================================

  function getOfficialReportText(workerReport) {
    if (!workerReport) return "";
    const report = getPrescriptionFilteredWorker(
      workerReport,
      state.prescriptionRule,
      state.prescriptionCutoffDate,
      state.prescriptionStartYear,
      state.exportStartYear
    );

    const ruleDesc = getPrescriptionDescriptionText(state.prescriptionRule);

    let text = `========================================================================\n`;
    text += `AUDITORIA PERICIAL DE FGTS — CAIXA ECONÔMICA FEDERAL\n`;
    text += `TRABALHADOR: ${report.workerName}\n`;
    text += `CPF: ${report.workerCPF || "NÃO INFORMADO"}  |  PIS/PASEP: ${report.workerPIS || "NÃO INFORMADO"}\n`;
    text += `DATA DA APURAÇÃO: ${report.calculationDate || getTodayDateBR()}\n`;
    text += `REGRA PRESCRICIONAL: ${ruleDesc}\n`;
    text += `========================================================================\n\n`;

    let totalPendingFiltered = 0;

    report.companies.forEach(company => {
      const formattedExit = company.exitDate ? company.exitDate : `ativa (Apuração até presente mês)`;
      text += `═══════════════════════════════════════════════════════════════════════\n`;
      text += `EMPRESA: ${company.name}\n`;
      text += `Período do Vínculo: ${company.admissionDate} → ${formattedExit}\n`;
      text += `Contas Analisadas: ${company.accounts.join(", ")}\n`;
      text += `═══════════════════════════════════════════════════════════════════════\n\n`;

      text += `| Nº | Competência | Situação         | Data Depósito | Valor (R$)   |\n`;
      text += `|----|-------------|------------------|---------------|--------------|\n`;

      const depositsMap = {};
      company.paidDeposits.forEach(d => { depositsMap[d.competence] = d; });

      const allComps = [];
      company.paidDeposits.forEach(d => allComps.push(d.competence));
      company.pendingCompetencies.forEach(c => {
        if (!allComps.includes(c)) allComps.push(c);
      });

      allComps.sort((a, b) => {
        const [am, ay] = a.split("/").map(Number);
        const [bm, by] = b.split("/").map(Number);
        return (ay * 12 + am) - (by * 12 + bm);
      });

      allComps.forEach((comp, idx) => {
        const numStr = (idx + 1).toString().padStart(2, "0");
        const compAbbr = formatCompetencePTUpper(comp);
        const dep = depositsMap[comp];

        if (dep) {
          const valFormatted = (dep.value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
          const statusText = dep.isConsolidated ? "✅ PAGO CONSOL*" : "✅ PAGO        ";
          const depDateStr = (dep.depositDate || "—").toString();
          text += `| ${numStr} | ${compAbbr.padEnd(11)} | ${statusText.padEnd(16)} | ${depDateStr.padEnd(13)} | ${valFormatted.padStart(12)} |\n`;
        } else {
          totalPendingFiltered++;
          text += `| ${numStr} | ${compAbbr.padEnd(11)} | ❌ PENDENTE      | —             | —            |\n`;
        }
      });

      const consolidatedCount = company.paidDeposits.filter(d => d.isConsolidated).length;
      text += `\n`;
      text += `- Total de competências auditadas: ${allComps.length}\n`;
      text += `- Total de competências pagas: ${company.paidDeposits.length}\n`;
      if (consolidatedCount > 0) {
        text += `- Depósitos pagos via consolidação cruzada: ${consolidatedCount} (*)\n`;
      }
      text += `- Total PENDENTES: ${company.pendingCompetencies.length}\n`;
      text += `- Lista das pendências: ${company.pendingCompetencies.map(formatCompetencePTUpper).join(", ") || "Nenhuma — Tudo Regularizado"}\n\n`;
    });

    text += `═══════════════════════════════════════════════════════════════════════\n`;
    text += `SÍNTESE EXECUTIVA DO PASSIVO\n`;
    text += `═══════════════════════════════════════════════════════════════════════\n`;
    text += `TOTAL GERAL DE COMPETÊNCIAS EM ABERTO: ${totalPendingFiltered}\n`;
    text += `VALOR ESTIMADO DO PASSIVO NÃO RECOLHIDO: ${report.summary.totalEstimatedLoss.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}\n`;
    text += `\n(*) Nota Pericial: Lançamentos com marcação '✅ PAGO CONSOL*' são depósitos identificados sob outra conta de FGTS ou recolhidos em atraso vinculados à mesma empresa.\n`;

    return text;
  }

  function getPrescriptionDescriptionText(rule) {
    if (rule === "NONE") return "Histórico Integral (Sem Prescrição)";
    if (rule === "QUINQUENAL") return "Prescrição Quinquenal (CF/88 Art. 7º, XXIX - Últimos 5 anos)";
    if (rule === "STF_709212") return "Regra de Transição STF ARE 709.212 / Súmula 362 TST";
    if (rule === "YEAR_START") return "A Partir de Ano Específico";
    return "Quinquenal";
  }

  // ==========================================================================
  // 10. DADOS DE EXEMPLO PRONTOS PARA TESTE IMEDIATO
  // ==========================================================================

  const SAMPLE_EXTRATO_TEXT = `001 --- CAIXA ECONOMICA FEDERAL --- 15/10/2026
FGTS - EXTRATO ANALITICO DO TRABALHADOR

NOME: LEONARDO SANTOS PIRES
PIS/PASEP: 153.65288.97-5
CPF: 118.935.374-12
EMPRESA: CONSTRUTORA SILVA LTDA
NUMERO DA CONTA: 1004825-1
DATA ADMISSAO: 10/05/2021
DATA DE AFAST.: 00/00/0000

-------------------------------------------------------------------------
HISTÓRICO DE LANÇAMENTOS OFICIAIS
-------------------------------------------------------------------------
10/06/2021 R$ 220,00  115-DEPOSITO MAIO 2021
12/07/2021 R$ 220,00  115-DEPOSITO JUNHO 2021
10/08/2021 R$ 220,00  115-DEPOSITO JULHO 2021
11/09/2021 R$ 220,00  115-DEPOSITO AGOSTO 2021
10/11/2021 R$ 220,00  115-DEPOSITO OUTUBRO 2021
12/12/2021 R$ 220,00  115-DEPOSITO NOVEMBRO 2021
10/01/2022 R$ 220,00  115-DEPOSITO DEZEMBRO 2021
10/02/2022 CREDITO DE JAM 0,0042 R$ 15,20
10/02/2022 R$ 220,00  115-DEPOSITO JANEIRO 2022
11/03/2022 R$ 220,00  115-DEPOSITO FEVEREIRO 2022
10/05/2022 R$ 440,00  115-DEPOSITO EM ATRASO MARCO 2022
10/05/2022 R$ 220,00  115-DEPOSITO ABRIL 2022
10/06/2022 R$ 220,00  115-DEPOSITO MAIO 2022
10/07/2022 CREDITO DE JAM R$ 20,40
10/11/2022 R$ 220,00  115-DEPOSITO OUTUBRO 2022
10/12/2022 R$ 220,00  115-DEPOSITO NOVEMBRO 2022
10/01/2023 R$ 220,00  115-DEPOSITO DEZEMBRO 2022
10/02/2023 R$ 240,00  115-DEPOSITO JANEIRO 2023
10/03/2023 R$ 240,00  115-DEPOSITO FEVEREIRO 2023
10/04/2023 R$ 240,00  115-DEPOSITO MARCO 2023
10/05/2023 R$ 240,00  115-DEPOSITO ABRIL 2023
10/06/2023 R$ 240,00  115-DEPOSITO MAIO 2023
10/07/2023 R$ 240,00  115-DEPOSITO JUNHO 2023
10/10/2023 AC CRED DIST RESULTADO ANO BASE 2022 R$ 180,50
12/12/2023 R$ 240,00  115-DEPOSITO NOVEMBRO 2023
10/01/2024 R$ 240,00  115-DEPOSITO DEZEMBRO 2023
10/02/2024 R$ 240,00  115-DEPOSITO JANEIRO 2024
10/03/2024 R$ 240,00  115-DEPOSITO FEVEREIRO 2024
10/04/2024 R$ 240,00  115-DEPOSITO MARCO 2024
10/05/2024 R$ 240,00  115-DEPOSITO ABRIL 2024
10/08/2024 R$ 480,00  115-DEPOSITO EM ATRASO MAIO 2024
10/08/2024 R$ 240,00  115-DEPOSITO JUNHO 2024
10/09/2024 R$ 240,00  115-DEPOSITO JULHO 2024
10/10/2024 R$ 240,00  115-DEPOSITO AGOSTO 2024
10/11/2024 R$ 240,00  115-DEPOSITO SETEMBRO 2024
10/01/2025 R$ 240,00  115-DEPOSITO NOVEMBRO 2024
10/02/2025 R$ 240,00  115-DEPOSITO DEZEMBRO 2024
10/03/2025 R$ 260,00  115-DEPOSITO JANEIRO 2025
10/04/2025 R$ 260,00  115-DEPOSITO FEVEREIRO 2025
10/05/2025 R$ 260,00  115-DEPOSITO MARCO 2025
10/06/2025 R$ 260,00  115-DEPOSITO ABRIL 2025
10/07/2025 R$ 260,00  115-DEPOSITO MAIO 2025
10/08/2025 R$ 260,00  115-DEPOSITO JUNHO 2025
10/09/2025 R$ 260,00  115-DEPOSITO JULHO 2025
10/10/2025 R$ 260,00  115-DEPOSITO AGOSTO 2025
10/11/2025 R$ 260,00  115-DEPOSITO SETEMBRO 2025
10/12/2025 R$ 260,00  115-DEPOSITO OUTUBRO 2025
10/01/2026 R$ 260,00  115-DEPOSITO NOVEMBRO 2025
10/02/2026 R$ 260,00  115-DEPOSITO DEZEMBRO 2025
10/03/2026 R$ 260,00  115-DEPOSITO JANEIRO 2026
10/04/2026 R$ 260,00  115-DEPOSITO FEVEREIRO 2026
10/05/2026 R$ 260,00  115-DEPOSITO MARCO 2026`;

  // ==========================================================================
  // 11. CONTROLE E RENDERIZAÇÃO DA INTERFACE (DOM)
  // ==========================================================================

  // Elementos do DOM
  const dom = {
    dropzone: document.getElementById("dropzone"),
    fileInput: document.getElementById("fileInput"),
    btnBrowseFiles: document.getElementById("btnBrowseFiles"),
    fileQueueContainer: document.getElementById("fileQueueContainer"),
    fileCountSpan: document.getElementById("fileCountSpan"),
    fileQueueStatus: document.getElementById("fileQueueStatus"),
    fileQueueList: document.getElementById("fileQueueList"),
    rawTextarea: document.getElementById("rawTextarea"),
    charCounter: document.getElementById("charCounter"),
    btnClearInput: document.getElementById("btnClearInput"),
    btnLoadSample: document.getElementById("btnLoadSample"),
    btnRunAudit: document.getElementById("btnRunAudit"),
    errorAlert: document.getElementById("errorAlert"),
    errorMessage: document.getElementById("errorMessage"),

    // Filtros
    selectPrescriptionRule: document.getElementById("selectPrescriptionRule"),
    inputCutoffDate: document.getElementById("inputCutoffDate"),
    inputStartYear: document.getElementById("inputStartYear"),
    prescriptionRuleDesc: document.getElementById("prescriptionRuleDesc"),

    // Abas
    tabDashboardBtn: document.getElementById("tabDashboardBtn"),
    tabReportBtn: document.getElementById("tabReportBtn"),
    tabTimelineBtn: document.getElementById("tabTimelineBtn"),
    tabInspectorBtn: document.getElementById("tabInspectorBtn"),

    // Containers de Estado
    emptyState: document.getElementById("emptyState"),
    loadingState: document.getElementById("loadingState"),
    loadingStateDesc: document.getElementById("loadingStateDesc"),
    resultsContainer: document.getElementById("resultsContainer"),

    // Múltiplos Trabalhadores
    multiWorkersBanner: document.getElementById("multiWorkersBanner"),
    multiWorkersCountSpan: document.getElementById("multiWorkersCountSpan"),
    workersListButtons: document.getElementById("workersListButtons"),

    // Identificação do Trabalhador
    workerNameDisplay: document.getElementById("workerNameDisplay"),
    workerCpfDisplay: document.getElementById("workerCpfDisplay"),
    workerPisDisplay: document.getElementById("workerPisDisplay"),
    calcDateDisplay: document.getElementById("calcDateDisplay"),
    companiesButtonsList: document.getElementById("companiesButtonsList"),

    // Barra de Exportação e Ações Rápidas
    exportYearFilterInput: document.getElementById("exportYearFilterInput"),
    btnClearYearFilter: document.getElementById("btnClearYearFilter"),
    btnQuickExcelWorker: document.getElementById("btnQuickExcelWorker"),
    btnQuickExcelWorkerText: document.getElementById("btnQuickExcelWorkerText"),
    btnQuickPdfWorker: document.getElementById("btnQuickPdfWorker"),
    btnQuickPdfWorkerText: document.getElementById("btnQuickPdfWorkerText"),
    btnExportWorkerExcel: document.getElementById("btnExportWorkerExcel"),
    btnExportWorkerExcelText: document.getElementById("btnExportWorkerExcelText"),
    btnExportWorkerPdf: document.getElementById("btnExportWorkerPdf"),
    btnExportWorkerPdfText: document.getElementById("btnExportWorkerPdfText"),
    btnExportAllExcel: document.getElementById("btnExportAllExcel"),
    btnExportAllExcelText: document.getElementById("btnExportAllExcelText"),
    btnExportAllPdf: document.getElementById("btnExportAllPdf"),
    btnExportAllPdfText: document.getElementById("btnExportAllPdfText"),
    btnExportTxt: document.getElementById("btnExportTxt"),
    btnCopyReport: document.getElementById("btnCopyReport"),
    btnCopyReportText: document.getElementById("btnCopyReportText"),

    // Painel / Dashboard
    unifiedCompaniesBadge: document.getElementById("unifiedCompaniesBadge"),
    valTotalChecked: document.getElementById("valTotalChecked"),
    valTotalPaid: document.getElementById("valTotalPaid"),
    valPaidPercentBadge: document.getElementById("valPaidPercentBadge"),
    valTotalPending: document.getElementById("valTotalPending"),
    valPendingPercentBadge: document.getElementById("valPendingPercentBadge"),
    valTotalDeposited: document.getElementById("valTotalDeposited"),
    valTotalEstimatedLoss: document.getElementById("valTotalEstimatedLoss"),
    progressBarPaid: document.getElementById("progressBarPaid"),
    progressBarPending: document.getElementById("progressBarPending"),
    progressLabelPaid: document.getElementById("progressLabelPaid"),
    progressLabelPending: document.getElementById("progressLabelPending"),
    openCompetenciesContainer: document.getElementById("openCompetenciesContainer"),

    // Aba Relatório
    reportPreBox: document.getElementById("reportPreBox"),
    btnReportDownloadTxt: document.getElementById("btnReportDownloadTxt"),
    btnReportCopy: document.getElementById("btnReportCopy"),

    // Aba Histórico
    calendarYearsContainer: document.getElementById("calendarYearsContainer"),

    // Aba Inspetor
    selectInspectorFilter: document.getElementById("selectInspectorFilter"),
    searchInspectorInput: document.getElementById("searchInspectorInput"),
    inspectorLinesList: document.getElementById("inspectorLinesList")
  };

  function updateIcons() {
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons();
    }
  }

  function showError(msg) {
    state.error = msg;
    if (dom.errorMessage) dom.errorMessage.textContent = msg;
    if (dom.errorAlert) dom.errorAlert.style.display = "flex";
  }

  function hideError() {
    state.error = null;
    if (dom.errorAlert) dom.errorAlert.style.display = "none";
  }

  function renderPrescriptionDescription() {
    const rule = state.prescriptionRule;
    let desc = "";
    if (rule === "NONE") {
      desc = "<strong>Regra: Integral (Sem Prescrição)</strong>Todas as competências desde a admissão até o presente momento serão auditadas sem expurgo temporal.";
    } else if (rule === "QUINQUENAL") {
      desc = `<strong>Regra: Prescrição Quinquenal (CF/88 Art. 7º, XXIX)</strong>Apenas competências devidas nos últimos 5 anos anteriores ao marco (${state.prescriptionCutoffDate}) são exigíveis.`;
    } else if (rule === "STF_709212") {
      desc = "<strong>Regra: Modulação STF ARE 709.212 / Súmula 362 TST</strong>Competências anteriores a 13/11/2014 observam prazo de 30 anos (limitadas a 13/11/2019) e as posteriores observam 5 anos.";
    } else if (rule === "YEAR_START") {
      desc = `<strong>Regra: Ano Específico</strong>Apenas competências a partir do ano de ${state.prescriptionStartYear || "definido"} serão computadas.`;
    }
    if (dom.prescriptionRuleDesc) dom.prescriptionRuleDesc.innerHTML = desc;
  }

  function renderWorkerResults() {
    if (!state.allWorkers || state.allWorkers.length === 0) {
      if (dom.resultsContainer) dom.resultsContainer.style.display = "none";
      if (dom.emptyState) dom.emptyState.style.display = "flex";
      return;
    }

    if (dom.emptyState) dom.emptyState.style.display = "none";
    if (dom.loadingState) dom.loadingState.style.display = "none";
    if (dom.resultsContainer) dom.resultsContainer.style.display = "flex";

    const rawWorker = state.allWorkers[state.selectedWorkerIndex] || state.allWorkers[0];
    const worker = getPrescriptionFilteredWorker(
      rawWorker,
      state.prescriptionRule,
      state.prescriptionCutoffDate,
      state.prescriptionStartYear,
      state.exportStartYear
    );

    const workerFirstName = (worker?.workerName || "Individual").trim().split(" ")[0].toUpperCase();

    // Múltiplos Trabalhadores Banner
    if (state.allWorkers.length > 1) {
      if (dom.multiWorkersBanner) dom.multiWorkersBanner.style.display = "flex";
      if (dom.multiWorkersCountSpan) dom.multiWorkersCountSpan.textContent = state.allWorkers.length;

      if (dom.workersListButtons) {
        dom.workersListButtons.innerHTML = "";
        state.allWorkers.forEach((w, idx) => {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = `worker-choice-btn ${state.selectedWorkerIndex === idx ? "active" : ""}`;
          btn.innerHTML = `<span>${w.workerName.split(" ")[0]} (${idx + 1})</span>`;
          btn.addEventListener("click", () => {
            state.selectedWorkerIndex = idx;
            state.selectedCompanyId = state.allWorkers[idx]?.companies[0]?.id || null;
            renderWorkerResults();
          });
          dom.workersListButtons.appendChild(btn);
        });
      }

      // Rótulos individuais destacando o colaborador atualmente ativo
      if (dom.btnQuickExcelWorkerText) dom.btnQuickExcelWorkerText.textContent = `Excel (${workerFirstName})`;
      if (dom.btnQuickPdfWorkerText) dom.btnQuickPdfWorkerText.textContent = `PDF (${workerFirstName})`;
      if (dom.btnExportWorkerExcelText) dom.btnExportWorkerExcelText.textContent = `Excel Individual (${workerFirstName})`;
      if (dom.btnExportWorkerPdfText) dom.btnExportWorkerPdfText.textContent = `PDF Individual (${workerFirstName})`;

      // Botões de lote (Todos)
      if (dom.btnExportAllExcel) dom.btnExportAllExcel.style.display = "inline-flex";
      if (dom.btnExportAllPdf) dom.btnExportAllPdf.style.display = "inline-flex";
      if (dom.btnExportAllExcelText) dom.btnExportAllExcelText.textContent = `Excel de Todos (${state.allWorkers.length})`;
      if (dom.btnExportAllPdfText) dom.btnExportAllPdfText.textContent = `PDF de Todos (${state.allWorkers.length})`;
    } else {
      if (dom.multiWorkersBanner) dom.multiWorkersBanner.style.display = "none";

      if (dom.btnQuickExcelWorkerText) dom.btnQuickExcelWorkerText.textContent = "Excel Individual";
      if (dom.btnQuickPdfWorkerText) dom.btnQuickPdfWorkerText.textContent = "PDF Individual";
      if (dom.btnExportWorkerExcelText) dom.btnExportWorkerExcelText.textContent = "Baixar Excel (.xlsx)";
      if (dom.btnExportWorkerPdfText) dom.btnExportWorkerPdfText.textContent = "Baixar PDF (.pdf)";

      if (dom.btnExportAllExcel) dom.btnExportAllExcel.style.display = "none";
      if (dom.btnExportAllPdf) dom.btnExportAllPdf.style.display = "none";
    }

    // Informações de cabeçalho do trabalhador
    if (dom.workerNameDisplay) dom.workerNameDisplay.textContent = worker.workerName;
    if (dom.workerCpfDisplay) dom.workerCpfDisplay.textContent = worker.workerCPF || "—";
    if (dom.workerPisDisplay) dom.workerPisDisplay.textContent = worker.workerPIS || "—";
    if (dom.calcDateDisplay) dom.calcDateDisplay.textContent = `Apuração: ${worker.calculationDate || getTodayDateBR()}`;

    // Lista de Vínculos / Empresas
    if (dom.companiesButtonsList) {
      dom.companiesButtonsList.innerHTML = "";
      if (!state.selectedCompanyId && worker.companies.length > 0) {
        state.selectedCompanyId = worker.companies[0].id;
      }

      worker.companies.forEach(comp => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = `company-pill-btn ${state.selectedCompanyId === comp.id ? "active" : ""}`;
        btn.textContent = comp.name.split(" ")[0];
        btn.title = comp.name;
        btn.addEventListener("click", () => {
          state.selectedCompanyId = comp.id;
          renderWorkerResults();
        });
        dom.companiesButtonsList.appendChild(btn);
      });
    }

    // Dashboard Cards
    if (dom.unifiedCompaniesBadge) dom.unifiedCompaniesBadge.textContent = `Vínculos Analisados: ${worker.companies.length}`;
    if (dom.valTotalChecked) dom.valTotalChecked.textContent = worker.summary.totalChecked;
    if (dom.valTotalPaid) dom.valTotalPaid.textContent = worker.summary.totalPaid;
    if (dom.valTotalPending) dom.valTotalPending.textContent = worker.summary.totalPending;

    const paidPercent = worker.summary.totalChecked > 0
      ? ((worker.summary.totalPaid / worker.summary.totalChecked) * 100).toFixed(1)
      : 0;
    const pendingPercent = worker.summary.totalChecked > 0
      ? ((worker.summary.totalPending / worker.summary.totalChecked) * 100).toFixed(1)
      : 0;

    if (dom.valPaidPercentBadge) dom.valPaidPercentBadge.textContent = `${paidPercent}%`;
    if (dom.valPendingPercentBadge) dom.valPendingPercentBadge.textContent = `${pendingPercent}%`;

    if (dom.valTotalDeposited) {
      dom.valTotalDeposited.textContent = (worker.summary.totalDepositedValue || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }
    if (dom.valTotalEstimatedLoss) {
      dom.valTotalEstimatedLoss.textContent = (worker.summary.totalEstimatedLoss || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }

    // Barra de progresso
    if (dom.progressBarPaid) dom.progressBarPaid.style.width = `${paidPercent}%`;
    if (dom.progressBarPending) dom.progressBarPending.style.width = `${pendingPercent}%`;
    if (dom.progressLabelPaid) dom.progressLabelPaid.textContent = `${worker.summary.totalPaid} / ${worker.summary.totalChecked}`;
    if (dom.progressLabelPending) dom.progressLabelPending.textContent = `${worker.summary.totalPending} / ${worker.summary.totalChecked}`;

    // Lista de Competências em Aberto
    if (dom.openCompetenciesContainer) {
      dom.openCompetenciesContainer.innerHTML = "";
      const hasAnyPending = worker.companies.some(c => c.pendingCompetencies.length > 0);

      if (!hasAnyPending) {
        dom.openCompetenciesContainer.innerHTML = `
          <div class="alert-box alert-info" style="padding: 1.25rem;">
            <i data-lucide="check-circle-2" style="width: 22px; height: 22px; color: var(--gold-primary);"></i>
            <div>
              <strong style="display: block; font-size: 13px; color: #ffffff;">Vínculo 100% Quitado e Regularizado!</strong>
              Nenhuma competência em débito identificada no período apurado após a reconciliação cruzada de depósitos.
            </div>
          </div>
        `;
      } else {
        worker.companies.forEach(company => {
          if (company.pendingCompetencies.length === 0) return;

          const block = document.createElement("div");
          block.className = "company-pendency-block";

          const sortedPendings = [...company.pendingCompetencies].sort((a, b) => {
            const [am, ay] = a.split("/").map(Number);
            const [bm, by] = b.split("/").map(Number);
            return (ay * 12 + am) - (by * 12 + bm);
          });

          let tilesHtml = "";
          sortedPendings.forEach(comp => {
            const [mStr] = comp.split("/");
            const labelStr = PT_MONTHS[parseInt(mStr, 10) - 1] || "Mês";
            tilesHtml += `
              <div class="comp-tile">
                <span class="comp-month-name">${labelStr}</span>
                <span class="comp-month-val">${comp}</span>
              </div>
            `;
          });

          block.innerHTML = `
            <div class="pendency-block-header">
              <div>
                <span class="pendency-company-name">${company.name}</span>
                <div class="pendency-company-period">
                  Período do Vínculo: <strong>${company.admissionDate}</strong> a <strong>${company.exitDate || "Ativo"}</strong>
                </div>
              </div>
              <span class="pendency-badge-count">${company.pendingCompetencies.length} EM ABERTO</span>
            </div>
            <div class="competencies-tiles-grid">${tilesHtml}</div>
          `;
          dom.openCompetenciesContainer.appendChild(block);
        });
      }
    }

    // Aba 2: Relatório Oficial Formatado
    if (dom.reportPreBox) {
      dom.reportPreBox.textContent = getOfficialReportText(rawWorker);
    }

    // Aba 3: Histórico Anual / Calendário
    renderCalendarTimeline(worker);

    // Aba 4: Match Inspector
    renderInspectorLog(worker);

    updateIcons();
  }

  function renderCalendarTimeline(worker) {
    if (!dom.calendarYearsContainer) return;
    dom.calendarYearsContainer.innerHTML = "";

    const selectedCompany = worker.companies.find(c => c.id === state.selectedCompanyId) || worker.companies[0];
    if (!selectedCompany) return;

    const yearMap = {};
    const depositsMap = {};
    selectedCompany.paidDeposits.forEach(d => { depositsMap[d.competence] = d; });

    const allComps = [];
    selectedCompany.paidDeposits.forEach(d => allComps.push(d.competence));
    selectedCompany.pendingCompetencies.forEach(c => {
      if (!allComps.includes(c)) allComps.push(c);
    });

    allComps.forEach(comp => {
      const [mStr, yStr] = comp.split("/");
      const year = parseInt(yStr, 10);
      if (!yearMap[year]) yearMap[year] = [];
      const dep = depositsMap[comp];
      yearMap[year].push({
        competence: comp,
        isPaid: !!dep,
        deposit: dep
      });
    });

    const sortedYears = Object.keys(yearMap).map(Number).sort((a, b) => b - a);

    sortedYears.forEach(year => {
      const yearBlock = document.createElement("div");
      yearBlock.className = "calendar-year-block";

      const items = yearMap[year];
      items.sort((a, b) => {
        const mA = parseInt(a.competence.split("/")[0], 10);
        const mB = parseInt(b.competence.split("/")[0], 10);
        return mA - mB;
      });

      let monthsHtml = "";
      items.forEach(({ competence, isPaid, deposit }) => {
        const mNum = parseInt(competence.split("/")[0], 10);
        const mName = PT_MONTHS[mNum - 1] || "Mês";
        const isConsolidated = deposit && deposit.isConsolidated;

        let cardClass = "pending";
        if (isPaid) {
          cardClass = isConsolidated ? "consolidated" : "paid";
        }

        const formattedVal = deposit
          ? (deposit.value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" })
          : "FALTA";

        monthsHtml += `
          <div class="cal-month-card ${cardClass}">
            <div class="cal-month-name">
              <span>${mName}</span>
              ${isConsolidated ? '<span style="font-size: 8px; background: rgba(217,119,6,0.3); padding: 1px 4px; border-radius: 2px;">C</span>' : ''}
            </div>
            <div class="cal-month-comp">${competence}</div>
            <div class="cal-month-val">${formattedVal}</div>
            <div class="cal-tooltip">
              <strong style="color: var(--gold-light);">${competence} (${mName})</strong><br>
              Status: ${isPaid ? (isConsolidated ? "PAGO (Consolidado)*" : "PAGO ✅") : "EM ABERTO ❌"}<br>
              ${deposit ? `Data: ${deposit.depositDate}<br>Valor: R$ ${deposit.value.toFixed(2)}<br>Conta: ${deposit.account}` : "Nenhum depósito localizado"}
              ${isConsolidated ? `<br><em style="color: #fbbf24;">Origem: ${deposit.consolidatedFrom || "Outra Conta"}</em>` : ""}
            </div>
          </div>
        `;
      });

      yearBlock.innerHTML = `
        <div class="calendar-year-header">
          <span class="calendar-year-title">${year}</span>
          <div class="calendar-legend">
            <span><i class="legend-dot dot-paid"></i> Pago</span>
            <span><i class="legend-dot dot-consolidated"></i> Consolidado*</span>
            <span><i class="legend-dot dot-pending"></i> Pendente</span>
          </div>
        </div>
        <div class="calendar-months-grid">${monthsHtml}</div>
      `;

      dom.calendarYearsContainer.appendChild(yearBlock);
    });
  }

  function renderInspectorLog(worker) {
    if (!dom.inspectorLinesList) return;
    dom.inspectorLinesList.innerHTML = "";

    const linesLog = worker.rawLinesLog || [];
    const filter = state.inspectorFilter;
    const search = state.inspectorSearch.toLowerCase();

    const filtered = linesLog.filter(item => {
      const matchesSearch = item.text.toLowerCase().includes(search);
      if (!matchesSearch) return false;
      if (filter === "ALL") return true;
      if (filter === "PAYMENT") return item.classification === "PAYMENT";
      if (filter === "IGNORED_JAM") return item.classification === "IGNORED_JAM";
      return item.classification !== "PAYMENT" && item.classification !== "IGNORED_JAM";
    });

    if (filtered.length === 0) {
      dom.inspectorLinesList.innerHTML = `
        <div style="text-align: center; padding: 2rem; color: var(--text-dim); font-size: 11px;">
          Nenhuma linha encontrada no filtro atual.
        </div>
      `;
      return;
    }

    filtered.forEach(line => {
      const itemEl = document.createElement("div");
      itemEl.className = "inspector-line-item";

      let badgeClass = "other";
      let badgeLabel = "Informativo";
      if (line.classification === "PAYMENT") {
        badgeClass = "payment";
        badgeLabel = "Depósito 115";
      } else if (line.classification === "IGNORED_JAM") {
        badgeClass = "jam";
        badgeLabel = "JAM Ignorado";
      }

      itemEl.innerHTML = `
        <div class="line-info-left">
          <span class="line-number-badge">L${line.lineNumber}</span>
          <div class="line-text-wrap">
            <span class="line-raw-str">${line.text}</span>
            <span class="line-reason-str">${line.reason}</span>
          </div>
        </div>
        <span class="classification-badge ${badgeClass}">${badgeLabel}</span>
      `;
      dom.inspectorLinesList.appendChild(itemEl);
    });
  }

  function switchTab(tabName) {
    state.activeTab = tabName;
    const tabs = ["Dashboard", "Report", "Timeline", "Inspector"];
    tabs.forEach(t => {
      const btn = dom[`tab${t}Btn`];
      const pane = dom[`tab${t}`];
      const isTarget = t.toLowerCase() === tabName.replace("_", "").toLowerCase();
      if (btn) btn.classList.toggle("active", isTarget);
      if (pane) pane.style.display = isTarget ? "block" : "none";
    });
  }

  // ==========================================================================
  // 12. FLUXO DE EXECUÇÃO DA AUDITORIA
  // ==========================================================================

  async function processTextAudit(textToAudit) {
    if (!textToAudit || !textToAudit.trim()) {
      showError("Por favor, cole o texto do extrato ou adicione um arquivo PDF/TXT para iniciar a auditoria.");
      return;
    }

    hideError();
    state.isLoading = true;

    if (dom.emptyState) dom.emptyState.style.display = "none";
    if (dom.resultsContainer) dom.resultsContainer.style.display = "none";
    if (dom.loadingState) dom.loadingState.style.display = "flex";
    if (dom.btnRunAudit) {
      dom.btnRunAudit.disabled = true;
      dom.btnRunAudit.innerHTML = '<span class="spinner-ring" style="width: 16px; height: 16px; margin-right: 8px;"></span> Processando Motor Pericial...';
    }

    // Executa em microtask para renderizar o spinner no navegador
    setTimeout(() => {
      try {
        const groups = groupTextByWorker(textToAudit);
        const reports = groups.map(g => parseSingleWorkerStatement(g.workerName, g.rawText));

        state.allWorkers = reports;
        state.selectedWorkerIndex = 0;
        state.selectedCompanyId = reports[0]?.companies[0]?.id || null;

        renderWorkerResults();
      } catch (err) {
        console.error("Erro na auditoria:", err);
        showError(`Erro durante a auditoria pericial: ${err.message}`);
        if (dom.emptyState) dom.emptyState.style.display = "flex";
        if (dom.resultsContainer) dom.resultsContainer.style.display = "none";
      } finally {
        state.isLoading = false;
        if (dom.loadingState) dom.loadingState.style.display = "none";
        if (dom.btnRunAudit) {
          dom.btnRunAudit.disabled = false;
          dom.btnRunAudit.innerHTML = '<i data-lucide="activity"></i><span>Realizar Auditoria de FGTS</span>';
          updateIcons();
        }
      }
    }, 150);
  }

  // ==========================================================================
  // 13. GERENCIAMENTO DE UPLOAD E LEITURA DE ARQUIVOS
  // ==========================================================================

  async function handleFilesUpload(files) {
    if (!files || files.length === 0) return;

    hideError();
    state.isReadingFiles = true;

    const newFiles = Array.from(files).map(f => ({
      name: f.name,
      size: f.size,
      status: "loading"
    }));

    state.uploadedFiles = [...state.uploadedFiles, ...newFiles];
    renderFileQueue();

    let accumulatedText = "";

    try {
      for (let idx = 0; idx < files.length; idx++) {
        const file = files[idx];
        updateFileStatus(file.name, "loading");
        if (dom.fileQueueStatus) {
          dom.fileQueueStatus.style.display = "inline-block";
          dom.fileQueueStatus.textContent = `Lendo "${file.name}"...`;
        }

        let extracted = "";
        try {
          if (file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf")) {
            extracted = await extractTextFromPdf(file, (page, total) => {
              if (dom.fileQueueStatus) {
                dom.fileQueueStatus.textContent = `Lendo "${file.name}": pág. ${page} de ${total}...`;
              }
            });
          } else {
            extracted = await new Promise((resolve, reject) => {
              const reader = new FileReader();
              reader.onload = e => resolve(e.target?.result || "");
              reader.onerror = () => reject(new Error("Falha ao ler arquivo de texto."));
              reader.readAsText(file, "UTF-8");
            });
          }

          accumulatedText += `\n--- INÍCIO DO DOCUMENTO: ${file.name} ---\n${extracted}\n--- FIM DO DOCUMENTO: ${file.name} ---\n`;
          updateFileStatus(file.name, "success");
        } catch (err) {
          console.error(err);
          updateFileStatus(file.name, "error");
          showError(`Erro ao ler "${file.name}": ${err.message}`);
        }
      }

      if (accumulatedText.trim()) {
        const currentVal = dom.rawTextarea ? dom.rawTextarea.value.trim() : "";
        const newVal = currentVal ? `${currentVal}\n\n${accumulatedText.trim()}` : accumulatedText.trim();
        if (dom.rawTextarea) {
          dom.rawTextarea.value = newVal;
          state.inputText = newVal;
          if (dom.charCounter) dom.charCounter.textContent = `${newVal.length} caracteres`;
        }

        // Executa a auditoria automaticamente após ler os arquivos
        processTextAudit(newVal);
      }
    } catch (gErr) {
      showError(gErr.message || "Erro no processamento dos arquivos.");
    } finally {
      state.isReadingFiles = false;
      if (dom.fileQueueStatus) dom.fileQueueStatus.style.display = "none";
      if (dom.fileInput) dom.fileInput.value = "";
    }
  }

  function renderFileQueue() {
    if (!dom.fileQueueContainer || !dom.fileQueueList) return;
    if (state.uploadedFiles.length === 0) {
      dom.fileQueueContainer.style.display = "none";
      return;
    }

    dom.fileQueueContainer.style.display = "flex";
    if (dom.fileCountSpan) dom.fileCountSpan.textContent = state.uploadedFiles.length;
    dom.fileQueueList.innerHTML = "";

    state.uploadedFiles.forEach((f, idx) => {
      const item = document.createElement("div");
      item.className = "file-queue-item";

      let badgeHtml = '<span class="file-badge-loading">Lendo...</span>';
      if (f.status === "success") badgeHtml = '<span class="file-badge-success">Lido ✓</span>';
      if (f.status === "error") badgeHtml = '<span class="file-badge-error">Falha ✗</span>';

      item.innerHTML = `
        <span class="file-name-span">
          <span style="color: var(--text-dim);">[${idx + 1}]</span>
          <span style="overflow: hidden; text-overflow: ellipsis;">${f.name}</span>
        </span>
        ${badgeHtml}
      `;
      dom.fileQueueList.appendChild(item);
    });
  }

  function updateFileStatus(name, status) {
    const file = state.uploadedFiles.find(f => f.name === name);
    if (file) {
      file.status = status;
      renderFileQueue();
    }
  }

  // ==========================================================================
  // 14. VINCULAÇÃO DE EVENTOS DO USUÁRIO
  // ==========================================================================

  function initEventListeners() {
    // Dropzone e Navegação de Arquivos
    if (dom.btnBrowseFiles && dom.fileInput) {
      dom.btnBrowseFiles.addEventListener("click", () => dom.fileInput.click());
    }

    if (dom.fileInput) {
      dom.fileInput.addEventListener("change", e => {
        if (e.target.files && e.target.files.length > 0) {
          handleFilesUpload(Array.from(e.target.files));
        }
      });
    }

    if (dom.dropzone) {
      dom.dropzone.addEventListener("dragover", e => {
        e.preventDefault();
        dom.dropzone.classList.add("drag-active");
      });
      dom.dropzone.addEventListener("dragleave", () => {
        dom.dropzone.classList.remove("drag-active");
      });
      dom.dropzone.addEventListener("drop", e => {
        e.preventDefault();
        dom.dropzone.classList.remove("drag-active");
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
          handleFilesUpload(Array.from(e.dataTransfer.files));
        }
      });
    }

    // Textarea input
    if (dom.rawTextarea) {
      dom.rawTextarea.addEventListener("input", e => {
        state.inputText = e.target.value;
        if (dom.charCounter) dom.charCounter.textContent = `${state.inputText.length} caracteres`;
      });
    }

    // Botão Limpar
    if (dom.btnClearInput) {
      dom.btnClearInput.addEventListener("click", () => {
        state.inputText = "";
        state.uploadedFiles = [];
        state.allWorkers = [];
        state.selectedWorkerIndex = 0;
        state.selectedCompanyId = null;
        if (dom.rawTextarea) dom.rawTextarea.value = "";
        if (dom.charCounter) dom.charCounter.textContent = "0 caracteres";
        hideError();
        renderFileQueue();
        renderWorkerResults();
      });
    }

    // Botão Exemplo Rápido
    if (dom.btnLoadSample) {
      dom.btnLoadSample.addEventListener("click", () => {
        if (dom.rawTextarea) {
          dom.rawTextarea.value = SAMPLE_EXTRATO_TEXT;
          state.inputText = SAMPLE_EXTRATO_TEXT;
          if (dom.charCounter) dom.charCounter.textContent = `${SAMPLE_EXTRATO_TEXT.length} caracteres`;
        }
        processTextAudit(SAMPLE_EXTRATO_TEXT);
      });
    }

    // Botão Realizar Auditoria
    if (dom.btnRunAudit) {
      dom.btnRunAudit.addEventListener("click", () => {
        const text = dom.rawTextarea ? dom.rawTextarea.value : state.inputText;
        processTextAudit(text);
      });
    }

    // Abas
    if (dom.tabDashboardBtn) dom.tabDashboardBtn.addEventListener("click", () => switchTab("dashboard"));
    if (dom.tabReportBtn) dom.tabReportBtn.addEventListener("click", () => switchTab("official_report"));
    if (dom.tabTimelineBtn) dom.tabTimelineBtn.addEventListener("click", () => switchTab("timeline"));
    if (dom.tabInspectorBtn) dom.tabInspectorBtn.addEventListener("click", () => switchTab("inspector"));

    // Filtros Prescricionais
    if (dom.selectPrescriptionRule) {
      dom.selectPrescriptionRule.value = state.prescriptionRule;
      dom.selectPrescriptionRule.addEventListener("change", e => {
        state.prescriptionRule = e.target.value;
        renderPrescriptionDescription();
        renderWorkerResults();
      });
    }

    if (dom.inputCutoffDate) {
      dom.inputCutoffDate.value = state.prescriptionCutoffDate;
      dom.inputCutoffDate.addEventListener("input", e => {
        state.prescriptionCutoffDate = e.target.value;
        renderPrescriptionDescription();
        renderWorkerResults();
      });
    }

    if (dom.inputStartYear) {
      dom.inputStartYear.addEventListener("input", e => {
        state.prescriptionStartYear = e.target.value;
        renderPrescriptionDescription();
        renderWorkerResults();
      });
    }

    // Filtro adicional de ano na barra de exportação
    if (dom.exportYearFilterInput) {
      dom.exportYearFilterInput.addEventListener("input", e => {
        state.exportStartYear = e.target.value;
        if (dom.btnClearYearFilter) {
          dom.btnClearYearFilter.style.display = e.target.value ? "inline" : "none";
        }
        renderWorkerResults();
      });
    }

    if (dom.btnClearYearFilter) {
      dom.btnClearYearFilter.addEventListener("click", () => {
        state.exportStartYear = "";
        if (dom.exportYearFilterInput) dom.exportYearFilterInput.value = "";
        dom.btnClearYearFilter.style.display = "none";
        renderWorkerResults();
      });
    }

    // Botões de Exportação Individual (Apenas o Colaborador Selecionado)
    if (dom.btnQuickExcelWorker) dom.btnQuickExcelWorker.addEventListener("click", () => handleExportExcel(false));
    if (dom.btnQuickPdfWorker) dom.btnQuickPdfWorker.addEventListener("click", () => handleExportPdf(false));
    if (dom.btnExportWorkerExcel) dom.btnExportWorkerExcel.addEventListener("click", () => handleExportExcel(false));
    if (dom.btnExportWorkerPdf) dom.btnExportWorkerPdf.addEventListener("click", () => handleExportPdf(false));

    // Botões de Exportação em Lote (Todos os Colaboradores)
    if (dom.btnExportAllExcel) dom.btnExportAllExcel.addEventListener("click", () => handleExportExcel(true));
    if (dom.btnExportAllPdf) dom.btnExportAllPdf.addEventListener("click", () => handleExportPdf(true));

    if (dom.btnExportTxt) {
      dom.btnExportTxt.addEventListener("click", () => {
        const rawWorker = state.allWorkers[state.selectedWorkerIndex] || state.allWorkers[0];
        if (!rawWorker) return;
        const text = getOfficialReportText(rawWorker);
        const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        const safeName = rawWorker.workerName.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-zA-Z0-9]/g, "_").toLowerCase();
        link.download = `laudo_pericial_fgts_${safeName}.txt`;
        link.click();
        URL.revokeObjectURL(url);
      });
    }

    // Botão Copiar Relatório
    const handleCopy = () => {
      const rawWorker = state.allWorkers[state.selectedWorkerIndex] || state.allWorkers[0];
      if (!rawWorker) return;
      const text = getOfficialReportText(rawWorker);
      navigator.clipboard.writeText(text).then(() => {
        if (dom.btnCopyReportText) dom.btnCopyReportText.textContent = "Copiado!";
        setTimeout(() => {
          if (dom.btnCopyReportText) dom.btnCopyReportText.textContent = "Copiar";
        }, 2000);
      });
    };

    if (dom.btnCopyReport) dom.btnCopyReport.addEventListener("click", handleCopy);
    if (dom.btnReportCopy) dom.btnReportCopy.addEventListener("click", handleCopy);

    if (dom.btnReportDownloadTxt && dom.btnExportTxt) {
      dom.btnReportDownloadTxt.addEventListener("click", () => dom.btnExportTxt.click());
    }

    // Inspetor Filtros
    if (dom.selectInspectorFilter) {
      dom.selectInspectorFilter.addEventListener("change", e => {
        state.inspectorFilter = e.target.value;
        const rawWorker = state.allWorkers[state.selectedWorkerIndex] || state.allWorkers[0];
        if (rawWorker) renderInspectorLog(rawWorker);
      });
    }

    if (dom.searchInspectorInput) {
      dom.searchInspectorInput.addEventListener("input", e => {
        state.inspectorSearch = e.target.value;
        const rawWorker = state.allWorkers[state.selectedWorkerIndex] || state.allWorkers[0];
        if (rawWorker) renderInspectorLog(rawWorker);
      });
    }
  }

  // ==========================================================================
  // 15. INICIALIZAÇÃO
  // ==========================================================================

  function init() {
    renderPrescriptionDescription();
    initEventListeners();
    updateIcons();
    console.log("[Auditor FGTS CEF] Aplicação Vanilla JS inicializada com sucesso.");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

})();
