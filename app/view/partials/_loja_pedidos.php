<?php
// =====================================================================
// VIP Imports — Partial: tabela de pedidos da Loja (desktop + mobile)
// Espera: $pedidosLista (array), $somenteLeitura (bool),
//         $pedidosVazioIcone (string), $pedidosVazioTexto (string)
// Usa:    $fmt, $statusClasse, $origemRotulo, $abaFormulario
// =====================================================================
$somenteLeitura   = $somenteLeitura ?? false;
$abaFormulario    = $abaFormulario ?? 'pedidos';
$pedidosVazioIcone = $pedidosVazioIcone ?? 'fa-receipt';
$pedidosVazioTexto = $pedidosVazioTexto ?? 'Nenhum pedido por aqui.';
?>
<?php if (count($pedidosLista) === 0): ?>
  <div class="section-card text-center text-muted py-5">
    <i class="fa-solid <?= htmlspecialchars($pedidosVazioIcone) ?> fa-2x mb-2 d-block"></i>
    <?= htmlspecialchars($pedidosVazioTexto) ?>
  </div>
<?php else: ?>
  <div class="section-card">
    <div class="table-responsive d-none d-md-block">
      <table class="table table-app align-middle">
        <thead>
          <tr>
            <th>Pedido</th>
            <th>Cliente</th>
            <th>Itens</th>
            <th class="text-end">Total</th>
            <th>Origem</th>
            <th>Status</th>
            <?php if (!$somenteLeitura): ?><th class="text-end">Ações</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pedidosLista as $pedido): ?>
            <tr>
              <td>
                <span class="trip-name">#<?= (int) $pedido['id'] ?></span>
                <div class="text-muted" style="font-size:.74rem;"><?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?></div>
                <?php if ($pedido['status'] === 'concluido' && !empty($pedido['atualizado_em'])): ?>
                  <div class="text-muted" style="font-size:.72rem;">
                    <i class="fa-solid fa-flag-checkered me-1" style="color:var(--app-ok,#15803d);"></i>Concluído em <?= date('d/m/Y', strtotime($pedido['atualizado_em'])) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($pedido['cliente_nome']) ?>
                <div class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars($pedido['cliente_contato']) ?></div>
                <?php if ($pedido['observacao']): ?>
                  <div class="text-muted fst-italic" style="font-size:.74rem;"><?= htmlspecialchars($pedido['observacao']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php foreach ($pedido['itens'] as $it): ?>
                  <div style="font-size:.82rem;"><?= (int) $it['quantidade'] ?>x <?= htmlspecialchars($it['produto']) ?></div>
                <?php endforeach; ?>
              </td>
              <td class="text-end fw-semibold" style="white-space:nowrap;"><?= $fmt($pedido['total']) ?></td>
              <td>
                <span class="badge text-bg-light border"><?= htmlspecialchars($origemRotulo[$pedido['origem']] ?? $pedido['origem']) ?></span>
              </td>
              <td>
                <span class="status-badge <?= $statusClasse[$pedido['status']] ?? 'status-ativa' ?>">
                  <span class="pt"></span><?= htmlspecialchars(Pedido::ROTULOS[$pedido['status']] ?? $pedido['status']) ?>
                </span>
              </td>
              <?php if (!$somenteLeitura): ?>
                <td class="text-end text-nowrap">
                  <?php $pid = (int) $pedido['id']; $st = $pedido['status']; ?>
                  <?php if ($st === 'novo'): ?>
                    <form action="../controller/LojaController.php" method="post" class="d-inline">
                      <input type="hidden" name="acao" value="pedido_status"><input type="hidden" name="aba" value="<?= htmlspecialchars($abaFormulario) ?>">
                      <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="status" value="confirmado">
                      <button class="btn btn-sm btn-brand-blue btn-round" title="Confirmar"><i class="fa-solid fa-check"></i></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($st === 'novo' || $st === 'confirmado'): ?>
                    <form action="../controller/LojaController.php" method="post" class="d-inline">
                      <input type="hidden" name="acao" value="pedido_status"><input type="hidden" name="aba" value="<?= htmlspecialchars($abaFormulario) ?>">
                      <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="status" value="concluido">
                      <button class="btn btn-sm btn-light border btn-round" title="Concluir"><i class="fa-solid fa-flag-checkered"></i></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($st !== 'cancelado' && $st !== 'concluido'): ?>
                    <form action="../controller/LojaController.php" method="post" class="d-inline"
                          onsubmit="return confirm('Cancelar este pedido?');">
                      <input type="hidden" name="acao" value="pedido_status"><input type="hidden" name="aba" value="<?= htmlspecialchars($abaFormulario) ?>">
                      <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="status" value="cancelado">
                      <button class="btn btn-sm btn-light border btn-round text-danger" title="Cancelar"><i class="fa-solid fa-xmark"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile -->
    <div class="d-md-none gasto-cards">
      <?php foreach ($pedidosLista as $pedido): ?>
        <div class="gasto-card">
          <div class="gasto-card-top">
            <div class="gasto-card-main">
              <div class="gasto-card-desc">#<?= (int) $pedido['id'] ?> · <?= htmlspecialchars($pedido['cliente_nome']) ?></div>
              <span class="status-badge <?= $statusClasse[$pedido['status']] ?? 'status-ativa' ?>">
                <span class="pt"></span><?= htmlspecialchars(Pedido::ROTULOS[$pedido['status']] ?? $pedido['status']) ?>
              </span>
              <span class="text-muted" style="font-size:.72rem;display:block;"><?= htmlspecialchars($pedido['cliente_contato']) ?></span>
              <?php foreach ($pedido['itens'] as $it): ?>
                <span class="text-muted" style="font-size:.72rem;display:block;"><?= (int) $it['quantidade'] ?>x <?= htmlspecialchars($it['produto']) ?></span>
              <?php endforeach; ?>
              <?php if ($pedido['status'] === 'concluido' && !empty($pedido['atualizado_em'])): ?>
                <span class="text-muted" style="font-size:.72rem;display:block;">Concluído em <?= date('d/m/Y', strtotime($pedido['atualizado_em'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="gasto-card-date"><?= date('d/m/Y', strtotime($pedido['criado_em'])) ?></div>
          </div>
          <div class="gasto-card-bottom <?= $somenteLeitura ? '' : 'gasto-card-bottom--stacked' ?>">
            <div class="gasto-card-value">
              <div><?= $fmt($pedido['total']) ?></div>
              <small class="text-muted fw-normal"><?= htmlspecialchars($origemRotulo[$pedido['origem']] ?? $pedido['origem']) ?></small>
            </div>
            <?php if (!$somenteLeitura): ?>
              <div class="gasto-card-actions">
                <?php $pid = (int) $pedido['id']; $st = $pedido['status']; ?>
                <?php if ($st === 'novo'): ?>
                  <form action="../controller/LojaController.php" method="post" class="m-0 w-100">
                    <input type="hidden" name="acao" value="pedido_status"><input type="hidden" name="aba" value="<?= htmlspecialchars($abaFormulario) ?>">
                    <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="status" value="confirmado">
                    <button class="btn btn-sm btn-brand-blue btn-round w-100" title="Confirmar"><i class="fa-solid fa-check"></i></button>
                  </form>
                <?php endif; ?>
                <?php if ($st === 'novo' || $st === 'confirmado'): ?>
                  <form action="../controller/LojaController.php" method="post" class="m-0 w-100">
                    <input type="hidden" name="acao" value="pedido_status"><input type="hidden" name="aba" value="<?= htmlspecialchars($abaFormulario) ?>">
                    <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="status" value="concluido">
                    <button class="btn btn-sm btn-light border btn-round w-100" title="Concluir"><i class="fa-solid fa-flag-checkered"></i></button>
                  </form>
                <?php endif; ?>
                <?php if ($st !== 'cancelado' && $st !== 'concluido'): ?>
                  <form action="../controller/LojaController.php" method="post" class="m-0 w-100"
                        onsubmit="return confirm('Cancelar este pedido?');">
                    <input type="hidden" name="acao" value="pedido_status"><input type="hidden" name="aba" value="<?= htmlspecialchars($abaFormulario) ?>">
                    <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="status" value="cancelado">
                    <button class="btn btn-sm btn-light border btn-round text-danger w-100" title="Cancelar"><i class="fa-solid fa-xmark"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
