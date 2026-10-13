<?php
// =====================================================================
// VIP Imports — Controller: Balanço
// Ações:
//   excel -> baixa o relatório de balanço em .xls (compatível com Excel)
// =====================================================================

require_once dirname(__DIR__) . '/model/Balanco.php';
require_once dirname(__DIR__) . '/model/UsuarioConfig.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];
$acao      = (string) ($_GET['acao'] ?? '');

if ($acao !== 'excel') {
    header('Location: ../view/balanco.php');
    exit;
}

$cfgEmpresa  = (new UsuarioConfig())->garantir($usuarioId);
$empresaNome = trim((string) ($cfgEmpresa['loja_nome'] ?? ''));
$empresaNome = $empresaNome === '' ? (string) $_SESSION['usuario']['nome'] : $empresaNome;

$dataIni    = trim((string) ($_GET['data_ini'] ?? ''));
$dataFim    = trim((string) ($_GET['data_fim'] ?? ''));
$viagensSel = isset($_GET['viagens']) && is_array($_GET['viagens'])
    ? array_map('intval', $_GET['viagens'])
    : [];

$relatorio = (new Balanco())->relatorio($usuarioId, $dataIni ?: null, $dataFim ?: null, $viagensSel);
$linhas    = $relatorio['linhas'];
$totais    = $relatorio['totais'];
$filtros   = $relatorio['filtros'];

if (count($filtros['viagens']) > 0) {
    $descricaoFiltro = count($filtros['viagens']) . ' viagem(ns) selecionada(s)';
} elseif ($filtros['data_ini'] || $filtros['data_fim']) {
    $i = $filtros['data_ini'] ? date('d/m/Y', strtotime($filtros['data_ini'])) : '—';
    $f = $filtros['data_fim'] ? date('d/m/Y', strtotime($filtros['data_fim'])) : 'em aberto';
    $descricaoFiltro = 'Período: ' . $i . ' a ' . $f;
} else {
    $descricaoFiltro = 'Todas as viagens';
}

$periodo = function (?string $ini, ?string $fim): string {
    $i = $ini ? date('d/m/Y', strtotime($ini)) : '—';
    $f = $fim ? date('d/m/Y', strtotime($fim)) : 'em aberto';
    return $i . ' a ' . $f;
};

$num  = fn($v) => number_format((float) $v, 2, '.', '');
$num1 = fn($v) => $v === null ? '' : number_format((float) $v, 1, '.', '');

$arquivo = 'balanco_' . ($filtros['data_ini'] ?: 'geral') . '_' . ($filtros['data_fim'] ?: 'geral') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM UTF-8 (acentuação no Excel)
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
  <meta charset="UTF-8">
  <style>
    table { border-collapse: collapse; }
    td, th { border: 1px solid #999; padding: 4px 6px; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    th { background: #0d4fae; color: #fff; font-weight: bold; }
    .titulo { font-size: 14pt; font-weight: bold; border: none; }
    .info { border: none; color: #444; }
    .num { mso-number-format: "\#\,\#\#0\.00"; text-align: right; }
    .total td { font-weight: bold; background: #eef2f9; }
  </style>
</head>
<body>
  <table>
    <tr><td class="titulo" colspan="8"><?= htmlspecialchars($empresaNome) ?> — Balanço</td></tr>
    <tr><td class="info" colspan="8"><?= htmlspecialchars($descricaoFiltro) ?></td></tr>
    <tr><td class="info" colspan="8">Emitido em <?= date('d/m/Y H:i') ?></td></tr>
    <tr><td colspan="8"></td></tr>
  </table>

  <table>
    <thead>
      <tr>
        <th>Viagem</th>
        <th>Período</th>
        <th>Itens vendidos</th>
        <th>Vendas (R$)</th>
        <th>Compras (R$)</th>
        <th>Gastos (R$)</th>
        <th>Custo total (R$)</th>
        <th>Resultado (R$)</th>
        <th>Margem (%)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($linhas as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['destino']) ?></td>
          <td><?= htmlspecialchars($periodo($l['data_inicial'], $l['data_final'])) ?></td>
          <td class="num"><?= (int) $l['qtd_vendas'] ?></td>
          <td class="num"><?= $num($l['receita']) ?></td>
          <td class="num"><?= $num($l['custo_compras']) ?></td>
          <td class="num"><?= $num($l['gastos']) ?></td>
          <td class="num"><?= $num($l['custo_total']) ?></td>
          <td class="num"><?= $num($l['resultado']) ?></td>
          <td class="num"><?= $num1($l['margem']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="total">
        <td>Total</td>
        <td><?= count($linhas) ?> <?= count($linhas) === 1 ? 'viagem' : 'viagens' ?></td>
        <td class="num"><?= (int) $totais['qtd_vendas'] ?></td>
        <td class="num"><?= $num($totais['receita']) ?></td>
        <td class="num"><?= $num($totais['custo_compras']) ?></td>
        <td class="num"><?= $num($totais['gastos']) ?></td>
        <td class="num"><?= $num($totais['custo_total']) ?></td>
        <td class="num"><?= $num($totais['resultado']) ?></td>
        <td class="num"><?= $num1($totais['margem']) ?></td>
      </tr>
    </tfoot>
  </table>
</body>
</html>
