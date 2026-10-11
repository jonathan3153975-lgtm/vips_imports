<?php
// =====================================================================
// VIP Imports — Controller: Cotação (endpoint JSON)
// GET /app/controller/CotacaoController.php?moeda=USD
// Retorna a cotação do dólar (BCB/PTAX) com cache diário.
// =====================================================================

require_once dirname(__DIR__) . '/model/Cotacao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'nao_autenticado']);
    exit;
}

$moeda = strtoupper((string) ($_GET['moeda'] ?? 'USD'));
if ($moeda !== 'USD') {
    echo json_encode(['ok' => false, 'erro' => 'moeda_nao_suportada']);
    exit;
}

try {
    $cotacao = (new Cotacao())->obterUsd();
} catch (Throwable $e) {
    $cotacao = null;
}

if ($cotacao === null) {
    echo json_encode(['ok' => false, 'erro' => 'indisponivel']);
    exit;
}

echo json_encode([
    'ok'      => true,
    'moeda'   => $cotacao['moeda'],
    'cotacao' => $cotacao['cotacao'],
    'compra'  => $cotacao['compra'],
    'venda'   => $cotacao['venda'],
    'data'    => $cotacao['data_hora'] ? substr((string) $cotacao['data_hora'], 0, 10) : $cotacao['data_consulta'],
    'fonte'   => $cotacao['fonte'],
]);
