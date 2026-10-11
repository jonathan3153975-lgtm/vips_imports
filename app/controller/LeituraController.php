<?php
// =====================================================================
// VIP Imports — Controller: Leitura de nota fiscal/recibo por IA
// POST multipart:  imagem=<arquivo>
// Retorna JSON:    { ok: true, dados: {...} }
//                  { ok: false, erro: '...' }
// =====================================================================

require_once dirname(__DIR__) . '/model/LeituraReceita.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'metodo']);
    exit;
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'nao_autenticado']);
    exit;
}

if (!isset($_FILES['imagem'])) {
    echo json_encode(['ok' => false, 'erro' => 'arquivo']);
    exit;
}

$arquivo = $_FILES['imagem'];

if (is_array($arquivo['error'])) {
    echo json_encode(['ok' => false, 'erro' => 'arquivo']);
    exit;
}

if ((int) $arquivo['error'] !== UPLOAD_ERR_OK) {
    $erros = [
        UPLOAD_ERR_INI_SIZE   => 'tamanho',
        UPLOAD_ERR_FORM_SIZE  => 'tamanho',
        UPLOAD_ERR_PARTIAL    => 'upload',
        UPLOAD_ERR_NO_FILE    => 'arquivo',
    ];
    echo json_encode(['ok' => false, 'erro' => $erros[$arquivo['error']] ?? 'upload']);
    exit;
}

if ((int) $arquivo['size'] > LeituraReceita::TAMANHO_MAXIMO) {
    echo json_encode(['ok' => false, 'erro' => 'tamanho']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = (string) $finfo->file($arquivo['tmp_name']);

if (!in_array($mime, LeituraReceita::MIMES_PERMITIDAS, true)) {
    echo json_encode(['ok' => false, 'erro' => 'formato']);
    exit;
}

$binario = file_get_contents($arquivo['tmp_name']);
if ($binario === false || $binario === '') {
    echo json_encode(['ok' => false, 'erro' => 'arquivo']);
    exit;
}

$modo = ($_GET['modo'] ?? 'gasto') === 'itens' ? 'itens' : 'gasto';

// Leitura de imagem pode demorar mais que 30 s (limite padrão do PHP).
if (function_exists('set_time_limit')) {
    set_time_limit(120);
}

try {
    $modelo   = new LeituraReceita();
    $dados    = $modo === 'itens'
        ? $modelo->lerItens(base64_encode($binario), $mime)
        : $modelo->ler(base64_encode($binario), $mime);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'api', 'mensagem' => $e->getMessage()]);
    exit;
}

if ($dados === null) {
    echo json_encode([
        'ok'       => false,
        'erro'     => 'config',
        'mensagem' => 'Chave da API Gemini não configurada. Adicione GEMINI_API_KEY no .env.',
    ]);
    exit;
}

echo json_encode(['ok' => true, 'dados' => $dados]);