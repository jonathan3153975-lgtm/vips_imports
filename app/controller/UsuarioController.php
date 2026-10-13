<?php
// =====================================================================
// VIP Imports — Controller: Usuários (gestão restrita ao master)
// Ações:
//   criar         -> cadastra um novo usuário
//   redefinir_senha -> define uma nova senha para um usuário
//   alternar_ativo  -> bloqueia / desbloqueia o acesso
// =====================================================================

require_once dirname(__DIR__) . '/model/Usuario.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

if (($_SESSION['usuario']['perfil'] ?? '') !== 'master') {
    header('Location: ../view/painel.php?erro=sem_permissao');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];

function redir(string $query = ''): void
{
    header('Location: ../view/usuarios.php' . ($query === '' ? '' : '?' . $query));
    exit;
}

$model = new Usuario();

switch ($_POST['acao'] ?? '') {
    case 'criar':
        $nome   = trim((string) ($_POST['nome'] ?? ''));
        $email  = trim((string) ($_POST['email'] ?? ''));
        $senha  = (string) ($_POST['senha'] ?? '');
        $perfil = (string) ($_POST['perfil'] ?? 'usuario');
        $ativo  = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

        if (!in_array($perfil, ['admin', 'usuario'], true)) {
            $perfil = 'usuario';
        }

        if ($nome === '' || $email === '' || $senha === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redir('erro=campos');
        }
        if (strlen($senha) < 8) {
            redir('erro=senha_curta');
        }

        try {
            if ($model->emailEmUso($email)) {
                redir('erro=email');
            }
            $model->criar($nome, $email, $senha, $perfil, $ativo);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        redir('msg=criado');
        break;

    case 'redefinir_senha':
        $id    = (int) ($_POST['id'] ?? 0);
        $senha = (string) ($_POST['nova_senha'] ?? '');

        if ($id <= 0) {
            redir('erro=id');
        }
        if (strlen($senha) < 8) {
            redir('erro=senha_curta');
        }

        try {
            if ($model->buscarPorId($id) === null) {
                redir('erro=id');
            }
            $model->atualizarSenha($id, $senha);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        redir('msg=senha');
        break;

    case 'alternar_ativo':
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redir('erro=id');
        }
        if ($id === $usuarioId) {
            redir('erro=proprio');
        }

        try {
            $alvo = $model->buscarPorId($id);
            if ($alvo === null) {
                redir('erro=id');
            }
            $novo = (int) $alvo['ativo'] === 1 ? 0 : 1;
            $model->definirAtivo($id, $novo);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        redir($novo === 1 ? 'msg=desbloqueado' : 'msg=bloqueado');
        break;

    default:
        redir();
}
