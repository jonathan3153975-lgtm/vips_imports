<?php
// =====================================================================
// VIP Imports — Tela de Login
// =====================================================================
session_start();

// Usuário já autenticado não precisa ver o login.
if (isset($_SESSION['usuario'])) {
    header('Location: painel.php');
    exit;
}

$paginaAtual = 'login';

$mensagens = [
    'campos'      => 'Informe o e-mail e a senha.',
    'credenciais' => 'E-mail ou senha inválidos.',
    'bloqueado'   => 'Sua conta está bloqueada. Procure o administrador.',
    'sistema'     => 'Não foi possível acessar o sistema. Tente novamente.',
];

$erro    = $_GET['erro'] ?? '';
$alerta  = $mensagens[$erro] ?? null;
$sucesso = (($_GET['msg'] ?? '') === 'logout') ? 'Sessão encerrada com sucesso.' : null;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Acessar — VIP Imports</title>

  <!-- Libs -->
  <link rel="stylesheet" href="../../public/assets/css/bootstrap-5.3.3/bootstrap.min.css">
  <link rel="stylesheet" href="../../public/assets/css/fontawesome-6.5.2/css/all.min.css">
  <!-- App -->
  <link rel="stylesheet" href="../../public/assets/css/app/estilo.css">
</head>
<body>

  <div class="login-wrap">

    <!-- Painel institucional (oculto no mobile) -->
    <aside class="login-brand">
      <div class="brand-lockup">
        <div class="brand-logo"><i class="fa-solid fa-plane-up"></i></div>
        <div class="brand-name">VIP Imports<small>Viagens · Compras · Vendas</small></div>
      </div>

      <h1>Suas viagens, compras e vendas <span>sob controle.</span></h1>
      <p class="lead">
        Registre a viagem, os gastos e os itens comprados. Acompanhe cada venda
        e descubra o lucro real de cada jornada — tudo em um único painel.
      </p>

      <ul class="login-features">
        <li>
          <span class="ico"><i class="fa-solid fa-chart-pie"></i></span>
          Painel geral com gastos e retornos por viagem
        </li>
        <li>
          <span class="ico"><i class="fa-solid fa-boxes-stacked"></i></span>
          Controle de compra, estoque e venda de itens
        </li>
        <li>
          <span class="ico"><i class="fa-solid fa-sack-dollar"></i></span>
          Resultado financeiro calculado em tempo real
        </li>
      </ul>
    </aside>

    <!-- Formulário -->
    <main class="login-form-side">
      <div style="position:absolute; top:1.2rem; right:1.2rem;">
        <button class="theme-switch-btn" type="button" title="Alternar tema" data-theme-toggle>
          <i class="fa-solid fa-sun" data-theme-icon></i>
        </button>
      </div>

      <div class="card login-card card-br">
        <div class="card-body">
          <div class="text-center mb-4 d-lg-none">
            <div class="brand-lockup justify-content-center">
              <div class="brand-logo"><i class="fa-solid fa-plane-up"></i></div>
              <div class="brand-name" style="color:var(--app-text)">VIP Imports<small class="text-brand-blue">Viagens · Compras · Vendas</small></div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="ico" style="width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:var(--brand-red);color:#fff;">
              <i class="fa-solid fa-lock"></i>
            </span>
            <h2 class="mb-0">Acessar o sistema</h2>
          </div>
          <p class="text-muted" style="font-size:.9rem; margin-top:.45rem;">Entre com suas credenciais para continuar</p>

          <?php if ($alerta !== null): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mt-3 mb-0" role="alert" style="font-size:.86rem;">
              <i class="fa-solid fa-circle-exclamation"></i>
              <span><?= htmlspecialchars($alerta) ?></span>
            </div>
          <?php elseif ($sucesso !== null): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mt-3 mb-0" role="alert" style="font-size:.86rem;">
              <i class="fa-solid fa-circle-check"></i>
              <span><?= htmlspecialchars($sucesso) ?></span>
            </div>
          <?php endif; ?>

          <form action="../controller/AuthController.php" method="post" class="mt-4">
            <input type="hidden" name="acao" value="login">
            <div class="mb-3">
              <label for="email" class="form-label">E-mail</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                <input type="email" name="email" class="form-control login-input" id="email" placeholder="voce@exemplo.com" required autofocus>
              </div>
            </div>

            <div class="mb-3">
              <label for="senha" class="form-label">Senha</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                <input type="password" name="senha" class="form-control login-input" id="senha" placeholder="••••••••" required>
                <button class="input-group-text pass-eye" type="button" data-pass-eye title="Mostrar senha">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="lembrar">
                <label class="form-check-label" for="lembrar" style="font-size:.85rem;">Manter conectado</label>
              </div>
              <a href="#" class="text-brand-red" style="font-size:.85rem; text-decoration:none;" data-alert="Recuperação de senha em construção.">Esqueci minha senha</a>
            </div>

            <button type="submit" class="btn btn-brand-red btn-round w-100 py-2 fw-semibold">
              <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Entrar
            </button>
          </form>

          <p class="text-center mt-4 mb-0" style="font-size:.86rem; color:var(--app-text-muted);">
            Ainda não tem conta?
            <a href="#" class="text-brand-blue fw-semibold" style="text-decoration:none;" data-alert="Cadastro em construção.">Solicite acesso</a>
          </p>
        </div>
      </div>

    </main>
  </div>

  <!-- Libs -->
  <script src="../../public/assets/js/jquery-3.7.1/jquery.min.js"></script>
  <script src="../../public/assets/js/bootstrap-5.3.3/bootstrap.bundle.min.js"></script>
  <!-- App -->
  <script src="../../public/assets/js/app/app.js"></script>
</body>
</html>