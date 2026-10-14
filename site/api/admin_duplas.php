<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dados.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    exigirLoginApi();
    respostaJson(['duplas' => listarDuplas()]);
}

if ($metodo === 'POST') {
    exigirGestorApi();
    exigirCsrfApi();
    $dados = corpoJson();
    $nome = trim($dados['nome'] ?? '');

    if (!$nome) {
        respostaJson(['erro' => 'Informe o nome da célula.'], 400);
    }

    $dupla = criarDupla($nome, $dados['consultorId'] ?? null);
    respostaJson(['dupla' => $dupla]);
}

if ($metodo === 'PATCH') {
    exigirGestorApi();
    exigirCsrfApi();
    $id = $_GET['id'] ?? '';
    $atual = $id ? buscarDuplaPorId($id) : null;
    if (!$atual) {
        respostaJson(['erro' => 'Célula não encontrada.'], 404);
    }

    $dados = corpoJson();
    $campos = [];
    if (array_key_exists('nome', $dados)) {
        $nome = trim($dados['nome']);
        if (!$nome) {
            respostaJson(['erro' => 'O nome da célula não pode ficar em branco.'], 400);
        }
        $campos['nome'] = $nome;
    }
    if (array_key_exists('consultorId', $dados)) {
        $campos['consultor_id'] = $dados['consultorId'] ?: null;
    }

    $atualizado = atualizarDupla($id, $campos);
    respostaJson(['dupla' => $atualizado]);
}

respostaJson(['erro' => 'Método não suportado.'], 405);
