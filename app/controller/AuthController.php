<?php
// =====================================================================
// VIP Imports — Controller: Autenticação
// Ações: login (POST) e logout (GET)
// =====================================================================

require_once dirname(__DIR__) . '/model/Usuario.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'login';

if ($acao === 'logout') {
    encerrarSessao();
    redirecionar('../view/login.php?msg=logout');
}

autenticar();

// ---------------------------------------------------------------------
// Tenta autenticar o usuário com as credenciais enviadas.
// ---------------------------------------------------------------------
function autenticar(): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        redirecionar('../view/login.php?erro=campos');
    }

    try {
        $model   = new Usuario();
        $usuario = $model->buscarPorEmail($email);
    } catch (Throwable $e) {
        redirecionar('../view/login.php?erro=sistema');
    }

    if (
        $usuario === null
        || !password_verify($senha, $usuario['senha'])
    ) {
        redirecionar('../view/login.php?erro=credenciais');
    }

    if ((int) $usuario['ativo'] !== 1) {
        redirecionar('../view/login.php?erro=bloqueado');
    }

    $model->registrarUltimoAcesso((int) $usuario['id']);

    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['id'],
        'nome'   => $usuario['nome'],
        'email'  => $usuario['email'],
        'perfil' => $usuario['perfil'],
    ];

    redirecionar('../view/painel.php');
}

// ---------------------------------------------------------------------
// Encerra a sessão e remove o cookie.
// ---------------------------------------------------------------------
function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

// ---------------------------------------------------------------------
// Envia um redirecionamento e encerra a execução.
// ---------------------------------------------------------------------
function redirecionar(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}
