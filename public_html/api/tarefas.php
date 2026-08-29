<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/armazenamento.php';
header('Content-Type: application/json; charset=utf-8');
auth_exigir_login();

const STATUS_TAREFA = ['A fazer', 'Em andamento', 'Concluída'];
const ETAPAS_CRM = ['Novo contato', 'Consulta agendada', 'Proposta enviada', 'Contrato fechado', 'Perdido'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tarefas = armazenamento_transacao('tarefas', fn($d) => null);
    $leads = armazenamento_transacao('crm_leads', fn($d) => null);
    echo json_encode([
        'tarefas' => array_values($tarefas),
        'leads' => array_values($leads),
        'status_tarefa' => STATUS_TAREFA,
        'etapas_crm' => ETAPAS_CRM,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $usuario = auth_usuario_logado();

    if ($action === 'salvar_tarefa') {
        $reg = $body['registro'] ?? [];
        if (trim($reg['titulo'] ?? '') === '') {
            http_response_code(422);
            echo json_encode(['erro' => 'Título é obrigatório.']);
            exit;
        }
        if (!empty($reg['status']) && !in_array($reg['status'], STATUS_TAREFA, true)) {
            http_response_code(422);
            echo json_encode(['erro' => 'Status inválido.']);
            exit;
        }

        $resultado = armazenamento_transacao('tarefas', function ($dados) use ($reg, $usuario) {
            $agora = date('c');
            if (!empty($reg['id'])) {
                foreach ($dados as &$t) {
                    if ($t['id'] === (int)$reg['id']) {
                        $t = array_merge($t, $reg, ['id' => $t['id'], 'atualizado_em' => $agora]);
                        break;
                    }
                }
                unset($t);
            } else {
                $reg['id'] = armazenamento_gerar_id($dados);
                $reg['status'] = $reg['status'] ?? 'A fazer';
                $reg['criado_por'] = $usuario;
                $reg['criado_em'] = $agora;
                $reg['atualizado_em'] = $agora;
                $dados[] = $reg;
            }
            return $dados;
        });
        echo json_encode(['ok' => true, 'tarefas' => array_values($resultado)]);
        exit;
    }

    if ($action === 'excluir_tarefa') {
        $id = (int)($body['id'] ?? 0);
        $resultado = armazenamento_transacao('tarefas', fn($dados) => array_values(array_filter($dados, fn($t) => $t['id'] !== $id)));
        echo json_encode(['ok' => true, 'tarefas' => array_values($resultado)]);
        exit;
    }

    if ($action === 'salvar_lead') {
        $reg = $body['registro'] ?? [];
        if (trim($reg['nome'] ?? '') === '') {
            http_response_code(422);
            echo json_encode(['erro' => 'Nome é obrigatório.']);
            exit;
        }
        if (!empty($reg['etapa']) && !in_array($reg['etapa'], ETAPAS_CRM, true)) {
            http_response_code(422);
            echo json_encode(['erro' => 'Etapa inválida.']);
            exit;
        }

        $resultado = armazenamento_transacao('crm_leads', function ($dados) use ($reg) {
            $agora = date('c');
            if (!empty($reg['id'])) {
                foreach ($dados as &$l) {
                    if ($l['id'] === (int)$reg['id']) {
                        $l = array_merge($l, $reg, ['id' => $l['id'], 'atualizado_em' => $agora]);
                        break;
                    }
                }
                unset($l);
            } else {
                $reg['id'] = armazenamento_gerar_id($dados);
                $reg['etapa'] = $reg['etapa'] ?? 'Novo contato';
                $reg['criado_em'] = $agora;
                $reg['atualizado_em'] = $agora;
                $dados[] = $reg;
            }
            return $dados;
        });
        echo json_encode(['ok' => true, 'leads' => array_values($resultado)]);
        exit;
    }

    if ($action === 'excluir_lead') {
        $id = (int)($body['id'] ?? 0);
        $resultado = armazenamento_transacao('crm_leads', fn($dados) => array_values(array_filter($dados, fn($l) => $l['id'] !== $id)));
        echo json_encode(['ok' => true, 'leads' => array_values($resultado)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['erro' => 'Ação desconhecida.']);
    exit;
}

http_response_code(405);
echo json_encode(['erro' => 'Método não permitido.']);
