<?php
// =====================================================================
// VIP Imports — Gastos
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Viagem.php';
require_once dirname(__DIR__) . '/model/Gasto.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['master' => 'Master', 'admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Gastos';
$paginaAtual  = 'gastos';

$viagemModel = new Viagem();
$gastoModel  = new Gasto();

$viagens      = $viagemModel->listarTodas($usuarioId);
$badgeViagens = count($viagens);

$viagemId = (int) ($_GET['viagem'] ?? 0);
$viagemSelecionada = $viagemId > 0 ? $viagemModel->buscarPorId($viagemId, $usuarioId) : null;
if ($viagemSelecionada === null) {
    $viagemSelecionada = $viagemModel->viagemAberta($usuarioId) ?? ($viagens[0] ?? null);
}

$mensagens = [
    'gasto'         => ['success', 'Gasto registrado com sucesso.'],
    'excluido'      => ['success', 'Gasto excluído com sucesso.'],
    'sem_viagem'    => ['danger',  'Selecione uma viagem válida para lançar o gasto.'],
    'categoria'     => ['danger',  'Categoria de gasto inválida.'],
    'valor'         => ['danger',  'Informe um valor maior que zero.'],
    'campos'        => ['danger',  'Dados inválidos. Verifique e tente novamente.'],
    'sistema'       => ['danger',  'Não foi possível concluir a operação. Tente novamente.'],
    'cotacao'       => ['danger',  'Não foi possível obter a cotação do dólar. Informe o valor manualmente.'],
];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? $mensagens[$_GET['erro'] ?? ''] ?? null;

$gastos     = [];
$totais     = array_fill_keys(Gasto::CATEGORIAS, 0.0);
$totalGeral = 0.0;
if ($viagemSelecionada) {
    $gastos     = $gastoModel->listarPorViagem((int) $viagemSelecionada['id'], $usuarioId);
    $totais     = $gastoModel->totaisPorCategoria((int) $viagemSelecionada['id'], $usuarioId);
    $totalGeral = $gastoModel->totalPorViagem((int) $viagemSelecionada['id'], $usuarioId);
}

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Gastos</h1>
            <span class="muted small">Registre e acompanhe os gastos de cada viagem</span>
          </div>
          <?php if (count($viagens) > 0): ?>
            <div class="actions">
              <form action="gastos.php" method="get" class="d-flex align-items-center gap-2">
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
              Nenhuma viagem registrada. <a href="viagens.php" class="text-brand-blue fw-semibold" style="text-decoration:none;">Registre uma viagem</a> para lançar gastos.
            </div>
          </div>
        <?php else: ?>
          <?php
          $origem = 'gastos';
          include __DIR__ . '/partials/_gastos.php';
          ?>
        <?php endif; ?>

<?php include __DIR__ . '/partials/app_foot.php'; ?>
