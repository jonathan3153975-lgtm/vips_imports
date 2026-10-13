<?php
// =====================================================================
// VIP Imports — Usuários (gestão restrita ao master)
// Cadastrar usuários, redefinir senha e bloquear/desbloquear acesso.
// =====================================================================
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['usuario']['perfil'] ?? '') !== 'master') {
    header('Location: painel.php?erro=sem_permissao');
    exit;
}

require_once dirname(__DIR__) . '/model/Usuario.php';
require_once dirname(__DIR__) . '/model/Estoque.php';
require_once dirname(__DIR__) . '/model/Pedido.php';

$usuario      = $_SESSION['usuario'];
$usuarioId    = (int) $usuario['id'];
$nomeUsuario  = $usuario['nome'];
$emailUsuario = $usuario['email'];
$perfilLabel  = ['master' => 'Master', 'admin' => 'Administrador', 'usuario' => 'Usuário'][$usuario['perfil']] ?? $usuario['perfil'];
$titulo       = 'Usuários';
$paginaAtual  = 'usuarios';

$usuarioModel = new Usuario();
$estoqueModel = new Estoque();
$pedidoModel  = new Pedido();

$badgeEstoque = $estoqueModel->contarPublicados($usuarioId);
$badgePedidos = $pedidoModel->contarNovos($usuarioId);

$usuarios = $usuarioModel->listar();

$mensagens = [
    'msg'  => [
        'criado'       => ['success', 'Usuário cadastrado com sucesso.'],
        'senha'        => ['success', 'Senha redefinida com sucesso.'],
        'bloqueado'    => ['success', 'Acesso do usuário bloqueado.'],
        'desbloqueado' => ['success', 'Acesso do usuário desbloqueado.'],
    ],
    'erro' => [
        'campos'      => ['danger', 'Preencha nome, e-mail e senha corretamente.'],
        'senha_curta' => ['danger', 'A senha deve ter pelo menos 8 caracteres.'],
        'email'       => ['danger', 'Este e-mail já está cadastrado.'],
        'id'          => ['danger', 'Usuário não encontrado.'],
        'proprio'     => ['danger', 'Você não pode bloquear a própria conta.'],
        'sistema'     => ['danger', 'Não foi possível concluir a operação.'],
    ],
];

$flash = null;
foreach (['msg', 'erro'] as $chave) {
    if (isset($_GET[$chave]) && isset($mensagens[$chave][$_GET[$chave]])) {
        $flash = ['tipo' => $mensagens[$chave][$_GET[$chave]][0], 'texto' => $mensagens[$chave][$_GET[$chave]][1]];
        break;
    }
}

$perfisRotulo = ['master' => 'Master', 'admin' => 'Administrador', 'usuario' => 'Usuário'];

include __DIR__ . '/partials/app_head.php';
?>

        <!-- Cabeçalho -->
        <div class="page-head d-flex flex-wrap justify-content-between align-items-end gap-2">
          <div>
            <h1>Usuários</h1>
            <span class="muted small">Cadastre usuários, redefina senhas e bloqueie acessos</span>
          </div>
          <div class="actions">
            <button type="button" class="btn btn-brand-blue btn-round" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
              <i class="fa-solid fa-user-plus me-1"></i>Novo usuário
            </button>
          </div>
        </div>

        <?php if ($flash !== null): ?>
          <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?> d-flex align-items-center gap-2 py-2 px-3" role="alert" style="font-size:.9rem;">
            <i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($flash['texto']) ?></span>
          </div>
        <?php endif; ?>

        <div class="section-card">
          <div class="section-head">
            <h5><span class="ico me-2"><i class="fa-solid fa-users"></i></span>Usuários cadastrados</h5>
            <span class="badge text-bg-light border px-2 py-1" style="font-size:.72rem;">
              <?= count($usuarios) ?> <?= count($usuarios) === 1 ? 'usuário' : 'usuários' ?>
            </span>
          </div>

          <div class="table-responsive">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Usuário</th>
                  <th>Perfil</th>
                  <th>Status</th>
                  <th>Último acesso</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($usuarios as $u): $uid = (int) $u['id']; $ativo = (int) $u['ativo'] === 1; ?>
                  <tr>
                    <td>
                      <span class="trip-name"><?= htmlspecialchars($u['nome']) ?></span>
                      <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($u['email']) ?></div>
                    </td>
                    <td>
                      <span class="badge <?= $u['perfil'] === 'master' ? 'text-bg-dark' : ($u['perfil'] === 'admin' ? 'text-bg-primary' : 'text-bg-light border') ?>">
                        <?= htmlspecialchars($perfisRotulo[$u['perfil']] ?? $u['perfil']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($ativo): ?>
                        <span class="badge text-bg-success"><i class="fa-solid fa-circle-check me-1"></i>Ativo</span>
                      <?php else: ?>
                        <span class="badge text-bg-secondary"><i class="fa-solid fa-lock me-1"></i>Bloqueado</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:.84rem;">
                      <?= $u['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) : '—' ?>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-light border btn-round btn-redefinir"
                                data-bs-toggle="modal" data-bs-target="#modalSenha"
                                data-id="<?= $uid ?>" data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                title="Redefinir senha">
                          <i class="fa-solid fa-key"></i>
                        </button>

                        <?php if ($uid === $usuarioId): ?>
                          <button type="button" class="btn btn-sm btn-light border btn-round" disabled title="Você não pode bloquear a própria conta">
                            <i class="fa-solid fa-lock"></i>
                          </button>
                        <?php else: ?>
                          <form action="../controller/UsuarioController.php" method="post" class="d-inline"
                                onsubmit="return confirm('<?= $ativo ? 'Bloquear' : 'Desbloquear' ?> o acesso de <?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>?');">
                            <input type="hidden" name="acao" value="alternar_ativo">
                            <input type="hidden" name="id" value="<?= $uid ?>">
                            <button type="submit" class="btn btn-sm btn-round <?= $ativo ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    title="<?= $ativo ? 'Bloquear acesso' : 'Desbloquear acesso' ?>">
                              <i class="fa-solid <?= $ativo ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal: novo usuário -->
        <div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form action="../controller/UsuarioController.php" method="post" class="modal-content">
              <input type="hidden" name="acao" value="criar">
              <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Novo usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Nome</label>
                  <input type="text" name="nome" class="form-control login-input" maxlength="120" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">E-mail</label>
                  <input type="email" name="email" class="form-control login-input" maxlength="160" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Senha inicial</label>
                  <input type="password" name="senha" class="form-control login-input" minlength="8" autocomplete="new-password" required>
                  <small class="text-muted">Mínimo de 8 caracteres.</small>
                </div>
                <div class="row g-3">
                  <div class="col-6">
                    <label class="form-label">Perfil</label>
                    <select name="perfil" class="form-select login-input">
                      <option value="usuario">Usuário</option>
                      <option value="admin">Administrador</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="form-label">Acesso</label>
                    <select name="ativo" class="form-select login-input">
                      <option value="1">Ativo</option>
                      <option value="0">Bloqueado</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                  <i class="fa-solid fa-floppy-disk me-1"></i>Cadastrar
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal: redefinir senha -->
        <div class="modal fade" id="modalSenha" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form action="../controller/UsuarioController.php" method="post" class="modal-content">
              <input type="hidden" name="acao" value="redefinir_senha">
              <input type="hidden" name="id" id="senhaUserId">
              <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-key me-2"></i>Redefinir senha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <p class="text-muted small mb-3">Nova senha para <strong id="senhaUserNome"></strong>.</p>
                <label class="form-label">Nova senha</label>
                <input type="password" name="nova_senha" class="form-control login-input" minlength="8" autocomplete="new-password" required>
                <small class="text-muted">Mínimo de 8 caracteres.</small>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-light border btn-round" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-brand-blue btn-round fw-semibold">
                  <i class="fa-solid fa-key me-1"></i>Redefinir
                </button>
              </div>
            </form>
          </div>
        </div>

        <script>
        (function () {
          var modal = document.getElementById('modalSenha');
          if (!modal) return;
          modal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) return;
            document.getElementById('senhaUserId').value = btn.getAttribute('data-id');
            document.getElementById('senhaUserNome').textContent = btn.getAttribute('data-nome');
          });
        })();
        </script>

<?php include __DIR__ . '/partials/app_foot.php'; ?>