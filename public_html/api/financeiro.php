<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/armazenamento.php';
header('Content-Type: application/json; charset=utf-8');
auth_exigir_login();

const TIPOS_LANCAMENTO = ['Honorário contratual', 'Honorário de êxito', 'Despesa processual', 'Despesa administrativa', 'Outro'];
const STATUS_LANCAMENTO = ['Pendente', 'Pago', 'Atrasado', 'Cancelado'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dados = armazenamento_transacao('financeiro', fn($d) => null);
    echo json_encode(['registros' => array_values($dados), 'tipos' => TIPOS_LANCAMENTO, 'status' => STATUS_LANCAMENTO]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'salvar') {
        $reg = $body['registro'] ?? [];
        if (trim($reg['descricao'] ?? '') === '' || !isset($reg['valor'])) {
            http_response_code(422);
            echo json_encode(['erro' => 'Descrição e valor são obrigatórios.']);
            exit;
        }
        if (!empty($reg['tipo']) && !in_array($reg['tipo'], TIPOS_LANCAMENTO, true)) {
            http_response_code(422);
            echo json_encode(['erro' => 'Tipo inválido.']);
            exit;
        }
        if (!empty($reg['status']) && !in_array($reg['status'], STATUS_LANCAMENTO, true)) {
            http_response_code(422);
            echo json_encode(['erro' => 'Status inválido.']);
            exit;
        }
        $reg['valor'] = (float)$reg['valor'];

        $resultado = armazenamento_transacao('financeiro', function ($dados) use ($reg) {
            $agora = date('c');
            if (!empty($reg['id'])) {
                foreach ($dados as &$r) {
                    if ($r['id'] === (int)$reg['id']) {
                        $r = array_merge($r, $reg, ['id' => $r['id'], 'atualizado_em' => $agora]);
                        break;
                    }
                }
                unset($r);
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

    if ($action === 'criar_lote') {
        $registros = $body['registros'] ?? [];
        $resultado = armazenamento_transacao('financeiro', function ($dados) use ($registros) {
            $agora = date('c');
            foreach ($registros as $reg) {
                if (trim($reg['descricao'] ?? '') === '') {
                    continue; // linha incompleta é ignorada silenciosamente, não derruba o lote
                }
                if (!empty($reg['tipo']) && !in_array($reg['tipo'], TIPOS_LANCAMENTO, true)) {
                    continue;
                }
                $reg['id'] = armazenamento_gerar_id($dados);
                $reg['valor'] = (float)($reg['valor'] ?? 0);
                $reg['status'] = in_array($reg['status'] ?? '', STATUS_LANCAMENTO, true) ? $reg['status'] : 'Pendente';
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
        $resultado = armazenamento_transacao('financeiro', function ($dados) use ($id) {
            return array_values(array_filter($dados, fn($r) => $r['id'] !== $id));
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
