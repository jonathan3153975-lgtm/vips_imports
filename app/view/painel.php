<?php
// =====================================================================
// VIP Imports — Painel principal (protótipo visual)
// =====================================================================
$paginaAtual = 'painel';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Painel — VIP Imports</title>

  <!-- Libs -->
  <link rel="stylesheet" href="./assets/css/bootstrap-5.3.3/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/fontawesome-6.5.2/css/all.min.css">
  <!-- App -->
  <link rel="stylesheet" href="./assets/css/app/estilo.css">
</head>
<body>

  <div class="app-shell app-body">

    <!-- ============================ Sidebar ============================ -->
    <aside class="sidebar">
      <a href="painel.php" class="sidebar-brand">
        <div class="brand-logo"><i class="fa-solid fa-plane-up"></i></div>
        <div class="texts">
          <strong style="font-size:1.05rem;">VIP Imports</strong>
          <small>Viagens · Compras · Vendas</small>
        </div>
        <button class="btn btn-sm text-light ms-auto mobile-close" type="button" data-sidebar-close title="Fechar menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </a>

      <nav class="sidebar-nav">
        <p class="sidebar-section-label">Menu principal</p>
        <ul class="nav-group">
          <li class="nav-item">
            <a href="painel.php" class="<?= $paginaAtual === 'painel' ? 'active' : '' ?>">
              <i class="fa-solid fa-gauge-high"></i><span>Painel</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" data-alert="Módulo de viagens em construção.">
              <i class="fa-solid fa-plane"></i><span>Viagens</span>
              <span class="badge-opt">3</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" data-alert="Módulo de gastos em construção.">
              <i class="fa-solid fa-coins"></i><span>Gastos</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" data-alert="Módulo de compras em construção.">
              <i class="fa-solid fa-cart-shopping"></i><span>Compras</span>
            </a>
          </li>
        </ul>

        <p class="sidebar-section-label">Vendas &amp; Analítico</p>
        <ul class="nav-group">
          <li class="nav-item">
            <a href="#" data-alert="Módulo de vendas em construção.">
              <i class="fa-solid fa-store"></i><span>Vendas</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" data-alert="Relatórios em construção.">
              <i class="fa-solid fa-chart-column"></i><span>Relatórios</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" data-alert="Configurações em construção.">
              <i class="fa-solid fa-gear"></i><span>Configurações</span>
            </a>
          </li>
        </ul>
      </nav>

      <div class="sidebar-user">
        <span class="avatar">
          <div class="brand-logo" style="width:38px;height:38px;font-size:.95rem;border-radius:50%;">
            <i class="fa-regular fa-user"></i>
          </div>
        </span>
        <div class="meta">
          <strong>Maria Souza</strong>
          <small>maria@vip.app</small>
        </div>
        <button class="logout" type="button" title="Sair" data-alert="Logout em construção.">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </button>
      </div>
    </aside>
    <div class="sidebar-backdrop"></div>

    <!-- ============================ Conteúdo ============================ -->
    <div class="main-area">

      <!-- Topbar -->
      <header class="topbar">
        <button class="icon-btn" type="button" data-sidebar-toggle title="Alternar menu">
          <i class="fa-solid fa-bars"></i>
        </button>

        <div class="search">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-0 position-absolute" style="top:50%;transform:translateY(-50%);left:.9rem;color:var(--app-text-muted);z-index:5;">
              <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="search" class="form-control" placeholder="Buscar viagem, item ou venda..." style="padding-left:2.6rem;">
          </div>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
          <button class="icon-btn" type="button" data-theme-toggle title="Alternar tema claro/noturno">
            <i class="fa-solid fa-sun" data-theme-icon></i>
          </button>
          <button class="icon-btn" type="button" title="Notificações" data-alert="Nenhuma notificação no momento.">
            <i class="fa-regular fa-bell"></i>
            <span class="dot"></span>
          </button>
          <button class="avatar-btn ms-1" type="button" data-alert="Perfil em construção.">
            <span class="avatar">
              <span class="brand-logo" style="width:38px;height:38px;font-size:.95rem;border-radius:50%;">
                <i class="fa-regular fa-user"></i>
              </span>
            </span>
            <span class="meta d-none d-md-block">
              <strong style="font-size:.86rem;">Maria Souza</strong>
              <small>Administradora</small>
            </span>
          </button>
        </div>
      </header>

      <!-- Conteúdo -->
      <main class="main-content">

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Painel geral</h1>
            <span class="muted small">Visão financeira de todas as viagens</span>
          </div>
          <div class="actions desktop-only">
            <button class="btn btn-outline-danger btn-round" type="button" data-alert="Relatório em construção.">
              <i class="fa-solid fa-file-arrow-down me-1"></i>Relatório
            </button>
            <button class="btn btn-brand-blue btn-round" type="button" data-alert="Nova viagem em construção.">
              <i class="fa-solid fa-plus me-1"></i>Nova viagem
            </button>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="kpi red">
              <small>Total gasto</small>
              <div class="value">R$ 12.480,00</div>
              <div class="footer"><span class="chip"><i class="fa-solid fa-arrow-up"></i> 8,4%</span> vs. mês anterior</div>
              <i class="fa-solid fa-wallet ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi blue">
              <small>Total vendido</small>
              <div class="value">R$ 28.930,00</div>
              <div class="footer"><span class="chip"><i class="fa-solid fa-arrow-up"></i> 21,0%</span> vs. mês anterior</div>
              <i class="fa-solid fa-sack-dollar ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi darkblue">
              <small>Lucro líquido</small>
              <div class="value">R$ 16.450,00</div>
              <div class="footer"><span class="chip"><i class="fa-solid fa-arrow-up"></i> 12,6%</span> margem de 56,8%</div>
              <i class="fa-solid fa-chart-line ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="kpi outline">
              <small>Viagens ativas</small>
              <div class="value">3</div>
              <div class="footer"><span class="chip" style="background:rgba(10,132,255,.12);color:var(--brand-blue);">2 planejadas</span> 1 em andamento</div>
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
                <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">últimos 6 meses</span>
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
                <button class="btn btn-sm btn-light border" type="button" data-alert="Detalhe de gastos em construção.">Ver tudo</button>
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
            <a href="#" class="small fw-semibold text-brand-blue" style="text-decoration:none;" data-alert="Lista completa em construção.">Ver todas <i class="fa-solid fa-arrow-right ms-1"></i></a>
          </div>
          <div class="table-responsive">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Viagem</th>
                  <th>Período</th>
                  <th>Status</th>
                  <th class="text-end">Gasto</th>
                  <th class="text-end">Vendido</th>
                  <th class="text-end">Lucro</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php
                $viagens = [
                  ['Orlando / EUA', '12 Fev – 02 Mar', 'ativa',      'Já concluída', 'R$ 6.220',   'R$ 15.800',  'R$ 9.580',  'text-success'],
                  ['Nova Iorque / EUA', '05 Abr – 25 Abr', 'planejada', 'Planejada',    'R$ 4.500',   '—',          '—',          'text-muted'],
                  ['Cidade do México / MX', '20 Set – 05 Out', 'concluida', 'Concluída',    'R$ 8.940',   'R$ 20.310',  'R$ 11.370', 'text-success'],
                  ['Lisboa / PT', '08 Mai – 22 Mai', 'planejada', 'Planejada',    'R$ 2.980',   '—',          '—',          'text-muted'],
                ];
                foreach ($viagens as $v) {
                  $statusClass = $v[2] === 'ativa' ? 'status-ativa' : ($v[2] === 'concluida' ? 'status-concluida' : 'status-planejada');
                  echo '<tr>';
                  echo '<td><span class="trip-name">' . $v[0] . '</span><br><small class="text-muted">' . $v[3] . '</small></td>';
                  echo '<td class="text-muted">' . $v[1] . '</td>';
                  echo '<td><span class="status-badge ' . $statusClass . '"><span class="pt"></span>' . ucfirst($v[2]) . '</span></td>';
                  echo '<td class="text-end">' . $v[4] . '</td>';
                  echo '<td class="text-end">' . $v[5] . '</td>';
                  echo '<td class="text-end fw-bold ' . $v[7] . '">' . $v[6] . '</td>';
                  echo '<td class="text-end"><button class="btn btn-sm btn-light border" type="button" data-alert="Detalhes da viagem em construção."><i class="fa-solid fa-chevron-right"></i></button></td>';
                  echo '</tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>
    </div>

    <!-- ============================ Barra de ações (mobile) ============================ -->
    <nav class="bottom-actions">
      <button class="bar-btn" type="button" data-bar="viagem" data-alert="Nova viagem em construção.">
        <i class="fa-solid fa-plane-up"></i>Viagem
      </button>
      <button class="bar-btn danger" type="button" data-bar="gasto" data-alert="Novo gasto em construção.">
        <i class="fa-solid fa-coins"></i>Gasto
      </button>
      <button class="bar-btn home" type="button" data-bar="painel" title="Painel" onclick="window.scrollTo({top:0,behavior:'smooth'});">
        <i class="fa-solid fa-house-chimney"></i>
        <span style="font-size:.6rem;">Início</span>
      </button>
      <button class="bar-btn primary" type="button" data-bar="compra" data-alert="Nova compra em construção.">
        <i class="fa-solid fa-cart-shopping"></i>Compra
      </button>
      <button class="bar-btn" type="button" data-bar="venda" data-alert="Nova venda em construção.">
        <i class="fa-solid fa-store"></i>Venda
      </button>
    </nav>
  </div>

  <!-- Libs -->
  <script src="./assets/js/jquery-3.7.1/jquery.min.js"></script>
  <script src="./assets/js/bootstrap-5.3.3/bootstrap.bundle.min.js"></script>
  <script src="./assets/js/chart.js-4.5.1/chart.umd.min.js"></script>
  <!-- App -->
  <script src="./assets/js/app/app.js"></script>

  <script>
    (function () {
      var dark = document.documentElement.getAttribute("data-bs-theme") === "dark";
      var colors = {
        grid: dark ? "rgba(255,255,255,.06)" : "rgba(13,40,80,.06)",
        text: dark ? "#8fa2c4" : "#6b7a95",
        blue: "#0a84ff",
        blueDark: "#0d4fae",
        red: "#e02626",
        redDark: "#b71c1c"
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
            plugins: {
              legend: { labels: { color: colors.text, usePointStyle: true, boxWidth: 8 } }
            },
            scales: {
              x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
              y: { grid: { color: colors.grid }, ticks: { color: colors.text, callback: function (v) { return "R$" + v / 1000 + "k"; } } }
            }
          }
        });

        var catData = { Transporte: 3200, Hospedagem: 4100, Mercadorias: 3600, Alimentação: 980, Outros: 600 };
        new Chart(document.getElementById("chartCat"), {
          type: "doughnut",
          data: {
            labels: Object.keys(catData),
            datasets: [{
              data: Object.values(catData),
              backgroundColor: [colors.blue, colors.blueDark, colors.red, colors.red + "88", "#cfd8ec"],
              borderWidth: 0, hoverOffset: 6
            }]
          },
          options: {
            responsive: true, maintainAspectRatio: false, cutout: "68%",
            plugins: { legend: { display: false } }
          }
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
</body>
</html>