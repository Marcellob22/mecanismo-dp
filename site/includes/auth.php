<?php
require_once __DIR__ . '/db.php';

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function fazerLogin(array $usuario): void
{
    iniciarSessao();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $usuario['id'];
}

function fazerLogout(): void
{
    iniciarSessao();
    $_SESSION = [];
    session_destroy();
}

function usuarioLogado(): ?array
{
    iniciarSessao();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ? AND ativo = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function exigirLogin(): array
{
    $user = usuarioLogado();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function exigirGestorPagina(): array
{
    $user = exigirLogin();
    if ($user['role'] !== 'GESTOR') {
        header('Location: /dashboard.php');
        exit;
    }
    return $user;
}

// Para as rotas de API (respondem com JSON em vez de redirecionar)
function exigirLoginApi(): array
{
    $user = usuarioLogado();
    if (!$user) {
        respostaJson(['erro' => 'Não autenticado'], 401);
    }
    return $user;
}

function exigirGestorApi(): array
{
    $user = exigirLoginApi();
    if ($user['role'] !== 'GESTOR') {
        respostaJson(['erro' => 'Apenas gestores podem realizar esta ação.'], 403);
    }
    return $user;
}

function respostaJson(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

function corpoJson(): array
{
    $raw = file_get_contents('php://input');
    $dados = json_decode($raw, true);
    return is_array($dados) ? $dados : [];
}

function gerarCsrfToken(): string
{
    iniciarSessao();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrfToken(?string $token): bool
{
    iniciarSessao();
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function exigirCsrfApi(): void
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-Csrf-Token'] ?? $headers['X-CSRF-Token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!validarCsrfToken($token)) {
        respostaJson(['erro' => 'Sessão inválida, recarregue a página e tente novamente.'], 403);
    }
}
