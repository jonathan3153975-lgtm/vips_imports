<?php
// =====================================================================
// VIP Imports — Compras
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
$titulo       = 'Compras';
$paginaAtual  = 'compras';

$viagemModel = new Viagem();
$compraModel = new Compra();

$viagens      = $viagemModel->listarTodas($usuarioId);
$badgeCompras = $compraModel->contar($usuarioId);

$rotulos = [
    'calca'   => ['Calça', 'fa-shirt'],
    'casaco'  => ['Casaco', 'fa-vest'],
    'jaqueta' => ['Jaqueta', 'fa-person-hiking'],
    'tenis'   => ['Tênis', 'fa-shoe-prints'],
    'outros'  => ['Outros', 'fa-ellipsis'],
];

$viagemId = (int) ($_GET['viagem'] ?? 0);
$viagemSelecionada = $viagemId > 0 ? $viagemModel->buscarPorId($viagemId, $usuarioId) : null;
if ($viagemSelecionada === null) {
    $viagemSelecionada = $viagemModel->viagemAberta($usuarioId) ?? ($viagens[0] ?? null);
}

$editandoId = (int) ($_GET['editar'] ?? 0);
$editando   = $editandoId > 0 ? $compraModel->buscarPorId($editandoId, $usuarioId) : null;
if ($editando !== null && $viagemSelecionada === null) {
    $viagemSelecionada = $viagemModel->buscarPorId((int) $editando['viagem_id'], $usuarioId);
}

$mensagens = [
    'compra'        => ['success', 'Itens registrados com sucesso.'],
    'atualizado'    => ['success', 'Item atualizado com sucesso.'],
    'excluido'      => ['success', 'Item excluído com sucesso.'],
    'foto'          => ['success', 'Foto adicionada à galeria.'],
    'foto_removida' => ['success', 'Foto removida.'],
    'sem_viagem'    => ['danger',  'Selecione uma viagem válida para lançar as compras.'],
    'categoria'     => ['danger',  'Categoria de item inválida.'],
    'valor'         => ['danger',  'Informe pelo menos um item com valor maior que zero.'],
    'item'          => ['danger',  'Informe pelo menos um item para registrar.'],
    'campos'        => ['danger',  'Dados inválidos. Verifique e tente novamente.'],
    'sistema'       => ['danger',  'Não foi possível concluir a operação. Tente novamente.'],
    'cotacao'       => ['danger',  'Não foi possível obter a cotação do dólar. Informe o valor manualmente.'],
    'tamanho'       => ['danger',  'A imagem excede o tamanho máximo (2 MB).'],
    'formato'       => ['danger',  'Formato de imagem não permitido (use JPG, PNG ou WEBP).'],
    'upload'        => ['danger',  'Não foi possível enviar a imagem.'],
];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? $mensagens[$_GET['erro'] ?? ''] ?? null;

$bronze = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

$itens           = [];
$totais          = array_fill_keys(Compra::CATEGORIAS, 0.0);
$totalGeral      = 0.0;
$qtdItens        = 0;
$marcasDistintas = 0;
$custoViagem     = 0.0;
$totalVendas     = 0.0;
$saldoViagem     = 0.0;
if ($viagemSelecionada) {
    $itens           = $compraModel->listarPorViagem((int) $viagemSelecionada['id'], $usuarioId);
    $totais          = $compraModel->totaisPorCategoria((int) $viagemSelecionada['id'], $usuarioId);
    $totalGeral      = $compraModel->totalPorViagem((int) $viagemSelecionada['id'], $usuarioId);
    $qtdItens        = array_sum(array_column($itens, 'quantidade'));
    $marcasDistintas = $compraModel->contarMarcas((int) $viagemSelecionada['id'], $usuarioId);
    $custoViagem     = (new Gasto())->totalPorViagem((int) $viagemSelecionada['id'], $usuarioId);
    $totalVendas     = (new Estoque())->totalVendidoPorViagem((int) $viagemSelecionada['id'], $usuarioId);
    $saldoViagem     = $totalVendas - ($custoViagem + $totalGeral);
}

$categoriasSugestao = array_values(array_unique(array_merge(
    array_map(fn($r) => $r[0], $rotulos),
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
            <h1>Compras</h1>
            <span class="muted small">Itens comprados por viagem — roupas, calçados e acessórios</span>
          </div>
          <?php if (count($viagens) > 0): ?>
            <div class="actions">
              <form action="compras.php" method="get" class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Viagem</label>
                <select name="viagem" class="form-select login-input" style="min-width:240px;" onchange="this.form.submit()">
                  <?php foreach ($viagens as $v): ?>
                    <option value="<?= (int) $v['id'] ?>" <?= ($viagemSelecionada && (int) $viagemSelecionada['id'] === (int) $v['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($v['destino']) ?> — <?= $v['status'] === 'aberta' ? 'em aberto' : 'encerrada' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($msg !== null): ?>
          <div class="alert alert-<?= $msg[0] ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($msg[1]) ?></span>
          </div>
        <?php endif; ?>

        <?php if (count($viagens) === 0): ?>
          <div class="section-card">
            <div class="p-4 text-center text-muted">
              <i class="fa-solid fa-plane-slash me-1"></i>
              Nenhuma viagem registrada. <a href="viagens.php" class="text-brand-blue fw-semibold" style="text-decoration:none;">Registre uma viagem</a> para lançar compras.
            </div>
          </div>
        <?php else: ?>
          <?php include __DIR__ . '/partials/_compras.php'; ?>
        <?php endif; ?>

        <!-- Modal: editar item -->
          <div class="modal fade" id="modalEditarCompra" tabindex="-1" aria-labelledby="modalEditarCompraLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content card-br">
                <form action="../controller/CompraController.php" method="post">
                  <input type="hidden" name="acao" value="atualizar">
                  <input type="hidden" name="id" id="editarId" value="<?= (int) ($editando['id'] ?? 0) ?>">
                  <input type="hidden" name="viagem_id" id="editarViagemId" value="<?= (int) ($editando['viagem_id'] ?? $viagemSelecionada['id'] ?? 0) ?>">

                  <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="modalEditarCompraLabel">
                      <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-red);color:#fff;vertical-align:middle;">
                        <i class="fa-solid fa-pen"></i>
                      </span>
                      Editar item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                  </div>

                  <div class="modal-body">
                    <div class="row g-2">
                      <div class="col-md-7 mb-2">
                        <label class="form-label">Produto</label>
                        <input type="text" name="produto" id="editarProduto" class="form-control login-input" required maxlength="160"
                               value="<?= htmlspecialchars($editando['produto'] ?? '') ?>">
                      </div>
                      <div class="col-md-5 mb-2">
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" id="editarMarca" list="marcasSugestoes" class="form-control login-input" maxlength="80"
                               value="<?= htmlspecialchars($editando['marca'] ?? '') ?>">
                      </div>
                    </div>

                    <div class="row g-2">
                      <div class="col-md-5 mb-2">
                        <label class="form-label">Categoria</label>
                        <input type="text" name="categoria" id="editarCategoria" list="categoriasSugestoes"
                               class="form-control login-input" required maxlength="40"
                               value="<?= htmlspecialchars($editando['categoria'] ?? '') ?>"
                               placeholder="Ex.: Tênis, Calça...">
                      </div>
                      <div class="col-3 col-md-2 mb-2">
                        <label class="form-label">Qtd</label>
                        <input type="number" name="quantidade" id="editarQtd"
                               value="<?= (int) ($editando['quantidade'] ?? 1) ?>" min="1" max="999"
                               class="form-control login-input" required>
                      </div>
                      <div class="col-9 col-md-5 mb-2">
                        <label class="form-label">Valor</label>
                        <div class="input-group">
                          <span class="input-group-text" id="editarValorPrefixo"><?= ($editando['moeda'] ?? 'BRL') === 'USD' ? 'US$' : 'R$' ?></span>
                          <input type="text" inputmode="decimal" name="valor" id="editarValor" class="form-control login-input" required
                                 value="<?= ($editando['valor'] ?? 0) ? number_format((float) $editando['valor'], 2, ',', '.') : '' ?>"
                                 data-mask-moeda data-mask-int="9">
                        </div>
                      </div>
                    </div>

                    <div class="row g-2">
                      <div class="col-md-6 mb-2">
                        <label class="form-label">Loja <span class="text-muted">(opcional)</span></label>
                        <input type="text" name="loja" id="editarLoja" class="form-control login-input" maxlength="160"
                               value="<?= htmlspecialchars($editando['loja'] ?? '') ?>">
                      </div>
                      <div class="col-md-6 mb-2">
                        <label class="form-label">Data da compra</label>
                        <input type="date" name="data_compra" id="editarData" class="form-control login-input" required
                               value="<?= htmlspecialchars($editando['data_compra'] ?? date('Y-m-d')) ?>">
                      </div>
                    </div>

                    <div class="row g-2">
                      <div class="col-md-5 mb-2">
                        <label class="form-label">Moeda</label>
                        <select name="moeda" id="editarMoeda" class="form-select login-input">
                          <option value="BRL" <?= ($editando['moeda'] ?? 'BRL') === 'BRL' ? 'selected' : '' ?>>Real (R$)</option>
                          <option value="USD" <?= ($editando['moeda'] ?? '') === 'USD' ? 'selected' : '' ?>>Dólar (US$)</option>
                        </select>
                      </div>
                      <div class="col-md-7 mb-2 <?= ($editando['moeda'] ?? 'BRL') === 'USD' ? '' : 'd-none' ?>" id="editarCotacaoWrap">
                        <label class="form-label">Cotação <span class="text-muted">(R$ por US$)</span></label>
                        <div class="input-group">
                          <span class="input-group-text">R$</span>
                          <input type="text" inputmode="decimal" name="cotacao" id="editarCotacao"
                                 value="<?= ($editando['moeda'] ?? '') === 'USD' ? number_format((float) ($editando['cotacao'] ?? 0), 4, ',', '.') : '' ?>"
                                 class="form-control login-input" placeholder="Ex.: 5,23"
                                 data-mask-moeda data-mask-int="4">
                        </div>
                        <small class="text-muted" id="editarCotacaoInfo"></small>
                      </div>
                    </div>

                    <div class="mb-1">
                      <label class="form-label">Observações <span class="text-muted">(opcional)</span></label>
                      <textarea name="observacoes" id="editarObs" class="form-control login-input" rows="2"
                                maxlength="500"><?= htmlspecialchars($editando['observacoes'] ?? '') ?></textarea>
                    </div>
                  </div>

                  <div class="modal-footer border-0 pt-0">
                    <a href="compras.php?viagem=<?= (int) ($viagemSelecionada['id'] ?? 0) ?>" class="btn btn-light border btn-round">Cancelar</a>
                    <button type="submit" class="btn btn-brand-red btn-round fw-semibold">
                      <i class="fa-solid fa-floppy-disk me-1"></i>Salvar alterações
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <script>
            (function () {
              var moeda = document.getElementById('editarMoeda');
              var wrap = document.getElementById('editarCotacaoWrap');
              var cot = document.getElementById('editarCotacao');
              var info = document.getElementById('editarCotacaoInfo');
              var prefix = document.getElementById('editarValorPrefixo');
              if (!moeda || !wrap || !cot || !prefix) return;

              var buscando = false;

              function mostrar() {
                var usd = moeda.value === 'USD';
                wrap.classList.toggle('d-none', !usd);
                prefix.textContent = usd ? 'US$' : 'R$';
                if (usd && !cot.value && !buscando) buscar();
              }

              function buscar() {
                buscando = true;
                info.textContent = 'Buscando cotação automática...';
                fetch('../controller/CotacaoController.php?moeda=USD')
                  .then(function (r) { return r.json(); })
                  .then(function (d) {
                    if (d && d.ok) {
                      cot.value = String(d.cotacao).replace('.', ',');
                      info.textContent = d.fonte + ' · ' + d.data + ' — você pode editar.';
                    } else {
                      info.textContent = 'Não foi possível obter a cotação. Informe manualmente.';
                    }
                  })
                  .catch(function () {
                    info.textContent = 'Falha ao consultar. Informe a cotação manualmente.';
                  })
                  .finally(function () { buscando = false; });
              }

              moeda.addEventListener('change', mostrar);
              document.getElementById('modalEditarCompra').addEventListener('show.bs.modal', mostrar);

              // Abre o modal preenchendo os campos a partir do item clicado.
              document.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest('.btn-editar') : null;
                if (!btn) return;
                var item;
                try { item = JSON.parse(btn.getAttribute('data-item') || '{}'); } catch (err) { return; }
                if (!item || !item.id) return;

                document.getElementById('editarId').value = item.id;
                document.getElementById('editarViagemId').value = item.viagem_id || 0;
                document.getElementById('editarProduto').value = item.produto || '';
                document.getElementById('editarMarca').value = item.marca || '';
                document.getElementById('editarCategoria').value = item.categoria || '';
                document.getElementById('editarQtd').value = item.quantidade || 1;
                document.getElementById('editarValor').value = item.valor || '';
                document.getElementById('editarLoja').value = item.loja || '';
                document.getElementById('editarData').value = item.data || '';
                document.getElementById('editarObs').value = item.obs || '';
                moeda.value = item.moeda === 'USD' ? 'USD' : 'BRL';
                cot.value = item.cotacao || '';
                if (item.moeda === 'USD') {
                  document.getElementById('editarCotacaoWrap').classList.remove('d-none');
                  prefix.textContent = 'US$';
                } else {
                  document.getElementById('editarCotacaoWrap').classList.add('d-none');
                  prefix.textContent = 'R$';
                }
                info.textContent = '';

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarCompra')).show();
              });

              <?php if ($editando !== null): ?>
              if (window.bootstrap) { bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarCompra')).show(); }
              <?php endif; ?>
            })();
          </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>