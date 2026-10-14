(function () {
  const cfg = window.MECANISMO_DP;
  const form = document.getElementById('form-senha');
  const msg = document.getElementById('senha-msg');
  const campos = form.querySelectorAll('input[type=password]');

  document.getElementById('mostrar-senhas').addEventListener('change', (e) => {
    campos.forEach((c) => { c.type = e.target.checked ? 'text' : 'password'; });
  });

  function mostrar(texto, ok) {
    msg.textContent = texto;
    msg.className = 'opcoes-msg ' + (ok ? 'ok' : 'erro');
  }

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const dados = Object.fromEntries(new FormData(form).entries());
    if (!dados.senhaAtual || !dados.novaSenha || !dados.confirmarSenha) { mostrar('Preencha os três campos.', false); return; }
    if (dados.novaSenha.length < 6) { mostrar('A nova senha precisa ter pelo menos 6 caracteres.', false); return; }
    if (dados.novaSenha !== dados.confirmarSenha) { mostrar('A confirmação não é igual à nova senha.', false); return; }

    const botao = form.querySelector('button[type=submit]');
    botao.disabled = true;
    mostrar('Salvando...', true);
    try {
      const resp = await fetch('api/senha.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Csrf-Token': cfg.csrfToken },
        body: JSON.stringify(dados),
      });
      const r = await resp.json().catch(() => ({}));
      if (!resp.ok) throw new Error(r.erro || 'Não foi possível alterar a senha.');
      form.reset();
      mostrar('Senha alterada. Use a nova senha no próximo acesso.', true);
    } catch (e) {
      mostrar(e.message, false);
    } finally {
      botao.disabled = false;
    }
  });
})();
