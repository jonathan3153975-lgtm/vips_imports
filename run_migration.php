<?php
// Aplica uma migração SQL via PDO (MariaDB). Uso: php run.migration.php <arquivo.sql>
require 'database/conexao.php';

$arquivo = $argv[1] ?? null;
if (!$arquivo || !is_file($arquivo)) {
    fwrite(STDERR, "Uso: php run_migration.php <arquivo.sql>" . PHP_EOL);
    exit(1);
}

$sql = file_get_contents($arquivo);
if ($sql === false) {
    fwrite(STDERR, "Não foi possível ler o arquivo." . PHP_EOL);
    exit(1);
}

// Remove comentários de linha e a instrução USE (o PDO já aponta para o banco).
$linhas = array_filter(array_map('trim', explode("\n", $sql)), function ($l) {
    return $l !== '' && !str_starts_with($l, '--') && !str_starts_with($l, 'USE ');
});
$script = implode("\n", $linhas);

$conexao = new Connection();
$pdo = $conexao->PDOconn();

foreach (array_filter(array_map('trim', explode(';', $script))) as $stmtSql) {
    if ($stmtSql === '') continue;
    try {
        $pdo->exec($stmtSql);
        echo "OK: " . mb_substr(strtok($stmtSql, "\n"), 0, 60) . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n  em: " . mb_substr(strtok($stmtSql, "\n"), 0, 60) . "\n");
        exit(1);
    }
}
echo "Migração aplicada com sucesso.\n";