<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dados.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    exigirGestorApi(); // dados cadastrais (email/cpf) só o gestor pode ver
    respostaJson(['usuarios' => listarUsuarios()]);
}

if ($metodo === 'POST') {
    exigirGestorApi();
    exigirCsrfApi();
    $dados = corpoJson();

    $nome = trim($dados['nome'] ?? '');
    $username = trim($dados['username'] ?? '');
    $senha = $dados['senha'] ?? '';
    $role = $dados['role'] ?? '';
    $duplaId = $dados['duplaId'] ?? null;
    $email = trim($dados['email'] ?? '');
    $cpf = preg_replace('/\D/', '', $dados['cpf'] ?? '');

    if (!$nome || !$username || !$senha || !$role) {
        respostaJson(['erro' => 'Preencha nome, usuário, senha e nível de acesso.'], 400);
    }
    if (strlen($senha) < 6) {
        respostaJson(['erro' => 'A senha precisa ter pelo menos 6 caracteres.'], 400);
    }
    if (!in_array($role, ['GESTOR', 'CONSULTOR', 'OPERACIONAL'], true)) {
        respostaJson(['erro' => 'Nível de acesso inválido.'], 400);
    }
    if (buscarUsuarioPorUsername($username)) {
        respostaJson(['erro' => 'Já existe um usuário com esse nome de usuário.'], 409);
    }
    if ($email && buscarUsuarioPorEmail($email)) {
        respostaJson(['erro' => 'Já existe um usuário com esse e-mail.'], 409);
    }
    if ($cpf && buscarUsuarioPorCpf($cpf)) {
        respostaJson(['erro' => 'Já existe um usuário com esse CPF.'], 409);
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $usuario = criarUsuario($nome, $username, $hash, $role, $duplaId ?: null, $email ?: null, $cpf ?: null);
    unset($usuario['password_hash']);
    respostaJson(['usuario' => $usuario]);
}

if ($metodo === 'PATCH') {
    exigirGestorApi();
    exigirCsrfApi();
    $id = $_GET['id'] ?? '';
    $alvo = $id ? buscarUsuarioPorId($id) : null;
    if (!$alvo) {
        respostaJson(['erro' => 'Usuário não encontrado.'], 404);
    }

    $dados = corpoJson();
    $campos = [];
    if (!empty($dados['nome'])) $campos['nome'] = $dados['nome'];
    if (!empty($dados['role'])) $campos['role'] = $dados['role'];
    if (array_key_exists('duplaId', $dados)) $campos['dupla_id'] = $dados['duplaId'] ?: null;
    if (array_key_exists('ativo', $dados)) $campos['ativo'] = $dados['ativo'] ? 1 : 0;
    if (array_key_exists('email', $dados)) $campos['email'] = $dados['email'] ?: null;
    if (array_key_exists('cpf', $dados)) $campos['cpf'] = $dados['cpf'] ? preg_replace('/\D/', '', $dados['cpf']) : null;
    if (!empty($dados['novaSenha'])) {
        if (strlen($dados['novaSenha']) < 6) {
            respostaJson(['erro' => 'A senha precisa ter pelo menos 6 caracteres.'], 400);
        }
        $campos['password_hash'] = password_hash($dados['novaSenha'], PASSWORD_DEFAULT);
    }

    $atualizado = atualizarUsuario($id, $campos);
    unset($atualizado['password_hash']);
    respostaJson(['usuario' => $atualizado]);
}

respostaJson(['erro' => 'Método não suportado.'], 405);
