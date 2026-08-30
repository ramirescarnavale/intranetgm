<?php
// Arquivo temporário de diagnóstico — remover depois de resolver o caminho do secrets.php.
header('Content-Type: application/json; charset=utf-8');

$candidatos = [
    __DIR__ . '/../intranetgm_secrets/secrets.php',
    __DIR__ . '/../../intranetgm_secrets/secrets.php',
    __DIR__ . '/../../../intranetgm_secrets/secrets.php',
    __DIR__ . '/intranetgm_secrets/secrets.php',
];

$resultado = [];
foreach ($candidatos as $c) {
    $resultado[] = [
        'caminho' => $c,
        'real' => realpath($c) ?: null,
        'existe' => file_exists($c),
    ];
}

echo json_encode([
    'este_arquivo_esta_em' => __DIR__,
    'open_basedir' => ini_get('open_basedir'),
    'candidatos' => $resultado,
], JSON_PRETTY_PRINT);
