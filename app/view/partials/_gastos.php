<?php
// =====================================================================
// VIP Imports — Partial: tabela de gastos de uma viagem
// Espera: $viagemSelecionada (array), $gastos (array), $totais (array),
//         $totalGeral (float), $origem ('viagens'|'gastos')
// =====================================================================
$rotulos = [
    'passagem'    => ['Passagem', 'fa-plane-departure'],
    'estadia'     => ['Estadia', 'fa-hotel'],
    'alimentacao' => ['Alimentação', 'fa-utensils'],
    'outros'      => ['Outros', 'fa-ellipsis'],
];
$viagemId = (int) $viagemSelecionada['id'];
$moeda    = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$fmt      = fn($cod, $v) => ($cod === 'USD' ? 'US$ ' : 'R$ ') . number_format((float) $v, 2, ',', '.');
$mostrarCards = $mostrarCards ?? true;
?>

<!-- Cards de somatório (viram lista no mobile) -->
<?php if ($mostrarCards): ?>
<div class="row g-3 mb-3 stat-cards">
  <div class="col-6 col-md-4 col-xl">
    <div class="kpi darkblue h-100">
      <small>Total de gastos</small>
      <div class="value" style="font-size:1.3rem;"><?= $moeda($totalGeral) ?></div>
      <i class="fa-solid fa-wallet ico"></i>
    </div>
  </div>
  <?php foreach ($rotulos as $chave => [$nome, $icone]): ?>
    <div class="col-6 col-md-4 col-xl">
      <div class="kpi outline h-100">
        <small class="text-muted"><?= $nome ?></small>
        <div class="value" style="font-size:1.1rem;"><?= $moeda($totais[$chave] ?? 0) ?></div>
        <i class="fa-solid <?= $icone ?> ico"></i>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-head section-head--stack">
    <h5>
      <span class="ico me-2"><i class="fa-solid fa-receipt"></i></span>
      Gastos de <?= htmlspecialchars($viagemSelecionada['destino']) ?>
    </h5>
    <div class="section-head-actions d-flex align-items-center gap-2">
      <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
        <?= count($gastos) ?> lançamento<?= count($gastos) === 1 ? '' : 's' ?>
      </span>
      <button type="button" class="btn btn-brand-red btn-round btn-sm fw-semibold"
              data-bs-toggle="modal" data-bs-target="#modalGasto">
        <i class="fa-solid fa-plus me-1"></i>Registrar gasto
      </button>
    </div>
  </div>
  <!-- Tabela (desktop) -->
  <div class="table-responsive d-none d-md-block">
    <table class="table table-app align-middle">
      <thead>
        <tr>
          <th>Data</th>
          <th>Categoria</th>
          <th>Descrição</th>
          <th class="text-end">Valor</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($gastos) === 0): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              <i class="fa-solid fa-inbox me-1"></i> Nenhum gasto registrado nesta viagem.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($gastos as $g): ?>
            <tr>
              <td class="text-muted"><?= date('d/m/Y', strtotime($g['data_gasto'])) ?></td>
              <td>
                <span class="status-badge status-ativa">
                  <span class="pt"></span><?= $rotulos[$g['categoria']][0] ?? $g['categoria'] ?>
                </span>
              </td>
              <td><?= htmlspecialchars($g['descricao'] ?? '—') ?></td>
              <td class="text-end fw-bold">
                <?= $fmt($g['moeda'], $g['valor']) ?>
                <?php if ($g['moeda'] === 'USD'): ?>
                  <div class="text-muted" style="font-size:.72rem;font-weight:500;">≈ <?= $moeda($g['valor_brl']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <form action="../controller/GastoController.php" method="post" class="d-inline"
                      onsubmit="return confirm('Excluir este gasto?');">
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                  <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">
                  <input type="hidden" name="origem" value="<?= htmlspecialchars($origem) ?>">
                  <button type="submit" class="btn btn-sm btn-light border text-danger" title="Excluir">
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
    <?php if (count($gastos) === 0): ?>
      <div class="text-center text-muted py-4">
        <i class="fa-solid fa-inbox me-1"></i> Nenhum gasto registrado nesta viagem.
      </div>
    <?php else: ?>
      <?php foreach ($gastos as $g): ?>
        <div class="gasto-card">
          <div class="gasto-card-top">
            <div class="gasto-card-main">
              <div class="gasto-card-desc"><?= htmlspecialchars($g['descricao'] ?? 'Sem descrição') ?></div>
              <span class="status-badge status-ativa">
                <span class="pt"></span><?= $rotulos[$g['categoria']][0] ?? $g['categoria'] ?>
              </span>
            </div>
            <div class="gasto-card-date"><?= date('d/m/Y', strtotime($g['data_gasto'])) ?></div>
          </div>
          <div class="gasto-card-bottom">
            <form action="../controller/GastoController.php" method="post" class="d-inline"
                  onsubmit="return confirm('Excluir este gasto?');">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">
              <input type="hidden" name="origem" value="<?= htmlspecialchars($origem) ?>">
              <button type="submit" class="btn btn-sm btn-light border text-danger" title="Excluir">
                <i class="fa-solid fa-trash me-1"></i>Excluir
              </button>
            </form>
            <div class="gasto-card-value">
              <div style="white-space:nowrap;"><?= $fmt($g['moeda'], $g['valor']) ?>
                <?php if ($g['moeda'] === 'USD'): ?>
                  <small class="text-muted fw-normal">≈ <?= $moeda($g['valor_brl']) ?></small>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: registrar gasto -->
<div class="modal fade" id="modalGasto" tabindex="-1" aria-labelledby="modalGastoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content card-br">
      <form action="../controller/GastoController.php" method="post">
        <input type="hidden" name="acao" value="criar">
        <input type="hidden" name="viagem_id" value="<?= $viagemId ?>">
        <input type="hidden" name="origem" value="<?= htmlspecialchars($origem) ?>">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title" id="modalGastoLabel">
            <span class="ico me-2" style="width:34px;height:34px;display:inline-grid;place-items:center;border-radius:10px;background:var(--brand-red);color:#fff;vertical-align:middle;">
              <i class="fa-solid fa-coins"></i>
            </span>
            Registrar gasto
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
              <input type="file" id="gastoImagem" accept="image/jpeg,image/png,image/webp"
                     capture="environment" style="display:none;">
              <button type="button" class="btn btn-light border w-100 d-flex align-items-center justify-content-center gap-2"
                      id="btnEscanear">
                <i class="fa-solid fa-camera"></i> Fotografar / enviar nota
              </button>
            </div>
            <small class="text-muted" id="gastoScanInfo">
              Lê valor, data, moeda e categoria e preenche este formulário — revise antes de salvar.
            </small>
          </div>

          <div class="mb-3">
            <label class="form-label">Categoria</label>
            <select name="categoria" class="form-select login-input" required>
              <?php foreach ($rotulos as $chave => [$nome, $icone]): ?>
                <option value="<?= $chave ?>"><?= $nome ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-2">
            <div class="col-5 mb-3">
              <label class="form-label">Moeda</label>
              <select name="moeda" id="gastoMoeda" class="form-select login-input">
                <option value="BRL">Real (R$)</option>
                <option value="USD">Dólar (US$)</option>
              </select>
            </div>
            <div class="col-7 mb-3">
              <label class="form-label">Valor</label>
              <div class="input-group">
                <span class="input-group-text" id="gastoValorPrefixo">R$</span>
                <input type="text" inputmode="decimal" name="valor" class="form-control login-input"
                       placeholder="0,00" required data-mask-moeda data-mask-int="9">
              </div>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-7 mb-3 d-none" id="gastoCotacaoWrap">
              <label class="form-label">Cotação <span class="text-muted">(R$ por US$)</span></label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" inputmode="decimal" name="cotacao" id="gastoCotacao"
                       class="form-control login-input" placeholder="Ex.: 5,23"
                       data-mask-moeda data-mask-int="4">
              </div>
              <small class="text-muted" id="gastoCotacaoInfo"></small>
            </div>
            <div class="col-5 mb-3">
              <label class="form-label">Data</label>
              <input type="date" name="data_gasto" class="form-control login-input"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div class="mb-1">
            <label class="form-label">Descrição <span class="text-muted">(opcional)</span></label>
            <input type="text" name="descricao" class="form-control login-input"
                   placeholder="Ex.: Passagem aérea SP → Orlando" maxlength="200">
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-brand-red btn-round fw-semibold">
            <i class="fa-solid fa-circle-plus me-1"></i>Adicionar gasto
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var moeda  = document.getElementById('gastoMoeda');
  var wrap   = document.getElementById('gastoCotacaoWrap');
  var cot    = document.getElementById('gastoCotacao');
  var info   = document.getElementById('gastoCotacaoInfo');
  var prefix = document.getElementById('gastoValorPrefixo');
  if (!moeda || !wrap || !cot) return;

  var buscando = false;

  function mostrarCotacao() {
    var usd = moeda.value === 'USD';
    wrap.classList.toggle('d-none', !usd);
    prefix.textContent = usd ? 'US$' : 'R$';
    if (usd && cot.dataset.auto === undefined && !cot.value) buscarCotacao();
  }

  function buscarCotacao(force) {
    if (buscando) return;
    if (force === undefined || force === null) force = false;
    if (!force && cot.value) return;
    buscando = true;
    info.textContent = 'Buscando cotação automática...';
    fetch('../controller/CotacaoController.php?moeda=USD')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          cot.value = String(d.cotacao).replace('.', ',');
          cot.dataset.auto = '1';
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

  function marcarManual() {
    cot.dataset.auto = '0';
    info.textContent = 'Cotação manual — será usada no registro.';
  }

  moeda.addEventListener('change', mostrarCotacao);
  cot.addEventListener('input', marcarManual);
  mostrarCotacao();

  // ---------- Escanear nota fiscal (IA) ----------
  var btnScan = document.getElementById('btnEscanear');
  var inputImg = document.getElementById('gastoImagem');
  var scanInfo = document.getElementById('gastoScanInfo');
  if (!btnScan || !inputImg || !scanInfo) return;

  function fmtValor(v) {
    var num = Math.round((Number(v) || 0) * 100) / 100;
    var s = Math.abs(num).toFixed(2).replace('.', '');
    var inteiro = String(Number(s.slice(0, -2))).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    var txt = (num < 0 ? '-' : '') + inteiro + ',' + s.slice(-2);
    return txt;
  }

  function preencher(d) {
    var q = function (sel) { return document.querySelector('#modalGasto [name="' + sel + '"]'); };
    var selectCat = q('categoria');
    var inputDesc = q('descricao');
    var inputData = q('data_gasto');
    var inputValor = q('valor');

    if (d.categoria && selectCat && selectCat.options) {
      for (var i = 0; i < selectCat.options.length; i++) {
        if (selectCat.options[i].value === d.categoria) { selectCat.selectedIndex = i; break; }
      }
    }
    if (d.descricao && inputDesc) inputDesc.value = d.descricao;
    if (d.data && inputData) inputData.value = d.data;
    if (d.valor > 0 && inputValor) inputValor.value = fmtValor(d.valor);

    if (moeda) {
      moeda.value = d.moeda === 'USD' ? 'USD' : 'BRL';
      mostrarCotacao();
      if (d.moeda === 'USD') buscarCotacao(true);
    }

    scanInfo.textContent = 'Nota lida com sucesso! Revise os campos antes de salvar.';
    scanInfo.className = 'text-success';
  }

  btnScan.addEventListener('click', function () { inputImg.click(); });

  inputImg.addEventListener('change', function () {
    if (!inputImg.files || !inputImg.files.length) return;
    var arquivo = inputImg.files[0];

    scanInfo.textContent = 'Escaneando nota... aguarde.';
    scanInfo.className = 'text-muted';

    var fd = new FormData();
    fd.append('imagem', arquivo);

    fetch('../controller/LeituraController.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          preencher(d.dados);
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
})();
</script>
