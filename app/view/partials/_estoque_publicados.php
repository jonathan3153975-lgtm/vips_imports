<?php
// =====================================================================
// VIP Imports — Partial: produtos publicados na loja (à venda | vendidos)
// Espera: $produtos (array), $modo ('avenda'|'vendido'), $aba (aba ativa),
//         $mostrarBusca (bool), $fmt (moeda), $baseFotos (caminho)
// =====================================================================
$produtos       = $produtos ?? [];
$modo           = $modo ?? 'avenda';
$aba            = $aba ?? 'disponiveis';
$mostrarBusca   = $mostrarBusca ?? false;
$fmt            = $fmt ?? fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$baseFotos      = $baseFotos ?? '../../public/uploads/compras/';

$ehVendido   = $modo === 'vendido';
$titulo      = $ehVendido ? 'Produtos vendidos' : 'Produtos na loja';
$vazioIcone  = $ehVendido ? 'fa-sack-dollar' : 'fa-store-slash';
$vazioTexto  = $ehVendido ? 'Nenhum produto vendido ainda.' : 'Nenhum produto publicado nesta lista.';
$idBusca     = $mostrarBusca ? 'tabelaLojaPublicados' : 'tabelaLojaVendidos';
$idCards     = $mostrarBusca ? 'cardsLojaPublicados' : 'cardsLojaVendidos';
?>

<div class="section-card mb-4">
  <div class="section-head section-head--stack">
    <h5>
      <span class="ico me-2"><i class="fa-solid <?= $ehVendido ? 'fa-circle-check' : 'fa-store' ?>"></i></span>
      <?= $titulo ?>
    </h5>
    <div class="section-head-actions">
      <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
            <?= count($produtos) ?> <?= count($produtos) === 1 ? 'item' : 'itens' ?>
        <?php if (!$ehVendido): ?> · <?= $fmt(array_sum(array_column($produtos, 'preco'))) ?><?php endif; ?>
      </span>
    </div>
  </div>

  <?php if ($mostrarBusca && count($produtos) > 0): ?>
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
      <tbody id="<?= $idBusca ?>">
        <?php if (count($produtos) === 0): ?>
          <tr data-busca="">
            <td colspan="6" class="text-center text-muted py-4">
              <i class="fa-solid <?= $vazioIcone ?> me-1"></i> <?= $vazioTexto ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($produtos as $p): ?>
            <?php
            $buscaTxt   = mb_strtolower($p['produto'] . ' ' . ($p['marca'] ?? '') . ' ' . ($p['descricao'] ?? ''));
            $editarData = htmlspecialchars(json_encode([
                'id'        => (int) $p['id'],
                'produto'   => $p['produto'],
                'marca'     => $p['marca'] ?? '',
                'categoria' => $p['categoria'] ?? '',
                'descricao' => $p['descricao'] ?? '',
                'preco'     => (float) $p['preco'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
            ?>
            <tr data-busca="<?= htmlspecialchars($buscaTxt) ?>">
              <td>
                <span class="trip-name"><?= htmlspecialchars($p['produto']) ?></span>
                <?php if ($p['vendido']): ?>
                  <span class="badge rounded-pill ms-1 align-middle"
                        style="background:rgba(45,144,105,.12);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.68rem;">Vendido</span>
                <?php endif; ?>
                <?php if (!empty($p['reservado'])): ?>
                  <span class="badge rounded-pill ms-1 align-middle"
                        style="background:rgba(184,134,11,.12);color:#8a6508;border:1px solid rgba(184,134,11,.35);font-size:.68rem;">
                    Reservado<?= !empty($p['reservado_pedido_id']) ? ' · #' . (int) $p['reservado_pedido_id'] : '' ?>
                  </span>
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
                <form action="../controller/EstoqueController.php" method="post" class="d-inline">
                  <input type="hidden" name="acao" value="marcar_vendido">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="vendido" value="<?= $p['vendido'] ? 0 : 1 ?>">
                  <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
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
                <button type="button" class="btn btn-sm btn-light border text-muted btn-editar-item" title="Editar item"
                        data-item='<?= $editarData ?>'>
                  <i class="fa-solid fa-pen"></i>
                </button>
                <form action="../controller/EstoqueController.php" method="post" class="d-inline"
                      onsubmit="return confirm('Remover este produto da loja? Ele volta para a listagem de compras.');">
                  <input type="hidden" name="acao" value="remover">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
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

  <div class="d-md-none gasto-cards <?= $idCards ?>">
    <?php if (count($produtos) === 0): ?>
      <div class="text-center text-muted py-4">
        <i class="fa-solid <?= $vazioIcone ?> me-1"></i> <?= $vazioTexto ?>
      </div>
    <?php else: ?>
      <?php foreach ($produtos as $p): ?>
        <?php
        $buscaTxt   = mb_strtolower($p['produto'] . ' ' . ($p['marca'] ?? '') . ' ' . ($p['descricao'] ?? ''));
        $editarData = htmlspecialchars(json_encode([
            'id'        => (int) $p['id'],
            'produto'   => $p['produto'],
            'marca'     => $p['marca'] ?? '',
            'categoria' => $p['categoria'] ?? '',
            'descricao' => $p['descricao'] ?? '',
            'preco'     => (float) $p['preco'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
        ?>
        <div class="gasto-card" data-busca="<?= htmlspecialchars($buscaTxt) ?>">
          <div class="gasto-card-top">
            <div class="gasto-card-main">
              <div class="gasto-card-desc">
                <?= htmlspecialchars($p['produto']) ?>
                <?php if ($p['vendido']): ?>
                  <span class="badge rounded-pill ms-1 align-middle"
                        style="background:rgba(45,144,105,.12);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.64rem;">Vendido</span>
                <?php endif; ?>
                <?php if (!empty($p['reservado'])): ?>
                  <span class="badge rounded-pill ms-1 align-middle"
                        style="background:rgba(184,134,11,.12);color:#8a6508;border:1px solid rgba(184,134,11,.35);font-size:.64rem;">
                    Reservado<?= !empty($p['reservado_pedido_id']) ? ' · #' . (int) $p['reservado_pedido_id'] : '' ?>
                  </span>
                <?php endif; ?>
              </div>
              <?php if ($p['marca']): ?>
                <span class="text-muted" style="font-size:.72rem;display:block;"><?= htmlspecialchars($p['marca']) ?></span>
              <?php endif; ?>
            </div>
            <div class="gasto-card-date"><?= date('d/m/Y', strtotime($p['publicado_em'])) ?></div>
          </div>
          <div class="gasto-card-bottom gasto-card-bottom--stacked">
            <div class="gasto-card-value">
              <div style="white-space:nowrap;"><?= $fmt($p['preco']) ?></div>
              <small class="text-muted fw-normal">custo <?= $fmt($p['custo_item']) ?></small>
            </div>
            <div class="gasto-card-actions">
              <form action="../controller/EstoqueController.php" method="post" class="m-0">
                <input type="hidden" name="acao" value="marcar_vendido">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="vendido" value="<?= $p['vendido'] ? 0 : 1 ?>">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                <?php if ($p['vendido']): ?>
                  <button type="submit" class="btn btn-sm btn-light border text-muted w-100" title="Desmarcar como vendido">
                    <i class="fa-solid fa-circle-xmark"></i>
                  </button>
                <?php else: ?>
                  <button type="submit" class="btn btn-sm btn-light border w-100" style="color:var(--app-ok);" title="Marcar como vendido">
                    <i class="fa-solid fa-circle-check"></i>
                  </button>
                <?php endif; ?>
              </form>
              <button type="button" class="btn btn-sm btn-light border text-muted w-100 btn-editar-item" title="Editar item"
                      data-item='<?= $editarData ?>'>
                <i class="fa-solid fa-pen"></i>
              </button>
              <form action="../controller/EstoqueController.php" method="post" class="m-0"
                    onsubmit="return confirm('Remover este produto da loja? Ele volta para a listagem de compras.');">
                <input type="hidden" name="acao" value="remover">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                <button type="submit" class="btn btn-sm btn-light border text-danger w-100" title="Remover da loja">
                  <i class="fa-solid fa-arrow-rotate-left"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>