<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['usuario' => auth_usuario_logado()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true) ?? [];

    if (($dados['action'] ?? '') === 'logout') {
        $_SESSION = [];
        session_destroy();
        echo json_encode(['ok' => true]);
        exit;
    }

    $nome = trim($dados['usuario'] ?? '');
    $senha = (string)($dados['senha'] ?? '');
    $usuarios = auth_carregar_usuarios()['usuarios'] ?? [];

    if (!isset($usuarios[$nome]) || !password_verify($senha, $usuarios[$nome]['senha_hash'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Usuário ou senha inválidos.']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = $nome;
    echo json_encode(['ok' => true, 'usuario' => $nome, 'papel' => $usuarios[$nome]['papel']]);
    exit;
}

http_response_code(405);
echo json_encode(['erro' => 'Método não permitido.']);
