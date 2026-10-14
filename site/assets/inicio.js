(function () {
  const cfg = window.MECANISMO_DP;
  const raiz = document.getElementById('app-inicio');

  const DIAS_SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
  const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

  function iso(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function daISO(s) { return new Date(s.slice(0, 10) + 'T00:00:00'); }
  function somarDias(s, n) { const d = daISO(s); d.setDate(d.getDate() + n); return iso(d); }
  function dm(s) { const [a, m, d] = s.slice(0, 10).split('-'); return d + '/' + m; }
  function dmy(s) { const [a, m, d] = s.slice(0, 10).split('-'); return d + '/' + m + '/' + a; }
  function diffDias(a, b) { return Math.round((daISO(a) - daISO(b)) / 86400000); }

  const hoje = iso(new Date());
  const params = new URLSearchParams(location.search);
  let dia = /^\d{4}-\d{2}-\d{2}$/.test(params.get('dia') || '') ? params.get('dia') : hoje;
  let dados = { executadas: [], a_executar: [], grandes: [] };

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
    if (corpo !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(corpo);
    }
    const resp = await fetch(url, opts);
    let d = {};
    try { d = await resp.json(); } catch (e) { /* sem corpo */ }
    if (!resp.ok) throw new Error(d.erro || 'Erro inesperado (' + resp.status + ').');
    return d;
  }

  let msgEl = null;
  function aviso(texto) {
    if (!msgEl) return;
    msgEl.textContent = texto || '';
  }

  async function carregar() {
    dados = await api('GET', 'api/inicio.php?dia=' + dia + '&hoje=' + hoje);
    render();
  }

  async function alternar(item) {
    aviso('');
    try {
      await api('PATCH', 'api/inicio.php', {
        origem: item.origem, id: item.id, hoje,
        status: item.status === 'CONCLUIDA' ? 'PENDENTE' : 'CONCLUIDA',
      });
      await carregar();
    } catch (e) { aviso(e.message); }
  }

  async function excluir(item) {
    if (!window.confirm('Excluir "' + item.titulo + '"?')) return;
    aviso('');
    try {
      await api('DELETE', 'api/inicio.php?id=' + encodeURIComponent(item.id));
      await carregar();
    } catch (e) { aviso(e.message); }
  }

  function iconeCheck() {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '16');
    svg.setAttribute('height', '16');
    const p = document.createElementNS(ns, 'path');
    p.setAttribute('d', 'M5 12.5l4.5 4.5L19 7.5');
    p.setAttribute('fill', 'none');
    p.setAttribute('stroke', 'currentColor');
    p.setAttribute('stroke-width', '3');
    p.setAttribute('stroke-linecap', 'round');
    p.setAttribute('stroke-linejoin', 'round');
    svg.append(p);
    return svg;
  }

  function prazoInfo(item) {
    const f = diffDias(item.prazo, hoje);
    if (f < 0) return { texto: 'Prazo ' + dm(item.prazo) + ' · atrasada há ' + (-f) + (f === -1 ? ' dia' : ' dias'), classe: 'atrasada' };
    if (f === 0) return { texto: 'Prazo ' + dm(item.prazo) + ' · vence hoje', classe: 'hoje' };
    return { texto: 'Prazo ' + dm(item.prazo) + ' · faltam ' + f + (f === 1 ? ' dia' : ' dias'), classe: '' };
  }

  function linha(item, grupo) {
    const feito = item.status === 'CONCLUIDA';
    const btn = el('button', {
      type: 'button', className: 'check-btn' + (feito ? ' feito' : ''),
      'aria-label': feito ? 'Desfazer conclusão' : 'Marcar como concluída',
      title: feito ? 'Desfazer' : 'Concluir',
      onclick: () => alternar(item),
    }, [iconeCheck()]);

    const sub = [];
    if (grupo === 2 && item.data && item.data.slice(0, 10) < dia) {
      sub.push(el('span', { className: 'tag-acumulo', text: 'do dia ' + dm(item.data) }));
    }
    if (item.origem === 'demanda') sub.push(el('span', { className: 'tag-origem', text: 'Calendário' }));
    if (item.tipo === 'GRANDE' && grupo === 1) sub.push(el('span', { className: 'tag-origem', text: 'Grande pendência' }));
    if (grupo === 3) {
      const info = prazoInfo(item);
      sub.push(el('span', { className: 'prazo ' + info.classe, text: info.texto }));
    }

    const corpo = el('div', { className: 'item-corpo' }, [
      el('div', { className: 'item-titulo', text: item.titulo }),
      sub.length ? el('div', { className: 'item-sub' }, sub) : null,
    ]);

    const linhaEl = el('div', { className: 'atividade' + (feito ? ' feita' : '') }, [btn, corpo]);
    if (item.origem === 'atividade') {
      linhaEl.append(el('button', { type: 'button', className: 'btn-remover', title: 'Excluir', text: '×', onclick: () => excluir(item) }));
    }
    return linhaEl;
  }

  function formSimples(placeholder, rotuloBotao, aoEnviar) {
    const input = el('input', { type: 'text', placeholder, maxlength: '255', className: 'add-input' });
    const enviar = async () => {
      const t = input.value.trim();
      if (!t) return;
      aviso('');
      try { await aoEnviar(t); input.value = ''; await carregar(); } catch (e) { aviso(e.message); }
    };
    input.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); enviar(); } });
    return el('div', { className: 'add-linha' }, [input, el('button', { type: 'button', className: 'btn btn-outline btn-sm', text: rotuloBotao, onclick: enviar })]);
  }

  function formGrande() {
    const input = el('input', { type: 'text', placeholder: 'Nova demanda grande', maxlength: '255', className: 'add-input' });
    const prazo = el('input', { type: 'date', className: 'add-data', value: somarDias(hoje, 7), 'aria-label': 'Prazo' });
    const enviar = async () => {
      const t = input.value.trim();
      if (!t) return;
      aviso('');
      try {
        await api('POST', 'api/inicio.php', { titulo: t, tipo: 'GRANDE', prazo: prazo.value });
        input.value = '';
        await carregar();
      } catch (e) { aviso(e.message); }
    };
    input.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); enviar(); } });
    return el('div', { className: 'add-grande' }, [
      input,
      el('div', { className: 'add-linha' }, [
        el('label', { className: 'texto-fraco', text: 'Prazo' }), prazo,
        el('button', { type: 'button', className: 'btn btn-outline btn-sm', text: 'Adicionar', onclick: enviar }),
      ]),
    ]);
  }

  function bloco(titulo, subtitulo, qtd, filhos) {
    return el('section', { className: 'card bloco-inicio' }, [
      el('div', { className: 'bloco-topo' }, [
        el('h2', { className: 'font-display', text: titulo }),
        el('span', { className: 'contagem', text: String(qtd) }),
      ]),
      subtitulo ? el('p', { className: 'texto-fraco', text: subtitulo }) : null,
      ...filhos,
    ]);
  }

  function rotuloDia() {
    const d = daISO(dia);
    const rel = diffDias(dia, hoje);
    const nome = rel === 0 ? 'Hoje' : rel === 1 ? 'Amanhã' : rel === -1 ? 'Ontem' : null;
    const data = DIAS_SEMANA[d.getDay()] + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear();
    return { nome, data };
  }

  function navegarPara(novo) {
    dia = novo;
    history.replaceState(null, '', dia === hoje ? 'inicio.php' : 'inicio.php?dia=' + dia);
    carregar().catch((e) => aviso(e.message));
  }

  function render() {
    raiz.replaceChildren();
    const r = rotuloDia();

    const seletor = el('input', { type: 'date', value: dia, className: 'add-data', 'aria-label': 'Ir para o dia' });
    seletor.addEventListener('change', () => { if (seletor.value) navegarPara(seletor.value); });

    raiz.append(el('div', { className: 'nav-dia' }, [
      el('button', { type: 'button', className: 'btn btn-ghost btn-sm', text: '←', title: 'Dia anterior', onclick: () => navegarPara(somarDias(dia, -1)) }),
      el('div', { className: 'nav-dia-texto' }, [
        el('div', { className: 'nav-dia-nome font-display', text: r.nome || dmy(dia) }),
        el('div', { className: 'texto-fraco', text: r.data }),
      ]),
      el('button', { type: 'button', className: 'btn btn-ghost btn-sm', text: '→', title: 'Próximo dia', onclick: () => navegarPara(somarDias(dia, 1)) }),
      seletor,
      dia !== hoje ? el('button', { type: 'button', className: 'btn btn-outline btn-sm', text: 'Hoje', onclick: () => navegarPara(hoje) }) : null,
    ]));

    msgEl = el('p', { className: 'erro-msg' });
    raiz.append(msgEl);

    const vazio = (t) => el('p', { className: 'texto-fraco vazio', text: t });

    const b1 = bloco('Atividades Executadas', 'Concluídas em ' + (r.nome ? r.nome.toLowerCase() : dm(dia)) + '.', dados.executadas.length, [
      ...(dados.executadas.length ? dados.executadas.map((i) => linha(i, 1)) : [vazio('Nada concluído neste dia ainda.')]),
      formSimples('Registrar atividade já executada', 'Registrar', (t) =>
        api('POST', 'api/inicio.php', { titulo: t, tipo: 'DIA', data: dia, executada: true })),
    ]);

    const b2 = bloco('Atividades a Executar', 'Marque o check para concluir.', dados.a_executar.length, [
      ...(dados.a_executar.length ? dados.a_executar.map((i) => linha(i, 2)) : [vazio('Nenhuma atividade pendente.')]),
      formSimples('Nova atividade para ' + (r.nome ? r.nome.toLowerCase() : dm(dia)), 'Adicionar', (t) =>
        api('POST', 'api/inicio.php', { titulo: t, tipo: 'DIA', data: dia })),
    ]);

    const b3 = bloco('Grandes Pendências', 'Demandas grandes, com prazo.', dados.grandes.length, [
      ...(dados.grandes.length ? dados.grandes.map((i) => linha(i, 3)) : [vazio('Nenhuma demanda grande em aberto.')]),
      formGrande(),
    ]);

    raiz.append(el('div', { className: 'inicio-grid' }, [b1, b2, b3]));
  }

  carregar().catch((e) => { raiz.replaceChildren(el('p', { className: 'erro-msg', text: 'Não foi possível carregar: ' + e.message })); });
})();
