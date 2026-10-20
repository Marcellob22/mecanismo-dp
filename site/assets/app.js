(function () {
  const cfg = window.MECANISMO_DP;
  const duplas = cfg.duplas;
  const csrfToken = cfg.csrfToken;

  const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  const CORES_PRIORIDADE = { BAIXA: '#10b981', MEDIA: '#c9a227', ALTA: '#ef4444' };
  const DOURADO = '#c9a227';
  const CORES_PLANEJAMENTO = ['#ef4444', '#ffffff', '#a855f7', '#3b82f6', '#22c55e']; // vermelho, branco, roxo, azul, verde
  const LABEL_STATUS = { A_FAZER: 'A fazer', EM_ANDAMENTO: 'Em andamento', CONCLUIDA: 'Concluída', ATRASADA: 'Atrasada' };
  const LABEL_STATUS_PLANEJAMENTO = { ATIVO: 'Ativo', CONCLUIDO: 'Concluído' };

  const hoje = new Date();
  let ano = hoje.getFullYear();
  let mes = hoje.getMonth();
  let diaSelecionado = toISODate(hoje);
  let demandas = [], planejamentos = [], dailies = [], atividades = [];
  let corPorPlanejamento = new Map();
  let celulaFiltro = '';

  function recalcularCoresDosPlanejamentos() {
    corPorPlanejamento = new Map();
    const ordenados = [...planejamentos].sort((a, b) => (a.data_inicio < b.data_inicio ? -1 : 1) || (a.id < b.id ? -1 : 1));
    ordenados.forEach((p, i) => corPorPlanejamento.set(p.id, CORES_PLANEJAMENTO[i % CORES_PLANEJAMENTO.length]));
  }

  function toISODate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  // ---------- Feriados nacionais ----------
  const FERIADOS_FIXOS = [
    ['01-01', 'Confraternização Universal'],
    ['04-21', 'Tiradentes'],
    ['05-01', 'Dia do Trabalho'],
    ['09-07', 'Independência do Brasil'],
    ['10-12', 'Nossa Senhora Aparecida'],
    ['11-02', 'Finados'],
    ['11-15', 'Proclamação da República'],
    ['11-20', 'Dia da Consciência Negra'],
    ['12-25', 'Natal'],
  ];
  const cacheFeriados = new Map();

  function calcularPascoa(a) {
    // Algoritmo de Meeus/Jones/Butcher
    const A = a % 19, B = Math.floor(a / 100), C = a % 100;
    const D = Math.floor(B / 4), E = B % 4, F = Math.floor((B + 8) / 25);
    const G = Math.floor((B - F + 1) / 3), H = (19 * A + B - D - G + 15) % 30;
    const I = Math.floor(C / 4), K = C % 4, L = (32 + 2 * E + 2 * I - H - K) % 7;
    const M = Math.floor((A + 11 * H + 22 * L) / 451);
    const mesP = Math.floor((H + L - 7 * M + 114) / 31);
    const diaP = ((H + L - 7 * M + 114) % 31) + 1;
    return new Date(a, mesP - 1, diaP);
  }

  function somarDias(d, n) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
  }

  // Mapa 'YYYY-MM-DD' -> { nome, tipo: 'feriado' | 'facultativo' }
  function feriadosDoAno(a) {
    if (cacheFeriados.has(a)) return cacheFeriados.get(a);
    const mapa = new Map();
    for (const [md, nome] of FERIADOS_FIXOS) mapa.set(a + '-' + md, { nome, tipo: 'feriado' });
    const pascoa = calcularPascoa(a);
    mapa.set(toISODate(somarDias(pascoa, -2)), { nome: 'Sexta-feira Santa', tipo: 'feriado' });
    mapa.set(toISODate(somarDias(pascoa, -48)), { nome: 'Carnaval', tipo: 'facultativo' });
    mapa.set(toISODate(somarDias(pascoa, -47)), { nome: 'Carnaval', tipo: 'facultativo' });
    mapa.set(toISODate(somarDias(pascoa, 60)), { nome: 'Corpus Christi', tipo: 'facultativo' });
    cacheFeriados.set(a, mapa);
    return mapa;
  }

  function feriadoDaData(chave) {
    return feriadosDoAno(Number(chave.slice(0, 4))).get(chave) || null;
  }

  function nomeDupla(id) {
    const d = duplas.find((x) => x.id === id);
    return d ? d.nome : '—';
  }

  async function apiFetch(url, options = {}) {
    options.headers = options.headers || {};
    if (options.method && options.method !== 'GET') {
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-Csrf-Token'] = csrfToken;
    }
    const res = await fetch(url, options);
    const dados = await res.json().catch(() => ({}));
    return { ok: res.ok, dados };
  }

  async function carregar() {
    const { ok, dados } = await apiFetch('api/calendario.php');
    if (ok) {
      demandas = dados.demandas;
      planejamentos = dados.planejamentos;
      dailies = dados.dailies;
      atividades = dados.atividades || [];
      recalcularCoresDosPlanejamentos();
    }
    render();
  }

  function gerarGradeDoMes(ano, mes) {
    const primeiro = new Date(ano, mes, 1);
    const ultimo = new Date(ano, mes + 1, 0);
    const inicio = new Date(primeiro);
    inicio.setDate(inicio.getDate() - primeiro.getDay());
    const fim = new Date(ultimo);
    fim.setDate(fim.getDate() + (6 - ultimo.getDay()));
    const dias = [];
    const cursor = new Date(inicio);
    while (cursor <= fim) {
      dias.push(new Date(cursor));
      cursor.setDate(cursor.getDate() + 1);
    }
    return dias;
  }

  function agruparPorDia(itens, campoData) {
    const mapa = new Map();
    for (const item of itens) {
      const chave = item[campoData].slice(0, 10);
      if (!mapa.has(chave)) mapa.set(chave, []);
      mapa.get(chave).push(item);
    }
    return mapa;
  }

  function agruparPlanejamentosPorDia(itens) {
    const mapa = new Map();
    for (const p of itens) {
      const ini = new Date(p.data_inicio.slice(0, 10) + 'T00:00:00');
      const fim = new Date(p.data_fim.slice(0, 10) + 'T00:00:00');
      const cursor = new Date(ini);
      while (cursor <= fim) {
        const chave = toISODate(cursor);
        if (!mapa.has(chave)) mapa.set(chave, []);
        mapa.get(chave).push(p);
        cursor.setDate(cursor.getDate() + 1);
      }
    }
    return mapa;
  }

  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (k === 'class') node.className = v;
      else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else if (k === 'html') node.innerHTML = v;
      else node.setAttribute(k, v);
    }
    for (const child of children || []) {
      if (child) node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    }
    return node;
  }

  function render() {
    const raiz = document.getElementById('app-calendario');
    raiz.innerHTML = '';

    const demandasFiltradas = celulaFiltro ? demandas.filter((d) => d.dupla_id === celulaFiltro) : demandas;
    const planejamentosFiltrados = celulaFiltro ? planejamentos.filter((p) => p.dupla_id === celulaFiltro) : planejamentos;
    const dailiesFiltradas = celulaFiltro ? dailies.filter((d) => d.dupla_id === celulaFiltro) : dailies;
    const duplasFiltradas = celulaFiltro ? duplas.filter((d) => d.id === celulaFiltro) : duplas;

    const demandasPorDia = agruparPorDia(demandasFiltradas, 'data');
    const planejamentosPorDia = agruparPlanejamentosPorDia(planejamentosFiltrados);
    const dailiesPorDia = agruparPorDia(dailiesFiltradas, 'data');
    const atividadesPorDia = new Map();
    for (const at of atividades) {
      const chaveAt = ((at.tipo === 'GRANDE' ? at.prazo : at.data) || '').slice(0, 10);
      if (!chaveAt) continue;
      if (!atividadesPorDia.has(chaveAt)) atividadesPorDia.set(chaveAt, []);
      atividadesPorDia.get(chaveAt).push(at);
    }

    const hojeISO = toISODate(hoje);
    const registradasHoje = new Set((dailiesPorDia.get(hojeISO) || []).map((d) => d.dupla_id));
    const duplasSemDaily = duplasFiltradas.filter((d) => !registradasHoje.has(d.id));

    if (duplasSemDaily.length > 0) {
      const alerta = el('div', { class: 'alerta-daily' }, [
        el('span', { style: 'font-weight:600;' }, ['Daily pendente hoje:']),
        ...duplasSemDaily.map((d) => el('span', { class: 'pill' }, [d.nome])),
      ]);
      raiz.appendChild(alerta);
    }

    // Filtro de célula (só aparece quando há mais de uma célula visível)
    if (duplas.length > 1) {
      const selectFiltro = el('select', {
        onchange: (e) => { celulaFiltro = e.target.value; render(); },
      }, [
        el('option', { value: '' }, ['Todas as células']),
        ...duplas.map((d) => {
          const opt = el('option', { value: d.id }, [d.nome]);
          if (d.id === celulaFiltro) opt.setAttribute('selected', 'selected');
          return opt;
        }),
      ]);
      raiz.appendChild(el('div', { class: 'campo', style: 'max-width:280px; margin-bottom:16px;' }, [
        el('label', {}, ['Visualizando']),
        selectFiltro,
      ]));
    }

    // Cabeçalho
    const header = el('div', { class: 'cal-header' }, [
      el('div', { class: 'cal-nav' }, [
        el('button', { onclick: () => mudarMes(-1) }, ['←']),
        el('div', { class: 'mes-ano font-display' }, [MESES[mes] + ' ', el('span', { class: 'ano' }, [String(ano)])]),
        el('button', { onclick: () => mudarMes(1) }, ['→']),
      ]),
      el('div', { class: 'cal-acoes' }, [
        el('button', { class: 'btn btn-outline btn-sm', onclick: () => abrirModalDemanda() }, ['+ Nova Demanda']),
        el('button', { class: 'btn btn-ghost btn-sm', onclick: () => abrirModalPlanejamento() }, ['+ Novo Planejamento']),
        el('button', { class: 'btn btn-ghost btn-sm', onclick: () => abrirModalDaily() }, ['Registrar Daily']),
      ]),
    ]);
    raiz.appendChild(header);

    // Grade
    const diasSemana = el('div', { class: 'cal-dias-semana' }, DIAS_SEMANA.map((d) => el('div', {}, [d])));
    const dias = gerarGradeDoMes(ano, mes);
    const semanas = el('div', { class: 'cal-semanas' });

    for (const dia of dias) {
      const chave = toISODate(dia);
      const doMesAtual = dia.getMonth() === mes;
      const itensDemanda = demandasPorDia.get(chave) || [];
      const itensPlanejamento = planejamentosPorDia.get(chave) || [];
      const itensDaily = dailiesPorDia.get(chave) || [];
      const itensAtividade = atividadesPorDia.get(chave) || [];
      const ehHoje = chave === hojeISO;

      const marcadores = el('div', { class: 'marcadores' }, [
        ...itensDemanda.slice(0, 6).map((d) => el('span', {
          class: 'bolinha' + (d.status === 'CONCLUIDA' ? ' concluida' : ''),
          title: d.abreviacao + ' — ' + LABEL_STATUS[d.status],
          style: 'background:' + DOURADO + ';',
        }, [])),
        itensDaily.length > 0 ? el('span', { class: 'bolinha', title: 'Daily realizada', style: 'background:#a78bfa;' }, []) : null,
        ...itensAtividade.slice(0, 4).map((at) => el('span', {
          class: 'bolinha ' + (at.tipo === 'GRANDE' ? 'atividade-grande' : 'atividade-dia') + (at.status === 'CONCLUIDA' ? ' concluida' : ''),
          title: (at.tipo === 'GRANDE' ? 'Grande pendência: ' : 'Atividade: ') + at.titulo,
        }, [])),
      ]);

      const faixas = el('div', { class: 'faixas-planejamento' }, itensPlanejamento.slice(0, 3).map((p) => {
        const inicioVisual = chave === p.data_inicio.slice(0, 10) || dia.getDay() === 0;
        const fimVisual = chave === p.data_fim.slice(0, 10) || dia.getDay() === 6;
        const classes = ['faixa-planejamento'];
        if (inicioVisual) classes.push('inicio');
        if (fimVisual) classes.push('fim');
        if (p.status === 'CONCLUIDO') classes.push('concluido');
        return el('span', {
          class: classes.join(' '),
          title: p.titulo + (p.status === 'CONCLUIDO' ? ' (concluído)' : ''),
          style: 'background:' + corPorPlanejamento.get(p.id) + ';',
        }, []);
      }));

      const numero = el('span', { class: 'numero' }, [String(dia.getDate())]);

      const feriado = feriadoDaData(chave);
      const topoFilhos = [numero];
      if (feriado) {
        topoFilhos.push(el('span', {
          class: 'rotulo-feriado',
          title: (feriado.tipo === 'feriado' ? 'Feriado nacional: ' : 'Ponto facultativo: ') + feriado.nome,
        }, [feriado.nome]));
      }

      const celula = el('button', {
        class: 'cal-dia' + (doMesAtual ? '' : ' fora-do-mes') + (chave === diaSelecionado ? ' selecionado' : '') + (ehHoje ? ' hoje' : '')
          + (feriado ? ' ' + feriado.tipo : ''),
        onclick: () => { diaSelecionado = chave; render(); },
      }, [
        el('div', { class: 'topo-dia' }, topoFilhos),
        faixas,
        marcadores,
      ]);
      semanas.appendChild(celula);
    }

    const grid = el('div', { class: 'cal-grid' }, [diasSemana, semanas]);
    raiz.appendChild(grid);

    // Painel do dia selecionado
    raiz.appendChild(renderPainelDoDia(
      diaSelecionado,
      demandasPorDia.get(diaSelecionado) || [],
      planejamentosPorDia.get(diaSelecionado) || [],
      dailiesPorDia.get(diaSelecionado) || [],
      atividadesPorDia.get(diaSelecionado) || []
    ));
  }

  function renderPainelDoDia(dia, itensDemanda, itensPlanejamento, itensDaily, itensAtividade) {
    const [a, m, d] = dia.split('-');
    const painel = el('div', { class: 'card painel-dia' }, [
      el('p', { class: 'titulo-dia font-display' }, [d + '/' + m + '/' + a]),
    ]);

    const feriadoDia = feriadoDaData(dia);
    if (feriadoDia) {
      painel.appendChild(el('div', { class: 'banner-feriado ' + feriadoDia.tipo }, [
        el('span', { class: 'banner-feriado-tag' }, [feriadoDia.tipo === 'feriado' ? 'Feriado nacional' : 'Ponto facultativo']),
        el('span', { class: 'banner-feriado-nome' }, [feriadoDia.nome]),
      ]));
    }

    if (itensDemanda.length === 0 && itensPlanejamento.length === 0 && itensDaily.length === 0 && itensAtividade.length === 0) {
      painel.appendChild(el('p', { class: 'texto-fraco' }, ['Nenhum item para este dia.']));
      return painel;
    }

    if (itensDemanda.length > 0) {
      painel.appendChild(el('p', { class: 'secao-titulo' }, ['Demandas']));
      for (const dm of itensDemanda) {
        const select = el('select', {
          onchange: (e) => atualizarStatus(dm.id, e.target.value),
        }, Object.entries(LABEL_STATUS).map(([valor, label]) => {
          const opt = el('option', { value: valor }, [label]);
          if (valor === dm.status) opt.setAttribute('selected', 'selected');
          return opt;
        }));
        painel.appendChild(el('div', { class: 'item-lista' }, [
          el('div', { class: 'esquerda' }, [
            el('span', { class: 'bolinha', style: 'background:' + CORES_PRIORIDADE[dm.prioridade] + ';' }, []),
            el('span', {}, [dm.abreviacao]),
            el('span', { class: 'dupla-nome' }, [nomeDupla(dm.dupla_id)]),
          ]),
          select,
        ]));
      }
    }

    if (itensAtividade.length > 0) {
      painel.appendChild(el('p', { class: 'secao-titulo mt-3' }, ['Minhas atividades']));
      for (const at of itensAtividade) {
        const feita = at.status === 'CONCLUIDA';
        painel.appendChild(el('div', { class: 'item-lista' + (feita ? ' feita-cal' : '') }, [
          el('div', { class: 'esquerda' }, [
            el('span', { class: 'bolinha ' + (at.tipo === 'GRANDE' ? 'atividade-grande' : 'atividade-dia') }, []),
            el('span', { class: 'titulo-cal' }, [at.titulo]),
            el('span', { class: 'dupla-nome' }, [at.tipo === 'GRANDE' ? 'Grande pendência' : 'Atividade do dia']),
          ]),
          el('button', {
            class: 'btn btn-outline btn-sm',
            onclick: () => alternarAtividade(at),
          }, [feita ? 'Desfazer' : 'Concluir']),
        ]));
      }
    }

    if (itensPlanejamento.length > 0) {
      painel.appendChild(el('p', { class: 'secao-titulo mt-3' }, ['Planejamentos']));
      for (const p of itensPlanejamento) {
        const select = el('select', {
          onchange: (e) => atualizarStatusPlanejamento(p.id, e.target.value),
        }, Object.entries(LABEL_STATUS_PLANEJAMENTO).map(([valor, label]) => {
          const opt = el('option', { value: valor }, [label]);
          if (valor === p.status) opt.setAttribute('selected', 'selected');
          return opt;
        }));
        painel.appendChild(el('div', { class: 'item-lista' + (p.status === 'CONCLUIDO' ? ' concluido' : '') }, [
          el('div', { class: 'esquerda' }, [
            el('span', { class: 'bolinha', style: 'background:' + corPorPlanejamento.get(p.id) + ';' }, []),
            el('span', {}, [p.titulo]),
            el('span', { class: 'dupla-nome' }, [nomeDupla(p.dupla_id)]),
          ]),
          select,
        ]));
      }
    }

    if (itensDaily.length > 0) {
      painel.appendChild(el('p', { class: 'secao-titulo mt-3' }, ['Dailies registradas']));
      for (const dl of itensDaily) {
        painel.appendChild(el('div', { class: 'daily-item' }, [
          el('p', { class: 'dupla' }, [nomeDupla(dl.dupla_id)]),
          el('p', {}, [el('span', { class: 'rotulo' }, ['Planejamento: ']), dl.planejamento_do_dia]),
          el('p', {}, [el('span', { class: 'rotulo' }, ['Impedimentos: ']), dl.impedimentos]),
          el('p', {}, [el('span', { class: 'rotulo' }, ['Como ajudar: ']), dl.como_ajudar]),
        ]));
      }
    }

    return painel;
  }

  async function atualizarStatus(id, status) {
    await apiFetch('api/demandas.php?id=' + encodeURIComponent(id), {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
    carregar();
  }

  async function alternarAtividade(at) {
    await apiFetch('api/inicio.php', {
      method: 'PATCH',
      body: JSON.stringify({
        origem: 'atividade', id: at.id, hoje: toISODate(new Date()),
        status: at.status === 'CONCLUIDA' ? 'PENDENTE' : 'CONCLUIDA',
      }),
    });
    carregar();
  }

  async function atualizarStatusPlanejamento(id, status) {
    await apiFetch('api/planejamentos.php?id=' + encodeURIComponent(id), {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
    carregar();
  }

  function mudarMes(delta) {
    const novo = new Date(ano, mes + delta, 1);
    ano = novo.getFullYear();
    mes = novo.getMonth();
    render();
  }

  // ---------- Modais ----------
  function montarOverlay(conteudoEl) {
    const overlay = el('div', { class: 'modal-overlay aberto', onclick: (e) => { if (e.target === overlay) fecharModal(); } }, [
      el('div', { class: 'modal', onclick: (e) => e.stopPropagation() }, [conteudoEl]),
    ]);
    document.body.appendChild(overlay);
    return overlay;
  }

  function fecharModal() {
    document.querySelectorAll('.modal-overlay').forEach((n) => n.remove());
  }

  function campoModal(label, inputEl) {
    return el('div', { class: 'campo' }, [el('label', {}, [label]), inputEl]);
  }

  function selectDuplas(valorInicial) {
    return el('select', {}, duplas.map((d) => {
      const opt = el('option', { value: d.id }, [d.nome]);
      if (d.id === valorInicial) opt.setAttribute('selected', 'selected');
      return opt;
    }));
  }

  function abrirModalDemanda() {
    const inputAbrev = el('input', { type: 'text', placeholder: 'Ex: eSocial folha' }, []);
    const inputData = el('input', { type: 'date', value: diaSelecionado }, []);
    const selectPrioridade = el('select', {}, [
      el('option', { value: 'BAIXA' }, ['Baixa']),
      el('option', { value: 'MEDIA', selected: 'selected' }, ['Média']),
      el('option', { value: 'ALTA' }, ['Alta']),
    ]);
    const selectDupla = selectDuplas(duplas[0] && duplas[0].id);
    const inputObs = el('textarea', { rows: '3' }, []);
    const erroEl = el('p', { class: 'erro-msg', style: 'display:none;' }, []);

    const form = el('div', {}, [
      el('h3', { class: 'font-display' }, ['Nova Demanda']),
      campoModal('Abreviação', inputAbrev),
      campoModal('Data', inputData),
      campoModal('Prioridade', selectPrioridade),
      campoModal('Célula', selectDupla),
      campoModal('Observações', inputObs),
      erroEl,
      el('div', { class: 'modal-acoes' }, [
        el('button', { class: 'btn btn-ghost', onclick: fecharModal }, ['Cancelar']),
        el('button', {
          class: 'btn btn-primary',
          onclick: async () => {
            if (!inputAbrev.value || !selectDupla.value) {
              erroEl.textContent = 'Preencha a abreviação e a dupla.';
              erroEl.style.display = 'block';
              return;
            }
            const { ok, dados } = await apiFetch('api/demandas.php', {
              method: 'POST',
              body: JSON.stringify({
                abreviacao: inputAbrev.value,
                data: inputData.value,
                prioridade: selectPrioridade.value,
                duplaId: selectDupla.value,
                observacoes: inputObs.value,
              }),
            });
            if (ok) { fecharModal(); carregar(); }
            else { erroEl.textContent = dados.erro || 'Erro ao salvar.'; erroEl.style.display = 'block'; }
          },
        }, ['Salvar']),
      ]),
    ]);
    montarOverlay(form);
  }

  function abrirModalPlanejamento() {
    const inputTitulo = el('input', { type: 'text', placeholder: 'Ex: Fechamento da folha' }, []);
    const inputInicio = el('input', { type: 'date', value: diaSelecionado }, []);
    const inputFim = el('input', { type: 'date', value: diaSelecionado }, []);
    const selectDupla = selectDuplas(duplas[0] && duplas[0].id);
    const inputObs = el('textarea', { rows: '3' }, []);
    const erroEl = el('p', { class: 'erro-msg', style: 'display:none;' }, []);

    const form = el('div', {}, [
      el('h3', { class: 'font-display' }, ['Novo Planejamento']),
      campoModal('Título', inputTitulo),
      el('div', { style: 'display:grid; grid-template-columns:1fr 1fr; gap:12px;' }, [
        campoModal('Início', inputInicio),
        campoModal('Fim', inputFim),
      ]),
      campoModal('Célula', selectDupla),
      campoModal('Observações', inputObs),
      erroEl,
      el('div', { class: 'modal-acoes' }, [
        el('button', { class: 'btn btn-ghost', onclick: fecharModal }, ['Cancelar']),
        el('button', {
          class: 'btn btn-primary',
          onclick: async () => {
            if (!inputTitulo.value || !selectDupla.value) {
              erroEl.textContent = 'Preencha o título e a dupla.';
              erroEl.style.display = 'block';
              return;
            }
            const { ok, dados } = await apiFetch('api/planejamentos.php', {
              method: 'POST',
              body: JSON.stringify({
                titulo: inputTitulo.value,
                dataInicio: inputInicio.value,
                dataFim: inputFim.value,
                duplaId: selectDupla.value,
                observacoes: inputObs.value,
              }),
            });
            if (ok) { fecharModal(); carregar(); }
            else { erroEl.textContent = dados.erro || 'Erro ao salvar.'; erroEl.style.display = 'block'; }
          },
        }, ['Salvar']),
      ]),
    ]);
    montarOverlay(form);
  }

  function abrirModalDaily() {
    const selectDupla = selectDuplas(duplas[0] && duplas[0].id);
    const inputPlan = el('textarea', { rows: '2' }, []);
    const inputImped = el('textarea', { rows: '2' }, []);
    const inputAjuda = el('textarea', { rows: '2' }, []);
    const erroEl = el('p', { class: 'erro-msg', style: 'display:none;' }, []);

    const form = el('div', {}, [
      el('h3', { class: 'font-display' }, ['Daily']),
      campoModal('Célula', selectDupla),
      campoModal('O que vamos fazer hoje?', inputPlan),
      campoModal('Algum impedimento?', inputImped),
      campoModal('Como podemos ajudar?', inputAjuda),
      erroEl,
      el('div', { class: 'modal-acoes' }, [
        el('button', { class: 'btn btn-ghost', onclick: fecharModal }, ['Cancelar']),
        el('button', {
          class: 'btn btn-primary',
          onclick: async () => {
            if (!selectDupla.value || !inputPlan.value || !inputImped.value || !inputAjuda.value) {
              erroEl.textContent = 'Responda as três perguntas da daily.';
              erroEl.style.display = 'block';
              return;
            }
            const { ok, dados } = await apiFetch('api/dailies.php', {
              method: 'POST',
              body: JSON.stringify({
                duplaId: selectDupla.value,
                planejamentoDoDia: inputPlan.value,
                impedimentos: inputImped.value,
                comoAjudar: inputAjuda.value,
              }),
            });
            if (ok) { fecharModal(); carregar(); }
            else { erroEl.textContent = dados.erro || 'Erro ao salvar.'; erroEl.style.display = 'block'; }
          },
        }, ['Registrar']),
      ]),
    ]);
    montarOverlay(form);
  }

  carregar();
})();
