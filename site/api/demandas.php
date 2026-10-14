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
    if (empty($dados['abreviacao']) || empty($dados['data'])) {
        respostaJson(['erro' => 'Campos obrigatórios ausentes.'], 400);
    }

    $demanda = criarDemanda([
        'abreviacao' => $dados['abreviacao'],
        'data' => $dados['data'],
        'prioridade' => $dados['prioridade'] ?? 'MEDIA',
        'status' => 'A_FAZER',
        'observacoes' => $dados['observacoes'] ?? null,
        'dupla_id' => $dados['duplaId'],
        'criado_por_id' => $user['id'],
    ]);
    respostaJson(['demanda' => $demanda]);
}

if ($metodo === 'PATCH') {
    exigirCsrfApi();
    $id = $_GET['id'] ?? '';
    $demandaAtual = $id ? buscarDemandaPorId($id) : null;

    if (!$demandaAtual || !in_array($demandaAtual['dupla_id'], $duplaIds, true)) {
        respostaJson(['erro' => 'Demanda não encontrada ou sem acesso.'], 403);
    }

    $dados = corpoJson();
    $statusValidos = ['A_FAZER', 'EM_ANDAMENTO', 'CONCLUIDA', 'ATRASADA'];
    if (empty($dados['status']) || !in_array($dados['status'], $statusValidos, true)) {
        respostaJson(['erro' => 'Status inválido.'], 400);
    }

    atualizarStatusDemanda($id, $dados['status']);
    respostaJson(['ok' => true]);
}

respostaJson(['erro' => 'Método não suportado.'], 405);
