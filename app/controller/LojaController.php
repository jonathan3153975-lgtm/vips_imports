<?php
// =====================================================================
// VIP Imports — Controller: Loja (loja virtual)
// Ações: pedido_status (muda o status de um pedido)
//        renovar_token (gera um novo link público da loja)
// =====================================================================

require_once dirname(__DIR__) . '/model/Pedido.php';
require_once dirname(__DIR__) . '/model/UsuarioConfig.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];

$abasValidas = ['produtos', 'pedidos', 'concluidos'];
$aba = (string) ($_POST['aba'] ?? $_GET['aba'] ?? 'produtos');
if (!in_array($aba, $abasValidas, true)) {
    $aba = 'produtos';
}

function redir(string $query, string $aba = 'produtos'): void
{
    $abasValidas = ['produtos', 'pedidos', 'concluidos'];
    if (!in_array($aba, $abasValidas, true)) {
        $aba = 'produtos';
    }
    header('Location: ../view/loja.php' . ($query === '' ? '?' : '?' . $query . '&') . 'aba=' . $aba);
    exit;
}

switch ($_POST['acao'] ?? '') {
    case 'pedido_status':
        $pedidoId = (int) ($_POST['id'] ?? 0);
        $status   = (string) ($_POST['status'] ?? '');

        if ($pedidoId <= 0 || !in_array($status, Pedido::STATUS, true)) {
            redir('erro=campos', $aba);
        }

        try {
            $ok = (new Pedido())->atualizarStatus($pedidoId, $usuarioId, $status);
        } catch (Throwable $e) {
            redir('erro=sistema', $aba);
        }

        redir($ok ? 'msg=status' : 'erro=pedido', 'pedidos');
        break;

    case 'renovar_token':
        try {
            (new UsuarioConfig())->renovarToken($usuarioId);
        } catch (Throwable $e) {
            redir('erro=sistema', $aba);
        }

        redir('msg=token', $aba);
        break;

    default:
        redir('', $aba);
}
