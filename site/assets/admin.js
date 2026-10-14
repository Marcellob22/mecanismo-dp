(function () {
  const cfg = window.MECANISMO_DP;
  const csrfToken = cfg.csrfToken;
  let usuarios = cfg.usuarios;
  let duplas = cfg.duplas;
  let abaAtiva = 'duplas';

  const LABEL_ROLE = { GESTOR: 'Gestor', CONSULTOR: 'Consultor', OPERACIONAL: 'Operacional' };

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

  async function recarregar() {
    const [ru, rd] = await Promise.all([apiFetch('api/admin_users.php'), apiFetch('api/admin_duplas.php')]);
    if (ru.ok) usuarios = ru.dados.usuarios;
    if (rd.ok) duplas = rd.dados.duplas;
    render();
  }

  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (k === 'class') node.className = v;
      else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else node.setAttribute(k, v);
    }
    for (const child of children || []) {
      if (child) node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    }
    return node;
  }

  function campo(label, inputEl) {
    return el('div', { class: 'campo' }, [el('label', {}, [label]), inputEl]);
  }

  function render() {
    const raiz = document.getElementById('app-admin');
    raiz.innerHTML = '';
    raiz.appendChild(el('p', { class: 'font-display', style: 'font-size:24px; color:#fff; margin-bottom:20px;' }, ['Administração']));

    const tabs = el('div', { class: 'tabs' }, [
      el('button', { class: abaAtiva === 'duplas' ? 'ativo' : '', onclick: () => { abaAtiva = 'duplas'; render(); } }, ['Células']),
      el('button', { class: abaAtiva === 'usuarios' ? 'ativo' : '', onclick: () => { abaAtiva = 'usuarios'; render(); } }, ['Usuários']),
      el('button', { class: abaAtiva === 'estrutura' ? 'ativo' : '', onclick: () => { abaAtiva = 'estrutura'; render(); } }, ['Estrutura de Times']),
    ]);
    raiz.appendChild(tabs);

    if (abaAtiva === 'duplas') raiz.appendChild(renderDuplas());
    else if (abaAtiva === 'usuarios') raiz.appendChild(renderUsuarios());
    else raiz.appendChild(renderEstrutura());
  }

  function renderEstrutura() {
    const container = el('div', { class: 'card' }, []);
    const gestores = usuarios.filter((u) => u.role === 'GESTOR');
    const consultores = usuarios.filter((u) => u.role === 'CONSULTOR');
    const duplasSemConsultor = duplas.filter((d) => !d.consultor_id);
    const operacionaisSemDupla = usuarios.filter((u) => u.role === 'OPERACIONAL' && !u.dupla_id);

    function blocoDupla(d) {
      const membros = usuarios.filter((u) => u.dupla_id === d.id);
      return el('div', { style: 'margin:8px 0 8px 20px; padding-left:12px; border-left:2px solid var(--borda);' }, [
        el('p', { style: 'color:#fff;' }, ['📁 ' + d.nome]),
        ...(membros.length
          ? membros.map((m) => el('p', { class: 'texto-fraco', style: 'margin-left:12px;' }, ['— ' + m.nome + ' (' + LABEL_ROLE[m.role] + ')']))
          : [el('p', { class: 'texto-fraco', style: 'margin-left:12px; opacity:.6;' }, ['— nenhum membro ainda'])]),
      ]);
    }

    container.appendChild(el('p', { class: 'font-display', style: 'color:#fff; margin-bottom:4px;' }, ['👑 Gestão']));
    for (const g of gestores) {
      container.appendChild(el('p', { class: 'texto-fraco', style: 'margin-left:20px;' }, [g.nome]));
    }

    for (const c of consultores) {
      const duplasDoConsultor = duplas.filter((d) => d.consultor_id === c.id);
      container.appendChild(el('p', { class: 'font-display', style: 'color:#fff; margin-top:16px;' }, ['🧭 ' + c.nome + ' (Consultor)']));
      if (duplasDoConsultor.length === 0) {
        container.appendChild(el('p', { class: 'texto-fraco', style: 'margin-left:20px; opacity:.6;' }, ['Nenhuma célula vinculada ainda']));
      }
      duplasDoConsultor.forEach((d) => container.appendChild(blocoDupla(d)));
    }

    if (duplasSemConsultor.length > 0) {
      container.appendChild(el('p', { class: 'font-display', style: 'color:#fff; margin-top:16px;' }, ['⚠️ Células sem consultor responsável']));
      duplasSemConsultor.forEach((d) => container.appendChild(blocoDupla(d)));
    }

    if (operacionaisSemDupla.length > 0) {
      container.appendChild(el('p', { class: 'font-display', style: 'color:#fff; margin-top:16px;' }, ['⚠️ Sem célula atribuída']));
      operacionaisSemDupla.forEach((u) => container.appendChild(el('p', { class: 'texto-fraco', style: 'margin-left:20px;' }, [u.nome])));
    }

    container.appendChild(el('p', { class: 'texto-fraco mt-3' }, [
      'Para organizar os times: crie as células na aba "Células" (definindo o consultor responsável por cada uma) e depois atribua cada operacional a uma célula na aba "Usuários".',
    ]));

    return container;
  }

  function renderDuplas() {
    const lista = el('div', {}, []);
    if (duplas.length === 0) {
      lista.appendChild(el('p', { class: 'texto-fraco' }, ['Nenhuma célula cadastrada ainda.']));
    }
    const consultoresGlobal = usuarios.filter((u) => u.role === 'CONSULTOR' || u.role === 'GESTOR');
    for (const d of duplas) {
      lista.appendChild(linhaCelula(d, consultoresGlobal));
    }

    const inputNome = el('input', { type: 'text', placeholder: 'Nome da célula' }, []);
    const selectConsultor = el('select', {}, [
      el('option', { value: '' }, ['Sem consultor responsável']),
      ...consultoresGlobal.map((c) => el('option', { value: c.id }, [c.nome + ' (' + LABEL_ROLE[c.role] + ')'])),
    ]);
    const erroEl = el('p', { class: 'erro-msg', style: 'display:none;' }, []);

    const formulario = el('div', { class: 'card' }, [
      el('p', { class: 'font-display', style: 'color:#fff; margin-bottom:16px;' }, ['Nova célula']),
      campo('', inputNome),
      campo('', selectConsultor),
      erroEl,
      el('button', {
        class: 'btn btn-primary', style: 'width:100%;',
        onclick: async () => {
          if (!inputNome.value) {
            erroEl.textContent = 'Informe o nome da célula.';
            erroEl.style.display = 'block';
            return;
          }
          const { ok, dados } = await apiFetch('api/admin_duplas.php', {
            method: 'POST',
            body: JSON.stringify({ nome: inputNome.value, consultorId: selectConsultor.value || null }),
          });
          if (ok) { inputNome.value = ''; recarregar(); }
          else { erroEl.textContent = dados.erro || 'Erro ao criar célula.'; erroEl.style.display = 'block'; }
        },
      }, ['Criar célula']),
    ]);

    return el('div', { class: 'admin-grid' }, [lista, formulario]);
  }

  function linhaCelula(d, consultoresGlobal) {
    const membros = usuarios.filter((u) => u.dupla_id === d.id).map((u) => u.nome).join(', ') || 'nenhum ainda';

    const inputNome = el('input', { type: 'text', value: d.nome, style: 'display:none;' }, []);
    const nomeTexto = el('p', { style: 'color:#fff;' }, [d.nome]);
    const btnEditarNome = el('button', { class: 'btn btn-sm btn-ghost' }, ['Editar nome']);
    const btnSalvarNome = el('button', { class: 'btn btn-sm btn-outline', style: 'display:none;' }, ['Salvar']);

    btnEditarNome.addEventListener('click', () => {
      inputNome.style.display = 'inline-block';
      btnSalvarNome.style.display = 'inline-block';
      btnEditarNome.style.display = 'none';
      nomeTexto.style.display = 'none';
    });
    btnSalvarNome.addEventListener('click', async () => {
      if (!inputNome.value.trim()) return;
      await atualizarDupla(d.id, { nome: inputNome.value.trim() });
    });

    const selectConsultor = el('select', {
      onchange: (e) => atualizarDupla(d.id, { consultorId: e.target.value || null }),
    }, [
      el('option', { value: '' }, ['Sem consultor responsável']),
      ...consultoresGlobal.map((c) => {
        const opt = el('option', { value: c.id }, [c.nome + ' (' + LABEL_ROLE[c.role] + ')']);
        if (c.id === d.consultor_id) opt.setAttribute('selected', 'selected');
        return opt;
      }),
    ]);

    return el('div', { class: 'card', style: 'margin-bottom:12px;' }, [
      el('div', { class: 'usuario-linha' }, [
        el('div', {}, [nomeTexto, inputNome]),
        el('div', { class: 'usuario-controles' }, [btnEditarNome, btnSalvarNome]),
      ]),
      el('div', { class: 'mt-3' }, [
        campo('Consultor responsável', selectConsultor),
      ]),
      el('p', { class: 'texto-fraco', style: 'opacity:.7;' }, ['Membros: ' + membros]),
    ]);
  }

  async function atualizarDupla(id, campos) {
    await apiFetch('api/admin_duplas.php?id=' + encodeURIComponent(id), {
      method: 'PATCH',
      body: JSON.stringify(campos),
    });
    recarregar();
  }

  function renderUsuarios() {
    const lista = el('div', {}, usuarios.map((u) => linhaUsuario(u)));

    const inputNome = el('input', { type: 'text', placeholder: 'Nome completo' }, []);
    const inputUsername = el('input', { type: 'text', placeholder: 'Nome de usuário (login)' }, []);
    const inputSenha = el('input', { type: 'password', placeholder: 'Senha inicial' }, []);
    const inputEmail = el('input', { type: 'email', placeholder: 'E-mail (opcional)' }, []);
    const inputCpf = el('input', { type: 'text', placeholder: 'CPF (opcional)' }, []);
    const selectRole = el('select', {}, Object.entries(LABEL_ROLE).map(([v, l]) => el('option', { value: v }, [l])));
    const selectDupla = el('select', {}, [
      el('option', { value: '' }, ['Sem célula']),
      ...duplas.map((d) => el('option', { value: d.id }, [d.nome])),
    ]);
    const erroEl = el('p', { class: 'erro-msg', style: 'display:none;' }, []);

    const formulario = el('div', { class: 'card' }, [
      el('p', { class: 'font-display', style: 'color:#fff; margin-bottom:16px;' }, ['Novo usuário']),
      campo('', inputNome),
      campo('', inputUsername),
      campo('', inputSenha),
      campo('', inputEmail),
      campo('', inputCpf),
      campo('', selectRole),
      campo('', selectDupla),
      erroEl,
      el('button', {
        class: 'btn btn-primary', style: 'width:100%;',
        onclick: async () => {
          if (!inputNome.value || !inputUsername.value || !inputSenha.value) {
            erroEl.textContent = 'Preencha nome, usuário e senha.';
            erroEl.style.display = 'block';
            return;
          }
          const { ok, dados } = await apiFetch('api/admin_users.php', {
            method: 'POST',
            body: JSON.stringify({
              nome: inputNome.value, username: inputUsername.value, senha: inputSenha.value,
              role: selectRole.value, duplaId: selectDupla.value || null,
              email: inputEmail.value, cpf: inputCpf.value,
            }),
          });
          if (ok) {
            inputNome.value = ''; inputUsername.value = ''; inputSenha.value = '';
            inputEmail.value = ''; inputCpf.value = '';
            recarregar();
          } else { erroEl.textContent = dados.erro || 'Erro ao criar usuário.'; erroEl.style.display = 'block'; }
        },
      }, ['Criar usuário']),
    ]);

    return el('div', { class: 'admin-grid' }, [lista, formulario]);
  }

  function formatarCpf(cpf) {
    if (!cpf) return '';
    const digitos = String(cpf).replace(/\D/g, '');
    if (digitos.length !== 11) return cpf;
    return digitos.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  }

  function linhaUsuario(u) {
    const selectRole = el('select', {
      onchange: (e) => atualizarUsuario(u.id, { role: e.target.value }),
    }, Object.entries(LABEL_ROLE).map(([v, l]) => {
      const opt = el('option', { value: v }, [l]);
      if (v === u.role) opt.setAttribute('selected', 'selected');
      return opt;
    }));

    const selectDupla = el('select', {
      onchange: (e) => atualizarUsuario(u.id, { duplaId: e.target.value || null }),
    }, [
      el('option', { value: '' }, ['Sem célula']),
      ...duplas.map((d) => {
        const opt = el('option', { value: d.id }, [d.nome]);
        if (d.id === u.dupla_id) opt.setAttribute('selected', 'selected');
        return opt;
      }),
    ]);

    const btnAtivo = el('button', {
      class: 'btn btn-sm ' + (Number(u.ativo) ? 'btn-ghost' : 'btn-outline'),
      onclick: () => atualizarUsuario(u.id, { ativo: Number(u.ativo) ? 0 : 1 }),
    }, [Number(u.ativo) ? 'Bloquear acesso' : 'Reativar acesso']);

    const inputSenha = el('input', { type: 'password', placeholder: 'Nova senha (mín. 6 caracteres)', style: 'display:none;' }, []);
    const btnSenha = el('button', { class: 'btn btn-sm btn-ghost' }, ['Redefinir senha']);
    btnSenha.addEventListener('click', () => {
      inputSenha.style.display = inputSenha.style.display === 'none' ? 'inline-block' : 'none';
    });
    const btnConfirmarSenha = el('button', { class: 'btn btn-sm btn-outline', style: 'display:none;' }, ['Confirmar']);
    inputSenha.addEventListener('input', () => {
      btnConfirmarSenha.style.display = inputSenha.value.length >= 6 ? 'inline-block' : 'none';
    });
    btnConfirmarSenha.addEventListener('click', async () => {
      await atualizarUsuario(u.id, { novaSenha: inputSenha.value });
      inputSenha.value = '';
      inputSenha.style.display = 'none';
      btnConfirmarSenha.style.display = 'none';
    });

    // Edição de dados cadastrais (email/CPF)
    const inputEmail = el('input', { type: 'email', placeholder: 'E-mail', value: u.email || '', style: 'display:none;' }, []);
    const inputCpf = el('input', { type: 'text', placeholder: 'CPF', value: formatarCpf(u.cpf), style: 'display:none;' }, []);
    const btnEditarDados = el('button', { class: 'btn btn-sm btn-ghost' }, ['Editar dados cadastrais']);
    const btnConfirmarDados = el('button', { class: 'btn btn-sm btn-outline', style: 'display:none;' }, ['Salvar']);
    btnEditarDados.addEventListener('click', () => {
      const mostrar = inputEmail.style.display === 'none';
      inputEmail.style.display = mostrar ? 'inline-block' : 'none';
      inputCpf.style.display = mostrar ? 'inline-block' : 'none';
      btnConfirmarDados.style.display = mostrar ? 'inline-block' : 'none';
    });
    btnConfirmarDados.addEventListener('click', async () => {
      await atualizarUsuario(u.id, { email: inputEmail.value, cpf: inputCpf.value });
    });

    return el('div', { class: 'card', style: 'margin-bottom:12px;' }, [
      el('div', { class: 'usuario-linha' }, [
        el('div', {}, [
          el('p', { style: 'color:#fff;' }, [u.nome]),
          el('p', { class: 'texto-fraco' }, ['@' + u.username]),
          u.email ? el('p', { class: 'texto-fraco', style: 'opacity:.7;' }, [u.email]) : null,
          u.cpf ? el('p', { class: 'texto-fraco', style: 'opacity:.7;' }, ['CPF: ' + formatarCpf(u.cpf)]) : null,
        ]),
        el('div', { class: 'usuario-controles' }, [selectRole, selectDupla, btnAtivo]),
      ]),
      el('div', { class: 'usuario-controles mt-3' }, [btnSenha, inputSenha, btnConfirmarSenha, btnEditarDados, inputEmail, inputCpf, btnConfirmarDados]),
    ]);
  }

  async function atualizarUsuario(id, campos) {
    await apiFetch('api/admin_users.php?id=' + encodeURIComponent(id), {
      method: 'PATCH',
      body: JSON.stringify(campos),
    });
    recarregar();
  }

  render();
})();
