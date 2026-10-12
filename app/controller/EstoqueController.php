<?php
// =====================================================================
// VIP Imports — Controller: Estoque
// Ações: publicar, remover (redirect p/ estoque.php)
//        descrever (JSON — IA lê as fotos do item e sugere a ficha)
// =====================================================================

require_once dirname(__DIR__) . '/model/Estoque.php';
require_once dirname(__DIR__) . '/model/Compra.php';
require_once dirname(__DIR__) . '/model/LeituraReceita.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];

$abaValidas = ['publicados', 'vendidos', 'disponiveis'];
$aba = (string) ($_POST['aba'] ?? $abaValidas[2]);
if (!in_array($aba, $abaValidas, true)) {
    $aba = $abaValidas[2];
}

const DIR_FOTOS = __DIR__ . '/../../public/uploads/compras';

function redir(string $query, string $aba = 'disponiveis'): void
{
    $abaValidas = ['publicados', 'vendidos', 'disponiveis'];
    if (!in_array($aba, $abaValidas, true)) {
        $aba = $abaValidas[2];
    }
    header('Location: ../view/estoque.php' . ($query === '' ? '?' : '?' . $query . '&') . 'aba=' . $aba);
    exit;
}

function normalizarValor($entrada): ?float
{
    if (is_array($entrada)) {
        return null;
    }

    $v = trim((string) $entrada);
    if ($v === '') {
        return null;
    }

    $v = str_replace(['R$', 'US$', ' ', "\xc2\xa0"], '', $v);

    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }

    return is_numeric($v) ? (float) $v : null;
}

$model = new Estoque();

switch ($_POST['acao'] ?? '') {
    case 'publicar':
        $compraId   = (int) ($_POST['compra_id'] ?? 0);
        $produto    = trim((string) ($_POST['produto'] ?? ''));
        $marca      = trim((string) ($_POST['marca'] ?? ''));
        $categoria  = trim((string) ($_POST['categoria'] ?? ''));
        $descricao  = trim((string) ($_POST['descricao'] ?? ''));
        $preco      = normalizarValor($_POST['preco'] ?? null);
        $sugerido   = normalizarValor($_POST['preco_sugerido'] ?? null);

        if ($compraId <= 0 || $produto === '' || $categoria === '') {
            redir('erro=campos', $aba);
        }
        if ($preco === null || $preco <= 0) {
            redir('erro=preco', $aba);
        }

        try {
            $ok = $model->publicar(
                $usuarioId,
                $compraId,
                $produto,
                $marca === '' ? null : mb_substr($marca, 0, 80),
                mb_substr($categoria, 0, 40),
                $descricao === '' ? null : mb_substr($descricao, 0, 500),
                round($preco, 2),
                $sugerido !== null && $sugerido > 0 ? round($sugerido, 2) : null
            );
        } catch (Throwable $e) {
            redir('erro=sistema', $aba);
        }

        redir($ok ? 'msg=publicado' : 'erro=publicado', $aba);
        break;

    case 'remover':
        $produtoId = (int) ($_POST['id'] ?? 0);
        if ($produtoId <= 0) {
            redir('erro=campos', $aba);
        }

        try {
            $ok = $model->remover($produtoId, $usuarioId);
        } catch (Throwable $e) {
            redir('erro=sistema', $aba);
        }

        redir($ok ? 'msg=removido' : 'erro=loja', $aba);
        break;

    case 'marcar_vendido':
        $produtoId = (int) ($_POST['id'] ?? 0);
        $vendido   = ($_POST['vendido'] ?? '0') === '1';

        if ($produtoId <= 0) {
            redir('erro=campos', $aba);
        }

        try {
            $ok = $model->marcarVendido($produtoId, $usuarioId, $vendido);
        } catch (Throwable $e) {
            redir('erro=sistema', $aba);
        }

        redir($ok ? 'msg=vendido' : 'erro=loja', $aba);
        break;

    case 'editar':
        $produtoId = (int) ($_POST['id'] ?? 0);
        $produto   = trim((string) ($_POST['produto'] ?? ''));
        $marca     = trim((string) ($_POST['marca'] ?? ''));
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $preco     = normalizarValor($_POST['preco'] ?? null);

        if ($produtoId <= 0 || $produto === '' || $categoria === '') {
            redir('erro=campos', $aba);
        }
        if ($preco === null || $preco <= 0) {
            redir('erro=preco', $aba);
        }

        try {
            $ok = $model->atualizarItem(
                $produtoId,
                $usuarioId,
                $produto,
                $marca === '' ? null : mb_substr($marca, 0, 80),
                mb_substr($categoria, 0, 40),
                $descricao === '' ? null : mb_substr($descricao, 0, 500),
                round($preco, 2)
            );
        } catch (Throwable $e) {
            redir('erro=sistema', $aba);
        }

        redir($ok ? 'msg=editado' : 'erro=loja', $aba);
        break;

    case 'descrever':
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $compraId = (int) ($_POST['compra_id'] ?? 0);
        if ($compraId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'campos']);
            exit;
        }

        $compra = (new Compra())->buscarPorId($compraId, $usuarioId);
        if ($compra === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'loja']);
            exit;
        }

        $fotos = $model->fotosDoItem($compraId, $usuarioId);
        if (count($fotos) === 0) {
            echo json_encode(['ok' => false, 'erro' => 'sem_fotos']);
            exit;
        }

        $finfo  = new finfo(FILEINFO_MIME_TYPE);
        $mimes  = ['image/jpeg', 'image/png', 'image/webp'];
        $imagens = [];

        foreach ($fotos as $foto) {
            $caminho = DIR_FOTOS . '/' . $foto['arquivo'];
            if (!is_file($caminho)) {
                continue;
            }

            $mime  = (string) $finfo->file($caminho);
            $bytes = file_get_contents($caminho);
            if (!in_array($mime, $mimes, true) || $bytes === false || $bytes === '') {
                continue;
            }

            $imagens[] = ['mime' => $mime, 'base64' => base64_encode($bytes)];
        }

        if (count($imagens) === 0) {
            echo json_encode(['ok' => false, 'erro' => 'sem_fotos']);
            exit;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        try {
            $dados = (new LeituraReceita())->lerDescricao($imagens);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'api', 'mensagem' => $e->getMessage()]);
            exit;
        }

        if ($dados === null) {
            http_response_code(422);
            echo json_encode([
                'ok'       => false,
                'erro'     => 'config',
                'mensagem' => 'Chave da API Gemini não configurada. Adicione GEMINI_API_KEY no .env.',
            ]);
            exit;
        }

$precos = $model->precosSugeridos([$compraId], $usuarioId);
$precoSugerido = $precos[$compraId]['sugerido'] ?? null;

echo json_encode(['ok' => true, 'dados' => $dados, 'preco_sugerido' => $precoSugerido]);
exit;

    default:
        redir('', $aba);
}