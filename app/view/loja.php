<?php
// =====================================================================
// VIP Imports — Loja (vitrine de produtos publicados)
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Loja.php';
require_once dirname(__DIR__) . '/model/Compra.php';
require_once dirname(__DIR__) . '/model/Gasto.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Loja';
$paginaAtual  = 'loja';

$lojaModel   = new Loja();
$compraModel = new Compra();
$gastoModel  = new Gasto();

$badgeLoja    = $lojaModel->contarPublicados($usuarioId);
$badgeCompras = $compraModel->contar($usuarioId);

$mensagens = [
    'publicado'  => ['success', 'Item publicado na loja com sucesso.'],
    'removido'   => ['success', 'Produto removido da loja e devolvido às compras.'],
    'vendido'    => ['success', 'Status de venda atualizado.'],
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

$publicados = $lojaModel->publicados($usuarioId);
$disponiveis = $lojaModel->disponiveisParaPublicar($usuarioId);

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
$sugeridos = $lojaModel->precosSugeridos(array_column($disponiveis, 'id'), $usuarioId);

foreach ($disponiveis as &$item) {
    $item['sugerido']   = $sugeridos[(int) $item['id']]['sugerido'] ?? 0.0;
    $item['parte']      = $sugeridos[(int) $item['id']]['parte'] ?? 0.0;
    $item['custo_item'] = round((float) $item['valor_brl'] * (int) $item['quantidade'], 2);
    $item['custo_viagem'] = $custoViagem[(int) $item['viagem_id']] ?? 0.0;
}
unset($item);

$totalVitrine = array_sum(array_column($publicados, 'preco'));

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
            <h1>Loja</h1>
            <span class="muted small">Publicar itens comprados e definir o preço de venda</span>
          </div>
        </div>

        <?php if ($msgChave !== null): ?>
          <div class="alert alert-<?= $msgChave[0] ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($msgChave[1]) ?></span>
          </div>
        <?php endif; ?>

        <div class="row g-3 mb-3 stat-cards">
          <div class="col-6 col-md-4">
            <div class="kpi darkblue h-100">
              <small>Produtos publicados</small>
              <div class="value" style="font-size:1.3rem;"><?= count($publicados) ?></div>
              <div class="footer">na vitrine</div>
              <i class="fa-solid fa-store ico"></i>
            </div>
          </div>
          <div class="col-6 col-md-4">
            <div class="kpi green h-100">
              <small>Total estimado de vendas</small>
              <div class="value" style="font-size:1.3rem;"><?= $fmt($totalVitrine) ?></div>
              <div class="footer">soma dos preços publicados</div>
              <i class="fa-solid fa-sack-dollar ico"></i>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="kpi blue h-100">
              <small>Disponíveis para publicar</small>
              <div class="value" style="font-size:1.3rem;"><?= count($disponiveis) ?></div>
              <div class="footer">itens comprados ainda na listagem</div>
              <i class="fa-solid fa-boxes-stacked ico"></i>
            </div>
          </div>
        </div>

        <!-- ===================== Publicados na vitrine ===================== -->
        <div class="section-card mb-4">
          <div class="section-head section-head--stack">
            <h5>
              <span class="ico me-2"><i class="fa-solid fa-store"></i></span>
              Produtos na loja
            </h5>
            <div class="section-head-actions">
              <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
                <?= count($publicados) ?> publicado<?= count($publicados) === 1 ? '' : 's' ?>
                <?php $nVendidos = count(array_filter($publicados, fn($x) => $x['vendido'])); if ($nVendidos > 0): ?>
                  · <?= $nVendidos ?> vendido<?= $nVendidos === 1 ? '' : 's' ?>
                <?php endif; ?>
                · <?= $fmt($totalVitrine) ?>
              </span>
            </div>
          </div>

          <?php if (count($publicados) > 0): ?>
          <div class="mb-3" style="max-width:360px;">
            <div class="input-group position-relative">
              <span class="input-group-text bg-transparent border-0 position-absolute"
                    style="top:50%;transform:translateY(-50%);left:.9rem;color:var(--app-text-muted);z-index:5;">
                <i class="fa-solid fa-magnifying-glass"></i>
              </span>
              <input type="search" id="buscaLoja" class="form-control" placeholder="Buscar produto na loja..."
                     style="padding-left:2.6rem;">
            </div>
          </div>
          <?php endif; ?>

          <div class="table-responsive d-none d-md-block">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Descrição</th>
                  <th class="text-end">Preço</th>
                  <th>Viagem</th>
                  <th>Publicado em</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody id="tabelaLojaPublicados">
                <?php if (count($publicados) === 0): ?>
                  <tr data-busca="">
                    <td colspan="6" class="text-center text-muted py-4">
                      <i class="fa-solid fa-store-slash me-1"></i> Nenhum produto publicado ainda.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($publicados as $p): ?>
                    <?php $buscaTxt = mb_strtolower($p['produto'] . ' ' . ($p['marca'] ?? '') . ' ' . ($p['descricao'] ?? '')); ?>
                    <tr data-busca="<?= htmlspecialchars($buscaTxt) ?>">
                      <td>
                        <span class="trip-name"><?= htmlspecialchars($p['produto']) ?></span>
                        <?php if ($p['vendido']): ?>
                          <span class="badge rounded-pill ms-1 align-middle"
                                style="background:rgba(45,144,105,.12);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.68rem;">Vendido</span>
                        <?php endif; ?>
                        <?php if ($p['marca']): ?>
                          <div class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars($p['marca']) ?></div>
                        <?php endif; ?>
                        <?php if (count($p['fotos']) > 0): ?>
                          <div class="text-muted" style="font-size:.72rem;">
                            <i class="fa-solid fa-images"></i> <?= count($p['fotos']) ?> foto<?= count($p['fotos']) === 1 ? '' : 's' ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="text-muted" style="font-size:.82rem;">
                          <?= $p['descricao'] ? nl2br(htmlspecialchars(mb_strimwidth($p['descricao'], 0, 180, '…'))) : '—' ?>
                        </span>
                      </td>
                      <td class="text-end fw-bold" style="white-space:nowrap;">
                        <div><?= $fmt($p['preco']) ?></div>
                        <div class="text-muted" style="font-size:.76rem;font-weight:500;">custo <?= $fmt($p['custo_item']) ?></div>
                      </td>
                      <td class="text-muted"><?= htmlspecialchars($p['viagem_destino'] ?? '—') ?></td>
                      <td class="text-muted">
                        <?= date('d/m/Y', strtotime($p['publicado_em'])) ?>
                        <?php if ($p['vendido_em']): ?>
                          <div style="font-size:.72rem;"><i class="fa-solid fa-check me-1"></i>Vendido em <?= date('d/m/Y', strtotime($p['vendido_em'])) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end text-nowrap">
                        <form action="../controller/LojaController.php" method="post" class="d-inline">
                          <input type="hidden" name="acao" value="marcar_vendido">
                          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                          <input type="hidden" name="vendido" value="<?= $p['vendido'] ? 0 : 1 ?>">
                          <?php if ($p['vendido']): ?>
                            <button type="submit" class="btn btn-sm btn-light border text-muted" title="Desmarcar como vendido">
                              <i class="fa-solid fa-circle-xmark"></i>
                            </button>
                          <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-light border" style="color:var(--app-ok);" title="Marcar como vendido">
                              <i class="fa-solid fa-circle-check"></i>
                            </button>
                          <?php endif; ?>
                        </form>
                        <form action="../controller/LojaController.php" method="post" class="d-inline"
                              onsubmit="return confirm('Remover este produto da loja? Ele volta para a listagem de compras.');">
                          <input type="hidden" name="acao" value="remover">
                          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-light border text-danger" title="Remover da loja">
                            <i class="fa-solid fa-arrow-rotate-left"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-md-none gasto-cards cardsLojaPublicados">
            <?php if (count($publicados) === 0): ?>
              <div class="text-center text-muted py-4">
                <i class="fa-solid fa-store-slash me-1"></i> Nenhum produto publicado ainda.
              </div>
            <?php else: ?>
              <?php foreach ($publicados as $p): ?>
                <?php $buscaTxt = mb_strtolower($p['produto'] . ' ' . ($p['marca'] ?? '') . ' ' . ($p['descricao'] ?? '')); ?>
                <div class="gasto-card" data-busca="<?= htmlspecialchars($buscaTxt) ?>">
                  <div class="gasto-card-top">
                    <div class="gasto-card-main">
                      <div class="gasto-card-desc">
                        <?= htmlspecialchars($p['produto']) ?>
                        <?php if ($p['vendido']): ?>
                          <span class="badge rounded-pill ms-1 align-middle"
                                style="background:rgba(45,144,105,.12);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.64rem;">Vendido</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($p['marca']): ?>
                        <span class="text-muted" style="font-size:.72rem;display:block;"><?= htmlspecialchars($p['marca']) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="gasto-card-date"><?= date('d/m/Y', strtotime($p['publicado_em'])) ?></div>
                  </div>
                  <div class="gasto-card-bottom">
                    <div class="gasto-card-actions">
                      <form action="../controller/LojaController.php" method="post" class="m-0">
                        <input type="hidden" name="acao" value="marcar_vendido">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="vendido" value="<?= $p['vendido'] ? 0 : 1 ?>">
                        <?php if ($p['vendido']): ?>
                          <button type="submit" class="btn btn-sm btn-light border text-muted" title="Desmarcar como vendido">
                            <i class="fa-solid fa-circle-xmark"></i>
                          </button>
                        <?php else: ?>
                          <button type="submit" class="btn btn-sm btn-light border" style="color:var(--app-ok);" title="Marcar como vendido">
                            <i class="fa-solid fa-circle-check"></i>
                          </button>
                        <?php endif; ?>
                      </form>
                      <form action="../controller/LojaController.php" method="post" class="m-0"
                            onsubmit="return confirm('Remover este produto da loja? Ele volta para a listagem de compras.');">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light border text-danger" title="Remover da loja">
                          <i class="fa-solid fa-arrow-rotate-left"></i>
                        </button>
                      </form>
                    </div>
                    <div class="gasto-card-value">
                      <div style="white-space:nowrap;"><?= $fmt($p['preco']) ?></div>
                      <small class="text-muted fw-normal">custo <?= $fmt($p['custo_item']) ?></small>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <script>
        (function () {
          var busca = document.getElementById('buscaLoja');
          if (!busca) return;
          var alvos = document.querySelectorAll('#tabelaLojaPublicados [data-busca], .cardsLojaPublicados [data-busca]');
          busca.addEventListener('input', function () {
            var termo = (busca.value || '').toLowerCase().trim();
            for (var i = 0; i < alvos.length; i++) {
              alvos[i].style.display = alvos[i].getAttribute('data-busca').indexOf(termo) !== -1 ? '' : 'none';
            }
          });
        })();
        </script>

        <!-- ===================== Disponíveis para publicar ===================== -->
        <div class="section-card mb-4">
          <div class="section-head section-head--stack">
            <h5>
              <span class="ico me-2"><i class="fa-solid fa-boxes-stacked"></i></span>
              Disponíveis para publicar
            </h5>
            <div class="section-head-actions">
              <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
                <?= count($disponiveis) ?> item<?= count($disponiveis) === 1 ? '' : 'ns' ?> aguardando
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
                    </div>
                    <div class="gasto-card-date"><?= date('d/m/Y', strtotime($item['data_compra'])) ?></div>
                  </div>
                  <div class="gasto-card-bottom">
                    <div class="gasto-card-actions">
                      <span class="text-muted" style="font-size:.74rem;">
                        <?= htmlspecialchars($item['viagem_destino']) ?> · <?= (int) $item['quantidade'] ?>x
                      </span>
                      <button type="button" class="btn btn-sm btn-brand-blue btn-round fw-semibold btn-publicar w-100 text-nowrap"
                              data-item='<?= $publicarData ?>'>
                        <i class="fa-solid fa-store me-1"></i>Publicar
                      </button>
                    </div>
                    <div class="gasto-card-value">
                      <div style="white-space:nowrap;"><?= $fmt($item['custo_item']) ?></div>
                      <small class="text-muted fw-normal">sugerido <?= $fmt($item['sugerido']) ?></small>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

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
              <form action="../controller/LojaController.php" method="post">
                <input type="hidden" name="acao" value="publicar">
                <input type="hidden" name="compra_id" id="publicarCompraId" value="0">
                <input type="hidden" name="preco_sugerido" id="publicarSugeridoHidden" value="">

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

          btnIa.addEventListener('click', function () {
            if (!atual || !atual.id) return;

            btnIa.disabled = true;
            iaInfo.className = 'text-muted';
            iaInfo.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>IA analisando as fotos... aguarde.';

            var fd = new FormData();
            fd.append('acao', 'descrever');
            fd.append('compra_id', String(atual.id));

            fetch('../controller/LojaController.php', { method: 'POST', body: fd })
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