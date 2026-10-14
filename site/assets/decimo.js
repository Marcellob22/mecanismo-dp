(function () {
  const cfg = window.MECANISMO_DP;
  const modo = cfg.modo; // 'controle' ou 'dashboard'
  const ehGestor = cfg.usuario.role === 'GESTOR';
  const raiz = document.getElementById('app-decimo');

  const ETAPAS = [
    { k: 'ad', nome: 'Envio 13º Ad' },
    { k: 'int', nome: 'Envio 13º Int' },
    { k: 'enc', nome: 'Encargos' },
  ];
  // 0 = pendente, 1 = feito, 2 = não se aplica
  const PENDENTE = 0, FEITO = 1, NA = 2;

  let empresas = [];
  let executores = [];

  // ---------- utilidades ----------
  function el(tag, attrs, filhos) {
    const n = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
      if (v === false || v === null || v === undefined) return;
      if (k === 'className') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2).toLowerCase(), v);
      else if (v === true) n.setAttribute(k, '');
      else n.setAttribute(k, v);
    });
    (filhos || []).forEach((f) => { if (f) n.append(f); });
    return n;
  }

  async function api(metodo, url, corpo) {
    const opts = { method: metodo, headers: { 'X-Csrf-Token': cfg.csrfToken } };
    if (corpo instanceof FormData) {
      opts.body = corpo;
    } else if (corpo !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(corpo);
    }
    const resp = await fetch(url, opts);
    let dados = {};
    try { dados = await resp.json(); } catch (e) { /* resposta sem JSON */ }
    if (!resp.ok) throw new Error(dados.erro || 'Erro inesperado (' + resp.status + ').');
    return dados;
  }

  async function carregar() {
    const d = await api('GET', 'api/decimo.php');
    empresas = d.empresas;
    executores = d.executores;
  }

  function nomeExecutor(id) {
    const e = executores.find((x) => x.id === id);
    return e ? e.nome : null;
  }

  // ---------- times ----------
  let filtroEquipe = ''; // '' = todos | '__sem__' = sem time | nome do time

  function listaEquipes() {
    return [...new Set(empresas.map((e) => e.equipe).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
  }

  function noFiltroEquipe(e) {
    if (!filtroEquipe) return true;
    if (filtroEquipe === '__sem__') return !e.equipe;
    return e.equipe === filtroEquipe;
  }

  function opcoesEquipe(select) {
    const equipes = listaEquipes();
    const semTime = empresas.some((e) => !e.equipe);
    select.replaceChildren(el('option', { value: '', text: 'Todos os times' }));
    equipes.forEach((t) => select.append(el('option', { value: t, text: t })));
    if (equipes.length && semTime) select.append(el('option', { value: '__sem__', text: 'Sem time definido' }));
    select.value = [...select.options].some((o) => o.value === filtroEquipe) ? filtroEquipe : '';
    filtroEquipe = select.value;
  }

  // ---------- cálculos (usados pelo dashboard) ----------
  function calcularResumo(lista) {
    // Empresas em S.M/Pró-labore ficam fora de todos os cálculos
    const todas = lista || empresas;
    const empresas_ = todas.filter((e) => !e.pro_labore);
    const foraDoCalculo = todas.length - empresas_.length;
    const porEtapa = {};
    let feitosTot = 0, aplicTot = 0;
    ETAPAS.forEach(({ k }) => {
      let feitos = 0, na = 0;
      empresas_.forEach((e) => {
        const s = e[k + '_status'];
        if (s === FEITO) feitos++;
        else if (s === NA) na++;
      });
      const aplic = empresas_.length - na;
      porEtapa[k] = { feitos, na, aplic, faltam: aplic - feitos, pct: aplic > 0 ? (feitos / aplic) * 100 : null };
      feitosTot += feitos;
      aplicTot += aplic;
    });
    const geral = { feitos: feitosTot, aplic: aplicTot, faltam: aplicTot - feitosTot, pct: aplicTot > 0 ? (feitosTot / aplicTot) * 100 : null };

    // Ranking: soma das três etapas feitas por cada executor
    const mapa = new Map();
    let semExecutor = 0;
    empresas_.forEach((e) => {
      ETAPAS.forEach(({ k }) => {
        const s = e[k + '_status'];
        const exId = e[k + '_executor_id'];
        if (!exId) {
          if (s === FEITO) semExecutor++;
          return;
        }
        if (!mapa.has(exId)) mapa.set(exId, { id: exId, nome: nomeExecutor(exId) || '(removido)', ad: 0, int: 0, enc: 0, total: 0, pendentes: 0 });
        const r = mapa.get(exId);
        if (s === FEITO) { r[k]++; r.total++; }
        else if (s === PENDENTE) r.pendentes++;
      });
    });
    const ranking = [...mapa.values()].sort((a, b) => b.total - a.total || a.nome.localeCompare(b.nome, 'pt-BR'));

    const empresasCompletas = empresas_.filter((e) => ETAPAS.every(({ k }) => e[k + '_status'] !== PENDENTE)).length;
    return { porEtapa, geral, ranking, semExecutor, empresasCompletas, totalNoCalculo: empresas_.length, foraDoCalculo };
  }

  function fmtPct(p) {
    if (p === null) return '—';
    return (Math.round(p * 10) / 10).toString().replace('.', ',') + '%';
  }

  // =====================================================================
  //  DASHBOARD
  // =====================================================================
  function medidor(titulo, r, destaque) {
    const C = 2 * Math.PI * 52;
    const pct = r.pct === null ? 0 : r.pct;
    const cor = pct >= 100 ? '#22c55e' : '#c9a227';
    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 120 120');
    svg.setAttribute('width', '120');
    svg.setAttribute('height', '120');
    const trilho = document.createElementNS(svgNS, 'circle');
    [['cx', 60], ['cy', 60], ['r', 52], ['fill', 'none'], ['stroke', '#2a2a2a'], ['stroke-width', 10]].forEach(([a, v]) => trilho.setAttribute(a, v));
    const arco = document.createElementNS(svgNS, 'circle');
    [['cx', 60], ['cy', 60], ['r', 52], ['fill', 'none'], ['stroke', cor], ['stroke-width', 10], ['stroke-linecap', 'round'],
      ['stroke-dasharray', (pct / 100) * C + ' ' + C], ['transform', 'rotate(-90 60 60)']].forEach(([a, v]) => arco.setAttribute(a, v));
    svg.append(trilho, arco);

    const texto = el('div', { className: 'medidor-pct', text: fmtPct(r.pct) });
    const miolo = el('div', { className: 'medidor-miolo' }, [svg, texto]);

    const detalhe = r.aplic > 0
      ? r.feitos + ' de ' + r.aplic + (r.na ? ' (' + r.na + ' N/A)' : '')
      : 'sem empresas aplicáveis';
    return el('div', { className: 'card medidor' + (destaque ? ' medidor-destaque' : '') }, [
      el('div', { className: 'secao-titulo', text: titulo }),
      miolo,
      el('div', { className: 'medidor-detalhe', text: detalhe }),
      el('div', { className: 'texto-fraco', text: r.faltam > 0 ? 'Faltam ' + r.faltam : (r.aplic > 0 ? 'Tudo concluído' : '') }),
    ]);
  }

  let dashConteudo = null;
  let dashSelectEquipe = null;

  function renderDashboard() {
    if (!dashConteudo) {
      dashSelectEquipe = el('select', { className: 'filtro-select', 'aria-label': 'Filtrar por time' });
      dashSelectEquipe.addEventListener('change', () => { filtroEquipe = dashSelectEquipe.value; renderDashboard(); });
      dashConteudo = el('div');
      raiz.replaceChildren(el('div', { className: 'barra-filtros' }, [dashSelectEquipe]), dashConteudo);
    }
    opcoesEquipe(dashSelectEquipe);
    dashSelectEquipe.parentElement.style.display = listaEquipes().length ? '' : 'none';

    const lista = empresas.filter(noFiltroEquipe);
    const r = calcularResumo(lista);
    const alvo = dashConteudo;
    alvo.replaceChildren();

    if (empresas.length === 0) {
      alvo.append(el('div', { className: 'card', style: 'text-align:center;' }, [
        el('p', { text: 'Ainda não há empresas cadastradas no controle do 13º.' }),
        el('p', { className: 'texto-fraco', text: 'O gestor pode importar a lista em Controle 13º.' }),
      ]));
      return;
    }

    const grade = el('div', { className: 'medidores' }, [
      medidor('Geral', r.geral, true),
      ...ETAPAS.map((e) => medidor(e.nome, r.porEtapa[e.k], false)),
    ]);
    alvo.append(grade);

    alvo.append(el('p', { className: 'texto-fraco mb-3', text: r.empresasCompletas + ' de ' + r.totalNoCalculo + ' empresas com as três etapas resolvidas (feitas ou N/A).' + (r.foraDoCalculo ? ' ' + r.foraDoCalculo + ' empresa(s) em S.M/Pró-labore ficam fora do cálculo.' : '') }));

    // Andamento por time (só quando não está filtrado)
    const equipes = listaEquipes();
    if (!filtroEquipe && equipes.length > 1) {
      const cardTimes = el('div', { className: 'card mb-3' }, [el('div', { className: 'secao-titulo', text: 'Andamento por time (Ad + Int + Encargos)' })]);
      const grupos = equipes.map((t) => [t, empresas.filter((e) => e.equipe === t)]);
      const semTime = empresas.filter((e) => !e.equipe);
      if (semTime.length) grupos.push(['Sem time definido', semTime]);
      grupos.forEach(([nome, lst]) => {
        const g = calcularResumo(lst);
        const linha = el('div', { className: 'time-linha' }, [
          el('div', { className: 'rank-topo' }, [
            el('span', { className: 'rank-nome', text: nome }),
            el('span', { className: 'rank-total', text: fmtPct(g.geral.pct) }),
          ]),
          el('div', { className: 'rank-barra' }, [el('div', { className: 'rank-barra-cheia', style: 'width:' + (g.geral.pct || 0) + '%' })]),
          el('div', { className: 'texto-fraco', text: g.totalNoCalculo + ' empresas · Ad ' + fmtPct(g.porEtapa.ad.pct) + ' · Int ' + fmtPct(g.porEtapa.int.pct) + ' · Encargos ' + fmtPct(g.porEtapa.enc.pct) }),
        ]);
        cardTimes.append(linha);
      });
      alvo.append(cardTimes);
    }

    // Ranking
    const maxTotal = r.ranking.length ? Math.max(1, ...r.ranking.map((x) => x.total)) : 1;
    const card = el('div', { className: 'card' }, [el('div', { className: 'secao-titulo', text: 'Ranking de executores (Ad + Int + Encargos)' + (filtroEquipe ? ' · ' + (filtroEquipe === '__sem__' ? 'sem time' : filtroEquipe) : '') })]);
    if (r.ranking.length === 0) {
      card.append(el('p', { className: 'texto-fraco', text: 'Nenhum executor atribuído ainda.' }));
    } else {
      r.ranking.forEach((x, i) => {
        const medalha = ['🥇', '🥈', '🥉'][i] || String(i + 1) + 'º';
        card.append(el('div', { className: 'rank-linha' }, [
          el('div', { className: 'rank-pos', text: medalha }),
          el('div', { className: 'rank-corpo' }, [
            el('div', { className: 'rank-topo' }, [
              el('span', { className: 'rank-nome', text: x.nome }),
              el('span', { className: 'rank-total', text: x.total + (x.total === 1 ? ' atividade' : ' atividades') }),
            ]),
            el('div', { className: 'rank-barra' }, [el('div', { className: 'rank-barra-cheia', style: 'width:' + (x.total / maxTotal) * 100 + '%' })]),
            el('div', { className: 'texto-fraco', text: 'Ad ' + x.ad + ' · Int ' + x.int + ' · Encargos ' + x.enc + (x.pendentes ? ' · ' + x.pendentes + ' pendente(s) atribuída(s)' : '') }),
          ]),
        ]));
      });
    }
    if (r.semExecutor > 0) {
      card.append(el('p', { className: 'texto-fraco mt-3', text: r.semExecutor + ' etapa(s) marcada(s) como feita(s) sem executor definido (não entram no ranking).' }));
    }
    alvo.append(card);

    const agora = new Date();
    alvo.append(el('p', { className: 'texto-fraco mt-3', text: 'Atualizado às ' + agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) + ' (atualiza sozinho a cada 30 segundos).' }));
  }

  // =====================================================================
  //  CONTROLE
  // =====================================================================
  let filtroBusca = '';
  let filtroSituacao = 'todas'; // todas | pendentes | concluidas
  let filtroExecutor = '';
  let tbody, contador, estadoSalvar, painelConfig;

  function marcarSalvando(texto, erro) {
    if (!estadoSalvar) return;
    estadoSalvar.textContent = texto;
    estadoSalvar.className = 'estado-salvar' + (erro ? ' erro' : '');
    if (!erro && texto === 'Salvo') {
      setTimeout(() => { if (estadoSalvar.textContent === 'Salvo') estadoSalvar.textContent = ''; }, 2000);
    }
  }

  async function salvarCampo(empresa, campo, valor) {
    const anterior = empresa[campo];
    empresa[campo] = valor === '' ? null : valor;
    marcarSalvando('Salvando...');
    try {
      await api('PATCH', 'api/decimo.php?id=' + encodeURIComponent(empresa.id), { campo, valor });
      marcarSalvando('Salvo');
      return true;
    } catch (e) {
      empresa[campo] = anterior;
      marcarSalvando('Não foi possível salvar: ' + e.message, true);
      return false;
    }
  }

  function empresaCompleta(e) {
    return !e.pro_labore && ETAPAS.every(({ k }) => e[k + '_status'] !== PENDENTE);
  }

  function passaFiltro(e) {
    if (!noFiltroEquipe(e)) return false;
    if (filtroBusca) {
      const alvo = (e.nome + ' ' + (e.cnpj || '') + ' ' + (e.codigo || '')).toLowerCase();
      if (!alvo.includes(filtroBusca)) return false;
    }
    if (filtroSituacao === 'prolabore' && !e.pro_labore) return false;
    if (filtroSituacao === 'pendentes' && (e.pro_labore || empresaCompleta(e))) return false;
    if (filtroSituacao === 'concluidas' && !empresaCompleta(e)) return false;
    if (filtroExecutor) {
      const alvo = filtroExecutor === '__sem__'
        ? ETAPAS.some(({ k }) => !e[k + '_executor_id'] && e[k + '_status'] !== NA)
        : ETAPAS.some(({ k }) => e[k + '_executor_id'] === filtroExecutor);
      if (!alvo) return false;
    }
    return true;
  }

  function atualizarContador() {
    const concl = empresas.filter(empresaCompleta).length;
    const visiveis = empresas.filter(passaFiltro).length;
    const fora = empresas.filter((e) => e.pro_labore).length;
    contador.textContent = visiveis + ' de ' + empresas.length + ' empresas na tela · ' + concl + ' com as três etapas resolvidas'
      + (fora ? ' · ' + fora + ' em S.M/Pró-labore (fora do cálculo)' : '');
  }

  function opcoesExecutor(select, atualId) {
    select.append(el('option', { value: '', text: '— executor —' }));
    executores.forEach((x) => {
      if (x.ativo || x.id === atualId) {
        select.append(el('option', { value: x.id, text: x.nome + (x.ativo ? '' : ' (inativo)') }));
      }
    });
    select.value = atualId || '';
  }

  function celulaEtapa(empresa, k, tr) {
    const status = empresa[k + '_status'];
    const td = el('td', { className: 'etapa-cel' + (status === NA ? ' na' : '') });

    const caixa = el('input', { type: 'checkbox', className: 'etapa-check', 'aria-label': 'Concluído' });
    caixa.checked = status === FEITO;
    caixa.disabled = status === NA || !!empresa.pro_labore;

    const btnNa = el('button', { type: 'button', className: 'btn-na' + (status === NA ? ' ativo' : ''), title: 'Não se aplica', text: 'N/A' });

    const select = el('select', { className: 'etapa-select', 'aria-label': 'Executor' });
    opcoesExecutor(select, empresa[k + '_executor_id']);
    select.disabled = status === NA || !!empresa.pro_labore;
    btnNa.disabled = !!empresa.pro_labore;

    function redesenharEstado() {
      const s = empresa[k + '_status'];
      td.className = 'etapa-cel' + (s === NA ? ' na' : '');
      caixa.checked = s === FEITO;
      caixa.disabled = s === NA || !!empresa.pro_labore;
      select.disabled = s === NA || !!empresa.pro_labore;
      btnNa.className = 'btn-na' + (s === NA ? ' ativo' : '');
      tr.className = classeLinha(empresa);
    }

    caixa.addEventListener('change', async () => {
      const novo = caixa.checked ? FEITO : PENDENTE;
      const ok = await salvarCampo(empresa, k + '_status', novo);
      redesenharEstado();
      if (ok) atualizarContador();
    });
    btnNa.addEventListener('click', async () => {
      const novo = empresa[k + '_status'] === NA ? PENDENTE : NA;
      const ok = await salvarCampo(empresa, k + '_status', novo);
      redesenharEstado();
      if (ok) atualizarContador();
    });
    select.addEventListener('change', async () => {
      const ok = await salvarCampo(empresa, k + '_executor_id', select.value);
      if (!ok) select.value = empresa[k + '_executor_id'] || '';
    });

    td.append(el('div', { className: 'etapa-topo' }, [caixa, btnNa]), select);
    return td;
  }

  function classeLinha(e) {
    return e.pro_labore ? 'prolabore' : (empresaCompleta(e) ? 'completa' : '');
  }

  function linhaEmpresa(e) {
    const tr = el('tr', { className: classeLinha(e) });
    const nomeTd = el('td', { className: 'col-empresa' }, [
      el('div', { className: 'empresa-nome', text: e.nome }),
      el('div', { className: 'texto-fraco', text: [e.codigo, e.cnpj].filter(Boolean).join(' · ') }),
      e.equipe ? el('span', { className: 'tag-equipe', text: e.equipe }) : null,
    ]);
    const caixaPl = el('input', { type: 'checkbox', 'aria-label': 'S.M/Pró-labore' });
    caixaPl.checked = !!e.pro_labore;
    caixaPl.addEventListener('change', async () => {
      const ok = await salvarCampo(e, 'pro_labore', caixaPl.checked ? 1 : 0);
      if (!ok) { caixaPl.checked = !!e.pro_labore; return; }
      const nova = linhaEmpresa(e);
      if (passaFiltro(e)) tr.replaceWith(nova); else tr.remove();
      atualizarContador();
    });
    nomeTd.append(el('label', { className: 'pl-toggle', title: 'Marque se a empresa tem apenas S.M/Pró-labore: ela sai de todos os cálculos' }, [caixaPl, el('span', { text: 'S.M/Pró-labore' })]));
    if (ehGestor) {
      nomeTd.append(el('button', {
        type: 'button', className: 'btn-remover', title: 'Remover empresa', text: '×',
        onclick: async () => {
          if (!window.confirm('Remover "' + e.nome + '" do controle? Isso apaga as marcações dela.')) return;
          try {
            await api('POST', 'api/decimo.php', { acao: 'remover_empresa', id: e.id });
            empresas = empresas.filter((x) => x.id !== e.id);
            renderLinhas();
          } catch (err) { marcarSalvando(err.message, true); }
        },
      }));
    }
    tr.append(nomeTd);
    ETAPAS.forEach(({ k }) => tr.append(celulaEtapa(e, k, tr)));

    const obs = el('textarea', { className: 'obs', rows: 2, placeholder: 'Observação', 'aria-label': 'Observação' });
    obs.value = e.observacao || '';
    obs.addEventListener('blur', async () => {
      if ((obs.value.trim() || null) === (e.observacao || null)) return;
      const ok = await salvarCampo(e, 'observacao', obs.value);
      if (!ok) obs.value = e.observacao || '';
    });
    tr.append(el('td', { className: 'col-obs' }, [obs]));
    return tr;
  }

  function renderLinhas() {
    tbody.replaceChildren();
    const visiveis = empresas.filter(passaFiltro);
    if (visiveis.length === 0) {
      tbody.append(el('tr', {}, [el('td', { colspan: 5, className: 'texto-fraco', style: 'padding:24px; text-align:center;', text: empresas.length ? 'Nenhuma empresa com esses filtros.' : 'Nenhuma empresa cadastrada ainda.' })]));
    } else {
      visiveis.forEach((e) => tbody.append(linhaEmpresa(e)));
    }
    atualizarContador();
  }

  function renderBarraFiltros() {
    const busca = el('input', { type: 'search', placeholder: 'Buscar empresa, CNPJ ou código', className: 'filtro-busca' });
    busca.addEventListener('input', () => { filtroBusca = busca.value.trim().toLowerCase(); renderLinhas(); });

    const sit = el('select', { className: 'filtro-select' }, [
      el('option', { value: 'todas', text: 'Todas as empresas' }),
      el('option', { value: 'pendentes', text: 'Só com pendências' }),
      el('option', { value: 'concluidas', text: 'Só concluídas' }),
      el('option', { value: 'prolabore', text: 'Só S.M/Pró-labore' }),
    ]);
    sit.addEventListener('change', () => { filtroSituacao = sit.value; renderLinhas(); });

    const eq = el('select', { className: 'filtro-select', 'aria-label': 'Filtrar por time' });
    opcoesEquipe(eq);
    eq.addEventListener('change', () => { filtroEquipe = eq.value; renderLinhas(); });

    const ex = el('select', { className: 'filtro-select' }, [el('option', { value: '', text: 'Todos os executores' }), el('option', { value: '__sem__', text: 'Com etapa sem executor' })]);
    executores.forEach((x) => ex.append(el('option', { value: x.id, text: x.nome })));
    ex.addEventListener('change', () => { filtroExecutor = ex.value; renderLinhas(); });

    const recarregar = el('button', {
      type: 'button', className: 'btn btn-ghost btn-sm', text: 'Recarregar dados',
      onclick: async () => { await carregar(); renderControle(); },
    });
    return el('div', { className: 'barra-filtros' }, [busca, ...(listaEquipes().length ? [eq] : []), sit, ex, recarregar]);
  }

  // ----- painel do gestor: executores e importação -----
  function msgConfig(texto, erro) {
    const alvo = painelConfig.querySelector('.config-msg');
    alvo.textContent = texto;
    alvo.className = 'config-msg' + (erro ? ' erro-msg' : ' texto-fraco');
  }

  function renderPainelGestor() {
    const det = el('details', { className: 'card config-decimo' });
    det.append(el('summary', { text: 'Configurar (gestor): executores e importação de empresas' }));
    painelConfig = det;

    // Executores
    const lista = el('div', { className: 'chips-executores' });
    function desenharExecutores() {
      lista.replaceChildren();
      if (executores.length === 0) lista.append(el('span', { className: 'texto-fraco', text: 'Nenhum executor cadastrado.' }));
      executores.forEach((x) => {
        lista.append(el('span', { className: 'chip' + (x.ativo ? '' : ' inativo') }, [
          el('span', { text: x.nome }),
          el('button', {
            type: 'button', className: 'chip-btn', text: x.ativo ? 'desativar' : 'reativar',
            onclick: async () => {
              try {
                await api('POST', 'api/decimo.php', { acao: 'executor_ativo', id: x.id, ativo: x.ativo ? 0 : 1 });
                x.ativo = x.ativo ? 0 : 1;
                desenharExecutores();
                renderControle(true);
              } catch (err) { msgConfig(err.message, true); }
            },
          }),
        ]));
      });
    }
    desenharExecutores();

    const txtExec = el('textarea', { rows: 4, placeholder: 'Um nome por linha', className: 'config-texto' });
    const btnExec = el('button', {
      type: 'button', className: 'btn btn-outline btn-sm', text: 'Adicionar executores',
      onclick: async () => {
        try {
          const r = await api('POST', 'api/decimo.php', { acao: 'importar_executores', texto: txtExec.value });
          await carregar();
          txtExec.value = '';
          msgConfig(r.novos + ' executor(es) adicionado(s)' + (r.ignorados ? ', ' + r.ignorados + ' já existia(m)' : '') + '.');
          renderControle(true);
        } catch (err) { msgConfig(err.message, true); }
      },
    });

    // Importação de empresas
    const txtEmp = el('textarea', {
      rows: 5, className: 'config-texto',
      placeholder: 'Cole aqui direto do Excel (colunas: código, nome, CNPJ, time) ou só os nomes, um por linha',
    });
    const arquivo = el('input', { type: 'file', accept: '.xlsx,.csv,.txt' });
    const btnEmp = el('button', {
      type: 'button', className: 'btn btn-outline btn-sm', text: 'Importar empresas',
      onclick: async () => {
        try {
          let r;
          if (arquivo.files.length) {
            const fd = new FormData();
            fd.append('arquivo', arquivo.files[0]);
            r = await api('POST', 'api/decimo.php', fd);
          } else if (txtEmp.value.trim()) {
            r = await api('POST', 'api/decimo.php', { acao: 'importar_empresas', texto: txtEmp.value });
          } else {
            msgConfig('Cole a lista ou escolha um arquivo.', true);
            return;
          }
          await carregar();
          txtEmp.value = '';
          arquivo.value = '';
          msgConfig(r.novas + ' empresa(s) importada(s)' + (r.atualizadas ? ', ' + r.atualizadas + ' com time atualizado' : '') + (r.ignoradas ? ', ' + r.ignoradas + ' já existia(m) e não mudou(aram)' : '') + '.');
          renderControle(true);
        } catch (err) { msgConfig(err.message, true); }
      },
    });

    det.append(
      el('div', { className: 'config-bloco' }, [
        el('div', { className: 'secao-titulo', text: 'Executores' }),
        lista, txtExec, btnExec,
      ]),
      el('div', { className: 'config-bloco' }, [
        el('div', { className: 'secao-titulo', text: 'Importar empresas' }),
        el('p', { className: 'texto-fraco', text: 'Aceita colar do Excel ou enviar .xlsx/.csv, com as colunas código, nome, CNPJ e time (nessa ordem, ou com cabeçalho). Empresas que já existem não duplicam nem perdem marcações; só o time é atualizado.' }),
        txtEmp,
        el('div', { className: 'config-linha' }, [arquivo, btnEmp]),
      ]),
      el('p', { className: 'config-msg texto-fraco' }),
    );
    return det;
  }

  let abrirConfig = false;
  function renderControle(manterConfig) {
    if (manterConfig && painelConfig) abrirConfig = painelConfig.open;
    raiz.replaceChildren();
    if (ehGestor) {
      const p = renderPainelGestor();
      if (manterConfig && abrirConfig) p.open = true;
      raiz.append(p);
    }
    raiz.append(renderBarraFiltros());

    contador = el('span', { className: 'texto-fraco' });
    estadoSalvar = el('span', { className: 'estado-salvar' });
    raiz.append(el('div', { className: 'linha-contador' }, [contador, estadoSalvar]));

    tbody = el('tbody');
    const cabecalho = el('tr', {}, [
      el('th', { className: 'col-empresa', text: 'Empresa' }),
      ...ETAPAS.map((e) => el('th', { text: e.nome })),
      el('th', { className: 'col-obs', text: 'Observação' }),
    ]);
    raiz.append(el('div', { className: 'tabela-rolagem' }, [
      el('table', { className: 'tabela-decimo' }, [el('thead', {}, [cabecalho]), tbody]),
    ]));
    renderLinhas();
  }

  // ---------- início ----------
  async function iniciar() {
    try {
      await carregar();
    } catch (e) {
      raiz.replaceChildren(el('p', { className: 'erro-msg', text: 'Não foi possível carregar os dados: ' + e.message }));
      return;
    }
    if (modo === 'dashboard') {
      renderDashboard();
      setInterval(async () => {
        try { await carregar(); renderDashboard(); } catch (e) { /* mantém a tela atual */ }
      }, 30000);
    } else {
      renderControle();
    }
  }

  iniciar();
})();
