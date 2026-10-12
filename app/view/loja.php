<?php
// =====================================================================
// VIP Imports — Loja (loja virtual: catálogo publicado + pedidos)
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Estoque.php';
require_once dirname(__DIR__) . '/model/Pedido.php';
require_once dirname(__DIR__) . '/model/UsuarioConfig.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Loja';
$paginaAtual  = 'loja';

$estoqueModel = new Estoque();
$pedidoModel  = new Pedido();
$cfgModel     = new UsuarioConfig();

$cfg   = $cfgModel->garantir($usuarioId);
$token = (string) $cfg['loja_token'];

$badgeEstoque = $estoqueModel->contarPublicados($usuarioId);
$badgePedidos = $pedidoModel->contarNovos($usuarioId);

$mensagens = [
    'status'  => ['success', 'Status do pedido atualizado.'],
    'token'   => ['success', 'Novo link da loja gerado. O link anterior deixou de funcionar.'],
    'pedido'  => ['danger',  'Pedido não encontrado.'],
    'campos'  => ['danger',  'Dados inválidos. Verifique e tente novamente.'],
    'sistema' => ['danger',  'Não foi possível concluir a operação. Tente novamente.'],
];
$msgChave = $mensagens[$_GET['msg'] ?? ''] ?? ($mensagens[$_GET['erro'] ?? ''] ?? null);

$fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

// ---- Produtos publicados (todos) — a vitrine mostra só os disponíveis ----
$publicados = $estoqueModel->publicados($usuarioId);
$aVenda     = array_values(array_filter($publicados, fn($x) => !$x['vendido'] && !$x['reservado']));
$reservados = array_values(array_filter($publicados, fn($x) => !$x['vendido'] && $x['reservado']));
$vendidos   = array_values(array_filter($publicados, fn($x) => $x['vendido']));

// ---- Pedidos: separa os concluídos dos que ainda estão em andamento ----
$pedidos           = $pedidoModel->listarPorUsuario($usuarioId);
$pedidosAndamento  = array_values(array_filter($pedidos, fn($x) => $x['status'] !== 'concluido'));
$pedidosConcluidos = array_values(array_filter($pedidos, fn($x) => $x['status'] === 'concluido'));

// ---- Link público divulgável ----
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base    = preg_replace('#/app/view/[^/]+$#', '', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$urlLoja = $esquema . '://' . $host . $base . '/public/loja.php?c=' . $token;

$abasPermitidas = ['produtos', 'pedidos', 'concluidos'];
$aba = $_GET['aba'] ?? 'produtos';
if (!in_array($aba, $abasPermitidas, true)) {
    $aba = 'produtos';
}

$baseFotos = '../../public/uploads/compras/';

$statusClasse = [
    'novo'       => 'status-ativa',
    'confirmado' => 'status-ativa',
    'concluido'  => 'status-concluida',
    'cancelado'  => 'status-planejada',
];
$origemRotulo = ['loja' => 'Loja', 'telegram' => 'Telegram'];

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Loja</h1>
            <span class="muted small">Sua loja virtual: catálogo publicado e pedidos recebidos</span>
          </div>
          <div class="actions align-items-center">
            <a href="<?= htmlspecialchars($urlLoja) ?>" target="_blank" rel="noopener" class="btn btn-brand-blue btn-round">
              <i class="fa-solid fa-up-right-from-square me-1"></i>Abrir loja
            </a>
          </div>
        </div>

        <?php if ($msgChave !== null): ?>
          <div class="alert alert-<?= $msgChave[0] ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($msgChave[1]) ?></span>
          </div>
        <?php endif; ?>

        <!-- Link público -->
        <div class="section-card mb-3">
          <div class="section-head section-head--stack">
            <h5>
              <span class="ico me-2"><i class="fa-solid fa-link"></i></span>
              Link da loja para divulgar
            </h5>
            <div class="section-head-actions d-flex align-items-center gap-2">
              <form action="../controller/LojaController.php" method="post" onsubmit="return confirm('Gerar um novo link? O link atual deixará de funcionar.');">
                <input type="hidden" name="acao" value="renovar_token">
                <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">
                <button type="submit" class="btn btn-sm btn-light border btn-round fw-semibold">
                  <i class="fa-solid fa-rotate me-1"></i>Novo link
                </button>
              </form>
            </div>
          </div>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-globe"></i></span>
            <input type="text" class="form-control login-input" id="lojaLink" value="<?= htmlspecialchars($urlLoja) ?>" readonly>
            <button class="btn btn-light border fw-semibold" type="button" id="btnCopiarLink">
              <i class="fa-regular fa-copy me-1"></i>Copiar
            </button>
          </div>
          <small class="text-muted d-block mt-2">
            Envie este link para seus clientes. Quem tiver o link vê apenas os itens à venda e pode montar um pedido.
          </small>
        </div>

        <!-- Abas -->
        <ul class="nav nav-tabs d-none d-md-flex mb-4" id="abasLojaVirtual" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'produtos' ? 'active' : '' ?>" data-valor="produtos"
                    data-bs-toggle="tab" data-bs-target="#abaProdutos" type="button" role="tab">
              Produtos publicados
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Itens à venda"
                    style="background:rgba(59,80,124,.12);color:var(--app-muted);font-size:.66rem;">
                <?= count($aVenda) ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'pedidos' ? 'active' : '' ?>" data-valor="pedidos"
                    data-bs-toggle="tab" data-bs-target="#abaPedidos" type="button" role="tab">
              Pedidos
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Pedidos em andamento"
                    style="background:rgba(45,144,105,.14);color:var(--app-ok);border:1px solid rgba(45,144,105,.3);font-size:.66rem;">
                <?= count($pedidosAndamento) ?>
              </span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'concluidos' ? 'active' : '' ?>" data-valor="concluidos"
                    data-bs-toggle="tab" data-bs-target="#abaConcluidos" type="button" role="tab">
              Concluídos
              <span class="badge rounded-pill ms-1 align-middle" aria-label="Pedidos concluídos"
                    style="background:rgba(59,80,124,.12);color:var(--app-muted);font-size:.66rem;">
                <?= count($pedidosConcluidos) ?>
              </span>
            </button>
          </li>
        </ul>

        <select class="form-select d-md-none mb-3" id="selAbaLojaVirtual" aria-label="Seção da loja">
          <option value="produtos" <?= $aba === 'produtos' ? 'selected' : '' ?>>Produtos publicados (<?= count($aVenda) ?>)</option>
          <option value="pedidos" <?= $aba === 'pedidos' ? 'selected' : '' ?>>Pedidos (<?= count($pedidosAndamento) ?>)</option>
          <option value="concluidos" <?= $aba === 'concluidos' ? 'selected' : '' ?>>Concluídos (<?= count($pedidosConcluidos) ?>)</option>
        </select>

        <div class="tab-content">

          <!-- ================= Produtos publicados ================= -->
          <div class="tab-pane fade <?= $aba === 'produtos' ? 'show active' : '' ?>" id="abaProdutos" role="tabpanel">
            <?php if (count($aVenda) === 0 && count($reservados) === 0): ?>
              <div class="section-card text-center text-muted py-5">
                <i class="fa-solid fa-store fa-2x mb-2 d-block"></i>
                Nenhum item à venda ainda. Publique itens no <a href="estoque.php">Estoque</a> para vê-los aqui.
              </div>
            <?php else: ?>

              <?php if (count($aVenda) === 0): ?>
                <div class="section-card text-center text-muted py-4">
                  <i class="fa-solid fa-store-slash me-1"></i>
                  Nenhum item disponível para venda no momento.
                </div>
              <?php else: ?>
                <div class="row g-3">
                  <?php foreach ($aVenda as $item): ?>
                    <?php $foto = $item['fotos'][0]['arquivo'] ?? null; ?>
                    <div class="col-6 col-md-4 col-lg-3">
                      <div class="card card-br h-100">
                        <?php if ($foto): ?>
                          <img src="<?= htmlspecialchars($baseFotos . $foto) ?>" alt="<?= htmlspecialchars($item['produto']) ?>"
                               class="card-img-top" style="height:180px;object-fit:cover;">
                        <?php else: ?>
                          <div class="d-grid" style="height:180px;place-items:center;background:var(--app-surface-2);color:var(--app-text-muted);">
                            <i class="fa-solid fa-image fa-2x"></i>
                          </div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                          <div class="d-flex justify-content-between align-items-start gap-2">
                            <strong style="font-size:.92rem;"><?= htmlspecialchars($item['produto']) ?></strong>
                          </div>
                          <?php if ($item['marca']): ?>
                            <small class="text-muted"><?= htmlspecialchars($item['marca']) ?></small>
                          <?php endif; ?>
                          <span class="status-badge status-ativa mt-2 align-self-start">
                            <span class="pt"></span><?= htmlspecialchars($item['categoria']) ?>
                          </span>
                          <div class="mt-auto d-flex justify-content-between align-items-end pt-3">
                            <span class="fw-bold" style="font-size:1.05rem;"><?= $fmt($item['preco']) ?></span>
                            <small class="text-muted"><?= (int) $item['quantidade'] ?>x</small>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (count($reservados) > 0): ?>
                <div class="section-card mt-3">
                  <div class="section-head section-head--stack">
                    <h5>
                      <span class="ico me-2" style="background:var(--brand-amber,#b8860b);"><i class="fa-solid fa-hourglass-half"></i></span>
                      Reservados aguardando confirmação
                    </h5>
                    <div class="section-head-actions">
                      <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
                        <?= count($reservados) ?> <?= count($reservados) === 1 ? 'item' : 'itens' ?>
                      </span>
                    </div>
                  </div>
                  <div class="row g-3 p-3">
                    <?php foreach ($reservados as $item): ?>
                      <?php $foto = $item['fotos'][0]['arquivo'] ?? null; ?>
                      <div class="col-6 col-md-4 col-lg-3">
                        <div class="card card-br h-100" style="border-color:rgba(184,134,11,.4);">
                          <?php if ($foto): ?>
                            <img src="<?= htmlspecialchars($baseFotos . $foto) ?>" alt="<?= htmlspecialchars($item['produto']) ?>"
                                 class="card-img-top" style="height:150px;object-fit:cover;filter:grayscale(.25);">
                          <?php else: ?>
                            <div class="d-grid" style="height:150px;place-items:center;background:var(--app-surface-2);color:var(--app-text-muted);">
                              <i class="fa-solid fa-image fa-2x"></i>
                            </div>
                          <?php endif; ?>
                          <div class="card-body d-flex flex-column">
                            <strong style="font-size:.9rem;"><?= htmlspecialchars($item['produto']) ?></strong>
                            <?php if ($item['marca']): ?>
                              <small class="text-muted"><?= htmlspecialchars($item['marca']) ?></small>
                            <?php endif; ?>
                            <span class="status-badge mt-2 align-self-start"
                                  style="background:rgba(184,134,11,.12);color:#8a6508;border:1px solid rgba(184,134,11,.35);">
                              <span class="pt"></span>Reservado
                              <?php if (!empty($item['reservado_pedido_id'])): ?>
                                · Pedido #<?= (int) $item['reservado_pedido_id'] ?>
                              <?php endif; ?>
                            </span>
                            <div class="mt-auto d-flex justify-content-between align-items-end pt-3">
                              <span class="fw-bold" style="font-size:1rem;"><?= $fmt($item['preco']) ?></span>
                              <small class="text-muted"><?= (int) $item['quantidade'] ?>x</small>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="px-3 pb-3">
                    <small class="text-muted">
                      <i class="fa-solid fa-circle-info me-1"></i>
                      Estes itens ficam fora da loja até que o pedido seja <strong>concluído</strong> (viram vendidos) ou
                      <strong>cancelado</strong> (voltam a ficar disponíveis). Gerencie na aba Pedidos.
                    </small>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (count($vendidos) > 0): ?>
                <p class="text-muted small mt-3 mb-0">
                  <i class="fa-solid fa-circle-check me-1"></i>
                  <?= count($vendidos) ?> <?= count($vendidos) === 1 ? 'item já vendido' : 'itens já vendidos' ?>
                  (não aparecem na loja).
                </p>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- ================= Pedidos (em andamento) ================= -->
          <div class="tab-pane fade <?= $aba === 'pedidos' ? 'show active' : '' ?>" id="abaPedidos" role="tabpanel">
            <?php
              $pedidosLista      = $pedidosAndamento;
              $somenteLeitura    = false;
              $pedidosVazioIcone = 'fa-receipt';
              $pedidosVazioTexto = 'Nenhum pedido em andamento. Pedidos concluídos ficam na aba "Concluídos".';
              $abaFormulario     = 'pedidos';
              include __DIR__ . '/partials/_loja_pedidos.php';
            ?>
          </div>

          <!-- ================= Concluídos ================= -->
          <div class="tab-pane fade <?= $aba === 'concluidos' ? 'show active' : '' ?>" id="abaConcluidos" role="tabpanel">
            <?php
              $pedidosLista      = $pedidosConcluidos;
              $somenteLeitura    = true;
              $pedidosVazioIcone = 'fa-box-open';
              $pedidosVazioTexto = 'Nenhum pedido concluído ainda. Ao concluir um pedido, ele aparece aqui.';
              $abaFormulario     = 'concluidos';
              include __DIR__ . '/partials/_loja_pedidos.php';
            ?>
          </div>

        </div><!-- /tab-content -->

        <script>
        (function () {
          var sel = document.getElementById('selAbaLojaVirtual');
          var btns = document.querySelectorAll('#abasLojaVirtual [data-bs-toggle="tab"]');
          for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener('shown.bs.tab', function () {
              if (sel) sel.value = this.getAttribute('data-valor');
            });
          }
          if (sel) {
            sel.addEventListener('change', function () {
              var btn = document.querySelector('#abasLojaVirtual [data-valor="' + sel.value + '"]');
              if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
            });
          }

          var btnCopiar = document.getElementById('btnCopiarLink');
          if (btnCopiar) {
            btnCopiar.addEventListener('click', function () {
              var inp = document.getElementById('lojaLink');
              if (!inp) return;
              inp.select();
              inp.setSelectionRange(0, 99999);
              var ok = function () {
                btnCopiar.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copiado!';
                setTimeout(function () {
                  btnCopiar.innerHTML = '<i class="fa-regular fa-copy me-1"></i>Copiar';
                }, 1800);
              };
              if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(inp.value).then(ok, function () { document.execCommand('copy'); ok(); });
              } else {
                document.execCommand('copy');
                ok();
              }
            });
          }
        })();
        </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>
