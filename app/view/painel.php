<?php
// =====================================================================
// VIP Imports — Painel principal
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Viagem.php';
require_once dirname(__DIR__) . '/model/Compra.php';

$usuario       = $_SESSION['usuario'];
$usuarioId     = (int) $usuario['id'];
$nomeUsuario   = $usuario['nome'];
$emailUsuario  = $usuario['email'];
$perfilLabel   = ['admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo        = 'Painel';
$paginaAtual   = 'painel';

$viagemModel   = new Viagem();
$compraModel   = new Compra();
$viagens       = $viagemModel->listarRecentes($usuarioId, 5);
$badgeViagens  = $viagemModel->contar($usuarioId);
$badgeCompras  = $compraModel->contar($usuarioId);
$totalGastos   = $viagemModel->totalGastos($usuarioId);
$totalCompras  = $compraModel->totalComprado($usuarioId);
$viagemAberta  = $viagemModel->viagemAberta($usuarioId);
$qtdViagens    = count($viagens);

$bronze = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$scriptsExtra = '<script src="../../public/assets/js/chart.js-4.5.1/chart.umd.min.js"></script>';

ob_start();
?>
    <script>
      (function () {
        var dark = document.documentElement.getAttribute("data-bs-theme") === "dark";
        var colors = {
          grid: dark ? "rgba(255,255,255,.06)" : "rgba(13,40,80,.06)",
          text: dark ? "#8fa2c4" : "#6b7a95",
          blue: "#0a84ff", blueDark: "#0d4fae", red: "#e02626", redDark: "#b71c1c"
        };

        function refreshCharts() {
          var vs = Chart.getChart("chartVs");
          var cat = Chart.getChart("chartCat");
          if (vs) vs.destroy();
          if (cat) cat.destroy();

          new Chart(document.getElementById("chartVs"), {
            type: "bar",
            data: {
              labels: ["Mai", "Jun", "Jul", "Ago", "Set", "Out"],
              datasets: [
                { type: "bar", label: "Gastos", data: [2600, 8200, 4500, 8900, 2980, 6200], backgroundColor: function (ctx) {
                    return ctx.dataIndex === 1 || ctx.dataIndex === 3 ? colors.red : colors.red + "88";
                  }, borderRadius: 6, maxBarThickness: 26 },
                { type: "line", label: "Vendas", data: [6900, 18800, 9200, 20310, 8400, 15800],
                  borderColor: colors.blue, backgroundColor: colors.blue, pointBackgroundColor: colors.blue,
                  fill: false, tension: .4, borderWidth: 3, pointRadius: 4, pointHoverRadius: 6 }
              ]
            },
            options: {
              responsive: true, maintainAspectRatio: false,
              plugins: { legend: { labels: { color: colors.text, usePointStyle: true, boxWidth: 8 } } },
              scales: {
                x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
                y: { grid: { color: colors.grid }, ticks: { color: colors.text, callback: function (v) { return "R$" + v / 1000 + "k"; } } }
              }
            }
          });

          var catData = { Transporte: 3200, Hospedagem: 4100, Mercadorias: 3600, "Alimentação": 980, Outros: 600 };
          new Chart(document.getElementById("chartCat"), {
            type: "doughnut",
            data: {
              labels: Object.keys(catData),
              datasets: [{ data: Object.values(catData),
                backgroundColor: [colors.blue, colors.blueDark, colors.red, colors.red + "88", "#cfd8ec"],
                borderWidth: 0, hoverOffset: 6 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: "68%", plugins: { legend: { display: false } } }
          });

          var total = Object.values(catData).reduce(function (a, b) { return a + b; }, 0);
          var legend = Object.keys(catData).map(function (k) {
            var pct = Math.round(catData[k] / total * 100);
            var hex = { Transporte: colors.blue, Hospedagem: colors.blueDark, Mercadorias: colors.red, "Alimentação": colors.red + "88" }[k] || "#cfd8ec";
            return '<span class="lg"><span class="sw" style="background:' + hex + '"></span>' + k +
                   ' <strong class="text-muted">' + pct + '%</strong></span>';
          }).join("");
          document.getElementById("legendCat").innerHTML = legend;
        }

        refreshCharts();
        window.addEventListener("vipThemeChange", refreshCharts);
      })();
    </script>
<?php
$bodyScriptExtra = ob_get_clean();

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Painel geral</h1>
            <span class="muted small">Visão financeira de todas as viagens</span>
          </div>
          <div class="actions desktop-only">
            <a href="balanco.php" class="btn btn-outline-danger btn-round">
              <i class="fa-solid fa-file-arrow-down me-1"></i>Relatório
            </a>
            <a href="viagens.php" class="btn btn-brand-blue btn-round">
              <i class="fa-solid fa-plus me-1"></i>Nova viagem
            </a>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4 stat-cards">
          <div class="col-6 col-xl-3">
            <div class="kpi red">
              <small>Total gasto</small>
              <div class="value"><?= $bronze($totalGastos) ?></div>
              <div class="footer">somatório de gastos das viagens</div>
              <i class="fa-solid fa-wallet ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi blue">
              <small>Total comprado</small>
              <div class="value"><?= $bronze($totalCompras) ?></div>
              <div class="footer">somatório das compras por item</div>
              <i class="fa-solid fa-cart-shopping ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi darkblue">
              <small>Lucro líquido</small>
              <div class="value">R$ 0,00</div>
              <div class="footer">disponível após o módulo de vendas</div>
              <i class="fa-solid fa-chart-line ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi outline">
              <small>Viagens</small>
              <div class="value"><?= $viagemAberta ? 1 : 0 ?></div>
              <div class="footer">
                <span class="chip" style="background:rgba(10,132,255,.12);color:var(--brand-blue);">
                  <?= $viagemAberta ? '1 em aberto' : 'nenhuma em aberto'
                  ?></span> <?= $qtdViagens ?> recente<?= $qtdViagens === 1 ? '' : 's' ?>
              </div>
              <i class="fa-solid fa-plane-up ico"></i>
            </div>
          </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mb-4">
          <div class="col-lg-7">
            <div class="section-card h-100">
              <div class="section-head">
                <h5><span class="ico me-2"><i class="fa-solid fa-chart-line"></i></span>Vendas × Gastos</h5>
                <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">ilustrativo</span>
              </div>
              <div class="card-chart">
                <div class="chart-box"><canvas id="chartVs"></canvas></div>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="section-card h-100">
              <div class="section-head">
                <h5><span class="ico me-2" style="background:var(--brand-red);"><i class="fa-solid fa-chart-pie"></i></span>Gastos por categoria</h5>
                <a href="gastos.php" class="btn btn-sm btn-light border">Ver tudo</a>
              </div>
              <div class="card-chart">
                <div class="chart-box"><canvas id="chartCat"></canvas></div>
              </div>
              <div class="chart-legend" id="legendCat"></div>
            </div>
          </div>
        </div>

        <!-- Últimas viagens -->
        <div class="section-card">
          <div class="section-head">
            <h5><span class="ico me-2"><i class="fa-solid fa-plane"></i></span>Últimas viagens</h5>
            <a href="viagens.php" class="small fw-semibold text-brand-blue" style="text-decoration:none;">Ver todas <i class="fa-solid fa-arrow-right ms-1"></i></a>
          </div>
          <div class="table-responsive">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Viagem</th>
                  <th>Período</th>
                  <th>Status</th>
                  <th class="text-end">Gasto total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($viagens) === 0): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                      <i class="fa-solid fa-plane-slash me-1"></i> Nenhuma viagem registrada ainda.
                      <a href="viagens.php" class="text-brand-blue fw-semibold" style="text-decoration:none;">Registrar a primeira</a>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($viagens as $v):
                      $aberta = $v['status'] === 'aberta';
                      $classe = $aberta ? 'status-ativa' : 'status-concluida';
                      $periodo = date('d/m/Y', strtotime($v['data_inicial']))
                          . ' – ' . ($v['data_final'] ? date('d/m/Y', strtotime($v['data_final'])) : 'em aberto');
                  ?>
                    <tr>
                      <td>
                        <span class="trip-name"><?= htmlspecialchars($v['destino']) ?></span>
                      </td>
                      <td class="text-muted"><?= $periodo ?></td>
                      <td><span class="status-badge <?= $classe ?>"><span class="pt"></span><?= $aberta ? 'Aberta' : 'Encerrada' ?></span></td>
                      <td class="text-end fw-bold"><?= $bronze($v['total_gasto']) ?></td>
                      <td class="text-end">
                        <a href="gastos.php?viagem=<?= (int) $v['id'] ?>" class="btn btn-sm btn-light border" title="Ver gastos">
                          <i class="fa-solid fa-chevron-right"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php include __DIR__ . '/partials/app_foot.php'; ?>
