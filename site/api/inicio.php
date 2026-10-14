<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inicio.php';

$user = exigirLoginApi();
$uid = $user['id'];
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $dia = $_GET['dia'] ?? '';
    $hoje = $_GET['hoje'] ?? '';
    if (!inicioDataValida($dia) || !inicioDataValida($hoje)) {
        respostaJson(['erro' => 'Data inválida.'], 400);
    }
    respostaJson(inicioDoDia($uid, $dia, $hoje));
}

if ($metodo === 'POST') {
    exigirCsrfApi();
    $d = corpoJson();
    $r = inicioCriarAtividade(
        $uid,
        (string)($d['titulo'] ?? ''),
        (string)($d['tipo'] ?? 'DIA'),
        isset($d['data']) ? (string)$d['data'] : null,
        isset($d['prazo']) ? (string)$d['prazo'] : null,
        !empty($d['executada'])
    );
    if (is_string($r)) respostaJson(['erro' => $r], 400);
    respostaJson(['atividade' => $r]);
}

if ($metodo === 'PATCH') {
    exigirCsrfApi();
    $d = corpoJson();
    $erro = inicioAlterarStatus(
        $uid,
        (string)($d['origem'] ?? ''),
        (string)($d['id'] ?? ''),
        (string)($d['status'] ?? ''),
        (string)($d['hoje'] ?? '')
    );
    if ($erro) respostaJson(['erro' => $erro], 400);
    respostaJson(['ok' => true]);
}

if ($metodo === 'DELETE') {
    exigirCsrfApi();
    inicioExcluirAtividade($uid, (string)($_GET['id'] ?? ''));
    respostaJson(['ok' => true]);
}

respostaJson(['erro' => 'Método não suportado.'], 405);
