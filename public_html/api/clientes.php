<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/armazenamento.php';
header('Content-Type: application/json; charset=utf-8');
auth_exigir_login();

const TIPOS_BENEFICIO = [
    'Aposentadoria por idade',
    'Aposentadoria por tempo de contribuição',
    'Aposentadoria por invalidez',
    'Auxílio-doença',
    'BPC/LOAS',
    'Pensão por morte',
    'Revisão de benefício',
    'Outro',
];

const STATUS_PROCESSO = ['Em análise', 'Protocolado', 'Aguardando perícia', 'Em recurso', 'Concedido', 'Indeferido', 'Arquivado'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dados = armazenamento_transacao('clientes', fn($d) => null);
    $busca = strtolower(trim($_GET['busca'] ?? ''));
    if ($busca !== '') {
        $dados = array_values(array_filter($dados, function ($c) use ($busca) {
            return str_contains(strtolower($c['nome'] ?? ''), $busca)
                || str_contains($c['cpf'] ?? '', $busca);
        }));
    }
    echo json_encode(['registros' => array_values($dados), 'tipos_beneficio' => TIPOS_BENEFICIO, 'status_processo' => STATUS_PROCESSO]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'salvar') {
        $reg = $body['registro'] ?? [];
        if (trim($reg['nome'] ?? '') === '') {
            http_response_code(422);
            echo json_encode(['erro' => 'Nome é obrigatório.']);
            exit;
        }
        if (!empty($reg['tipo_beneficio']) && !in_array($reg['tipo_beneficio'], TIPOS_BENEFICIO, true)) {
            http_response_code(422);
            echo json_encode(['erro' => 'Tipo de benefício inválido.']);
            exit;
        }
        if (!empty($reg['status']) && !in_array($reg['status'], STATUS_PROCESSO, true)) {
            http_response_code(422);
            echo json_encode(['erro' => 'Status inválido.']);
            exit;
        }

        $resultado = armazenamento_transacao('clientes', function ($dados) use ($reg) {
            $agora = date('c');
            if (!empty($reg['id'])) {
                foreach ($dados as &$c) {
                    if ($c['id'] === (int)$reg['id']) {
                        $c = array_merge($c, $reg, ['id' => $c['id'], 'atualizado_em' => $agora]);
                        break;
                    }
                }
                unset($c);
            } else {
                $reg['id'] = armazenamento_gerar_id($dados);
                $reg['criado_em'] = $agora;
                $reg['atualizado_em'] = $agora;
                $dados[] = $reg;
            }
            return $dados;
        });

        echo json_encode(['ok' => true, 'registros' => array_values($resultado)]);
        exit;
    }

    if ($action === 'excluir') {
        $id = (int)($body['id'] ?? 0);
        $resultado = armazenamento_transacao('clientes', function ($dados) use ($id) {
            return array_values(array_filter($dados, fn($c) => $c['id'] !== $id));
        });
        echo json_encode(['ok' => true, 'registros' => array_values($resultado)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['erro' => 'Ação desconhecida.']);
    exit;
}

http_response_code(405);
echo json_encode(['erro' => 'Método não permitido.']);
