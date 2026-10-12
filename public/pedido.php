<?php
// =====================================================================
// VIP Imports — Recebimento de pedido da loja pública
// POST /public/pedido.php?c=TOKEN  (JSON body)
// Cria o pedido do cliente e (se o Telegram estiver configurado) avisa
// o vendedor no grupo.
// =====================================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/app/model/UsuarioConfig.php';
require_once dirname(__DIR__) . '/app/model/Pedido.php';
require_once dirname(__DIR__) . '/app/model/Telegram.php';

function responder(int $http, array $dados): void
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responder(405, ['ok' => false, 'mensagem' => 'Método não permitido.']);
}

$token = trim((string) ($_GET['c'] ?? ''));
$cfg   = $token === '' ? null : (new UsuarioConfig())->buscarPorToken($token);

if ($cfg === null) {
    responder(404, ['ok' => false, 'mensagem' => 'Loja não encontrada.']);
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    responder(400, ['ok' => false, 'mensagem' => 'Dados inválidos.']);
}

$nome      = (string) ($body['nome'] ?? '');
$contato   = (string) ($body['contato'] ?? '');
$observacao = (string) ($body['observacao'] ?? '');
$itens     = is_array($body['itens'] ?? null) ? $body['itens'] : [];

$usuarioId = (int) $cfg['usuario_id'];

try {
    $pedidoId = (new Pedido())->criar($usuarioId, $nome, $contato, $observacao, $itens, 'loja');
} catch (Throwable $e) {
    responder(500, ['ok' => false, 'mensagem' => 'Não foi possível registrar o pedido. Tente novamente.']);
}

if ($pedidoId === null) {
    responder(422, ['ok' => false, 'mensagem' => 'Preencha nome e contato e escolha ao menos um item disponível.']);
}

// Avisa o vendedor no Telegram (se configurado).
$chatId = trim((string) ($cfg['telegram_group_chat_id'] ?? ''));
$bot    = new Telegram($cfg['telegram_bot_token'] ?? null);
if ($chatId !== '' && $bot->configurado()) {
    $pedidos = (new Pedido())->listarPorUsuario($usuarioId, 'novo');
    $texto = "🛒 <b>Novo pedido #{$pedidoId}</b>\n"
        . 'Cliente: ' . htmlspecialchars($nome, ENT_QUOTES) . ' (' . htmlspecialchars($contato, ENT_QUOTES) . ')';
    try {
        $bot->enviarMensagem($chatId, $texto);
    } catch (Throwable $e) {
        // silencioso — o pedido já foi gravado
    }
}

responder(200, ['ok' => true, 'pedido' => $pedidoId]);
