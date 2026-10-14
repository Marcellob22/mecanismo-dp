<?php
require_once __DIR__ . '/../includes/auth.php';

$user = exigirLoginApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson(['erro' => 'Método não suportado.'], 405);
}
exigirCsrfApi();

$d = corpoJson();
$atual = (string)($d['senhaAtual'] ?? '');
$nova = (string)($d['novaSenha'] ?? '');
$confirmar = (string)($d['confirmarSenha'] ?? '');

if (!password_verify($atual, $user['password_hash'])) {
    sleep(1); // freia tentativas repetidas
    respostaJson(['erro' => 'A senha atual está incorreta.'], 400);
}
if (strlen($nova) < 6) {
    respostaJson(['erro' => 'A nova senha precisa ter pelo menos 6 caracteres.'], 400);
}
if ($nova !== $confirmar) {
    respostaJson(['erro' => 'A confirmação não é igual à nova senha.'], 400);
}
if ($nova === $atual) {
    respostaJson(['erro' => 'A nova senha precisa ser diferente da atual.'], 400);
}

$st = getDB()->prepare("UPDATE usuario SET password_hash = ? WHERE id = ?");
$st->execute([password_hash($nova, PASSWORD_DEFAULT), $user['id']]);
session_regenerate_id(true);

respostaJson(['ok' => true]);
