<?php
// =====================================================================
// VIP Imports — Controller: Perfil do usuário
// Ações:
//   dados        -> nome e e-mail
//   loja         -> nome da loja + logotipo (upload)
//   remover_logo -> remove o logotipo atual
//   senha        -> troca de senha
// =====================================================================

require_once dirname(__DIR__) . '/model/Usuario.php';
require_once dirname(__DIR__) . '/model/UsuarioConfig.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];

const DIR_LOGOS = __DIR__ . '/../../public/uploads/logos';

function redir(string $query = ''): void
{
    header('Location: ../view/perfil.php' . ($query === '' ? '' : '?' . $query));
    exit;
}

/**
 * Salva o logotipo enviado. Retorna o nome do arquivo ou lança exceção
 * com código de erro amigável.
 */
function salvarLogo(array $arquivo): string
{
    if ((int) $arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE  => 'tamanho',
            UPLOAD_ERR_FORM_SIZE => 'tamanho',
            UPLOAD_ERR_PARTIAL   => 'upload',
            UPLOAD_ERR_NO_FILE   => 'upload',
        ];
        throw new RuntimeException($erros[$arquivo['error']] ?? 'upload');
    }

    if ((int) $arquivo['size'] > 2097152) { // 2 MB
        throw new RuntimeException('tamanho');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($arquivo['tmp_name']);
    $exts  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($exts[$mime])) {
        throw new RuntimeException('formato');
    }

    if (!is_dir(DIR_LOGOS)) {
        mkdir(DIR_LOGOS, 0777, true);
    }

    $nome = 'l_' . bin2hex(random_bytes(8)) . '.' . $exts[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], DIR_LOGOS . '/' . $nome)) {
        throw new RuntimeException('upload');
    }

    return $nome;
}

function apagarLogo(?string $arquivo): void
{
    $arquivo = (string) $arquivo;
    if ($arquivo !== '' && preg_match('/^l_[A-Za-z0-9]+\.(jpg|png|webp)$/', $arquivo)) {
        @unlink(DIR_LOGOS . '/' . $arquivo);
    }
}

$usuarioModel = new Usuario();
$configModel  = new UsuarioConfig();

switch ($_POST['acao'] ?? '') {
    case 'dados':
        $nome  = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($nome === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redir('erro=campos');
        }

        try {
            if ($usuarioModel->emailEmUso($email, $usuarioId)) {
                redir('erro=email');
            }
            $usuarioModel->atualizarDados($usuarioId, $nome, $email);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        $_SESSION['usuario']['nome']  = mb_substr($nome, 0, 120);
        $_SESSION['usuario']['email'] = mb_substr($email, 0, 160);

        redir('msg=dados');
        break;

    case 'loja':
        $lojaNome = trim((string) ($_POST['loja_nome'] ?? ''));
        if (mb_strlen($lojaNome) > 120) {
            $lojaNome = mb_substr($lojaNome, 0, 120);
        }

        $atual = $configModel->garantir($usuarioId);
        $logo  = $atual['loja_logo'] ?? null;

        if (isset($_FILES['logo']) && (int) ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $novo = salvarLogo($_FILES['logo']);
            } catch (RuntimeException $e) {
                redir('erro=' . $e->getMessage());
            }
            apagarLogo($logo);
            $logo = $novo;
        }

        try {
            $configModel->salvarLoja($usuarioId, $lojaNome === '' ? null : $lojaNome, $logo);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        redir('msg=loja');
        break;

    case 'remover_logo':
        $atual = $configModel->garantir($usuarioId);
        apagarLogo($atual['loja_logo'] ?? null);

        try {
            $configModel->salvarLoja($usuarioId, $atual['loja_nome'] ?? null, null);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        redir('msg=logo_removido');
        break;

    case 'senha':
        $atual     = (string) ($_POST['senha_atual'] ?? '');
        $nova      = (string) ($_POST['nova_senha'] ?? '');
        $confirmar = (string) ($_POST['confirmar_senha'] ?? '');

        if ($atual === '' || $nova === '' || $confirmar === '') {
            redir('erro=campos_senha');
        }
        if (strlen($nova) < 8) {
            redir('erro=senha_curta');
        }
        if ($nova !== $confirmar) {
            redir('erro=senha_confirma');
        }

        try {
            if (!$usuarioModel->senhaConfere($usuarioId, $atual)) {
                redir('erro=senha_atual');
            }
            $usuarioModel->atualizarSenha($usuarioId, $nova);
        } catch (Throwable $e) {
            redir('erro=sistema');
        }

        redir('msg=senha');
        break;

    default:
        redir();
}
