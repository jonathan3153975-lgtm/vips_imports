<?php
// =====================================================================
// VIP Imports — Configurações (abas: Bot do Telegram · Webhook · Loja)
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/UsuarioConfig.php';
require_once dirname(__DIR__) . '/model/Estoque.php';
require_once dirname(__DIR__) . '/model/Pedido.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['master' => 'Master', 'admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Configurações';
$paginaAtual  = 'configuracoes';

$cfgModel     = new UsuarioConfig();
$estoqueModel = new Estoque();
$pedidoModel  = new Pedido();

$badgeEstoque = $estoqueModel->contarPublicados($usuarioId);
$badgePedidos = $pedidoModel->contarNovos($usuarioId);

$cfg   = $cfgModel->garantir($usuarioId);
$token = (string) $cfg['loja_token'];

$flash = $_SESSION['config_flash'] ?? null;
unset($_SESSION['config_flash']);

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base    = preg_replace('#/app/view/[^/]+$#', '', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

$urlLoja    = $esquema . '://' . $host . $base . '/public/loja.php?c=' . $token;
$urlWebhook = $esquema . '://' . $host . $base . '/public/telegram.php?w=' . (string) ($cfg['telegram_webhook_secret'] ?? '');

$abasPermitidas = ['telegram', 'webhook', 'loja'];
$aba = $_GET['aba'] ?? 'telegram';
if (!in_array($aba, $abasPermitidas, true)) {
    $aba = 'telegram';
}

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Configurações</h1>
            <span class="muted small">Integração com o Telegram e link público da loja</span>
          </div>
        </div>

        <?php if ($flash !== null): ?>
          <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($flash['texto']) ?></span>
          </div>
        <?php endif; ?>

        <!-- Abas -->
        <ul class="nav nav-tabs d-none d-md-flex mb-4" id="abasConfig" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'telegram' ? 'active' : '' ?>" data-valor="telegram"
                    data-bs-toggle="tab" data-bs-target="#abaTelegram" type="button" role="tab">
              <i class="fa-brands fa-telegram me-1"></i>Bot do Telegram
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'webhook' ? 'active' : '' ?>" data-valor="webhook"
                    data-bs-toggle="tab" data-bs-target="#abaWebhook" type="button" role="tab">
              <i class="fa-solid fa-satellite-dish me-1"></i>Webhook
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $aba === 'loja' ? 'active' : '' ?>" data-valor="loja"
                    data-bs-toggle="tab" data-bs-target="#abaLoja" type="button" role="tab">
              <i class="fa-solid fa-store me-1"></i>Link da loja
            </button>
          </li>
        </ul>

        <select class="form-select d-md-none mb-3" id="selAbaConfig" aria-label="Seção das configurações">
          <option value="telegram" <?= $aba === 'telegram' ? 'selected' : '' ?>>Bot do Telegram</option>
          <option value="webhook" <?= $aba === 'webhook' ? 'selected' : '' ?>>Webhook</option>
          <option value="loja" <?= $aba === 'loja' ? 'selected' : '' ?>>Link da loja</option>
        </select>

        <div class="tab-content">

          <!-- ===================== Aba: Bot do Telegram ===================== -->
          <div class="tab-pane fade <?= $aba === 'telegram' ? 'show active' : '' ?>" id="abaTelegram" role="tabpanel">
            <div class="section-card">
              <div class="section-head section-head--stack">
                <h5><span class="ico me-2"><i class="fa-brands fa-telegram"></i></span>Bot do Telegram</h5>
              </div>
              <div class="p-3 p-md-4">
                <p class="text-muted small">
                  Crie um bot no <strong>@BotFather</strong>, cole o token abaixo e informe o ID do grupo onde o
                  catálogo será divulgado. Depois, registre o webhook na aba <strong>Webhook</strong>.
                </p>

                <form action="../controller/ConfiguracaoController.php" method="post">
                  <input type="hidden" name="acao" value="salvar_telegram">
                  <input type="hidden" name="aba" value="telegram">

                  <div class="mb-3">
                    <label class="form-label">Token do bot</label>
                    <input type="text" name="telegram_bot_token" class="form-control login-input"
                           placeholder="123456789:ABC..." maxlength="160"
                           value="<?= htmlspecialchars((string) ($cfg['telegram_bot_token'] ?? '')) ?>">
                    <small class="text-muted">Fornecido pelo @BotFather.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Segredo do webhook</label>
                    <div class="input-group">
                      <input type="text" name="telegram_webhook_secret" id="tgSecret" class="form-control login-input"
                             maxlength="80" value="<?= htmlspecialchars((string) ($cfg['telegram_webhook_secret'] ?? '')) ?>">
                      <button class="btn btn-light border fw-semibold" type="button" id="btnGerarSegredo">
                        <i class="fa-solid fa-dice me-1"></i>Gerar
                      </button>
                    </div>
                    <small class="text-muted">Valida que as chamadas vieram do Telegram.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">ID do grupo (chat_id)</label>
                    <input type="text" name="telegram_group_chat_id" class="form-control login-input"
                           placeholder="Ex.: -1001234567890" maxlength="40"
                           value="<?= htmlspecialchars((string) ($cfg['telegram_group_chat_id'] ?? '')) ?>">
                    <small class="text-muted">Adicione o bot ao grupo; o ID costuma começar com <code>-100</code>.</small>
                  </div>

                  <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Salvar
                  </button>
                </form>

                <hr class="my-4">

                <h6 class="fw-semibold">Comandos do bot</h6>
                <ul class="text-muted small mb-0">
                  <li><code>/catalogo</code> — lista os itens à venda no grupo</li>
                  <li><code>/pedido &lt;id&gt; &lt;qtd&gt; &lt;contato&gt;</code> — participante faz um pedido</li>
                  <li><code>/divulgar &lt;id&gt;</code> — publica um item no grupo (no privado com o bot)</li>
                  <li><code>/divulgar_catalogo</code> — publica o catálogo inteiro no grupo</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- ===================== Aba: Webhook ===================== -->
          <div class="tab-pane fade <?= $aba === 'webhook' ? 'show active' : '' ?>" id="abaWebhook" role="tabpanel">
            <div class="section-card">
              <div class="section-head section-head--stack">
                <h5><span class="ico me-2"><i class="fa-solid fa-satellite-dish"></i></span>Webhook do Telegram</h5>
              </div>
              <div class="p-3 p-md-4">
                <p class="text-muted small">
                  O webhook conecta o Telegram ao sistema: é por ele que o bot recebe comandos
                  (<code>/catalogo</code>, <code>/pedido</code>) e avisa você sobre novos pedidos.
                  É preciso uma URL pública (HTTPS) apontando para este sistema.
                </p>

                <form action="../controller/ConfiguracaoController.php" method="post">
                  <input type="hidden" name="acao" value="registrar_webhook">
                  <input type="hidden" name="aba" value="webhook">

                  <label class="form-label">URL do webhook</label>
                  <div class="input-group mb-3">
                    <input type="text" class="form-control login-input" readonly value="<?= htmlspecialchars($urlWebhook) ?>">
                  </div>

                  <button type="submit" class="btn btn-brand-blue btn-round fw-semibold"
                          <?= (empty($cfg['telegram_bot_token']) ? 'disabled' : '') ?>>
                    <i class="fa-solid fa-satellite-dish me-1"></i>Registrar webhook
                  </button>

                  <?php if (empty($cfg['telegram_bot_token'])): ?>
                    <small class="text-muted d-block mt-2">
                      Salve o token do bot (aba <a href="?aba=telegram">Bot do Telegram</a>) antes de registrar o webhook.
                    </small>
                  <?php else: ?>
                    <small class="text-muted d-block mt-2">
                      Ao registrar, o Telegram passa a enviar as mensagens para esta URL.
                    </small>
                  <?php endif; ?>
                </form>
              </div>
            </div>
          </div>

          <!-- ===================== Aba: Link da loja ===================== -->
          <div class="tab-pane fade <?= $aba === 'loja' ? 'show active' : '' ?>" id="abaLoja" role="tabpanel">
            <div class="section-card">
              <div class="section-head section-head--stack">
                <h5><span class="ico me-2"><i class="fa-solid fa-store"></i></span>Link da loja</h5>
              </div>
              <div class="p-3 p-md-4">
                <p class="text-muted small">
                  Divulgue este link para os clientes acessarem a loja e fazerem pedidos sem login.
                </p>

                <div class="input-group mb-3">
                  <span class="input-group-text"><i class="fa-solid fa-globe"></i></span>
                  <input type="text" class="form-control login-input" id="lojaLink" value="<?= htmlspecialchars($urlLoja) ?>" readonly>
                  <button class="btn btn-light border fw-semibold" type="button" id="btnCopiarLink">
                    <i class="fa-regular fa-copy me-1"></i>Copiar
                  </button>
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <a href="<?= htmlspecialchars($urlLoja) ?>" target="_blank" rel="noopener" class="btn btn-light border btn-round fw-semibold">
                    <i class="fa-solid fa-up-right-from-square me-1"></i>Abrir loja
                  </a>
                  <form action="../controller/ConfiguracaoController.php" method="post"
                        onsubmit="return confirm('Gerar um novo link? O atual deixará de funcionar.');">
                    <input type="hidden" name="acao" value="renovar_token">
                    <input type="hidden" name="aba" value="loja">
                    <button type="submit" class="btn btn-light border btn-round fw-semibold">
                      <i class="fa-solid fa-rotate me-1"></i>Novo link
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /tab-content -->

        <script>
        (function () {
          var sel = document.getElementById('selAbaConfig');
          var btns = document.querySelectorAll('#abasConfig [data-bs-toggle="tab"]');
          for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener('shown.bs.tab', function () {
              if (sel) sel.value = this.getAttribute('data-valor');
            });
          }
          if (sel) {
            sel.addEventListener('change', function () {
              var btn = document.querySelector('#abasConfig [data-valor="' + sel.value + '"]');
              if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
            });
          }

          var btn = document.getElementById('btnGerarSegredo');
          if (btn) {
            btn.addEventListener('click', function () {
              var chars = '0123456789abcdef';
              var s = '';
              for (var i = 0; i < 32; i++) s += chars.charAt(Math.floor(Math.random() * 16));
              document.getElementById('tgSecret').value = s;
            });
          }

          var copiar = document.getElementById('btnCopiarLink');
          if (copiar) {
            copiar.addEventListener('click', function () {
              var inp = document.getElementById('lojaLink');
              inp.select();
              inp.setSelectionRange(0, 99999);
              var ok = function () {
                copiar.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copiado!';
                setTimeout(function () { copiar.innerHTML = '<i class="fa-regular fa-copy me-1"></i>Copiar'; }, 1600);
              };
              if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(inp.value).then(ok, function () { document.execCommand('copy'); ok(); });
              } else { document.execCommand('copy'); ok(); }
            });
          }
        })();
        </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>
