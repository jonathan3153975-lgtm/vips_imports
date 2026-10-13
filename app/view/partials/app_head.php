<?php
// =====================================================================
// VIP Imports — Partial de layout: cabeçalho, sidebar e topbar
// Espera: $titulo, $paginaAtual, $nomeUsuario, $emailUsuario, $perfilLabel
// Opcional: $badgeViagens (int), $badgeCompras (int), $headExtra (HTML)
// =====================================================================
$badgeViagens  = $badgeViagens ?? 0;
$badgeCompras  = $badgeCompras ?? 0;
$badgeEstoque  = $badgeEstoque ?? 0;
$badgePedidos  = $badgePedidos ?? 0;

$ativoPainel   = $paginaAtual === 'painel'   ? 'active' : '';
$ativoViagens  = $paginaAtual === 'viagens'  ? 'active' : '';
$ativoGastos   = $paginaAtual === 'gastos'   ? 'active' : '';
$ativoCompras  = $paginaAtual === 'compras'  ? 'active' : '';
$ativoEstoque  = $paginaAtual === 'estoque'  ? 'active' : '';
$ativoLoja     = $paginaAtual === 'loja'     ? 'active' : '';
$ativoBalanco  = $paginaAtual === 'balanco'  ? 'active' : '';
$ativoConfig   = $paginaAtual === 'configuracoes' ? 'active' : '';
$ativoUsuarios = $paginaAtual === 'usuarios' ? 'active' : '';
$ehMaster      = ($_SESSION['usuario']['perfil'] ?? '') === 'master';

// Logotipo da empresa (avatar do usuário) — vem da configuração do usuário.
require_once dirname(__DIR__, 2) . '/model/UsuarioConfig.php';
$avatarCfg     = (new UsuarioConfig())->garantir((int) ($_SESSION['usuario']['id'] ?? 0));
$avatarArquivo = trim((string) ($avatarCfg['loja_logo'] ?? ''));
$avatarLogo    = null;
if ($avatarArquivo !== '' && is_file(dirname(__DIR__, 3) . '/public/uploads/logos/' . $avatarArquivo)) {
    $avatarLogo = '../../public/uploads/logos/' . $avatarArquivo;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($titulo) ?> — VIP Imports</title>

  <!-- Libs -->
  <link rel="stylesheet" href="../../public/assets/css/bootstrap-5.3.3/bootstrap.min.css">
  <link rel="stylesheet" href="../../public/assets/css/fontawesome-6.5.2/css/all.min.css">
  <!-- App -->
  <link rel="stylesheet" href="../../public/assets/css/app/estilo.css">
  <?= $headExtra ?? '' ?>
</head>
<body>

  <div class="app-shell app-body">

    <!-- ============================ Sidebar ============================ -->
    <aside class="sidebar">
      <a href="painel.php" class="sidebar-brand">
        <div class="brand-logo"><i class="fa-solid fa-plane-up"></i></div>
        <div class="texts">
          <strong style="font-size:1.05rem;">VIP Imports</strong>
          <small>Viagens · Compras · Vendas</small>
        </div>
        <button class="btn btn-sm text-light ms-auto mobile-close" type="button" data-sidebar-close title="Fechar menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </a>

      <nav class="sidebar-nav">
        <p class="sidebar-section-label">Menu principal</p>
        <ul class="nav-group">
          <li class="nav-item">
            <a href="painel.php" class="<?= $ativoPainel ?>">
              <i class="fa-solid fa-gauge-high"></i><span>Painel</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="viagens.php" class="<?= $ativoViagens ?>">
              <i class="fa-solid fa-plane"></i><span>Viagens</span>
              <?php if ($badgeViagens > 0): ?><span class="badge-opt"><?= (int) $badgeViagens ?></span><?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a href="gastos.php" class="<?= $ativoGastos ?>">
              <i class="fa-solid fa-coins"></i><span>Gastos</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="compras.php" class="<?= $ativoCompras ?>">
              <i class="fa-solid fa-cart-shopping"></i><span>Compras</span>
              <?php if ($badgeCompras > 0): ?><span class="badge-opt"><?= (int) $badgeCompras ?></span><?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a href="estoque.php" class="<?= $ativoEstoque ?>">
              <i class="fa-solid fa-boxes-stacked"></i><span>Estoque</span>
              <?php if ($badgeEstoque > 0): ?><span class="badge-opt"><?= (int) $badgeEstoque ?></span><?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a href="loja.php" class="<?= $ativoLoja ?>">
              <i class="fa-solid fa-store"></i><span>Loja</span>
              <?php if ($badgePedidos > 0): ?><span class="badge-opt"><?= (int) $badgePedidos ?></span><?php endif; ?>
            </a>
          </li>
        </ul>

        <p class="sidebar-section-label">Financeiro &amp; Analítico</p>
        <ul class="nav-group">
          <li class="nav-item">
            <a href="balanco.php" class="<?= $ativoBalanco ?>">
              <i class="fa-solid fa-scale-balanced"></i><span>Balanço</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" data-alert="Relatórios em construção.">
              <i class="fa-solid fa-chart-column"></i><span>Relatórios</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="configuracoes.php" class="<?= $ativoConfig ?>">
              <i class="fa-solid fa-gear"></i><span>Configurações</span>
            </a>
          </li>
        </ul>

        <?php if ($ehMaster): ?>
        <p class="sidebar-section-label">Administração</p>
        <ul class="nav-group">
          <li class="nav-item">
            <a href="usuarios.php" class="<?= $ativoUsuarios ?>">
              <i class="fa-solid fa-users-gear"></i><span>Usuários</span>
            </a>
          </li>
        </ul>
        <?php endif; ?>
      </nav>

      <div class="sidebar-user">
        <a class="sidebar-user-link" href="perfil.php" title="Editar perfil">
          <span class="avatar">
            <?php if ($avatarLogo !== null): ?>
              <img src="<?= htmlspecialchars($avatarLogo) ?>" alt="Logotipo" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
              <div class="brand-logo" style="width:38px;height:38px;font-size:.95rem;border-radius:50%;">
                <i class="fa-regular fa-user"></i>
              </div>
            <?php endif; ?>
          </span>
          <div class="meta">
            <strong><?= htmlspecialchars($nomeUsuario) ?></strong>
            <small><?= htmlspecialchars($emailUsuario) ?></small>
          </div>
        </a>
        <a class="logout" href="../controller/AuthController.php?acao=logout" title="Sair">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
      </div>
    </aside>
    <div class="sidebar-backdrop"></div>

    <!-- ============================ Conteúdo ============================ -->
    <div class="main-area">

      <!-- Topbar -->
      <header class="topbar">
        <button class="icon-btn" type="button" data-sidebar-toggle title="Alternar menu">
          <i class="fa-solid fa-bars"></i>
        </button>

        <div class="search">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-0 position-absolute" style="top:50%;transform:translateY(-50%);left:.9rem;color:var(--app-text-muted);z-index:5;">
              <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="search" class="form-control" placeholder="Buscar viagem, item ou venda..." style="padding-left:2.6rem;">
          </div>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
          <button class="icon-btn" type="button" data-theme-toggle title="Alternar tema claro/noturno">
            <i class="fa-solid fa-sun" data-theme-icon></i>
          </button>
          <button class="icon-btn" type="button" title="Notificações" data-alert="Nenhuma notificação no momento.">
            <i class="fa-regular fa-bell"></i>
            <span class="dot"></span>
          </button>
          <a class="avatar-btn ms-1" href="perfil.php" title="Editar perfil">
            <span class="avatar">
              <?php if ($avatarLogo !== null): ?>
                <img src="<?= htmlspecialchars($avatarLogo) ?>" alt="Logotipo" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
              <?php else: ?>
                <span class="brand-logo" style="width:38px;height:38px;font-size:.95rem;border-radius:50%;">
                  <i class="fa-regular fa-user"></i>
                </span>
              <?php endif; ?>
            </span>
            <span class="meta d-none d-md-block">
              <strong style="font-size:.86rem;"><?= htmlspecialchars($nomeUsuario) ?></strong>
              <small><?= htmlspecialchars($perfilLabel) ?></small>
            </span>
          </a>
        </div>
      </header>

      <!-- Conteúdo -->
      <main class="main-content">
