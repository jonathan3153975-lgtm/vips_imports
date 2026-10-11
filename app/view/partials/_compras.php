<?php
// =====================================================================
// VIP Imports — Partial: compras de uma viagem
// Espera: $viagemSelecionada (array), $itens (array), $totais (array),
//         $totalGeral (float), $qtdItens (int), $marcasDistintas (int)
// =====================================================================
$rotulos = [
    'calca'   => ['Calça', 'fa-shirt'],
    'casaco'  => ['Casaco', 'fa-vest'],
    'jaqueta' => ['Jaqueta', 'fa-person-hiking'],
    'tenis'   => ['Tênis', 'fa-shoe-prints'],
    'outros'  => ['Outros', 'fa-ellipsis'],
];
$viagemId  = (int) $viagemSelecionada['id'];
$moeda     = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$fmt       = fn($cod, $v) => ($cod === 'USD' ? 'US$ ' : 'R$ ') . number_format((float) $v, 2, ',', '.');
$baseFotos = '../../public/uploads/compras/';
$custoViagem = $custoViagem ?? 0.0;
$totalVendas = $totalVendas ?? 0.0;
$saldoViagem = $saldoViagem ?? 0.0;
$marcasSugestao     = $marcasSugestao ?? [];
$categoriasSugestao = $categoriasSugestao ?? [];
$origem        = $origem ?? 'compras';
$mostrarCards  = $mostrarCards ?? true;
$simples       = $simples ?? false;
?>

<!-- Cards de somatório (viram lista no mobile) -->
<?php if ($mostrarCards): ?>
<div class="row g-3 mb-3 stat-cards">
  <div class="col-6 col-md-4 col-xl-3">
    <div class="kpi darkblue h-100">
      <small>Total de compras</small>
      <div class="value" style="font-size:1.3rem;"><?= $moeda($totalGeral) ?></div>
      <div class="footer"><?= (int) $qtdItens ?> item<?= $qtdItens === 1 ? '' : 'ns' ?></div>
      <i class="fa-solid fa-cart-shopping ico"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="kpi blue h-100">
      <small>Custo da viagem</small>
      <div class="value" style="font-size:1.3rem;"><?= $moeda($custoViagem) ?></div>
      <div class="footer">passagens, estadia, alimentação...</div>
      <i class="fa-solid fa-plane ico"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="kpi green h-100">
      <small>Total de vendas</small>
      <div class="value" style="font-size:1.3rem;"><?= $moeda($totalVendas) ?></div>
      <div class="footer">disponível após o módulo de vendas</div>
      <i class="fa-solid fa-sack-dollar ico"></i>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="kpi <?= $saldoViagem >= 0 ? 'green' : 'outline-danger' ?> h-100">
      <small>Saldo da viagem</small>
      <div class="value" style="font-size:1.3rem;"><?= $moeda($saldoViagem) ?></div>
      <div class="footer">vendas − (custo viagem + compras)</div>
      <i class="fa-solid fa-scale-balanced ico"></i>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-head section-head--stack">
    <h5>
      <span class="ico me-2"><i class="fa-solid fa-boxes-stacked"></i></span>
      Compras de <?= htmlspecialchars($viagemSelecionada['destino']) ?>
    </h5>
    <div class="section-head-actions d-flex align-items-center gap-2">
      <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
        <?= (int) $qtdItens ?> item<?= $qtdItens === 1 ? '' : 's' ?>
        · <?= (int) $marcasDistintas ?> marca<?= $marcasDistintas === 1 ? '' : 's' ?>
      </span>
      <button type="button" class="btn btn-brand-red btn-round btn-sm fw-semibold"
              data-bs-toggle="modal" data-bs-target="#modalCompra">
        <i class="fa-solid fa-plus me-1"></i>Registrar compra
      </button>
    </div>
  </div>

  <!-- Tabela (desktop) -->
  <div class="table-responsive d-none d-md-block">
    <table class="table table-app align-middle">
      <thead>
        <tr>
          <th>Produto</th>
          <th>Categoria</th>
          <?php if (!$simples): ?><th>Loja</th><?php endif; ?>
          <th>Qtd</th>
          <th>Data</th>
          <th class="text-end">Valor</th>
          <?php if (!$simples): ?><th>Fotos</th><?php endif; ?>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($itens) === 0): ?>
          <tr>
            <td colspan="<?= $simples ? 6 : 8 ?>" class="text-center text-muted py-4">
              <i class="fa-solid fa-box-open me-1"></i> Nenhum item comprado nesta viagem.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($itens as $i): ?>
            <?php if (!$simples):
              $nFotos = count($i['fotos']);
              $fotosJson = json_encode(array_map(fn($f) => [
                  'id'  => (int) $f['id'],
                  'src' => $baseFotos . $f['arquivo'],
              ], $i['fotos']), JSON_UNESCAPED_SLASHES);
              $editarData = htmlspecialchars(json_encode([
                  'id'        => (int) $i['id'],
                  'viagem_id' => (int) $i['viagem_id'],
                  'produto'   => $i['produto'],
                  'marca'     => $i['marca'] ?? '',
                  'categoria' => $i['categoria'],
                  'quantidade'=> (int) $i['quantidade'],
                  'valor'     => number_format((float) $i['valor'], 2, ',', '.'),
                  'moeda'     => $i['moeda'],
                  'cotacao'   => $i['moeda'] === 'USD' ? number_format((float) $i['cotacao'], 4, ',', '.') : '',
                  'loja'      => $i['loja'] ?? '',
                  'data'      => $i['data_compra'],
                  'obs'       => $i['observacoes'] ?? '',
              ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            endif; ?>
            <tr>
              <td>
                <span class="trip-name"><?= htmlspecialchars($i['produto']) ?></span>
                <?php if ($i['marca']): ?>
                  <div class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars($i['marca']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-badge status-ativa">
                  <span class="pt"></span><?= $rotulos[$i['categoria']][0] ?? $i['categoria'] ?>
                </span>
              </td>
              <?php if (!$simples): ?><td class="text-muted"><?= htmlspecialchars($i['loja'] ?? '—') ?></td><?php endif; ?>
              <td><?= (int) $i['quantidade'] ?>x</td>
              <td class="text-muted"><?= date('d/m/Y', strtotime($i['data_compra'])) ?></td>
              <td class="text-end fw-bold" style="white-space:nowrap;">
                <?= $fmt($i['moeda'], $i['valor']) ?>
                <?php if ($i['moeda'] === 'USD'): ?>
                  <div class="text-muted" style="font-size:.72rem;font-weight:500;">≈ <?= $moeda($i['valor_brl']) ?></div>
                <?php endif; ?>
              </td>
              <?php if (!$simples): ?>
              <td>
                <button type="button" class="btn btn-sm btn-light border btn-fotos"
                        data-bs-toggle="modal" data-bs-target="#modalFotos"
                        data-nome="<?= htmlspecialchars($i['produto'], ENT_QUOTES) ?>"
                        data-id="<?= (int) $i['id'] ?>"
                        data-fotos='<?= $fotosJson ?>'>
                  <i class="fa-solid fa-images me-1"></i><?= $nFotos ?>
                </button>
              </td>
              <?php endif; ?>
              <td class="text-end text-nowrap">
                <?php if (!$simples): ?>
                <button type="button" class="btn btn-sm btn-light border btn-editar" title="Editar item"
                        data-item='<?= $editarData ?>'>
                  <i class="fa-solid fa-pen"></i>
                </button>
                <?php endif; ?>
                <form action="../controller/CompraController.php" method="post" class="d-inline"
                      onsubmit="return confirm('Excluir este item?');">
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="origem" value="<?= htmlspecialchars($origem) ?>">
                  <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                  <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">
                  <button type="submit" class="btn btn-sm btn-light border text-danger" title="Excluir item">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Cards (mobile) -->
  <div class="d-md-none gasto-cards">
    <?php if (count($itens) === 0): ?>
      <div class="text-center text-muted py-4">
        <i class="fa-solid fa-box-open me-1"></i> Nenhum item comprado nesta viagem.
      </div>
    <?php else: ?>
      <?php foreach ($itens as $i): ?>
        <?php if (!$simples):
          $nFotos = count($i['fotos']);
          $fotosJson = json_encode(array_map(fn($f) => [
              'id'  => (int) $f['id'],
              'src' => $baseFotos . $f['arquivo'],
          ], $i['fotos']), JSON_UNESCAPED_SLASHES);
          $editarData = htmlspecialchars(json_encode([
              'id'        => (int) $i['id'],
              'viagem_id' => (int) $i['viagem_id'],
              'produto'   => $i['produto'],
              'marca'     => $i['marca'] ?? '',
              'categoria' => $i['categoria'],
              'quantidade'=> (int) $i['quantidade'],
              'valor'     => number_format((float) $i['valor'], 2, ',', '.'),
              'moeda'     => $i['moeda'],
              'cotacao'   => $i['moeda'] === 'USD' ? number_format((float) $i['cotacao'], 4, ',', '.') : '',
              'loja'      => $i['loja'] ?? '',
              'data'      => $i['data_compra'],
              'obs'       => $i['observacoes'] ?? '',
          ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        endif; ?>
        <div class="gasto-card">
          <div class="gasto-card-top">
            <div class="gasto-card-main">
              <div class="gasto-card-desc"><?= htmlspecialchars($i['produto']) ?></div>
              <span class="status-badge status-ativa">
                <span class="pt"></span><?= $rotulos[$i['categoria']][0] ?? $i['categoria'] ?>
              </span>
              <?php if ($i['marca']): ?>
                <span class="text-muted" style="font-size:.72rem;display:block;"><?= htmlspecialchars($i['marca']) ?></span>
              <?php endif; ?>
            </div>
            <div class="gasto-card-date"><?= date('d/m/Y', strtotime($i['data_compra'])) ?></div>
          </div>
          <div class="gasto-card-bottom gasto-card-bottom--stacked">
            <div class="gasto-card-actions">
              <span class="text-muted" style="font-size:.74rem;">
                <?= htmlspecialchars($i['loja'] ?? 'Sem loja') ?> · <?= (int) $i['quantidade'] ?>x
              </span>
              <?php if (!$simples): ?>
              <button type="button" class="btn btn-sm btn-light border btn-fotos"
                      data-bs-toggle="modal" data-bs-target="#modalFotos"
                      data-nome="<?= htmlspecialchars($i['produto'], ENT_QUOTES) ?>"
                      data-id="<?= (int) $i['id'] ?>"
                      data-fotos='<?= $fotosJson ?>'>
                <i class="fa-solid fa-images me-1"></i>Fotos (<?= $nFotos ?>)
              </button>
              <button type="button" class="btn btn-sm btn-light border btn-editar" data-item='<?= $editarData ?>'>
                <i class="fa-solid fa-pen me-1"></i>Editar
              </button>
              <?php endif; ?>
              <form action="../controller/CompraController.php" method="post" class="m-0"
                    onsubmit="return confirm('Excluir este item?');">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="origem" value="<?= htmlspecialchars($origem) ?>">
                <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">
                <button type="submit" class="btn btn-sm btn-light border text-danger w-100 text-nowrap">
                  <i class="fa-solid fa-trash me-1"></i>Excluir
                </button>
              </form>
            </div>
            <div class="gasto-card-value">
              <div style="white-space:nowrap;"><?= $fmt($i['moeda'], $i['valor']) ?>
                <?php if ($i['moeda'] === 'USD'): ?>
                  <small class="text-muted fw-normal">≈ <?= $moeda($i['valor_brl']) ?></small>
                <?php endif; ?>
              </div>
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

<!-- ===================== Modal: registrar compras (bloco) ===================== -->
<div class="modal fade" id="modalCompra" tabindex="-1" aria-labelledby="modalCompraLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content card-br">
      <form action="../controller/CompraController.php" method="post">
        <input type="hidden" name="acao" value="criar">
        <input type="hidden" name="origem" value="<?= htmlspecialchars($origem) ?>">
        <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title" id="modalCompraLabel">
            <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-red);color:#fff;vertical-align:middle;">
              <i class="fa-solid fa-cart-shopping"></i>
            </span>
            Registrar compras
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="text-muted small mb-3">
            Viagem: <strong><?= htmlspecialchars($viagemSelecionada['destino']) ?></strong>
          </p>

          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label mb-0">Escanear nota fiscal</label>
              <span class="text-muted" style="font-size:.72rem;">opcional · IA</span>
            </div>
            <div class="input-group">
              <input type="file" id="comprasImagem" accept="image/jpeg,image/png,image/webp"
                     capture="environment" style="display:none;">
              <button type="button" class="btn btn-light border w-100 d-flex align-items-center justify-content-center gap-2"
                      id="btnEscanearCompra">
                <i class="fa-solid fa-camera"></i> Fotografar / enviar nota
              </button>
            </div>
            <small class="text-muted" id="comprasScanInfo">
              Lê a nota e preenche cada item da compra (produto, marca, categoria e valor) — revise antes de salvar.
            </small>
          </div>

          <div class="row g-2">
            <div class="col-7 mb-3">
              <label class="form-label">Loja <span class="text-muted">(opcional)</span></label>
              <input type="text" name="loja" id="compraLoja" class="form-control login-input"
                     placeholder="Ex.: Macys, Outlet, Amazon..." maxlength="160">
            </div>
            <div class="col-5 mb-3">
              <label class="form-label">Data da compra</label>
              <input type="date" name="data_compra" id="compraData" class="form-control login-input"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-5 mb-2">
              <label class="form-label">Moeda</label>
              <select name="moeda" id="compraMoeda" class="form-select login-input">
                <option value="BRL">Real (R$)</option>
                <option value="USD">Dólar (US$)</option>
              </select>
            </div>
            <div class="col-7 mb-2 d-none" id="compraCotacaoWrap">
              <label class="form-label">Cotação <span class="text-muted">(R$ por US$)</span></label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" inputmode="decimal" name="cotacao" id="compraCotacao"
                       class="form-control login-input" placeholder="Ex.: 5,23"
                       data-mask-moeda data-mask-int="4">
              </div>
              <small class="text-muted" id="compraCotacaoInfo"></small>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">Itens da compra</label>
            <button type="button" class="btn btn-sm btn-light border btn-round" id="btnAdicionarItem">
              <i class="fa-solid fa-plus me-1"></i>Adicionar item
            </button>
          </div>
          <div id="compraItens"></div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-brand-red btn-round fw-semibold">
            <i class="fa-solid fa-circle-plus me-1"></i>Salvar compras
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== Modal: galeria de fotos do item ===================== -->
<?php if (!$simples): ?>
<div class="modal fade" id="modalFotos" tabindex="-1" aria-labelledby="modalFotosLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content card-br">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="modalFotosLabel">
          <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-blue);color:#fff;vertical-align:middle;">
            <i class="fa-solid fa-images"></i>
          </span>
          Fotos do item <span id="fotosTitulo" class="text-muted"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-wrap gap-2 mb-3" id="fotosGrade"></div>
        <p class="text-muted small mb-1">Adicionar foto ao item</p>
        <form action="../controller/CompraController.php" method="post" enctype="multipart/form-data"
              class="d-flex gap-2" id="fotosForm">
          <input type="hidden" name="acao" value="upload_foto">
          <input type="hidden" name="compra_id" id="fotosCompraId">
          <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">
          <input type="file" name="imagem" accept="image/jpeg,image/png,image/webp"
                 class="form-control form-control-sm login-input" required>
          <button type="submit" class="btn btn-sm btn-brand-blue btn-round text-nowrap">
            <i class="fa-solid fa-upload me-1"></i>Enviar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  'use strict';
  var viagemId = <?= (int) $viagemId ?>;
  var rotulos = <?= json_encode(array_map(fn($r) => $r[0], $rotulos)) ?>;

  // ---------- Linhas dinâmicas do bloco ----------
  var container = document.getElementById('compraItens');
  var btnAddItem = document.getElementById('btnAdicionarItem');

  function novaLinha(dados) {
    var row = document.createElement('div');
    row.className = 'compra-row border rounded p-2 mb-2';

    row.innerHTML =
      '<div class="row g-2 mb-1">' +
        '<div class="col-11 col-md-12">' +
          '<label class="form-label small mb-1">Produto</label>' +
          '<input name="produto[]" class="form-control form-control-sm login-input" required ' +
                 'placeholder="Ex.: Calça jeans slim" maxlength="160" value="' + esc(dados && dados.produto) + '">' +
        '</div>' +
        '<div class="col-1 d-md-none d-flex align-items-end ps-0">' +
          '<button type="button" class="btn btn-sm btn-light border compra-toggle" title="Minimizar detalhes">' +
            '<i class="fa-solid fa-chevron-up"></i>' +
          '</button>' +
        '</div>' +
      '</div>' +
      '<div class="row g-2 compra-detalhes">' +
        '<div class="col-12 col-md-3 mb-1">' +
          '<label class="form-label small mb-1">Marca</label>' +
          '<input name="marca[]" list="marcasSugestoes" class="form-control form-control-sm login-input" ' +
                 'placeholder="Ex.: Levis" maxlength="80" value="' + esc(dados && dados.marca) + '">' +
        '</div>' +
        '<div class="col-12 col-md-3 mb-1">' +
          '<label class="form-label small mb-1">Categoria</label>' +
          '<input name="categoria[]" list="categoriasSugestoes" class="form-control form-control-sm login-input" required ' +
                 'placeholder="Ex.: Tênis, Calça..." maxlength="40" value="' + esc(dados && dados.categoria) + '">' +
        '</div>' +
        '<div class="col-12 col-md-2 mb-1">' +
          '<label class="form-label small mb-1">Qtd</label>' +
          '<input type="number" name="quantidade[]" value="' + ((dados && dados.quantidade) || 1) +
                 '" min="1" max="999" class="form-control form-control-sm login-input" required>' +
        '</div>' +
        '<div class="col-12 col-md-3 mb-1">' +
          '<label class="form-label small mb-1">Valor</label>' +
          '<div class="input-group">' +
            '<span class="input-group-text item-prefixo">' + prefixo() + '</span>' +
            '<input name="valor[]" class="form-control login-input" required placeholder="0,00" ' +
                   'data-mask-moeda data-mask-int="9" value="' + esc(dados && dados.valor) + '">' +
          '</div>' +
        '</div>' +
        '<div class="col-12 col-md-1 d-flex align-items-end mb-1 pb-2">' +
          '<button type="button" class="btn btn-sm btn-light border text-danger compra-remover w-100" title="Remover item">' +
            '<i class="fa-solid fa-trash"></i>' +
          '</button>' +
        '</div>' +
      '</div>';
    container.appendChild(row);
  }

  function esc(v) {
    return String(v === undefined || v === null ? '' : v)
      .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function prefixo() {
    var m = document.getElementById('compraMoeda');
    return m && m.value === 'USD' ? 'US$' : 'R$';
  }

  function ensureSugestao(listaId, valor) {
    if (!valor) return;
    var lista = document.getElementById(listaId);
    if (!lista) return;
    var opcoes = lista.querySelectorAll('option');
    for (var i = 0; i < opcoes.length; i++) {
      if (opcoes[i].value === valor) return;
    }
    var op = document.createElement('option');
    op.value = valor;
    lista.appendChild(op);
  }

  container.addEventListener('click', function (e) {
    var alvo = e.target;
    var btn = alvo.closest ? alvo.closest('.compra-remover') : null;
    if (btn) {
      var rows = container.querySelectorAll('.compra-row');
      if (rows.length <= 1) {
        var inputs = rows[0].querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
          if (inputs[i].name !== 'quantidade[]') inputs[i].value = '';
          else inputs[i].value = '1';
        }
      } else {
        btn.closest('.compra-row').remove();
      }
      return;
    }
    var tg = alvo.closest ? alvo.closest('.compra-toggle') : null;
    if (tg) {
      var detalhes = tg.closest('.compra-row').querySelector('.compra-detalhes');
      var oculto = detalhes.style.display === 'none';
      detalhes.style.display = oculto ? '' : 'none';
      tg.querySelector('i').className = 'fa-solid fa-chevron-' + (oculto ? 'up' : 'down');
    }
  });

  // Ao salvar, registra marcas/categorias novas nas sugestões da página.
  var formCompra = document.querySelector('#modalCompra form');
  if (formCompra) {
    formCompra.addEventListener('submit', function () {
      container.querySelectorAll('.compra-row').forEach(function (r) {
        ensureSugestao('marcasSugestoes', r.querySelector('[name="marca[]"]').value.trim());
        ensureSugestao('categoriasSugestoes', r.querySelector('[name="categoria[]"]').value.trim());
      });
    });
  }

  if (btnAddItem) {
    btnAddItem.addEventListener('click', function () { novaLinha(null); });
  }

  // ---------- Moeda / cotação ----------
  var moeda = document.getElementById('compraMoeda');
  var wrapCot = document.getElementById('compraCotacaoWrap');
  var cot = document.getElementById('compraCotacao');
  var infoCot = document.getElementById('compraCotacaoInfo');
  var buscando = false;

  function atualizarPrefixo() {
    var p = prefixo();
    var spans = document.querySelectorAll('#modalCompra .item-prefixo');
    for (var i = 0; i < spans.length; i++) spans[i].textContent = p;
  }

  function mostrarCotacao() {
    var usd = moeda.value === 'USD';
    wrapCot.classList.toggle('d-none', !usd);
    atualizarPrefixo();
    if (usd && cot.dataset.auto === undefined && !cot.value) buscarCotacao();
  }

  function buscarCotacao(force) {
    if (buscando) return;
    if (force === undefined || force === null) force = false;
    if (!force && cot.value) return;
    buscando = true;
    infoCot.textContent = 'Buscando cotação automática...';
    fetch('../controller/CotacaoController.php?moeda=USD')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          cot.value = String(d.cotacao).replace('.', ',');
          cot.dataset.auto = '1';
          infoCot.textContent = d.fonte + ' · ' + d.data + ' — você pode editar.';
        } else {
          infoCot.textContent = 'Não foi possível obter a cotação. Informe manualmente.';
        }
      })
      .catch(function () {
        infoCot.textContent = 'Falha ao consultar. Informe a cotação manualmente.';
      })
      .finally(function () { buscando = false; });
  }

  function marcarManual() {
    cot.dataset.auto = '0';
    infoCot.textContent = 'Cotação manual — será usada no registro.';
  }

  if (moeda && wrapCot && cot) {
    moeda.addEventListener('change', mostrarCotacao);
    cot.addEventListener('input', marcarManual);
    // primeira linha já montada ao abrir o modal
    document.getElementById('modalCompra').addEventListener('show.bs.modal', function () {
      if (!container.querySelector('.compra-row')) novaLinha(null);
      mostrarCotacao();
    });
  }

  // ---------- Escanear nota e lançar em bloco (IA) ----------
  var btnScan = document.getElementById('btnEscanearCompra');
  var inputImg = document.getElementById('comprasImagem');
  var scanInfo = document.getElementById('comprasScanInfo');
  if (!btnScan || !inputImg || !scanInfo) return;

  function fmtValor(v) {
    var num = Math.round((Number(v) || 0) * 100) / 100;
    var s = Math.abs(num).toFixed(2).replace('.', '');
    var inteiro = String(Number(s.slice(0, -2))).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (num < 0 ? '-' : '') + inteiro + ',' + s.slice(-2);
  }

  function etiquetaCategoria(chave) {
    return (rotulos && rotulos[chave]) ? rotulos[chave] : chave;
  }

  function preencherBloco(d) {
    if (d.data) document.getElementById('compraData').value = d.data;
    if (d.loja) document.getElementById('compraLoja').value = d.loja;
    if (d.moeda) moeda.value = d.moeda === 'USD' ? 'USD' : 'BRL';

    // Substitui as linhas pelos itens lidos.
    container.innerHTML = '';
    (d.itens || []).forEach(function (item) {
      novaLinha({
        produto: item.produto,
        marca: item.marca,
        categoria: etiquetaCategoria(item.categoria),
        quantidade: item.quantidade,
        valor: item.valor > 0 ? fmtValor(item.valor) : ''
      });
    });
    if (!container.querySelector('.compra-row')) novaLinha(null);

    mostrarCotacao();
    if (d.moeda === 'USD') buscarCotacao(true);

    scanInfo.textContent = (d.itens ? d.itens.length : 0) + ' item(ns) lidos! Revise as linhas antes de salvar.';
    scanInfo.className = 'text-success';
  }

  btnScan.addEventListener('click', function () { inputImg.click(); });

  inputImg.addEventListener('change', function () {
    if (!inputImg.files || !inputImg.files.length) return;
    var arquivo = inputImg.files[0];

    scanInfo.textContent = 'Lendo a nota... aguarde.';
    scanInfo.className = 'text-muted';

    var fd = new FormData();
    fd.append('imagem', arquivo);

    fetch('../controller/LeituraController.php?modo=itens', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          preencherBloco(d.dados);
        } else if (d && d.mensagem) {
          scanInfo.textContent = d.mensagem;
          scanInfo.className = 'text-danger';
        } else {
          scanInfo.textContent = 'Não foi possível ler a nota. Tente novamente.';
          scanInfo.className = 'text-danger';
        }
      })
      .catch(function () {
        scanInfo.textContent = 'Falha na leitura. Verifique a conexão.';
        scanInfo.className = 'text-danger';
      })
      .finally(function () { inputImg.value = ''; });
  });

  // ---------- Galeria de fotos ----------
  var galeriaTitulo = document.getElementById('fotosTitulo');
  var fotosCompraId = document.getElementById('fotosCompraId');
  var fotosGrade = document.getElementById('fotosGrade');

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.btn-fotos') : null;
    if (!btn) return;
    galeriaTitulo.textContent = ': ' + btn.getAttribute('data-nome');
    fotosCompraId.value = btn.getAttribute('data-id');
    renderFotos(btn.getAttribute('data-fotos'));
  });

  function renderFotos(jsonStr) {
    var fotos = [];
    try { fotos = JSON.parse(jsonStr || '[]'); } catch (e) { fotos = []; }
    fotosGrade.innerHTML = '';

    if (!fotos.length) {
      fotosGrade.innerHTML = '<div class="text-muted small py-2">Sem fotos para este item ainda.</div>';
      return;
    }

    for (var i = 0; i < fotos.length; i++) {
      var item = document.createElement('div');
      item.style.width = '84px';
      item.className = 'text-center';
      item.innerHTML =
        '<img src="' + fotos[i].src + '" alt="Foto" style="width:84px;height:84px;object-fit:cover;' +
          'border-radius:10px;border:1px solid var(--app-border);">' +
        '<form action="../controller/CompraController.php" method="post" class="mt-1">' +
          '<input type="hidden" name="acao" value="excluir_foto">' +
          '<input type="hidden" name="id" value="' + fotos[i].id + '">' +
          '<input type="hidden" name="viagem_id" value="' + viagemId + '">' +
          '<button type="submit" class="btn btn-sm btn-light border text-danger w-100" style="font-size:.68rem;" ' +
            'onclick="return confirm(\'Excluir esta foto?\');"><i class="fa-solid fa-trash"></i></button>' +
        '</form>';
      fotosGrade.appendChild(item);
    }
  }
})();
</script>