<?php
// =====================================================================
// VIP Imports — Webhook do Telegram
// POST /public/telegram.php?w=SEGREDO
// Recebe as mensagens do grupo/bot e responde:
//   /catalogo                 -> lista os itens à venda
//   /pedido <id> <qtd> <fone> -> registra um pedido do participante
//   /divulgar <id>            -> (privado) publica um item no grupo
//   /divulgar_catalogo        -> (privado) publica o catálogo no grupo
// =====================================================================

http_response_code(200); // sempre 200 para o Telegram não reenviar
header('Content-Type: text/plain; charset=utf-8');

require_once dirname(__DIR__) . '/app/model/UsuarioConfig.php';
require_once dirname(__DIR__) . '/app/model/Pedido.php';
require_once dirname(__DIR__) . '/app/model/Estoque.php';
require_once dirname(__DIR__) . '/app/model/Telegram.php';

$segredo = trim((string) ($_GET['w'] ?? ''));
$cfg = $segredo === '' ? null : (new UsuarioConfig())->buscarPorWebhookSecret($segredo);
if ($cfg === null) {
    exit('ok');
}

// Confere o segredo enviado pelo Telegram no cabeçalho (se presente).
$headerSecret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($headerSecret !== '' && !hash_equals((string) $cfg['telegram_webhook_secret'], $headerSecret)) {
    exit('ok');
}

$update = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($update) || !isset($update['message'])) {
    exit('ok');
}

$msg  = $update['message'];
$texto = trim((string) ($msg['text'] ?? ''));
$chat = $msg['chat'] ?? [];
$from = $msg['from'] ?? [];
$chatId = $chat['id'] ?? null;
$chatType = (string) ($chat['type'] ?? '');

if ($chatId === null) {
    exit('ok');
}

$usuarioId = (int) $cfg['usuario_id'];
$bot       = new Telegram($cfg['telegram_bot_token'] ?? null);
$grupoId   = trim((string) ($cfg['telegram_group_chat_id'] ?? ''));

$responder = function (string $texto) use ($bot, $chatId): void {
    if ($bot->configurado()) {
        $bot->enviarMensagem((string) $chatId, $texto);
    }
};

if (!$bot->configurado()) {
    exit('ok');
}

// URL base pública (para fotos e link da loja).
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? '';
$base    = preg_replace('#/public/[^/]+$#', '', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$urlRaiz = $esquema . '://' . $host . $base;

$fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$produtos = (new Estoque())->publicados($usuarioId);
$aVenda = [];
foreach ($produtos as $p) {
    if (!$p['vendido'] && !$p['reservado']) {
        $aVenda[(int) $p['id']] = $p;
    }
}

[$cmd, $args] = array_pad(explode(' ', $texto, 2), 2, '');
$cmd    = strtolower(trim($cmd));
$args   = trim($args);
$linkLoja = $urlRaiz . '/public/loja.php?c=' . $cfg['loja_token'];

switch ($cmd) {
    case '/start':
    case '/ajuda':
    case '/help':
        $responder(
            "👋 <b>Loja VIP Imports</b>\n\n" .
            "Comandos:\n" .
            "/catalogo — ver os itens à venda\n" .
            "/pedido &lt;id&gt; &lt;qtd&gt; &lt;contato&gt; — fazer um pedido\n\n" .
            "🛍️ Loja completa: {$linkLoja}"
        );
        break;

    case '/catalogo':
        if (count($aVenda) === 0) {
            $responder('Nenhum item à venda no momento.');
            break;
        }
        $linhas = ["🛍️ <b>Catálogo</b>\n"];
        foreach ($aVenda as $p) {
            $linhas[] = '#' . (int) $p['id'] . ' ' . htmlspecialchars($p['produto'], ENT_QUOTES)
                . ' — <b>' . $fmt($p['preco']) . '</b>';
        }
        $linhas[] = "\nPara pedir: /pedido &lt;id&gt; &lt;qtd&gt; &lt;seu contato&gt;";
        $linhas[] = "Loja completa: {$linkLoja}";
        $responder(implode("\n", $linhas));
        break;

    case '/pedido':
        $partes = preg_split('/\s+/', $args);
        if (count($partes) < 3) {
            $responder('Use: /pedido &lt;id&gt; &lt;qtd&gt; &lt;contato&gt;');
            break;
        }
        $produtoId = (int) $partes[0];
        $qtd       = max(1, (int) $partes[1]);
        $contato   = implode(' ', array_slice($partes, 2));
        $nome      = trim((string) (($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')));
        if ($nome === '') {
            $nome = (string) ($from['username'] ?? 'Cliente Telegram');
        }

        if (!isset($aVenda[$produtoId])) {
            $responder("Item #{$produtoId} não está disponível.");
            break;
        }

        try {
            $pedidoId = (new Pedido())->criar($usuarioId, $nome, $contato, 'Pedido via Telegram', [
                ['produto_id' => $produtoId, 'quantidade' => $qtd],
            ], 'telegram');
        } catch (Throwable $e) {
            $responder('Não foi possível registrar o pedido. Tente novamente.');
            break;
        }

        if ($pedidoId === null) {
            $responder('Não foi possível registrar o pedido. Verifique os dados.');
            break;
        }

        $responder("✅ Pedido #{$pedidoId} registrado!\nO vendedor entrará em contato.");

        if ($grupoId !== '') {
            $bot->enviarMensagem($grupoId,
                "🛒 <b>Novo pedido #{$pedidoId}</b>\n" .
                'Cliente: ' . htmlspecialchars($nome, ENT_QUOTES) . ' (' . htmlspecialchars($contato, ENT_QUOTES) . ")\n" .
                htmlspecialchars($aVenda[$produtoId]['produto'], ENT_QUOTES) . ' × ' . $qtd
            );
        }
        break;

    case '/divulgar':
        if ($chatType !== 'private') {
            $responder('O comando /divulgar só funciona na conversa privada com o bot.');
            break;
        }
        if ($grupoId === '') {
            $responder('Configure o ID do grupo em Configurações no painel.');
            break;
        }
        $produtoId = (int) trim($args);
        if (!isset($aVenda[$produtoId])) {
            $responder("Item #{$produtoId} não está disponível.");
            break;
        }
        $p = $aVenda[$produtoId];
        $legenda = '✨ <b>' . htmlspecialchars($p['produto'], ENT_QUOTES) . '</b>';
        if ($p['marca']) {
            $legenda .= ' · ' . htmlspecialchars($p['marca'], ENT_QUOTES);
        }
        $legenda .= "\n<b>" . $fmt($p['preco']) . '</b>';
        if ($p['descricao']) {
            $legenda .= "\n\n" . htmlspecialchars(mb_substr((string) $p['descricao'], 0, 300), ENT_QUOTES);
        }
        $legenda .= "\n\nPara pedir, use: /pedido {$produtoId} &lt;qtd&gt; &lt;contato&gt;";

        $arquivo = $p['fotos'][0]['arquivo'] ?? null;
        $ok = false;
        if ($arquivo) {
            $ok = $bot->enviarFoto($grupoId, $urlRaiz . '/public/uploads/compras/' . $arquivo, $legenda);
        }
        if (!$ok) {
            $bot->enviarMensagem($grupoId, $legenda);
        }
        $responder('Item divulgado no grupo.');
        break;

    case '/divulgar_catalogo':
        if ($chatType !== 'private') {
            $responder('O comando /divulgar_catalogo só funciona na conversa privada com o bot.');
            break;
        }
        if ($grupoId === '') {
            $responder('Configure o ID do grupo em Configurações no painel.');
            break;
        }
        if (count($aVenda) === 0) {
            $responder('Nenhum item à venda para divulgar.');
            break;
        }
        $linhas = ["🛍️ <b>Catálogo VIP Imports</b>\n"];
        foreach ($aVenda as $prod) {
            $linhas[] = '#' . (int) $prod['id'] . ' ' . htmlspecialchars($prod['produto'], ENT_QUOTES)
                . ' — <b>' . $fmt($prod['preco']) . '</b>';
        }
        $linhas[] = "\nPara pedir: /pedido &lt;id&gt; &lt;qtd&gt; &lt;contato&gt;";
        $linhas[] = "Loja completa: {$linkLoja}";
        $bot->enviarMensagem($grupoId, implode("\n", $linhas));
        $responder('Catálogo divulgado no grupo.');
        break;

    default:
        $responder('Não entendi. Envie /ajuda para ver os comandos.');
}

exit('ok');
