<?php
// =====================================================================
// VIP Imports — Controller: Gastos
// Ações: criar, excluir
// =====================================================================

require_once dirname(__DIR__) . '/model/Gasto.php';
require_once dirname(__DIR__) . '/model/Cotacao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];
$acao      = $_POST['acao'] ?? $_GET['acao'] ?? '';
$viagemId  = (int) ($_POST['viagem_id'] ?? $_GET['viagem_id'] ?? 0);
$origem    = ($_POST['origem'] ?? $_GET['origem'] ?? 'gastos') === 'viagens' ? 'viagens' : 'gastos';

// Redireciona de volta para a tela de origem, preservando a viagem.
function redir(string $origem, int $viagemId, string $query): void
{
    $sufixo = $query === '' ? '' : '?' . $query;

    if ($origem === 'viagens') {
        header('Location: ../view/viagens.php' . $sufixo);
    } else {
        header('Location: ../view/gastos.php?viagem=' . $viagemId . ($query === '' ? '' : '&' . $query));
    }
    exit;
}

// Converte valores como "1.234,56", "1234.56" ou "R$ 50" em float.
function normalizarValor($entrada): ?float
{
    if (is_array($entrada)) {
        return null;
    }

    $v = trim((string) $entrada);
    if ($v === '') {
        return null;
    }

    $v = str_replace(['R$', ' ', "\xc2\xa0"], '', $v);

    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }

    return is_numeric($v) ? (float) $v : null;
}

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

$model = new Gasto();

switch ($acao) {
    case 'criar':
        $categoria = (string) ($_POST['categoria'] ?? '');
        $valor     = normalizarValor($_POST['valor'] ?? null);
        $dataGasto = dataValida($_POST['data_gasto'] ?? null) ?? date('Y-m-d');
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $descricao = $descricao === '' ? null : $descricao;

        $moeda   = strtoupper((string) ($_POST['moeda'] ?? 'BRL'));
        $moeda   = in_array($moeda, Gasto::MOEDAS, true) ? $moeda : 'BRL';
        $cotacao = normalizarValor($_POST['cotacao'] ?? null);

        if ($viagemId <= 0 || !$model->viagemPertence($viagemId, $usuarioId)) {
            redir($origem, $viagemId, 'erro=sem_viagem');
        }
        if (!in_array($categoria, Gasto::CATEGORIAS, true)) {
            redir($origem, $viagemId, 'erro=categoria');
        }
        if ($valor === null || $valor <= 0) {
            redir($origem, $viagemId, 'erro=valor');
        }

        if ($moeda === 'BRL') {
            $cotacao = 1.0;
        } else {
            // Sem cotação informada? Tenta buscar automaticamente (BCB/PTAX).
            if ($cotacao === null || $cotacao <= 0) {
                try {
                    $auto    = (new Cotacao())->obterUsd();
                    $cotacao = $auto !== null ? (float) $auto['cotacao'] : null;
                } catch (Throwable $e) {
                    $cotacao = null;
                }
            }
            if ($cotacao === null || $cotacao <= 0) {
                redir($origem, $viagemId, 'erro=cotacao');
            }
        }

        try {
            $model->registrar($viagemId, $categoria, $descricao, $valor, $dataGasto, $moeda, $cotacao);
        } catch (Throwable $e) {
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, 'msg=gasto');
        break;

    case 'excluir':
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redir($origem, $viagemId, 'erro=campos');
        }

        try {
            $ok = $model->excluir($id, $usuarioId);
        } catch (Throwable $e) {
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, $ok ? 'msg=excluido' : 'erro=campos');
        break;

    default:
        redir($origem, $viagemId, '');
}
