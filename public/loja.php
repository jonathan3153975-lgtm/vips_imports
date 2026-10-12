<?php
// =====================================================================
// VIP Imports — Loja pública (catálogo divulgado para clientes)
// Acesso: /public/loja.php?c=TOKEN
// Não exige login. Mostra os itens à venda e permite montar um pedido.
// =====================================================================

require_once dirname(__DIR__) . '/app/model/UsuarioConfig.php';
require_once dirname(__DIR__) . '/app/model/Estoque.php';

$token = trim((string) ($_GET['c'] ?? ''));
$cfg   = $token === '' ? null : (new UsuarioConfig())->buscarPorToken($token);

$valido   = $cfg !== null;
$lojaNome = $valido ? trim((string) ($cfg['loja_nome'] ?? '')) : '';
$nomeLoja = $valido && $lojaNome !== '' ? $lojaNome : (string) ($cfg['usuario_nome'] ?? '');
$lojaLogo = $valido ? trim((string) ($cfg['loja_logo'] ?? '')) : '';
$produtos = [];

if ($valido) {
    $todos = (new Estoque())->publicados((int) $cfg['usuario_id']);
    $produtos = array_values(array_filter($todos, fn($x) => !$x['vendido'] && !$x['reservado']));
}

$baseFotos = 'uploads/compras/';
$baseLogos = 'uploads/logos/';
$fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$tokenJson = json_encode($token, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $valido ? htmlspecialchars($nomeLoja) . ' — Loja' : 'Loja indisponível' ?> · VIP Imports</title>
  <link rel="stylesheet" href="assets/css/bootstrap-5.3.3/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/fontawesome-6.5.2/css/all.min.css">
  <link rel="stylesheet" href="assets/css/app/estilo.css">
  <style>
    body { background: var(--app-bg, #f4f6fb); }
    .loja-top { background: linear-gradient(135deg, #0d4fae, #0a84ff); color: #fff; }
    .loja-top .brand-logo { background: rgba(255,255,255,.18); color: #fff; }
    .produto-foto { height: 210px; object-fit: cover; width: 100%; }
    .produto-sem-foto { height: 210px; display: grid; place-items: center; background: var(--app-surface-2, #eef1f7); color: var(--app-text-muted, #889); }
    .carrinho-flutuante { position: fixed; right: 1rem; bottom: 1rem; z-index: 1040; }
    .carrinho-flutuante .btn { border-radius: 50px; box-shadow: 0 8px 24px rgba(0,0,0,.22); }
  </style>
</head>
<body>

<?php if (!$valido): ?>
  <div class="container py-5" style="max-width:560px;">
    <div class="card card-br mt-5 text-center">
      <div class="card-body p-5">
        <i class="fa-solid fa-store-slash fa-3x mb-3 text-muted"></i>
        <h1 class="h4">Loja indisponível</h1>
        <p class="text-muted mb-0">Este link é inválido ou foi substituído. Peça um link atualizado ao vendedor.</p>
      </div>
    </div>
  </div>
</body>
</html>
<?php exit; endif; ?>

  <!-- Topo -->
  <header class="loja-top py-4 mb-4">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <?php if ($lojaLogo !== '' && is_file(__DIR__ . '/' . $baseLogos . $lojaLogo)): ?>
          <img src="<?= htmlspecialchars($baseLogos . $lojaLogo) ?>" class="brand-logo" alt="Logotipo da loja"
               style="object-fit:cover;">
        <?php else: ?>
          <div class="brand-logo"><i class="fa-solid fa-plane-up"></i></div>
        <?php endif; ?>
        <div>
          <div style="font-size:.8rem;letter-spacing:.5px;text-transform:uppercase;opacity:.85;">Loja de</div>
          <h1 class="h4 mb-0"><?= htmlspecialchars($nomeLoja) ?></h1>
        </div>
      </div>
      <div class="text-end small" style="opacity:.9;">
        <i class="fa-solid fa-truck-fast me-1"></i>Importados originais<br>
        <i class="fa-solid fa-shield-halved me-1"></i>Pedido direto com o vendedor
      </div>
    </div>
  </header>

  <main class="container pb-5">

    <?php if (count($produtos) === 0): ?>
      <div class="card card-br text-center py-5">
        <div class="card-body text-muted">
          <i class="fa-solid fa-box-open fa-2x mb-3 d-block"></i>
          Nenhum item disponível no momento. Volte em breve!
        </div>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($produtos as $item): ?>
          <?php $foto = $item['fotos'][0]['arquivo'] ?? null; ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="card card-br h-100">
              <?php if ($foto): ?>
                <img src="<?= htmlspecialchars($baseFotos . $foto) ?>" class="produto-foto" alt="<?= htmlspecialchars($item['produto']) ?>">
              <?php else: ?>
                <div class="produto-sem-foto"><i class="fa-solid fa-image fa-2x"></i></div>
              <?php endif; ?>
              <div class="card-body d-flex flex-column">
                <strong style="font-size:.95rem;"><?= htmlspecialchars($item['produto']) ?></strong>
                <?php if ($item['marca']): ?>
                  <small class="text-muted"><?= htmlspecialchars($item['marca']) ?></small>
                <?php endif; ?>
                <?php if ($item['descricao']): ?>
                  <p class="text-muted small mt-2 mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                    <?= htmlspecialchars($item['descricao']) ?>
                  </p>
                <?php endif; ?>
                <div class="mt-auto pt-3 d-flex justify-content-between align-items-center">
                  <span class="fw-bold" style="font-size:1.1rem;"><?= $fmt($item['preco']) ?></span>
                  <button type="button" class="btn btn-sm btn-brand-blue btn-round btn-add"
                          data-id="<?= (int) $item['id'] ?>"
                          data-nome="<?= htmlspecialchars($item['produto']) ?>"
                          data-preco="<?= htmlspecialchars((string) $item['preco']) ?>"
                          data-foto="<?= htmlspecialchars($foto ? $baseFotos . $foto : '') ?>"
                          title="Adicionar ao pedido">
                    <i class="fa-solid fa-cart-plus me-1"></i>Adicionar
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="text-center text-muted small mt-5 mb-0">
      A finalização do pedido é um combinado direto com o vendedor. Nenhum pagamento é processado aqui.
    </p>
  </main>

  <!-- Botão flutuante do carrinho -->
  <div class="carrinho-flutuante" id="carrinhoBtnWrap" style="display:none;">
    <button class="btn btn-brand-blue btn-lg fw-semibold" type="button" data-bs-toggle="offcanvas" data-bs-target="#carrinhoOff">
      <i class="fa-solid fa-cart-shopping me-2"></i>Meu pedido
      <span class="badge text-bg-light ms-2" id="carrinhoCount">0</span>
    </button>
  </div>

  <!-- Carrinho (offcanvas) -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="carrinhoOff" aria-labelledby="carrinhoOffLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="carrinhoOffLabel"><i class="fa-solid fa-cart-shopping me-2"></i>Meu pedido</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
      <div id="carrinhoLista" class="flex-grow-1"></div>

      <hr>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted">Total</span>
        <strong style="font-size:1.2rem;" id="carrinhoTotal">R$ 0,00</strong>
      </div>

      <form id="formPedido">
        <div class="mb-2">
          <label class="form-label">Seu nome <span class="text-danger">*</span></label>
          <input type="text" class="form-control login-input" name="nome" required maxlength="120">
        </div>
        <div class="mb-2">
          <label class="form-label">Contato (WhatsApp / e-mail) <span class="text-danger">*</span></label>
          <input type="text" class="form-control login-input" name="contato" id="contatoPedido"
                 required maxlength="160" inputmode="tel" placeholder="(11) 99999-9999 ou seu@email.com">
          <small class="text-muted" id="contatoAjuda" style="display:none;">
            Número formatado automaticamente. Digite um e-mail para remover a máscara.
          </small>
        </div>
        <div class="mb-3">
          <label class="form-label">Observação <span class="text-muted">(opcional)</span></label>
          <textarea class="form-control login-input" name="observacao" rows="2" maxlength="500"
                    placeholder="Tamanho, cor, detalhes da entrega..."></textarea>
        </div>
        <div id="pedidoAviso" class="alert py-2 px-3 mb-3" style="display:none;font-size:.86rem;"></div>
        <button type="submit" class="btn btn-brand-blue btn-round w-100 fw-semibold" id="btnEnviarPedido" disabled>
          <i class="fa-solid fa-paper-plane me-1"></i>Enviar pedido
        </button>
      </form>
    </div>
  </div>

  <script src="assets/js/bootstrap-5.3.3/bootstrap.bundle.min.js"></script>
  <script>
  (function () {
    'use strict';
    var TOKEN = <?= $tokenJson ?>;
    var KEY = 'vip_loja_cart_' + TOKEN;
    var cart = {};
    try { cart = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { cart = {}; }

    var listaEl  = document.getElementById('carrinhoLista');
    var totalEl  = document.getElementById('carrinhoTotal');
    var countEl  = document.getElementById('carrinhoCount');
    var wrapBtn  = document.getElementById('carrinhoBtnWrap');
    var btnEnviar = document.getElementById('btnEnviarPedido');
    var aviso    = document.getElementById('pedidoAviso');

    // Máscara de telefone no contato — aplicada só quando o conteúdo for numérico
    // (se o cliente digitar um e-mail, a máscara não interfere).
    var inputContato = document.getElementById('contatoPedido');
    var ajudaContato = document.getElementById('contatoAjuda');
    function mascaraTelefone(v) {
      var d = String(v).replace(/\D/g, '').slice(0, 11);
      if (d.length === 0) return '';
      if (d.length <= 2) return '(' + d;
      if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
      return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    }
    if (inputContato) {
      inputContato.addEventListener('input', function () {
        var el = this;
        if (/[a-zA-Z@]/.test(el.value)) {
          if (ajudaContato) ajudaContato.style.display = 'none';
          return;
        }
        if (ajudaContato) ajudaContato.style.display = el.value ? '' : 'none';
        var digitosAntes = el.value.slice(0, el.selectionStart).replace(/\D/g, '').length;
        el.value = mascaraTelefone(el.value);
        var pos = 0, cont = 0;
        while (pos < el.value.length && cont < digitosAntes) {
          if (/\d/.test(el.value.charAt(pos))) cont++;
          pos++;
        }
        try { el.setSelectionRange(pos, pos); } catch (e) {}
      });
    }

    function money(v) { return 'R$ ' + (Number(v) || 0).toFixed(2).replace('.', ','); }
    function esc(v) {
      return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function salvar() { try { localStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) {} }

    function itensArray() {
      var out = [];
      for (var id in cart) {
        out.push({ produto_id: parseInt(id, 10), quantidade: cart[id].qtd });
      }
      return out;
    }

    function render() {
      var ids = Object.keys(cart);
      var total = 0, qtdTotal = 0;
      listaEl.innerHTML = '';

      if (ids.length === 0) {
        listaEl.innerHTML = '<p class="text-muted text-center py-4"><i class="fa-solid fa-cart-shopping fa-2x d-block mb-2"></i>Seu pedido está vazio.</p>';
      }

      ids.forEach(function (id) {
        var it = cart[id];
        total += (Number(it.preco) || 0) * it.qtd;
        qtdTotal += it.qtd;

        var row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 mb-3';
        row.innerHTML =
          (it.foto ? '<img src="' + esc(it.foto) + '" style="width:52px;height:52px;object-fit:cover;border-radius:10px;">' : '<div style="width:52px;height:52px;border-radius:10px;background:var(--app-surface-2);display:grid;place-items:center;"><i class="fa-solid fa-image text-muted"></i></div>') +
          '<div class="flex-grow-1"><div style="font-size:.9rem;">' + esc(it.nome) + '</div>' +
          '<div class="text-muted" style="font-size:.8rem;">' + money(it.preco) + '</div></div>' +
          '<div class="btn-group btn-group-sm" role="group">' +
          '<button type="button" class="btn btn-light border" data-menos="' + esc(id) + '">-</button>' +
          '<button type="button" class="btn btn-light border disabled" style="min-width:34px;">' + it.qtd + '</button>' +
          '<button type="button" class="btn btn-light border" data-mais="' + esc(id) + '">+</button>' +
          '</div>' +
          '<button type="button" class="btn btn-sm btn-link text-danger px-1" data-remover="' + esc(id) + '"><i class="fa-solid fa-trash"></i></button>';
        listaEl.appendChild(row);
      });

      totalEl.textContent = money(total);
      countEl.textContent = qtdTotal;
      wrapBtn.style.display = ids.length ? '' : 'none';
      btnEnviar.disabled = ids.length === 0;
      aviso.style.display = 'none';
    }

    function addItem(btn) {
      var id = btn.getAttribute('data-id');
      if (!id) return;
      if (!cart[id]) {
        cart[id] = {
          nome: btn.getAttribute('data-nome') || '',
          preco: btn.getAttribute('data-preco') || '0',
          foto: btn.getAttribute('data-foto') || '',
          qtd: 0
        };
      }
      cart[id].qtd += 1;
      salvar();
      render();
      btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>No pedido';
      setTimeout(function () { btn.innerHTML = '<i class="fa-solid fa-cart-plus me-1"></i>Adicionar'; }, 1200);
    }

    document.addEventListener('click', function (e) {
      var t = e.target;
      var add = t.closest ? t.closest('.btn-add') : null;
      if (add) { e.preventDefault(); addItem(add); return; }

      var mais = t.closest ? t.closest('[data-mais]') : null;
      if (mais) { var id1 = mais.getAttribute('data-mais'); cart[id1].qtd++; salvar(); render(); return; }

      var menos = t.closest ? t.closest('[data-menos]') : null;
      if (menos) {
        var id2 = menos.getAttribute('data-menos');
        cart[id2].qtd--;
        if (cart[id2].qtd <= 0) { delete cart[id2]; }
        salvar(); render(); return;
      }

      var rem = t.closest ? t.closest('[data-remover]') : null;
      if (rem) { delete cart[rem.getAttribute('data-remover')]; salvar(); render(); return; }
    });

    document.getElementById('formPedido').addEventListener('submit', function (e) {
      e.preventDefault();
      var form = e.target;
      var itens = itensArray();
      if (itens.length === 0) return;

      btnEnviar.disabled = true;
      btnEnviar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Enviando...';

      fetch('pedido.php?c=' + encodeURIComponent(TOKEN), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nome: form.nome.value,
          contato: form.contato.value,
          observacao: form.observacao.value,
          itens: itens
        })
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            aviso.className = 'alert alert-success py-2 px-3 mb-3';
            aviso.textContent = 'Pedido enviado! O vendedor entrará em contato.';
            aviso.style.display = '';
            cart = {}; salvar(); render();
            aviso.style.display = '';
            form.reset();
          } else {
            aviso.className = 'alert alert-danger py-2 px-3 mb-3';
            aviso.textContent = (d && d.mensagem) ? d.mensagem : 'Não foi possível enviar o pedido. Tente novamente.';
            aviso.style.display = '';
          }
        })
        .catch(function () {
          aviso.className = 'alert alert-danger py-2 px-3 mb-3';
          aviso.textContent = 'Falha de conexão. Tente novamente.';
          aviso.style.display = '';
        })
        .finally(function () {
          btnEnviar.disabled = Object.keys(cart).length === 0;
          btnEnviar.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Enviar pedido';
        });
    });

    render();
  })();
  </script>
</body>
</html>
