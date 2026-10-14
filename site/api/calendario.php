<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dados.php';
require_once __DIR__ . '/../includes/inicio.php';

$user = exigirLoginApi();
$duplas = duplasVisiveis($user);
$duplaIds = idsDasDuplas($duplas);

$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;

respostaJson([
    'duplas' => $duplas,
    'demandas' => listarDemandasPorDuplas($duplaIds, $inicio, $fim),
    'planejamentos' => listarPlanejamentosPorDuplas($duplaIds),
    'dailies' => listarDailiesPorDuplas($duplaIds),
    'atividades' => inicioParaCalendario($user['id']),
    'usuario' => ['id' => $user['id'], 'role' => $user['role']],
]);
