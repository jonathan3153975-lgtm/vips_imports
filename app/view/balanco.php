<?php
// =====================================================================
// VIP Imports — Balanço (relatório financeiro por viagem)
// Filtra por período (data de cada transação) ou por viagens.
// Exporta em PDF (impressão do navegador) e Excel (.xls).
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Balanco.php';
require_once dirname(__DIR__) . '/model/UsuarioConfig.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['master' => 'Master', 'admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Balanço';
$paginaAtual  = 'balanco';

$balancoModel = new Balanco();

// Empresa (nome + logotipo) exibida no relatório.
$cfgEmpresa     = (new UsuarioConfig())->garantir($usuarioId);
$empresaNome    = trim((string) ($cfgEmpresa['loja_nome'] ?? ''));
$empresaNome    = $empresaNome === '' ? $nomeUsuario : $empresaNome;
$empresaArquivo = trim((string) ($cfgEmpresa['loja_logo'] ?? ''));
$empresaLogo    = null;
if ($empresaArquivo !== '' && is_file(dirname(__DIR__, 2) . '/public/uploads/logos/' . $empresaArquivo)) {
    $empresaLogo = '../../public/uploads/logos/' . $empresaArquivo;
}

$dataIni = trim((string) ($_GET['data_ini'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));
$viagensSel = isset($_GET['viagens']) && is_array($_GET['viagens'])
    ? array_map('intval', $_GET['viagens'])
    : [];

$relatorio = $balancoModel->relatorio($usuarioId, $dataIni ?: null, $dataFim ?: null, $viagensSel);
$linhas    = $relatorio['linhas'];
$totais    = $relatorio['totais'];
$filtros   = $relatorio['filtros'];
$viagens   = $balancoModel->listarViagens($usuarioId);

$fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$pct = fn($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%';

$periodo = function (?string $ini, ?string $fim): string {
    $i = $ini ? date('d/m/Y', strtotime($ini)) : '—';
    $f = $fim ? date('d/m/Y', strtotime($fim)) : 'em aberto';
    return $i . ' – ' . $f;
};

// Descrição do filtro aplicado (usada no cabeçalho de impressão).
if (count($filtros['viagens']) > 0) {
    $descricaoFiltro = count($filtros['viagens']) . ' viagem(ns) selecionada(s)';
} elseif ($filtros['data_ini'] || $filtros['data_fim']) {
    $descricaoFiltro = 'Período: ' . $periodo($filtros['data_ini'], $filtros['data_fim']);
} else {
    $descricaoFiltro = 'Todas as viagens';
}

// Link de exportação (Excel) preservando os filtros aplicados.
$qsFiltros = [];
if ($filtros['data_ini']) { $qsFiltros['data_ini'] = $filtros['data_ini']; }
if ($filtros['data_fim']) { $qsFiltros['data_fim'] = $filtros['data_fim']; }
foreach ($filtros['viagens'] as $vid) { $qsFiltros['viagens'][] = $vid; }
$urlExcel = '../controller/BalancoController.php?acao=excel' . ($qsFiltros ? '&' . http_build_query($qsFiltros) : '');

$temViagemSel = count($filtros['viagens']) > 0;

ob_start();
?>
    <style>
      .print-only { display: none; }
      .company-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.1rem; }
      .company-logo { width: 64px; height: 64px; object-fit: cover; border-radius: 14px; box-shadow: 0 6px 16px rgba(0,0,0,.15); }
      .company-head .brand-logo { width: 64px; height: 64px; font-size: 1.7rem; border-radius: 16px; }
      .company-name { margin: 0; font-size: 1.4rem; font-weight: 700; }
      .company-sub { color: var(--app-text-muted, #667); font-size: .85rem; }
      @media print {
        @page { size: A4 landscape; margin: 12mm; }
        body { background: #fff !important; }
        .sidebar, .sidebar-backdrop, .topbar, .bottom-actions, .print-hide { display: none !important; }
        .main-area { margin: 0 !important; }
        .main-content { padding: 0 !important; max-width: 100% !important; }
        .print-only { display: block !important; }
        .section-card { box-shadow: none !important; border: none !important; }
        .table-app tbody tr:hover { background: transparent !important; }
        .table-app thead th { color: #000 !important; }
        .table-app tbody td, .table-app thead th { padding: .35rem .5rem !important; font-size: .8rem !important; white-space: normal !important; }
        .company-head { margin-bottom: .9rem; }
        .company-logo { box-shadow: none !important; }
        .company-name { font-size: 1.2rem; }
        .company-sub { color: #555; }
      }
    </style>
<?php
$headExtra = ob_get_clean();

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2 print-hide">
          <div>
            <h1>Balanço</h1>
            <span class="muted small">Resultado financeiro da empresa por viagem</span>
          </div>
          <div class="actions align-items-center">
            <button type="button" class="btn btn-outline-danger btn-round" id="btnPdf">
              <i class="fa-solid fa-file-pdf me-1"></i>Gerar PDF
            </button>
            <a href="<?= htmlspecialchars($urlExcel) ?>" class="btn btn-brand-blue btn-round">
              <i class="fa-solid fa-file-excel me-1"></i>Baixar Excel
            </a>
          </div>
        </div>

        <!-- Cabeçalho da empresa (tela e impressão) -->
        <div class="company-head">
          <?php if ($empresaLogo !== null): ?>
            <img src="<?= htmlspecialchars($empresaLogo) ?>" class="company-logo" alt="Logotipo de <?= htmlspecialchars($empresaNome) ?>">
          <?php else: ?>
            <span class="brand-logo"><i class="fa-solid fa-building"></i></span>
          <?php endif; ?>
          <div>
            <h2 class="company-name"><?= htmlspecialchars($empresaNome) ?></h2>
            <div class="company-sub">
              Balanço · <?= htmlspecialchars($descricaoFiltro) ?> · Emitido em <?= date('d/m/Y H:i') ?>
            </div>
          </div>
        </div>

        <!-- Filtros -->
        <div class="section-card mb-4 print-hide">
          <div class="section-head section-head--stack">
            <h5><span class="ico me-2"><i class="fa-solid fa-filter"></i></span>Filtros do relatório</h5>
            <div class="section-head-actions">
              <a href="balanco.php" class="btn btn-sm btn-light border btn-round">
                <i class="fa-solid fa-rotate-left me-1"></i>Limpar
              </a>
            </div>
          </div>
          <form method="get" action="balanco.php" class="p-3">
            <div class="row g-3">
              <div class="col-12 col-lg-5">
                <label class="form-label fw-semibold">Período <small class="text-muted fw-normal">(data de cada transação)</small></label>
                <div class="row g-2">
                  <div class="col-6">
                    <input type="date" class="form-control" name="data_ini" id="dataIni"
                           value="<?= htmlspecialchars((string) $dataIni) ?>" aria-label="Data inicial">
                    <small class="text-muted" style="font-size:.72rem;">Inicial</small>
                  </div>
                  <div class="col-6">
                    <input type="date" class="form-control" name="data_fim" id="dataFim"
                           value="<?= htmlspecialchars((string) $dataFim) ?>" aria-label="Data final">
                    <small class="text-muted" style="font-size:.72rem;">Final</small>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                  <span>Viagens <small class="text-muted fw-normal">(tem prioridade sobre o período)</small></span>
                  <?php if (count($viagens) > 0): ?>
                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" id="btnTodasViagens">marcar/desmarcar todas</button>
                  <?php endif; ?>
                </label>
                <?php if (count($viagens) === 0): ?>
                  <div class="text-muted small border rounded p-3">Nenhuma viagem cadastrada ainda.</div>
                <?php else: ?>
                  <div id="listaViagens" class="border rounded p-2" style="max-height:150px;overflow:auto;">
                    <div class="row g-1">
                      <?php foreach ($viagens as $v): $vid = (int) $v['id']; ?>
                        <div class="col-12 col-md-6">
                          <div class="form-check">
                            <input class="form-check-input viagem-check" type="checkbox" name="viagens[]"
                                   id="v<?= $vid ?>" value="<?= $vid ?>"
                                   <?= ($temViagemSel && in_array($vid, $filtros['viagens'], true)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="v<?= $vid ?>" style="font-size:.86rem;">
                              <?= htmlspecialchars($v['destino']) ?>
                              <span class="text-muted" style="font-size:.74rem;">
                                (<?= date('d/m/Y', strtotime($v['data_inicial'])) ?>)
                              </span>
                            </label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-brand-blue btn-round">
                  <i class="fa-solid fa-magnifying-glass me-1"></i>Aplicar filtros
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4 stat-cards">
          <div class="col-6 col-xl-3">
            <div class="kpi blue">
              <small>Receita (vendas)</small>
              <div class="value"><?= $fmt($totais['receita']) ?></div>
              <div class="footer"><?= (int) $totais['qtd_vendas'] ?> <?= $totais['qtd_vendas'] === 1 ? 'item vendido' : 'itens vendidos' ?></div>
              <i class="fa-solid fa-tags ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi red">
              <small>Custo total</small>
              <div class="value"><?= $fmt($totais['custo_total']) ?></div>
              <div class="footer">compras + gastos</div>
              <i class="fa-solid fa-wallet ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi <?= $totais['resultado'] >= 0 ? 'green' : 'red' ?>">
              <small>Resultado</small>
              <div class="value"><?= $fmt($totais['resultado']) ?></div>
              <div class="footer">receita − custo total</div>
              <i class="fa-solid fa-chart-line ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi outline">
              <small>Margem</small>
              <div class="value"><?= $pct($totais['margem']) ?></div>
              <div class="footer">resultado ÷ receita</div>
              <i class="fa-solid fa-percent ico"></i>
            </div>
          </div>
        </div>

        <!-- Tabela -->
        <div class="section-card">
          <div class="section-head print-hide">
            <h5><span class="ico me-2"><i class="fa-solid fa-table"></i></span>Relatório de balanço</h5>
            <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
              <?= count($linhas) ?> <?= count($linhas) === 1 ? 'viagem' : 'viagens' ?>
            </span>
          </div>

          <?php if (count($linhas) === 0): ?>
            <div class="text-center text-muted py-5">
              <i class="fa-solid fa-scale-balanced fa-2x mb-2 d-block"></i>
              Nenhum lançamento encontrado para os filtros selecionados.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-app align-middle" id="tabelaBalanco">
                <thead>
                  <tr>
                    <th>Viagem</th>
                    <th>Período</th>
                    <th class="text-end">Vendas (R$)</th>
                    <th class="text-end">Compras (R$)</th>
                    <th class="text-end">Gastos (R$)</th>
                    <th class="text-end">Custo total (R$)</th>
                    <th class="text-end">Resultado (R$)</th>
                    <th class="text-end">Margem</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($linhas as $l): ?>
                    <tr>
                      <td>
                        <span class="trip-name"><?= htmlspecialchars($l['destino']) ?></span>
                        <div class="text-muted" style="font-size:.72rem;">
                          <?= $l['qtd_vendas'] ?> <?= $l['qtd_vendas'] === 1 ? 'item vendido' : 'itens vendidos' ?>
                          <?= $l['status'] === 'aberta' ? ' · em aberto' : '' ?>
                        </div>
                      </td>
                      <td class="text-muted" style="font-size:.84rem;"><?= $periodo($l['data_inicial'], $l['data_final']) ?></td>
                      <td class="text-end"><?= $fmt($l['receita']) ?></td>
                      <td class="text-end text-muted"><?= $fmt($l['custo_compras']) ?></td>
                      <td class="text-end text-muted"><?= $fmt($l['gastos']) ?></td>
                      <td class="text-end"><?= $fmt($l['custo_total']) ?></td>
                      <td class="text-end fw-bold <?= $l['resultado'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $fmt($l['resultado']) ?>
                      </td>
                      <td class="text-end <?= ($l['margem'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $pct($l['margem']) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="border-top:2px solid var(--app-border);">
                    <td class="fw-bold">Total</td>
                    <td class="text-muted" style="font-size:.82rem;"><?= count($linhas) ?> <?= count($linhas) === 1 ? 'viagem' : 'viagens' ?></td>
                    <td class="text-end fw-bold"><?= $fmt($totais['receita']) ?></td>
                    <td class="text-end fw-bold"><?= $fmt($totais['custo_compras']) ?></td>
                    <td class="text-end fw-bold"><?= $fmt($totais['gastos']) ?></td>
                    <td class="text-end fw-bold"><?= $fmt($totais['custo_total']) ?></td>
                    <td class="text-end fw-bold <?= $totais['resultado'] >= 0 ? 'text-success' : 'text-danger' ?>">
                      <?= $fmt($totais['resultado']) ?>
                    </td>
                    <td class="text-end fw-bold <?= ($totais['margem'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                      <?= $pct($totais['margem']) ?>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <script>
        (function () {
          var btnPdf = document.getElementById('btnPdf');
          if (btnPdf) { btnPdf.addEventListener('click', function () { window.print(); }); }

          var ini = document.getElementById('dataIni');
          var fim = document.getElementById('dataFim');
          var checks = document.querySelectorAll('.viagem-check');

          function sync() {
            var temViagem = false;
            for (var i = 0; i < checks.length; i++) { if (checks[i].checked) { temViagem = true; break; } }
            var temPeriodo = (ini && ini.value) || (fim && fim.value);

            if (ini) ini.disabled = temViagem;
            if (fim) fim.disabled = temViagem;

            for (var j = 0; j < checks.length; j++) {
              if (!temViagem) { checks[j].disabled = !!temPeriodo; }
              else { checks[j].disabled = false; }
            }
          }

          for (var k = 0; k < checks.length; k++) { checks[k].addEventListener('change', sync); }
          if (ini) ini.addEventListener('change', sync);
          if (fim) fim.addEventListener('change', sync);

          var btnTodas = document.getElementById('btnTodasViagens');
          if (btnTodas) {
            btnTodas.addEventListener('click', function () {
              var algum = false;
              for (var i = 0; i < checks.length; i++) { if (checks[i].checked) { algum = true; break; } }
              for (var j = 0; j < checks.length; j++) { checks[j].checked = !algum && !checks[j].disabled; }
              sync();
            });
          }

          sync();
        })();
        </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>
