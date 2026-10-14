<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dados.php';

$user = exigirLoginApi();
$duplas = duplasVisiveis($user);
$duplaIds = idsDasDuplas($duplas);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson(['erro' => 'Método não suportado.'], 405);
}
exigirCsrfApi();

$dados = corpoJson();

if (empty($dados['duplaId']) || !in_array($dados['duplaId'], $duplaIds, true)) {
    respostaJson(['erro' => 'Você não tem acesso a essa dupla.'], 403);
}
if (empty($dados['planejamentoDoDia']) || empty($dados['impedimentos']) || empty($dados['comoAjudar'])) {
    respostaJson(['erro' => 'Preencha as três perguntas da daily.'], 400);
}

$daily = criarDaily([
    'data' => $dados['data'] ?? date('Y-m-d'),
    'planejamento_do_dia' => $dados['planejamentoDoDia'],
    'impedimentos' => $dados['impedimentos'],
    'como_ajudar' => $dados['comoAjudar'],
    'dupla_id' => $dados['duplaId'],
    'criado_por_id' => $user['id'],
]);

respostaJson(['daily' => $daily]);
