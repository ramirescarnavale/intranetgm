<?php
// Autenticação central. Todo endpoint começa com:
//   require_once __DIR__ . '/auth.php'; auth_exigir_login();
// ou auth_exigir_usuario(['Geovanna']) para restringir por nome.

$cookie_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $cookie_secure,
]);
session_start();

function auth_carregar_usuarios(): array
{
    // Fora do repositório git e da pasta pública — ver secrets/secrets.php.
    $caminho = __DIR__ . '/../../secrets/secrets.php';
    if (!file_exists($caminho)) {
        http_response_code(500);
        echo json_encode(['erro' => 'Arquivo de credenciais não encontrado.']);
        exit;
    }
    return require $caminho;
}

function auth_usuario_logado(): ?string
{
    return $_SESSION['usuario'] ?? null;
}

function auth_exigir_login(): void
{
    if (!auth_usuario_logado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Não autenticado.']);
        exit;
    }
}

function auth_exigir_usuario(array $permitidos): void
{
    auth_exigir_login();
    if (!in_array(auth_usuario_logado(), $permitidos, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Sem permissão para este recurso.']);
        exit;
    }
}

function auth_papel_logado(): ?string
{
    $usuarios = auth_carregar_usuarios()['usuarios'] ?? [];
    $nome = auth_usuario_logado();
    return $usuarios[$nome]['papel'] ?? null;
}
