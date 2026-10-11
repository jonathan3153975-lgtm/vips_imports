<?php
// =====================================================================
// VIP Imports — Controller: Viagens
// Ações: criar, atualizar, finalizar
// =====================================================================

require_once dirname(__DIR__) . '/model/Viagem.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];
$acao      = $_POST['acao'] ?? $_GET['acao'] ?? '';

// Redireciona de volta para a tela de viagens.
function voltar(string $query = ''): void
{
    header('Location: ../view/viagens.php' . $query);
    exit;
}

// Valida uma data no formato Y-m-d. Retorna null se inválida.
function dataValida(?string $data): ?string
{
    $data = trim((string) $data);
    if ($data === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $data);
    if ($dt === false || $dt->format('Y-m-d') !== $data) {
        return null;
    }

    return $data;
}

switch ($acao) {
    case 'criar':
        $destino     = trim((string) ($_POST['destino'] ?? ''));
        $dataInicial = dataValida($_POST['data_inicial'] ?? null);
        $dataFinal   = ($_POST['data_final'] ?? '') === ''
            ? null
            : dataValida($_POST['data_final'] ?? null);

        if ($destino === '' || $dataInicial === null) {
            voltar('?erro=campos');
        }
        if (($_POST['data_final'] ?? '') !== '' && $dataFinal === null) {
            voltar('?erro=campos');
        }
        if ($dataFinal !== null && $dataFinal < $dataInicial) {
            voltar('?erro=periodo');
        }

        try {
            (new Viagem())->criar($usuarioId, $destino, $dataInicial, $dataFinal);
        } catch (Throwable $e) {
            voltar('?erro=sistema');
        }

        voltar('?msg=criada');
        break;

    case 'atualizar':
        $id          = (int) ($_POST['id'] ?? 0);
        $destino     = trim((string) ($_POST['destino'] ?? ''));
        $dataInicial = dataValida($_POST['data_inicial'] ?? null);
        $dataFinal   = ($_POST['data_final'] ?? '') === ''
            ? null
            : dataValida($_POST['data_final'] ?? null);

        if ($id <= 0 || $destino === '' || $dataInicial === null) {
            voltar('?erro=campos');
        }
        if (($_POST['data_final'] ?? '') !== '' && $dataFinal === null) {
            voltar('?erro=campos');
        }
        if ($dataFinal !== null && $dataFinal < $dataInicial) {
            voltar('?erro=periodo');
        }

        try {
            (new Viagem())->atualizar($id, $usuarioId, $destino, $dataInicial, $dataFinal);
        } catch (Throwable $e) {
            voltar('?erro=sistema');
        }

        voltar('?msg=atualizada');
        break;

    case 'finalizar':
        $id        = (int) ($_POST['id'] ?? 0);
        $dataFinal = ($_POST['data_final'] ?? '') === ''
            ? null
            : dataValida($_POST['data_final'] ?? null);

        if ($id <= 0) {
            voltar('?erro=campos');
        }
        if (($_POST['data_final'] ?? '') !== '' && $dataFinal === null) {
            voltar('?erro=campos');
        }

        try {
            $ok = (new Viagem())->finalizar($id, $usuarioId, $dataFinal);
        } catch (Throwable $e) {
            voltar('?erro=sistema');
        }

        voltar($ok ? '?msg=finalizada' : '?erro=estado');
        break;

    default:
        voltar();
}
