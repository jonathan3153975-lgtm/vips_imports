<?php
// =====================================================================
// VIP Imports — Controller: Configurações
// Ações: salvar_telegram (token/segredo/grupo do bot)
//        registrar_webhook (registra a URL no Telegram)
//        renovar_token (novo link público da loja)
// =====================================================================

require_once dirname(__DIR__) . '/model/UsuarioConfig.php';
require_once dirname(__DIR__) . '/model/Telegram.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];

$abasValidas = ['telegram', 'webhook', 'loja'];
$aba = (string) ($_POST['aba'] ?? $_GET['aba'] ?? 'telegram');
if (!in_array($aba, $abasValidas, true)) {
    $aba = 'telegram';
}

function flash(string $tipo, string $texto): void
{
    $_SESSION['config_flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function redir(string $query = '', string $aba = 'telegram'): void
{
    $abasValidas = ['telegram', 'webhook', 'loja'];
    if (!in_array($aba, $abasValidas, true)) {
        $aba = 'telegram';
    }
    header('Location: ../view/configuracoes.php' . ($query === '' ? '?' : '?' . $query . '&') . 'aba=' . $aba);
    exit;
}

function urlBase(): string
{
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base    = preg_replace('#/app/controller/[^/]+$#', '', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return $esquema . '://' . $host . $base;
}

$model = new UsuarioConfig();

switch ($_POST['acao'] ?? '') {
    case 'salvar_telegram':
        $botToken = trim((string) ($_POST['telegram_bot_token'] ?? ''));
        $segredo  = trim((string) ($_POST['telegram_webhook_secret'] ?? ''));
        $grupo    = trim((string) ($_POST['telegram_group_chat_id'] ?? ''));

        if ($segredo === '') {
            $segredo = bin2hex(random_bytes(16));
        }

        try {
            $model->salvarTelegram(
                $usuarioId,
                $botToken === '' ? null : $botToken,
                $segredo,
                $grupo === '' ? null : $grupo
            );
        } catch (Throwable $e) {
            flash('danger', 'Não foi possível salvar as configurações.');
            redir('erro=sistema', 'telegram');
        }

        flash('success', 'Configurações salvas. Registre o webhook para ativar o bot.');
        redir('msg=salvo', 'telegram');
        break;

    case 'registrar_webhook':
        $cfg = $model->obter($usuarioId);
        if ($cfg === null || empty($cfg['telegram_bot_token']) || empty($cfg['telegram_webhook_secret'])) {
            flash('danger', 'Preencha o token do bot e salve antes de registrar o webhook.');
            redir('erro=webhook', 'webhook');
        }

        $url = urlBase() . '/public/telegram.php?w=' . $cfg['telegram_webhook_secret'];

        try {
            $res = (new Telegram($cfg['telegram_bot_token']))->definirWebhook($url, (string) $cfg['telegram_webhook_secret']);
        } catch (Throwable $e) {
            flash('danger', 'Falha ao contatar o Telegram: ' . $e->getMessage());
            redir('erro=webhook', 'webhook');
        }

        flash($res['ok'] ? 'success' : 'danger', $res['ok'] ? 'Webhook registrado com sucesso.' : ('Erro: ' . $res['mensagem']));
        redir('msg=webhook', 'webhook');
        break;

    case 'renovar_token':
        try {
            $model->renovarToken($usuarioId);
        } catch (Throwable $e) {
            flash('danger', 'Não foi possível gerar um novo link.');
            redir('erro=sistema', $aba);
        }
        flash('success', 'Novo link da loja gerado. O anterior deixou de funcionar.');
        redir('msg=token', $aba);
        break;

    default:
        redir('', $aba);
}
