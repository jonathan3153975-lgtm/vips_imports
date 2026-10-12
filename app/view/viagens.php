<?php
// =====================================================================
// VIP Imports — Viagens
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Viagem.php';
require_once dirname(__DIR__) . '/model/Gasto.php';
require_once dirname(__DIR__) . '/model/Compra.php';
require_once dirname(__DIR__) . '/model/Estoque.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Viagens';
$paginaAtual  = 'viagens';

$viagemModel = new Viagem();
$gastoModel  = new Gasto();
$compraModel = new Compra();

$viagens      = $viagemModel->listarTodas($usuarioId);
$viagemAberta = $viagemModel->viagemAberta($usuarioId);
$badgeViagens = count($viagens);

$editarId = (int) ($_GET['editar'] ?? 0);
$editando = $editarId > 0 ? $viagemModel->buscarPorId($editarId, $usuarioId) : null;

$mensagens = [
    'criada'      => ['success', 'Viagem registrada com sucesso.'],
    'atualizada'  => ['success', 'Viagem atualizada com sucesso.'],
    'finalizada'  => ['success', 'Viagem finalizada com sucesso.'],
    'gasto'       => ['success', 'Gasto registrado com sucesso.'],
    'compra'      => ['success', 'Itens registrados com sucesso.'],
    'excluido'    => ['success', 'Registro excluído com sucesso.'],
    'atualizado'  => ['success', 'Item atualizado com sucesso.'],
    'foto'        => ['success', 'Foto adicionada à galeria.'],
    'foto_removida' => ['success', 'Foto removida.'],
    'campos'      => ['danger',  'Preencha o destino e a data inicial corretamente.'],
    'periodo'     => ['danger',  'A data final não pode ser anterior à data inicial.'],
    'estado'      => ['warning', 'A viagem já está encerrada.'],
    'sistema'     => ['danger',  'Não foi possível concluir a operação. Tente novamente.'],
    'cotacao'     => ['danger',  'Não foi possível obter a cotação do dólar. Informe o valor manualmente.'],
    'sem_viagem'  => ['danger',  'Selecione uma viagem válida para lançar o registo.'],
    'categoria'   => ['danger',  'Categoria inválida.'],
    'valor'       => ['danger',  'Informe pelo menos um valor maior que zero.'],
    'item'        => ['danger',  'Informe pelo menos um item para registrar.'],
    'tamanho'     => ['danger',  'A imagem excede o tamanho máximo (2 MB).'],
    'formato'     => ['danger',  'Formato de imagem não permitido (use JPG, PNG ou WEBP).'],
    'upload'      => ['danger',  'Não foi possível enviar a imagem.'],
];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? $mensagens[$_GET['erro'] ?? ''] ?? null;

$bronze = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$gastos         = [];
$totais         = array_fill_keys(Gasto::CATEGORIAS, 0.0);
$totalGeral     = 0.0;
$compras        = [];
$totaisCompras  = array_fill_keys(Compra::CATEGORIAS, 0.0);
$totalCompras   = 0.0;
$qtdItens       = 0;
$marcasDistintas = 0;
$totalVendido   = 0.0;
if ($viagemAberta) {
    $viagemIdAberta = (int) $viagemAberta['id'];
    $gastos     = $gastoModel->listarPorViagem($viagemIdAberta, $usuarioId);
    $totais     = $gastoModel->totaisPorCategoria($viagemIdAberta, $usuarioId);
    $totalGeral = $gastoModel->totalPorViagem($viagemIdAberta, $usuarioId);

    $compras         = $compraModel->listarPorViagem($viagemIdAberta, $usuarioId);
    $totaisCompras   = $compraModel->totaisPorCategoria($viagemIdAberta, $usuarioId);
    $totalCompras    = $compraModel->totalPorViagem($viagemIdAberta, $usuarioId);
    $qtdItens        = array_sum(array_column($compras, 'quantidade'));
    $marcasDistintas = $compraModel->contarMarcas($viagemIdAberta, $usuarioId);
    $totalVendido    = (new Estoque())->totalVendidoPorViagem($viagemIdAberta, $usuarioId);
}

$totalViagem = $totalGeral + $totalCompras;

$lucro       = $totalVendido - $totalViagem;
$lucroPct    = $totalViagem > 0 ? ($lucro / $totalViagem) * 100 : 0.0;
$lucroClasse = $lucro < 0 ? 'red' : ($lucroPct <= 20 ? 'blue' : 'green');
$lucroPctFmt = number_format($lucroPct, 1, ',', '.') . '%';

$abasViagem = ['gastos', 'compras', 'historico'];
$aba = $_GET['aba'] ?? ($viagemAberta ? 'gastos' : 'historico');
if (!in_array($aba, $abasViagem, true)) {
    $aba = $viagemAberta ? 'gastos' : 'historico';
}

$pctDe = fn($valor) => $totalViagem > 0 ? number_format(($valor / $totalViagem) * 100, 1, ',', '.') . '%' : '0,0%';

$resumoViagem = [
    ['Passagem',     'fa-plane-departure', $totais['passagem']],
    ['Estadia',      'fa-hotel',           $totais['estadia']],
    ['Alimentação',  'fa-utensils',        $totais['alimentacao']],
    ['Outros',       'fa-ellipsis',        $totais['outros']],
    ['Compras',      'fa-cart-shopping',   $totalCompras],
];

$categoriasSugestao = array_values(array_unique(array_merge(
    ['Calça', 'Casaco', 'Jaqueta', 'Tênis', 'Outros'],
    $compraModel->listarCategorias($usuarioId)
)));
$marcasSugestao = array_values(array_unique(array_merge(
    ['Nike', 'Adidas', 'Puma', "Levi's", 'New Balance', 'The North Face'],
    $compraModel->listarMarcas($usuarioId)
)));

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Viagens</h1>
            <span class="muted small">Registre, edite e finalize suas viagens</span>
          </div>
          <div class="actions align-items-center">
            <?php if ($viagemAberta): ?>
              <span class="status-badge status-ativa d-none d-md-inline-flex">
                <span class="pt"></span>Em aberto: <?= htmlspecialchars($viagemAberta['destino']) ?>
              </span>
            <?php endif; ?>
            <?php if ($editando): ?>
              <a href="viagens.php" class="btn btn-brand-blue btn-round">
                <i class="fa-solid fa-plus me-1"></i>Nova viagem
              </a>
            <?php else: ?>
              <button type="button" class="btn btn-brand-blue btn-round" data-bs-toggle="modal" data-bs-target="#modalViagem">
                <i class="fa-solid fa-plus me-1"></i>Registrar viagem
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($msg !== null): ?>
          <div class="alert alert-<?= $msg[0] ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($msg[1]) ?></span>
          </div>
        <?php endif; ?>

        <!-- Cards de resumo da viagem em aberto -->
        <?php if ($viagemAberta): ?>

          <div class="row g-3 mb-3 stat-cards">
            <div class="col-6 col-md-4 col-xl">
              <div class="kpi darkblue h-100">
                <small>Total da viagem</small>
                <div class="value" style="font-size:1.3rem;"><?= $bronze($totalViagem) ?></div>
                <div class="footer">gastos + compras</div>
                <i class="fa-solid fa-route ico"></i>
              </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
              <div class="kpi blue h-100">
                <small>Total de gastos</small>
                <div class="value" style="font-size:1.3rem;"><?= $bronze($totalGeral) ?></div>
                <div class="footer">passagens, estadia, alimentação...</div>
                <i class="fa-solid fa-wallet ico"></i>
              </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
              <div class="kpi green h-100">
                <small>Total de compras</small>
                <div class="value" style="font-size:1.3rem;"><?= $bronze($totalCompras) ?></div>
                <div class="footer"><?= (int) $qtdItens ?> <?= $qtdItens === 1 ? 'item' : 'itens' ?> comprados</div>
                <i class="fa-solid fa-cart-shopping ico"></i>
              </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
              <div class="kpi outline h-100">
                <small>Total vendido</small>
                <div class="value" style="font-size:1.3rem;"><?= $bronze($totalVendido) ?></div>
                <i class="fa-solid fa-arrow-trend-up ico"></i>
              </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
              <div class="kpi <?= $lucroClasse ?> h-100">
                <small>Lucro</small>
                <div class="value" style="font-size:1.3rem;"><?= $bronze($lucro) ?></div>
                <div class="footer text-muted"><?= $lucroPctFmt ?></div>
                <i class="fa-solid fa-sack-dollar ico"></i>
              </div>
            </div>
          </div>

          <!-- Mini-cards: composição (recolhível) -->
          <div class="section-card mb-3">
            <div class="section-head section-head--stack">
              <h5>
                <span class="ico me-2"><i class="fa-solid fa-chart-pie"></i></span>
                Composição da viagem
              </h5>
              <div class="section-head-actions d-flex align-items-center gap-2">
                <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">% do total</span>
                <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-1 collapse-mini"
                        data-bs-toggle="collapse" data-bs-target="#resumoViagem" aria-expanded="true" aria-controls="resumoViagem">
                  <i class="fa-solid fa-chevron-up"></i> Recolher
                </button>
              </div>
            </div>
            <div class="collapse show pb-2" id="resumoViagem">
              <div class="row g-3 stat-cards px-3 pb-1">
                <?php foreach ($resumoViagem as [$nome, $icone, $valor]): ?>
                  <div class="col-6 col-md-4 col-xl">
                    <div class="kpi outline h-100">
                      <small class="text-muted"><?= $nome ?></small>
                      <div class="value" style="font-size:1.1rem;"><?= $bronze($valor) ?></div>
                      <div class="footer">
                        <span class="chip" style="background:rgba(10,132,255,.12);color:var(--brand-blue);">
                          <?= $pctDe($valor) ?>
                        </span>do total
                      </div>
                      <i class="fa-solid <?= $icone ?> ico"></i>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

        <?php endif; ?>

        <!-- ===================== Abas da viagem ===================== -->
        <ul class="nav nav-tabs d-none d-md-flex mb-4" id="abasViagem" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'gastos' ? 'active' : '' ?>" data-valor="gastos"
                    data-bs-toggle="tab" data-bs-target="#abaGastos" type="button" role="tab">
              Gastos da viagem
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Lançamentos de gastos"
                    style="background:rgba(59,80,124,.12);color:var(--app-muted);font-size:.66rem;">
                <?= count($gastos) ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'compras' ? 'active' : '' ?>" data-valor="compras"
                    data-bs-toggle="tab" data-bs-target="#abaCompras" type="button" role="tab">
              Compras
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Itens comprados"
                    style="background:rgba(45,144,105,.14);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.66rem;">
                <?= (int) $qtdItens ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'historico' ? 'active' : '' ?>" data-valor="historico"
                    data-bs-toggle="tab" data-bs-target="#abaHistorico" type="button" role="tab">
              Histórico de viagem
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Viagens registradas"
                    style="background:rgba(59,80,124,.12);color:var(--app-muted);font-size:.66rem;">
                <?= count($viagens) ?>
              </span>
            </button>
          </li>
        </ul>

        <select class="form-select d-md-none mb-3" id="selAbaViagem" aria-label="Seção da viagem">
          <option value="gastos" <?= $aba === 'gastos' ? 'selected' : '' ?>>Gastos da viagem (<?= count($gastos) ?>)</option>
          <option value="compras" <?= $aba === 'compras' ? 'selected' : '' ?>>Compras (<?= (int) $qtdItens ?>)</option>
          <option value="historico" <?= $aba === 'historico' ? 'selected' : '' ?>>Histórico de viagem (<?= count($viagens) ?>)</option>
        </select>

        <div class="tab-content">

          <div class="tab-pane fade <?= $aba === 'gastos' ? 'show active' : '' ?>" id="abaGastos" role="tabpanel">
            <?php if ($viagemAberta): ?>
              <?php
              $viagemSelecionada = $viagemAberta;
              $origem       = 'viagens';
              $mostrarCards = false;
              include __DIR__ . '/partials/_gastos.php';
              ?>
            <?php else: ?>
              <div class="section-card mb-4">
                <div class="p-4 text-center text-muted">
                  <i class="fa-solid fa-lock me-1"></i>
                  Não há viagem em aberto. Use o botão <strong>Registrar viagem</strong> para liberar o lançamento de gastos.
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="tab-pane fade <?= $aba === 'compras' ? 'show active' : '' ?>" id="abaCompras" role="tabpanel">
            <?php if ($viagemAberta): ?>
              <?php
              $viagemSelecionada = $viagemAberta;
              $itens        = $compras;
              $totais       = $totaisCompras;
              $totalGeral   = $totalCompras;
              $origem       = 'viagens';
              $mostrarCards = false;
              $simples      = true;
              include __DIR__ . '/partials/_compras.php';
              ?>
            <?php else: ?>
              <div class="section-card mb-4">
                <div class="p-4 text-center text-muted">
                  <i class="fa-solid fa-lock me-1"></i>
                  Não há viagem em aberto. Use o botão <strong>Registrar viagem</strong> para liberar o lançamento de compras.
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="tab-pane fade <?= $aba === 'historico' ? 'show active' : '' ?>" id="abaHistorico" role="tabpanel">

        <!-- Todas as viagens -->
        <div class="section-card">
          <div class="section-head section-head--stack">
            <h5><span class="ico me-2"><i class="fa-solid fa-list"></i></span>Todas as viagens</h5>
            <div class="section-head-actions">
              <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;"><?= count($viagens) ?> registro<?= count($viagens) === 1 ? '' : 's' ?></span>
            </div>
          </div>
          <!-- Tabela (desktop) -->
          <div class="table-responsive d-none d-md-block">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Viagem</th>
                  <th>Período</th>
                  <th>Status</th>
                  <th class="text-end">Gasto total</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($viagens) === 0): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                      <i class="fa-solid fa-plane-slash me-1"></i> Nenhuma viagem registrada ainda.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($viagens as $v):
                      $aberta  = $v['status'] === 'aberta';
                      $classe  = $aberta ? 'status-ativa' : 'status-concluida';
                      $periodo = date('d/m/Y', strtotime($v['data_inicial']))
                          . ' – ' . ($v['data_final'] ? date('d/m/Y', strtotime($v['data_final'])) : 'em aberto');
                  ?>
                    <tr>
                      <td><span class="trip-name"><?= htmlspecialchars($v['destino']) ?></span></td>
                      <td class="text-muted"><?= $periodo ?></td>
                      <td><span class="status-badge <?= $classe ?>"><span class="pt"></span><?= $aberta ? 'Aberta' : 'Encerrada' ?></span></td>
                      <td class="text-end fw-bold"><?= $bronze($v['total_gasto']) ?></td>
                      <td class="text-end">
                        <a href="gastos.php?viagem=<?= (int) $v['id'] ?>" class="btn btn-sm btn-light border" title="Gastos">
                          <i class="fa-solid fa-coins"></i>
                        </a>
                        <a href="compras.php?viagem=<?= (int) $v['id'] ?>" class="btn btn-sm btn-light border" title="Compras">
                          <i class="fa-solid fa-cart-shopping"></i>
                        </a>
                        <a href="viagens.php?editar=<?= (int) $v['id'] ?>&aba=historico" class="btn btn-sm btn-light border" title="Editar">
                          <i class="fa-solid fa-pen"></i>
                        </a>
                        <?php if ($aberta): ?>
                          <form action="../controller/ViagemController.php" method="post" class="d-inline"
                                onsubmit="return confirm('Finalizar a viagem <?= htmlspecialchars($v['destino'], ENT_QUOTES) ?>?');">
                            <input type="hidden" name="acao" value="finalizar">
                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                            <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Finalizar viagem">
                              <i class="fa-solid fa-flag-checkered"></i>
                            </button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Cards (mobile) -->
          <div class="d-md-none gasto-cards">
            <?php if (count($viagens) === 0): ?>
              <div class="text-center text-muted py-4">
                <i class="fa-solid fa-plane-slash me-1"></i> Nenhuma viagem registrada ainda.
              </div>
            <?php else: ?>
              <?php foreach ($viagens as $v):
                  $aberta  = $v['status'] === 'aberta';
                  $classe  = $aberta ? 'status-ativa' : 'status-concluida';
                  $periodo = date('d/m/Y', strtotime($v['data_inicial']))
                      . ' – ' . ($v['data_final'] ? date('d/m/Y', strtotime($v['data_final'])) : 'em aberto');
              ?>
                <div class="gasto-card">
                  <div class="gasto-card-top">
                    <div class="gasto-card-main">
                      <div class="gasto-card-desc"><?= htmlspecialchars($v['destino']) ?></div>
                      <span class="status-badge <?= $classe ?>"><span class="pt"></span><?= $aberta ? 'Aberta' : 'Encerrada' ?></span>
                    </div>
                    <div class="gasto-card-date gasto-card-date--wrap"><?= $periodo ?></div>
                  </div>
                  <div class="gasto-card-bottom gasto-card-bottom--stacked">
                    <div class="gasto-card-value"><?= $bronze($v['total_gasto']) ?></div>
                    <div class="gasto-card-actions">
                      <a href="gastos.php?viagem=<?= (int) $v['id'] ?>" class="btn btn-sm btn-light border w-100" title="Gastos">
                        <i class="fa-solid fa-coins"></i>
                      </a>
                      <a href="compras.php?viagem=<?= (int) $v['id'] ?>" class="btn btn-sm btn-light border w-100" title="Compras">
                        <i class="fa-solid fa-cart-shopping"></i>
                      </a>
                      <a href="viagens.php?editar=<?= (int) $v['id'] ?>&aba=historico" class="btn btn-sm btn-light border w-100" title="Editar">
                        <i class="fa-solid fa-pen"></i>
                      </a>
                      <?php if ($aberta): ?>
                        <form action="../controller/ViagemController.php" method="post" class="m-0"
                              onsubmit="return confirm('Finalizar a viagem <?= htmlspecialchars($v['destino'], ENT_QUOTES) ?>?');">
                          <input type="hidden" name="acao" value="finalizar">
                          <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                          <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger w-100" title="Finalizar viagem">
                            <i class="fa-solid fa-flag-checkered"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
          </div><!-- /abaHistorico -->

        </div><!-- /tab-content -->

        <!-- Modal: registrar / editar viagem -->
        <div class="modal fade" id="modalViagem" tabindex="-1" aria-labelledby="modalViagemLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-br">
              <form action="../controller/ViagemController.php" method="post">
                <input type="hidden" name="acao" value="<?= $editando ? 'atualizar' : 'criar' ?>">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                <?php if ($editando): ?>
                  <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
                <?php endif; ?>

                <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title" id="modalViagemLabel">
                    <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-blue);color:#fff;vertical-align:middle;">
                      <i class="fa-solid fa-plane-up"></i>
                    </span>
                    <?= $editando ? 'Editar viagem' : 'Nova viagem' ?>
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                  <?php if (!$editando && $viagemAberta): ?>
                    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.86rem;">
                      <i class="fa-solid fa-triangle-exclamation me-1"></i>
                      Registrar uma nova viagem encerrará automaticamente a viagem em aberto
                      (<strong><?= htmlspecialchars($viagemAberta['destino']) ?></strong>).
                    </div>
                  <?php endif; ?>

                  <div class="mb-3">
                    <label class="form-label">Destino</label>
                    <input type="text" name="destino" class="form-control login-input"
                           placeholder="Ex.: Orlando / EUA" maxlength="160" required autofocus
                           value="<?= htmlspecialchars($editando['destino'] ?? '') ?>">
                  </div>
                  <div class="row g-2">
                    <div class="col-6 mb-1">
                      <label class="form-label">Data inicial</label>
                      <input type="date" name="data_inicial" class="form-control login-input" required
                             value="<?= htmlspecialchars($editando['data_inicial'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-6 mb-1">
                      <label class="form-label">Data final <span class="text-muted">(previsão)</span></label>
                      <input type="date" name="data_final" class="form-control login-input"
                             value="<?= htmlspecialchars($editando['data_final'] ?? '') ?>">
                    </div>
                  </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                  <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                    <i class="fa-solid fa-<?= $editando ? 'floppy-disk' : 'circle-plus' ?> me-1"></i>
                    <?= $editando ? 'Salvar alterações' : 'Registrar viagem' ?>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <?php if ($editando): ?>
          <script>
            document.addEventListener('DOMContentLoaded', function () {
              var el = document.getElementById('modalViagem');
              if (el && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(el).show(); }
            });
          </script>
        <?php endif; ?>

        <script>
        (function () {
          var sel = document.getElementById('selAbaViagem');

          function aplicarAba(nome) {
            if (sel) {
              sel.value = nome;
            }
            var inputs = document.querySelectorAll('form input[name="aba"]');
            for (var i = 0; i < inputs.length; i++) {
              inputs[i].value = nome;
            }
          }

          var btns = document.querySelectorAll('#abasViagem [data-bs-toggle="tab"]');
          for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener('shown.bs.tab', function () {
              aplicarAba(this.getAttribute('data-valor'));
            });
          }

          if (sel) {
            sel.addEventListener('change', function () {
              var btn = document.querySelector('#abasViagem [data-valor="' + sel.value + '"]');
              if (btn && window.bootstrap) bootstrap.Tab.getOrCreateInstance(btn).show();
            });
          }
        })();
        </script>

        <script>
          document.addEventListener('DOMContentLoaded', function () {
            var alvo = document.getElementById('resumoViagem');
            var botao = document.querySelector('.collapse-mini');
            if (!alvo || !botao || !window.bootstrap) return;

            alvo.addEventListener('hidden.bs.collapse', function () {
              botao.querySelector('i').className = 'fa-solid fa-chevron-down';
              botao.childNodes[2].nodeValue = ' Expandir';
              botao.setAttribute('aria-expanded', 'false');
            });
            alvo.addEventListener('shown.bs.collapse', function () {
              botao.querySelector('i').className = 'fa-solid fa-chevron-up';
              botao.childNodes[2].nodeValue = ' Recolher';
              botao.setAttribute('aria-expanded', 'true');
            });
          });
        </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>
