<?php
// Leitura/escrita atômica de arquivos JSON por módulo.
// flock() com 'c+' cobre leitura-modificação-escrita num único passo atômico;
// gravação final usa arquivo temporário + rename() para nunca deixar o arquivo
// original truncado (janela em que outra requisição leria '[]').

function armazenamento_caminho(string $modulo): string
{
    return __DIR__ . '/../data/' . basename($modulo) . '.json';
}

function armazenamento_transacao(string $modulo, callable $fn)
{
    $caminho = armazenamento_caminho($modulo);
    $handle = fopen($caminho, 'c+');
    if (!$handle) {
        throw new RuntimeException("Não foi possível abrir $caminho");
    }

    try {
        flock($handle, LOCK_EX);
        $conteudo = stream_get_contents($handle);
        $dados = $conteudo === '' ? [] : (json_decode($conteudo, true) ?? []);

        $resultado = $fn($dados);

        if ($resultado !== null) {
            $tmp = $caminho . '.tmp.' . getmypid();
            file_put_contents($tmp, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            rename($tmp, $caminho);
            return $resultado;
        }

        return $dados;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function armazenamento_gerar_id(array $registros): int
{
    $max = 0;
    foreach ($registros as $r) {
        $max = max($max, (int)($r['id'] ?? 0));
    }
    return $max + 1;
}
