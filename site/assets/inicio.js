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

  async function alternar(item, botao) {
    aviso('');
    try {
      if (botao) {
        botao.classList.add('pulsando');
        await new Promise((r) => setTimeout(r, 230));
      }
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
    if (f < 0) return { texto: 'Atrasada há ' + (-f) + (f === -1 ? ' dia' : ' dias') + ' (prazo ' + dm(item.prazo) + ')', classe: 'atrasada' };
    if (f === 0) return { texto: 'Vence hoje', classe: 'hoje' };
    return { texto: 'Faltam ' + f + (f === 1 ? ' dia' : ' dias') + ' (prazo ' + dm(item.prazo) + ')', classe: f <= 2 ? 'hoje' : '' };
  }

  function linha(item, grupo) {
    const feito = item.status === 'CONCLUIDA';
    const btn = el('button', {
      type: 'button', className: 'check-btn' + (feito ? ' feito' : ''),
      'aria-label': feito ? 'Desfazer conclusão' : 'Marcar como concluída',
      title: feito ? 'Desfazer' : 'Concluir',
      onclick: (ev) => alternar(item, ev.currentTarget),
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

    let extra = '';
    if (grupo === 3 && !feito) {
      const f = diffDias(item.prazo, hoje);
      extra = f < 0 ? ' atrasada' : f <= 2 ? ' urgente' : '';
    }
    const linhaEl = el('div', { className: 'atividade' + (feito ? ' feita' : '') + extra }, [btn, corpo]);
    if (item.origem === 'atividade') {
      linhaEl.append(el('button', { type: 'button', className: 'btn-remover', title: 'Excluir', text: '×', onclick: () => excluir(item) }));
    }
    return linhaEl;
  }

  function iconeMais() {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '18'); svg.setAttribute('height', '18');
    const p = document.createElementNS(ns, 'path');
    p.setAttribute('d', 'M12 5v14M5 12h14'); p.setAttribute('fill', 'none'); p.setAttribute('stroke', 'currentColor');
    p.setAttribute('stroke-width', '2.4'); p.setAttribute('stroke-linecap', 'round');
    svg.append(p);
    return svg;
  }

  function formSimples(placeholder, aoEnviar) {
    const input = el('input', { type: 'text', placeholder, maxlength: '255', className: 'add-input', 'aria-label': placeholder });
    const enviar = async () => {
      const t = input.value.trim();
      if (!t) { input.focus(); return; }
      aviso('');
      try { await aoEnviar(t); input.value = ''; await carregar(); } catch (e) { aviso(e.message); }
    };
    input.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); enviar(); } });
    return el('div', { className: 'add-pilula' }, [
      input,
      el('button', { type: 'button', className: 'add-mais', 'aria-label': 'Adicionar', title: 'Adicionar', onclick: enviar }, [iconeMais()]),
    ]);
  }

  function formGrande() {
    const input = el('input', { type: 'text', placeholder: 'Nova demanda grande', maxlength: '255', className: 'add-input', 'aria-label': 'Nova demanda grande' });
    const prazo = el('input', { type: 'date', className: 'add-data', value: somarDias(hoje, 7), 'aria-label': 'Prazo da demanda' });
    const enviar = async () => {
      const t = input.value.trim();
      if (!t) { input.focus(); return; }
      aviso('');
      try {
        await api('POST', 'api/inicio.php', { titulo: t, tipo: 'GRANDE', prazo: prazo.value });
        input.value = '';
        await carregar();
      } catch (e) { aviso(e.message); }
    };
    input.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); enviar(); } });
    return el('div', { className: 'add-grande' }, [
      el('div', { className: 'add-pilula' }, [
        input,
        el('button', { type: 'button', className: 'add-mais', 'aria-label': 'Adicionar', title: 'Adicionar', onclick: enviar }, [iconeMais()]),
      ]),
      el('label', { className: 'add-prazo' }, [el('span', { text: 'Prazo' }), prazo]),
    ]);
  }

  function bloco(classe, titulo, qtd, corpo, rodape) {
    return el('section', { className: 'coluna ' + classe }, [
      el('header', { className: 'coluna-topo' }, [
        el('h2', { className: 'font-display', text: titulo }),
        el('span', { className: 'contagem', text: String(qtd) }),
      ]),
      el('div', { className: 'bloco' }, [
        el('div', { className: 'bloco-lista' }, corpo),
        el('div', { className: 'bloco-rodape' }, [rodape]),
      ]),
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
    const d = daISO(dia);

    const seletor = el('input', { type: 'date', value: dia, className: 'seletor-data', 'aria-label': 'Escolher o dia' });
    seletor.addEventListener('change', () => { if (seletor.value) navegarPara(seletor.value); });

    const feitas = dados.executadas.length;
    const total = feitas + dados.a_executar.length;
    const pct = total ? Math.round((feitas / total) * 100) : 0;

    const cabecalho = el('div', { className: 'inicio-topo' }, [
      el('div', { className: 'dia-destaque' }, [
        el('div', { className: 'dia-numero font-display', text: String(d.getDate()) }),
        el('div', { className: 'dia-texto' }, [
          el('div', { className: 'dia-semana font-display', text: r.nome ? r.nome + ', ' + DIAS_SEMANA[d.getDay()] : DIAS_SEMANA[d.getDay()] }),
          el('div', { className: 'texto-fraco', text: MESES[d.getMonth()] + ' de ' + d.getFullYear() }),
          total ? el('div', { className: 'progresso-dia' }, [
            el('div', { className: 'progresso-barra' }, [el('span', { style: 'width:' + pct + '%' })]),
            el('span', { className: 'texto-fraco', text: feitas + ' de ' + total + (total === 1 ? ' atividade' : ' atividades') }),
          ]) : null,
        ]),
      ]),
      el('div', { className: 'nav-dia' }, [
        el('button', { type: 'button', className: 'nav-seta', 'aria-label': 'Dia anterior', title: 'Dia anterior', text: '‹', onclick: () => navegarPara(somarDias(dia, -1)) }),
        seletor,
        el('button', { type: 'button', className: 'nav-seta', 'aria-label': 'Próximo dia', title: 'Próximo dia', text: '›', onclick: () => navegarPara(somarDias(dia, 1)) }),
        dia !== hoje ? el('button', { type: 'button', className: 'btn btn-outline btn-sm', text: 'Voltar para hoje', onclick: () => navegarPara(hoje) }) : null,
      ]),
    ]);
    raiz.append(cabecalho);

    msgEl = el('p', { className: 'erro-msg' });
    raiz.append(msgEl);

    const vazio = (titulo, dica) => el('div', { className: 'vazio' }, [
      el('p', { className: 'vazio-titulo font-display', text: titulo }),
      el('p', { className: 'texto-fraco', text: dica }),
    ]);
    const nomeDia = r.nome ? r.nome.toLowerCase() : 'o dia ' + dm(dia);

    const b1 = bloco('col-feitas', 'Atividades Executadas', dados.executadas.length,
      dados.executadas.length ? dados.executadas.map((i) => linha(i, 1)) : [vazio('Nada concluído ainda', 'Marque o check em uma atividade ou registre o que você já fez.')],
      formSimples('Registrar o que já fez', (t) => api('POST', 'api/inicio.php', { titulo: t, tipo: 'DIA', data: dia, executada: true })));

    const b2 = bloco('col-fazer', 'Atividades a Executar', dados.a_executar.length,
      dados.a_executar.length ? dados.a_executar.map((i) => linha(i, 2)) : [vazio('Tudo em dia', 'Adicione uma atividade para ' + nomeDia + '.')],
      formSimples('Nova atividade para ' + nomeDia, (t) => api('POST', 'api/inicio.php', { titulo: t, tipo: 'DIA', data: dia })));

    const b3 = bloco('col-grandes', 'Grandes Pendências', dados.grandes.length,
      dados.grandes.length ? dados.grandes.map((i) => linha(i, 3)) : [vazio('Nenhuma demanda grande', 'Cadastre uma com prazo para acompanhar aqui.')],
      formGrande());

    raiz.append(el('div', { className: 'inicio-grid' }, [b1, b2, b3]));
  }

  carregar().catch((e) => { raiz.replaceChildren(el('p', { className: 'erro-msg', text: 'Não foi possível carregar: ' + e.message })); });
})();
