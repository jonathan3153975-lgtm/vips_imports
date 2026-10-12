<?php
// =====================================================================
// VIP Imports — Estoque (itens comprados e publicação na loja)
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Estoque.php';
require_once dirname(__DIR__) . '/model/Compra.php';
require_once dirname(__DIR__) . '/model/Gasto.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Estoque';
$paginaAtual  = 'estoque';

$estoqueModel = new Estoque();
$compraModel  = new Compra();
$gastoModel   = new Gasto();

$badgeEstoque = $estoqueModel->contarPublicados($usuarioId);
$badgeCompras = $compraModel->contar($usuarioId);

$mensagens = [
    'publicado'  => ['success', 'Item publicado na loja com sucesso.'],
    'removido'   => ['success', 'Produto removido da loja e devolvido às compras.'],
    'vendido'    => ['success', 'Status de venda atualizado.'],
    'editado'    => ['success', 'Item da loja atualizado com sucesso.'],
    'publicado_a' => ['danger',  'Este item já foi publicado na loja.'],
    'preco'      => ['danger',  'Informe um preço de venda maior que zero.'],
    'sem_fotos'  => ['danger',  'Este item não possui fotos para a IA analisar.'],
    'api'        => ['danger',  'Não foi possível gerar a descrição com a IA. Tente novamente.'],
    'loja'       => ['danger',  'Produto não encontrado na loja.'],
    'campos'     => ['danger',  'Dados inválidos. Verifique e tente novamente.'],
    'sistema'    => ['danger',  'Não foi possível concluir a operação. Tente novamente.'],
];

$roteia = function (array $m, string $chave): string {
    $caps = ['publicado_a' => 'publicado', 'preco' => 'preco', 'sem_fotos' => 'sem_fotos',
             'api' => 'api', 'loja' => 'loja', 'campos' => 'campos', 'sistema' => 'sistema'];
    return $caps[$chave] ?? $chave;
};
$msgChave = $mensagens[$_GET['msg'] ?? ''] ?? null;
if ($msgChave === null && isset($_GET['erro'])) {
    $msgChave = $mensagens[$roteia($mensagens, (string) $_GET['erro'])] ?? null;
}

$fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$publicados = $estoqueModel->publicados($usuarioId);
$disponiveis = $estoqueModel->disponiveisParaPublicar($usuarioId);

$abasPermitidas = ['publicados', 'vendidos', 'disponiveis'];
$aba = $_GET['aba'] ?? $abasPermitidas[2];
if (!in_array($aba, $abasPermitidas, true)) {
    $aba = $abasPermitidas[2];
}

$aVenda   = array_values(array_filter($publicados, fn($x) => !$x['vendido']));
$vendidos = array_values(array_filter($publicados, fn($x) => $x['vendido']));

// Custo total da viagem (gastos + compras) por viagem, para o contexto da publicação.
$custoViagem = [];
foreach ($disponiveis as $item) {
    $vid = (int) $item['viagem_id'];
    if (!isset($custoViagem[$vid])) {
        $custoViagem[$vid] = $gastoModel->totalPorViagem($vid, $usuarioId)
            + $compraModel->totalPorViagem($vid, $usuarioId);
    }
}

// Preços sugeridos proporcionais aos custos da viagem (gastos + compras, + 20%).
$sugeridos = $estoqueModel->precosSugeridos(array_column($disponiveis, 'id'), $usuarioId);

foreach ($disponiveis as &$item) {
    $item['sugerido']   = $sugeridos[(int) $item['id']]['sugerido'] ?? 0.0;
    $item['parte']      = $sugeridos[(int) $item['id']]['parte'] ?? 0.0;
    $item['custo_item'] = round((float) $item['valor_brl'] * (int) $item['quantidade'], 2);
    $item['custo_viagem'] = $custoViagem[(int) $item['viagem_id']] ?? 0.0;
}
unset($item);

// ===================== KPIs do painel =====================
// Estoque (a custo): disponíveis para publicar + publicados ainda não vendidos.
$estoqueValor = array_sum(array_map(fn($x) => (float) $x['custo_item'], $aVenda))
    + array_sum(array_map(fn($x) => (float) $x['custo_item'], $disponiveis));
$estoqueItens   = count($aVenda) + count($disponiveis);

$publicadoValor = array_sum(array_map(fn($x) => (float) $x['preco'], $aVenda));
$publicadoItens = count($aVenda);

$vendidoValor   = array_sum(array_map(fn($x) => (float) $x['preco'], $vendidos));
$vendidoItens   = count($vendidos);

// Lucro líquido = preço de venda − custo direto do item.
$lucroRealizado = array_sum(array_map(fn($x) => (float) $x['preco'] - (float) $x['custo_item'], $vendidos));
$lucroEstimado  = array_sum(array_map(fn($x) => (float) $x['preco'] - (float) $x['custo_item'], $aVenda));

$rotulos = [
    'calca'   => ['Calça', 'fa-shirt'],
    'casaco'  => ['Casaco', 'fa-vest'],
    'jaqueta' => ['Jaqueta', 'fa-person-hiking'],
    'tenis'   => ['Tênis', 'fa-shoe-prints'],
    'outros'  => ['Outros', 'fa-ellipsis'],
];
$categorias = function (string $chave) use ($rotulos): string {
    $semAcento = ['calca' => true, 'casaco' => true, 'jaqueta' => true, 'tenis' => true];
    if (isset($rotulos[$chave])) {
        return $rotulos[$chave][0];
    }
    return trim($chave) === '' ? 'Outros' : $chave;
};

$marcasSugestao = array_values(array_unique(array_merge(
    ['Nike', 'Adidas', 'Puma', "Levi's", 'New Balance', 'The North Face'],
    $compraModel->listarMarcas($usuarioId)
)));
$categoriasSugestao = array_values(array_unique(array_merge(
    array_map(fn($r) => $r[0], $rotulos),
    $compraModel->listarCategorias($usuarioId)
)));

$baseFotos = '../../public/uploads/compras/';

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Estoque</h1>
            <span class="muted small">Seus itens comprados, vendidos e publicados na loja</span>
          </div>
        </div>

        <?php if ($msgChave !== null): ?>
          <div class="alert alert-<?= $msgChave[0] ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($msgChave[1]) ?></span>
          </div>
        <?php endif; ?>

        <div class="row g-3 mb-3 stat-cards">
          <div class="col-6 col-xl">
            <div class="kpi outline h-100">
              <small>Estoque</small>
              <div class="value" style="font-size:1.25rem;"><?= $fmt($estoqueValor) ?></div>
              <div class="footer text-muted"><?= $estoqueItens === 1 ? '1 item em estoque' : $estoqueItens . ' itens em estoque' ?></div>
              <i class="fa-solid fa-boxes-stacked ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl">
            <div class="kpi outline h-100">
              <small>Total publicado</small>
              <div class="value" style="font-size:1.25rem;"><?= $fmt($publicadoValor) ?></div>
              <div class="footer text-muted"><?= $publicadoItens === 1 ? '1 item publicado' : $publicadoItens . ' itens publicados' ?></div>
              <i class="fa-solid fa-store ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl">
            <div class="kpi outline h-100">
              <small>Total vendido</small>
              <div class="value" style="font-size:1.25rem;"><?= $fmt($vendidoValor) ?></div>
              <div class="footer text-muted"><?= $vendidoItens === 1 ? '1 item vendido' : $vendidoItens . ' itens vendidos' ?></div>
              <i class="fa-solid fa-circle-check ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl">
            <div class="kpi outline h-100">
              <small>Lucro líquido</small>
              <div class="value" style="font-size:1.25rem;"><?= $fmt($lucroRealizado) ?></div>
              <div class="footer text-muted">sobre itens já vendidos</div>
              <i class="fa-solid fa-sack-dollar ico"></i>
            </div>
          </div>
          <div class="col-6 col-xl">
            <div class="kpi outline h-100">
              <small>Lucro líquido estimado</small>
              <div class="value" style="font-size:1.25rem;"><?= $fmt($lucroEstimado) ?></div>
              <div class="footer text-muted">itens ainda a vender na loja</div>
              <i class="fa-solid fa-arrow-trend-up ico"></i>
            </div>
          </div>
        </div>

        <!-- ===================== Abas do painel da loja ===================== -->
        <ul class="nav nav-tabs d-none d-md-flex mb-4" id="abasLoja" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'publicados' ? 'active' : '' ?>" data-valor="publicados"
                    data-bs-toggle="tab" data-bs-target="#abaPublicados" type="button" role="tab">
              Publicados na loja
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Itens publicados"
                    style="background:<?= $aba === 'publicados' ? 'var(--brand-blue)' : 'rgba(59,80,124,.12)' ?>;color:<?= $aba === 'publicados' ? '#fff' : 'var(--app-muted)' ?>;font-size:.66rem;">
                <?= count($aVenda) ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'vendidos' ? 'active' : '' ?>" data-valor="vendidos"
                    data-bs-toggle="tab" data-bs-target="#abaVendidos" type="button" role="tab">
              Vendidos
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Itens vendidos"
                    style="background:rgba(45,144,105,.14);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.66rem;">
                <?= count($vendidos) ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'disponiveis' ? 'active' : '' ?>" data-valor="disponiveis"
                    data-bs-toggle="tab" data-bs-target="#abaDisponiveis" type="button" role="tab">
              Em estoque · Disponíveis para publicar
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Itens disponíveis"
                    style="background:rgba(59,80,124,.12);color:var(--app-muted);font-size:.66rem;">
                <?= count($disponiveis) ?>
              </span>
            </button>
          </li>
        </ul>

        <select class="form-select d-md-none mb-3" id="selAbaLoja" aria-label="Seção da loja">
          <option value="publicados" <?= $aba === 'publicados' ? 'selected' : '' ?>>Publicados na loja (<?= count($aVenda) ?>)</option>
          <option value="vendidos" <?= $aba === 'vendidos' ? 'selected' : '' ?>>Vendidos (<?= count($vendidos) ?>)</option>
          <option value="disponiveis" <?= $aba === 'disponiveis' ? 'selected' : '' ?>>Em estoque · Disponíveis para publicar (<?= count($disponiveis) ?>)</option>
        </select>

        <div class="tab-content">

          <div class="tab-pane fade <?= $aba === 'publicados' ? 'show active' : '' ?>" id="abaPublicados" role="tabpanel">
            <?php
            $produtos     = $aVenda;
            $modo         = 'avenda';
            $mostrarBusca = true;
            include __DIR__ . '/partials/_estoque_publicados.php';
            ?>
          </div>

          <div class="tab-pane fade <?= $aba === 'vendidos' ? 'show active' : '' ?>" id="abaVendidos" role="tabpanel">
            <?php
            $produtos     = $vendidos;
            $modo         = 'vendido';
            $mostrarBusca = false;
            include __DIR__ . '/partials/_estoque_publicados.php';
            ?>
          </div>

          <div class="tab-pane fade <?= $aba === 'disponiveis' ? 'show active' : '' ?>" id="abaDisponiveis" role="tabpanel">

          
        <div class="section-card mb-4">
          <div class="section-head section-head--stack">
            <h5>
              <span class="ico me-2"><i class="fa-solid fa-boxes-stacked"></i></span>
              Disponíveis para publicar
            </h5>
            <div class="section-head-actions">
              <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
                <?= count($disponiveis) ?> <?= count($disponiveis) === 1 ? 'item' : 'itens' ?> aguardando
              </span>
            </div>
          </div>

          <div class="table-responsive d-none d-md-block">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Categoria</th>
                  <th>Qtd</th>
                  <th>Viagem</th>
                  <th>Data</th>
                  <th class="text-end">Custo</th>
                  <th class="text-end">Sugerido</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($disponiveis) === 0): ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                      <i class="fa-solid fa-box-open me-1"></i> Todos os itens comprados já foram publicados.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($disponiveis as $item): ?>
                    <?php
                    $publicarData = htmlspecialchars(json_encode([
                        'id'           => (int) $item['id'],
                        'viagem_id'    => (int) $item['viagem_id'],
                        'viagem'       => $item['viagem_destino'],
                        'produto'      => $item['produto'],
                        'marca'        => $item['marca'] ?? '',
                        'categoria'    => $categorias($item['categoria']),
                        'quantidade'   => (int) $item['quantidade'],
                        'custo_item'   => number_format((float) $item['custo_item'], 2, ',', '.'),
                        'custo_viagem' => number_format((float) $item['custo_viagem'], 2, ',', '.'),
                        'parte'        => number_format((float) $item['parte'], 2, ',', '.'),
                        'sugerido'     => number_format((float) $item['sugerido'], 2, ',', '.'),
                        'data'         => date('d/m/Y', strtotime($item['data_compra'])),
                        'loja'         => $item['loja'] ?? '',
                        'fotos'        => array_map(fn($f) => $baseFotos . $f['arquivo'], $item['fotos']),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
                    ?>
                    <tr>
                      <td>
                        <span class="trip-name"><?= htmlspecialchars($item['produto']) ?></span>
                        <?php if ($item['marca']): ?>
                          <div class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars($item['marca']) ?></div>
                        <?php endif; ?>
                        <?php if ($item['loja']): ?>
                          <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($item['loja']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="status-badge status-ativa">
                          <span class="pt"></span><?= htmlspecialchars($categorias($item['categoria'])) ?>
                        </span>
                      </td>
                      <td><?= (int) $item['quantidade'] ?>x</td>
                      <td class="text-muted"><?= htmlspecialchars($item['viagem_destino']) ?></td>
                      <td class="text-muted"><?= date('d/m/Y', strtotime($item['data_compra'])) ?></td>
                      <td class="text-end fw-semibold" style="white-space:nowrap;"><?= $fmt($item['custo_item']) ?></td>
                      <td class="text-end" style="white-space:nowrap;">
                        <span class="badge rounded-pill" style="background:rgba(45,144,105,.12);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);">
                          <?= $fmt($item['sugerido']) ?> <small>(20% lucro)</small>
                        </span>
                      </td>
                      <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-brand-blue btn-round fw-semibold btn-publicar"
                                data-item='<?= $publicarData ?>'>
                          <i class="fa-solid fa-store me-1"></i>Publicar
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-md-none gasto-cards">
            <?php if (count($disponiveis) === 0): ?>
              <div class="text-center text-muted py-4">
                <i class="fa-solid fa-box-open me-1"></i> Todos os itens comprados já foram publicados.
              </div>
            <?php else: ?>
              <?php foreach ($disponiveis as $item): ?>
                <?php
$publicarData = htmlspecialchars(json_encode([
                        'id'           => (int) $item['id'],
                        'viagem_id'    => (int) $item['viagem_id'],
                        'viagem'       => $item['viagem_destino'],
                        'produto'      => $item['produto'],
                        'marca'        => $item['marca'] ?? '',
                        'categoria'    => $categorias($item['categoria']),
                        'quantidade'   => (int) $item['quantidade'],
                        'custo_item'   => number_format((float) $item['custo_item'], 2, ',', '.'),
                        'custo_viagem' => number_format((float) $item['custo_viagem'], 2, ',', '.'),
                        'parte'        => number_format((float) $item['parte'], 2, ',', '.'),
                        'sugerido'     => number_format((float) $item['sugerido'], 2, ',', '.'),
                        'data'         => date('d/m/Y', strtotime($item['data_compra'])),
                        'loja'         => $item['loja'] ?? '',
                        'fotos'        => array_map(fn($f) => $baseFotos . $f['arquivo'], $item['fotos']),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
                    ?>
                <div class="gasto-card">
                  <div class="gasto-card-top">
                    <div class="gasto-card-main">
                      <div class="gasto-card-desc"><?= htmlspecialchars($item['produto']) ?></div>
                      <span class="status-badge status-ativa">
                        <span class="pt"></span><?= htmlspecialchars($categorias($item['categoria'])) ?>
                      </span>
                      <?php if ($item['marca']): ?>
                        <span class="text-muted" style="font-size:.72rem;display:block;"><?= htmlspecialchars($item['marca']) ?></span>
                      <?php endif; ?>
                      <span class="text-muted" style="font-size:.72rem;">
                        <?= htmlspecialchars($item['viagem_destino']) ?> · <?= (int) $item['quantidade'] ?>x
                      </span>
                    </div>
                    <div class="gasto-card-date"><?= date('d/m/Y', strtotime($item['data_compra'])) ?></div>
                  </div>
                  <div class="gasto-card-bottom gasto-card-bottom--stacked">
                    <div class="gasto-card-value">
                      <div style="white-space:nowrap;"><?= $fmt($item['custo_item']) ?></div>
                      <small class="text-muted fw-normal">sugerido <?= $fmt($item['sugerido']) ?></small>
                    </div>
                    <div class="gasto-card-actions">
                      <button type="button" class="btn btn-sm btn-brand-blue btn-round fw-semibold btn-publicar w-100"
                              title="Publicar na loja" data-item='<?= $publicarData ?>'>
                        <i class="fa-solid fa-store"></i>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        </div><!-- /abaDisponiveis -->
        </div><!-- /tab-content -->

        <script>
        (function () {
          var sel = document.getElementById('selAbaLoja');

          function aplicarAba(nome) {
            if (sel) {
              sel.value = nome;
            }
            var inputs = document.querySelectorAll('form input[name="aba"]');
            for (var i = 0; i < inputs.length; i++) {
              inputs[i].value = nome;
            }
          }

          var btns = document.querySelectorAll('#abasLoja [data-bs-toggle="tab"]');
          for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener('shown.bs.tab', function () {
              aplicarAba(this.getAttribute('data-valor'));
            });
          }

          if (sel) {
            sel.addEventListener('change', function () {
              var btn = document.querySelector('#abasLoja [data-valor="' + sel.value + '"]');
              if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
            });
          }

          var busca = document.getElementById('buscaLoja');
          if (busca) {
            var alvos = document.querySelectorAll('#tabelaLojaPublicados [data-busca], .cardsLojaPublicados [data-busca]');
            busca.addEventListener('input', function () {
              var termo = (busca.value || '').toLowerCase().trim();
              for (var i = 0; i < alvos.length; i++) {
                alvos[i].style.display = alvos[i].getAttribute('data-busca').indexOf(termo) !== -1 ? '' : 'none';
              }
            });
          }
        })();
        </script>

        <!-- Marcas e categorias sugeridas (datalist) -->
        <datalist id="marcasSugestoes">
          <?php foreach ($marcasSugestao as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <datalist id="categoriasSugestoes">
          <?php foreach ($categoriasSugestao as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"></option>
          <?php endforeach; ?>
        </datalist>

        <!-- ===================== Modal: publicar na loja ===================== -->
        <div class="modal fade" id="modalPublicar" tabindex="-1" aria-labelledby="modalPublicarLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-br">
              <form action="../controller/EstoqueController.php" method="post">
                <input type="hidden" name="acao" value="publicar">
                <input type="hidden" name="compra_id" id="publicarCompraId" value="0">
                <input type="hidden" name="preco_sugerido" id="publicarSugeridoHidden" value="">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">

                <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title" id="modalPublicarLabel">
                    <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-blue);color:#fff;vertical-align:middle;">
                      <i class="fa-solid fa-store"></i>
                    </span>
                    Publicar na loja
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                  <p class="text-muted small mb-3" id="publicarContexto"></p>

                  <div class="row g-2">
                    <div class="col-md-8 mb-2">
                      <label class="form-label">Produto</label>
                      <input type="text" name="produto" id="publicarProduto" class="form-control login-input" required maxlength="160"
                             placeholder="Ex.: Calça jeans slim, Tênis running...">
                    </div>
                    <div class="col-md-4 mb-2">
                      <label class="form-label">Marca</label>
                      <input type="text" name="marca" id="publicarMarca" list="marcasSugestoes" class="form-control login-input" maxlength="80">
                    </div>
                  </div>

                  <div class="row g-2">
                    <div class="col-md-6 mb-2">
                      <label class="form-label">Categoria</label>
                      <input type="text" name="categoria" id="publicarCategoria" list="categoriasSugestoes"
                             class="form-control login-input" required maxlength="40"
                             placeholder="Ex.: Tênis, Calça, Jaqueta...">
                    </div>
                    <div class="col-md-6 mb-2">
                      <label class="form-label">Preço de venda <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" inputmode="decimal" name="preco" id="publicarPreco"
                               class="form-control login-input" required placeholder="0,00"
                               data-mask-moeda data-mask-int="9">
                      </div>
                      <small class="text-muted" id="publicarSugeridoInfo"></small>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="form-label">Descrição <span class="text-muted">(opcional)</span></label>
                    <textarea name="descricao" id="publicarDescricao" class="form-control login-input" rows="4"
                              maxlength="500" placeholder="Descreva o produto para o anúncio..."></textarea>
                    <small class="text-muted" id="publicarIaInfo">
                      Use a IA para descrever o produto a partir das fotos.
                    </small>
                  </div>

                  <div class="d-flex flex-wrap gap-2 mb-2" id="publicarFotos" style="display:none;"></div>

                  <button type="button" class="btn btn-light border btn-round btn-sm fw-semibold"
                          id="btnGerarDescricao" disabled>
                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Gerar descrição com IA
                  </button>
                </div>

                <div class="modal-footer border-0 pt-0">
                  <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                    <i class="fa-solid fa-store me-1"></i>Publicar na loja
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- ===================== Modal: editar item da loja ===================== -->
        <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-br">
              <form action="../controller/EstoqueController.php" method="post">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editarId" value="0">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">

                <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title" id="modalEditarLabel">
                    <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-blue);color:#fff;vertical-align:middle;">
                      <i class="fa-solid fa-pen"></i>
                    </span>
                    Editar item da loja
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                  <div class="row g-2">
                    <div class="col-md-8 mb-2">
                      <label class="form-label">Produto</label>
                      <input type="text" name="produto" id="editarProduto" class="form-control login-input" required maxlength="160">
                    </div>
                    <div class="col-md-4 mb-2">
                      <label class="form-label">Marca</label>
                      <input type="text" name="marca" id="editarMarca" list="marcasSugestoes" class="form-control login-input" maxlength="80">
                    </div>
                  </div>

                  <div class="row g-2">
                    <div class="col-md-6 mb-2">
                      <label class="form-label">Categoria</label>
                      <input type="text" name="categoria" id="editarCategoria" list="categoriasSugestoes"
                             class="form-control login-input" required maxlength="40">
                    </div>
                    <div class="col-md-6 mb-2">
                      <label class="form-label">Preço de venda <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" inputmode="decimal" name="preco" id="editarPreco"
                               class="form-control login-input" required placeholder="0,00"
                               data-mask-moeda data-mask-int="9">
                      </div>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="form-label">Descrição <span class="text-muted">(opcional)</span></label>
                    <textarea name="descricao" id="editarDescricao" class="form-control login-input" rows="4" maxlength="500"></textarea>
                  </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                  <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Salvar alterações
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <script>
        (function () {
          'use strict';

          function fmtValor(v) {
            var num = Math.round((Number(v) || 0) * 100) / 100;
            var s = Math.abs(num).toFixed(2).replace('.', '');
            var inteiro = String(Number(s.slice(0, -2))).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return (num < 0 ? '-' : '') + inteiro + ',' + s.slice(-2);
          }

          var modalEl   = document.getElementById('modalPublicar');
          var btnIa     = document.getElementById('btnGerarDescricao');
          var iaInfo    = document.getElementById('publicarIaInfo');
          var contexto  = document.getElementById('publicarContexto');
          var fotoGrade = document.getElementById('publicarFotos');
          var sugerido  = document.getElementById('publicarSugeridoInfo');
          var sugeridoH = document.getElementById('publicarSugeridoHidden');
          var atual = null;

          document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.btn-publicar') : null;
            if (!btn) return;

            var item;
            try { item = JSON.parse(btn.getAttribute('data-item') || '{}'); } catch (err) { return; }
            if (!item || !item.id) return;

            atual = item;

            document.getElementById('publicarCompraId').value = item.id;
            document.getElementById('publicarProduto').value = item.produto || '';
            document.getElementById('publicarMarca').value = item.marca || '';
            document.getElementById('publicarCategoria').value = item.categoria || '';
            document.getElementById('publicarDescricao').value = '';
            document.getElementById('publicarPreco').value = '';
            sugeridoH.value = '';

            contexto.innerHTML =
              'Viagem: <strong>' + esc(item.viagem || '\u2014') + '</strong> \u00b7 ' +
              'custo da viagem: <strong>R$ ' + esc(item.custo_viagem || '0,00') + '</strong> \u00b7 ' +
              'parte deste item: <strong>R$ ' + esc(item.parte || '0,00') + '</strong>';

            if (item.sugerido) {
              sugestaoDisponivel(item.sugerido);
            } else {
              sugerido.innerHTML = '';
            }

            var fotos = item.fotos || [];
            fotoGrade.innerHTML = '';
            for (var i = 0; i < fotos.length; i++) {
              var f = document.createElement('img');
              f.src = fotos[i];
              f.alt = 'Foto do item';
              f.style.width = '64px';
              f.style.height = '64px';
              f.style.objectFit = 'cover';
              f.style.borderRadius = '10px';
              f.style.border = '1px solid var(--app-border)';
              fotoGrade.appendChild(f);
            }
            fotoGrade.style.display = fotos.length ? 'flex' : 'none';

            btnIa.disabled = !fotos.length;
            iaInfo.className = 'text-muted';
            iaInfo.textContent = fotos.length
              ? 'A IA vai analisar as fotos e sugerir nome, marca, categoria e uma descrição de venda.'
              : 'Este item não possui fotos — adicione fotos na tela de Compras para usar a IA.';

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
          });

          function sugestaoDisponivel(sugeridoBrl) {
            var link = document.createElement('a');
            link.href = '#';
            link.className = 'link-brand-blue';
            link.textContent = 'Usar sugerido: R$ ' + sugeridoBrl + ' (cobre os custos da viagem + 20% lucro)';
            link.addEventListener('click', function (ev) {
              ev.preventDefault();
              document.getElementById('publicarPreco').value = fmtValor(String(sugeridoBrl).replace(/\./g, '').replace(',', '.'));
              sugeridoH.value = String(sugeridoBrl).replace(/\./g, '').replace(',', '.');
            });
            sugerido.innerHTML = '';
            sugerido.appendChild(link);
          }

          function esc(v) {
            return String(v === undefined || v === null ? '' : v)
              .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
          }

          var modalEditar = document.getElementById('modalEditar');
          document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.btn-editar-item') : null;
            if (!btn) return;

            var item;
            try { item = JSON.parse(btn.getAttribute('data-item') || '{}'); } catch (err) { return; }
            if (!item || !item.id) return;

            document.getElementById('editarId').value        = item.id;
            document.getElementById('editarProduto').value   = item.produto || '';
            document.getElementById('editarMarca').value     = item.marca || '';
            document.getElementById('editarCategoria').value = item.categoria || '';
            document.getElementById('editarDescricao').value = item.descricao || '';
            document.getElementById('editarPreco').value     = fmtValor(item.preco);

            bootstrap.Modal.getOrCreateInstance(modalEditar).show();
          });

          btnIa.addEventListener('click', function () {
            if (!atual || !atual.id) return;

            btnIa.disabled = true;
            iaInfo.className = 'text-muted';
            iaInfo.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>IA analisando as fotos... aguarde.';

            var fd = new FormData();
            fd.append('acao', 'descrever');
            fd.append('compra_id', String(atual.id));

            fetch('../controller/EstoqueController.php', { method: 'POST', body: fd })
              .then(function (r) { return r.json(); })
              .then(function (d) {
                if (d && d.ok) {
                  var dados = d.dados || {};
                  if (dados.produto) document.getElementById('publicarProduto').value = dados.produto;
                  if (dados.marca) document.getElementById('publicarMarca').value = dados.marca;
                  if (dados.categoria) document.getElementById('publicarCategoria').value = dados.categoria;
                  if (dados.descricao) document.getElementById('publicarDescricao').value = dados.descricao;
                  if (d.preco_sugerido && !document.getElementById('publicarPreco').value) {
                    document.getElementById('publicarPreco').value = fmtValor(d.preco_sugerido);
                  }
                  iaInfo.innerHTML = '<i class="fa-solid fa-check me-1"></i>Descrição gerada! Revise antes de publicar.';
                  iaInfo.className = 'text-success';
                } else if (d && d.erro === 'sem_fotos') {
                  iaInfo.textContent = 'Este item não possui fotos para analisar.';
                  iaInfo.className = 'text-danger';
                } else if (d && d.mensagem) {
                  iaInfo.textContent = d.mensagem;
                  iaInfo.className = 'text-danger';
                } else {
                  iaInfo.textContent = 'Não foi possível gerar a descrição. Tente novamente.';
                  iaInfo.className = 'text-danger';
                }
              })
              .catch(function () {
                iaInfo.textContent = 'Falha na conexão com a IA. Verifique a internet.';
                iaInfo.className = 'text-danger';
              })
              .finally(function () { btnIa.disabled = !(atual && atual.fotos && atual.fotos.length); });
          });
        })();
        </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>