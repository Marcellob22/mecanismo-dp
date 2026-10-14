<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dados.php';

$user = exigirLoginApi();
$duplas = duplasVisiveis($user);
$duplaIds = idsDasDuplas($duplas);
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {
    exigirCsrfApi();
    $dados = corpoJson();

    if (empty($dados['duplaId']) || !in_array($dados['duplaId'], $duplaIds, true)) {
        respostaJson(['erro' => 'Você não tem acesso a essa dupla.'], 403);
    }
    if (empty($dados['titulo']) || empty($dados['dataInicio']) || empty($dados['dataFim'])) {
        respostaJson(['erro' => 'Campos obrigatórios ausentes.'], 400);
    }

    $planejamento = criarPlanejamento([
        'titulo' => $dados['titulo'],
        'data_inicio' => $dados['dataInicio'],
        'data_fim' => $dados['dataFim'],
        'observacoes' => $dados['observacoes'] ?? null,
        'dupla_id' => $dados['duplaId'],
        'criado_por_id' => $user['id'],
    ]);

    respostaJson(['planejamento' => $planejamento]);
}

if ($metodo === 'PATCH') {
    exigirCsrfApi();
    $id = $_GET['id'] ?? '';
    $atual = $id ? buscarPlanejamentoPorId($id) : null;

    if (!$atual || !in_array($atual['dupla_id'], $duplaIds, true)) {
        respostaJson(['erro' => 'Planejamento não encontrado ou sem acesso.'], 403);
    }

    $dados = corpoJson();
    $statusValidos = ['ATIVO', 'CONCLUIDO'];
    if (empty($dados['status']) || !in_array($dados['status'], $statusValidos, true)) {
        respostaJson(['erro' => 'Status inválido.'], 400);
    }

    atualizarStatusPlanejamento($id, $dados['status']);
    respostaJson(['ok' => true]);
}

respostaJson(['erro' => 'Método não suportado.'], 405);
