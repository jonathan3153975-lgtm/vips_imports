<?php
// =====================================================================
// VIP Imports — Perfil do usuário (dados pessoais, loja e senha)
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/model/Usuario.php';
require_once dirname(__DIR__) . '/model/UsuarioConfig.php';
require_once dirname(__DIR__) . '/model/Estoque.php';
require_once dirname(__DIR__) . '/model/Pedido.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Perfil';
$paginaAtual  = 'perfil';

$usuarioModel = new Usuario();
$cfgModel     = new UsuarioConfig();
$estoqueModel = new Estoque();
$pedidoModel  = new Pedido();

$badgeEstoque = $estoqueModel->contarPublicados($usuarioId);
$badgePedidos = $pedidoModel->contarNovos($usuarioId);

$dados = $usuarioModel->buscarPorId($usuarioId);
$cfg   = $cfgModel->garantir($usuarioId);
$token = (string) $cfg['loja_token'];

$mensagens = [
    'msg'  => [
        'dados'         => ['success', 'Dados pessoais atualizados.'],
        'loja'          => ['success', 'Dados da loja salvos.'],
        'logo_removido' => ['success', 'Logotipo removido.'],
        'senha'         => ['success', 'Senha alterada com sucesso.'],
    ],
    'erro' => [
        'campos'         => ['danger', 'Preencha nome e e-mail corretamente.'],
        'email'          => ['danger', 'Este e-mail já está em uso.'],
        'sistema'        => ['danger', 'Não foi possível salvar. Tente novamente.'],
        'tamanho'        => ['danger', 'O logotipo deve ter até 2 MB.'],
        'formato'        => ['danger', 'Formato não permitido. Use JPG, PNG ou WEBP.'],
        'upload'         => ['danger', 'Falha ao enviar o logotipo.'],
        'campos_senha'   => ['danger', 'Informe a senha atual e a nova senha.'],
        'senha_curta'    => ['danger', 'A nova senha deve ter pelo menos 8 caracteres.'],
        'senha_confirma' => ['danger', 'A confirmação não confere com a nova senha.'],
        'senha_atual'    => ['danger', 'Senha atual incorreta.'],
    ],
];

$flash = null;
foreach (['msg', 'erro'] as $chave) {
    if (isset($_GET[$chave]) && isset($mensagens[$chave][$_GET[$chave]])) {
        $flash = ['tipo' => $mensagens[$chave][$_GET[$chave]][0], 'texto' => $mensagens[$chave][$_GET[$chave]][1]];
        break;
    }
}

$lojaNome   = trim((string) ($cfg['loja_nome'] ?? ''));
$lojaLogo   = trim((string) ($cfg['loja_logo'] ?? ''));
$esquema    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base       = preg_replace('#/app/view/[^/]+$#', '', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$urlLoja    = $esquema . '://' . $host . $base . '/public/loja.php?c=' . $token;
$navLoja    = $lojaNome === '' ? $nomeUsuario : $lojaNome;

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Perfil</h1>
            <span class="muted small">Seus dados, sua loja e segurança</span>
          </div>
        </div>

        <?php if ($flash !== null): ?>
          <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($flash['texto']) ?></span>
          </div>
        <?php endif; ?>

        <div class="row g-3">

          <!-- ===================== Coluna de resumo ===================== -->
          <div class="col-lg-4">
            <div class="section-card">
              <div class="p-4 text-center">
                <div class="mx-auto mb-3 d-grid place-items-center" style="width:96px;height:96px;">
                  <?php if ($lojaLogo !== '' && is_file(dirname(__DIR__, 2) . '/public/uploads/logos/' . $lojaLogo)): ?>
                    <img src="../../public/uploads/logos/<?= htmlspecialchars($lojaLogo) ?>" alt="Logotipo"
                         style="width:96px;height:96px;object-fit:cover;border-radius:20px;box-shadow:0 8px 20px rgba(0,0,0,.16);">
                  <?php else: ?>
                    <span class="brand-logo" style="width:96px;height:96px;font-size:2.4rem;border-radius:22px;">
                      <i class="fa-solid fa-store"></i>
                    </span>
                  <?php endif; ?>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($navLoja) ?></h5>
                <p class="text-muted small mb-3"><?= htmlspecialchars($perfilLabel) ?></p>

                <ul class="list-unstyled text-start small text-muted mb-3" style="font-size:.85rem;">
                  <li class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-regular fa-user fa-fw"></i>
                    <span class="text-truncate"><?= htmlspecialchars($nomeUsuario) ?></span>
                  </li>
                  <li class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-regular fa-envelope fa-fw"></i>
                    <span class="text-truncate"><?= htmlspecialchars($emailUsuario) ?></span>
                  </li>
                  <li class="d-flex align-items-center gap-2">
                    <i class="fa-regular fa-calendar fa-fw"></i>
                    <span>Membro desde <?= date('d/m/Y', strtotime((string) ($dados['criado_em'] ?? date('Y-m-d')))) ?></span>
                  </li>
                </ul>

                <a href="<?= htmlspecialchars($urlLoja) ?>" target="_blank" rel="noopener"
                   class="btn btn-light border btn-round fw-semibold w-100">
                  <i class="fa-solid fa-store me-1"></i>Ver minha loja pública
                </a>
              </div>
            </div>
          </div>

          <!-- ===================== Coluna de formulários ===================== -->
          <div class="col-lg-8 d-flex flex-column gap-3">

            <!-- Dados da loja -->
            <div class="section-card">
              <div class="section-head section-head--stack">
                <h5><span class="ico me-2"><i class="fa-solid fa-store"></i></span>Dados da loja</h5>
              </div>
              <div class="p-3 p-md-4">
                <form action="../controller/PerfilController.php" method="post" enctype="multipart/form-data">
                  <input type="hidden" name="acao" value="loja">

                  <div class="mb-3">
                    <label class="form-label">Nome da loja</label>
                    <input type="text" name="loja_nome" class="form-control login-input"
                           placeholder="<?= htmlspecialchars($nomeUsuario) ?>" maxlength="120"
                           value="<?= htmlspecialchars($lojaNome) ?>">
                    <small class="text-muted">Aparece no topo da loja pública. Em branco, usa seu nome.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Logotipo</label>
                    <input type="file" name="logo" class="form-control login-input"
                           accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">JPG, PNG ou WEBP de até 2 MB. Envie outro para substituir.</small>
                  </div>

                  <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                      <i class="fa-solid fa-floppy-disk me-1"></i>Salvar loja
                    </button>
                    <?php if ($lojaLogo !== ''): ?>
                      <button type="submit" formaction="../controller/PerfilController.php" name="acao" value="remover_logo"
                              class="btn btn-light border btn-round fw-semibold text-danger"
                              onclick="return confirm('Remover o logotipo atual?');">
                        <i class="fa-regular fa-trash-can me-1"></i>Remover logotipo
                      </button>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
            </div>

            <!-- Dados pessoais -->
            <div class="section-card">
              <div class="section-head section-head--stack">
                <h5><span class="ico me-2"><i class="fa-regular fa-user"></i></span>Dados pessoais</h5>
              </div>
              <div class="p-3 p-md-4">
                <form action="../controller/PerfilController.php" method="post">
                  <input type="hidden" name="acao" value="dados">

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Nome</label>
                      <input type="text" name="nome" class="form-control login-input" maxlength="120" required
                             value="<?= htmlspecialchars($nomeUsuario) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">E-mail</label>
                      <input type="email" name="email" class="form-control login-input" maxlength="160" required
                             value="<?= htmlspecialchars($emailUsuario) ?>">
                    </div>
                  </div>

                  <div class="mt-3">
                    <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                      <i class="fa-solid fa-floppy-disk me-1"></i>Salvar dados
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Senha -->
            <div class="section-card">
              <div class="section-head section-head--stack">
                <h5><span class="ico me-2"><i class="fa-solid fa-key"></i></span>Alterar senha</h5>
              </div>
              <div class="p-3 p-md-4">
                <form action="../controller/PerfilController.php" method="post">
                  <input type="hidden" name="acao" value="senha">

                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label">Senha atual</label>
                      <input type="password" name="senha_atual" class="form-control login-input" autocomplete="current-password" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Nova senha</label>
                      <input type="password" name="nova_senha" class="form-control login-input" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Confirmar nova senha</label>
                      <input type="password" name="confirmar_senha" class="form-control login-input" autocomplete="new-password" minlength="8" required>
                    </div>
                  </div>

                  <div class="mt-3">
                    <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                      <i class="fa-solid fa-floppy-disk me-1"></i>Alterar senha
                    </button>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </div>

<?php include __DIR__ . '/partials/app_foot.php'; ?>