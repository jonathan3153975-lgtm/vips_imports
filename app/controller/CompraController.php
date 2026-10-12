<?php
// =====================================================================
// VIP Imports â€” Controller: Compras
// AÃ§Ãµes: criar (bloco), atualizar, excluir, upload_foto, excluir_foto
// =====================================================================

require_once dirname(__DIR__) . '/model/Compra.php';
require_once dirname(__DIR__) . '/model/Cotacao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../view/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario']['id'];
$acao      = $_POST['acao'] ?? $_GET['acao'] ?? '';
$viagemId  = (int) ($_POST['viagem_id'] ?? $_GET['viagem_id'] ?? 0);
$origem    = ($_POST['origem'] ?? $_GET['origem'] ?? 'compras') === 'viagens' ? 'viagens' : 'compras';
$aba       = (string) ($_POST['aba'] ?? $_GET['aba'] ?? '');

const DIR_FOTOS = __DIR__ . '/../../public/uploads/compras';

// Redireciona de volta para a tela de origem, preservando a viagem.
function redir(string $origem, int $viagemId, string $query): void
{
    global $aba;
    if ($aba !== '') {
        $query = $query === '' ? 'aba=' . urlencode($aba) : $query . '&aba=' . urlencode($aba);
    }

    $sufixo = $query === '' ? '' : '?' . $query;

    if ($origem === 'viagens') {
        header('Location: ../view/viagens.php' . $sufixo);
    } else {
        header('Location: ../view/compras.php?viagem=' . $viagemId . ($query === '' ? '' : '&' . $query));
    }
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

function dataValida(?string $data): ?string
{
    $data = trim((string) $data);
    if ($data === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $data);
    if ($dt === false || $dt->format('Y-m-d') !== $data) {
        return null;
    }

    return $data;
}

/**
 * Normaliza moeda e obtÃ©m a cotaÃ§Ã£o. Usa cotaÃ§Ã£o informada ou busca
 * automaticamente (BCB/PTAX) para dÃ³lar. Redireciona com erro se nÃ£o
 * houver conversÃ£o possÃ­vel.
 */
function moedaCotacao(int $viagemId, string $moedaPost, ?float $cotacao): array
{
    global $origem;
    $moeda = strtoupper($moedaPost);
    $moeda = in_array($moeda, Compra::MOEDAS, true) ? $moeda : 'BRL';

    if ($moeda === 'BRL') {
        return [$moeda, 1.0];
    }

    if ($cotacao === null || $cotacao <= 0) {
        try {
            $auto    = (new Cotacao())->obterUsd();
            $cotacao = $auto !== null ? (float) $auto['cotacao'] : null;
        } catch (Throwable $e) {
            $cotacao = null;
        }
    }

    if ($cotacao === null || $cotacao <= 0) {
        redir($origem, $viagemId, 'erro=cotacao');
    }

    return ['USD', $cotacao];
}

/**
 * Salva uma imagem enviada na galeria do item. Retorna o nome do arquivo
 * ou lanÃ§a exceÃ§Ã£o com cÃ³digo de erro amigÃ¡vel.
 */
function salvarFoto(array $arquivo): string
{
    if ((int) $arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE  => 'tamanho',
            UPLOAD_ERR_FORM_SIZE => 'tamanho',
            UPLOAD_ERR_PARTIAL   => 'upload',
            UPLOAD_ERR_NO_FILE   => 'upload',
        ];
        throw new RuntimeException($erros[$arquivo['error']] ?? 'upload');
    }

    if ((int) $arquivo['size'] > 2097152) { // 2 MB (upload_max_filesize)
        throw new RuntimeException('tamanho');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($arquivo['tmp_name']);
    $exts  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($exts[$mime])) {
        throw new RuntimeException('formato');
    }

    if (!is_dir(DIR_FOTOS)) {
        mkdir(DIR_FOTOS, 0777, true);
    }

    $nome = 'f_' . bin2hex(random_bytes(8)) . '.' . $exts[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], DIR_FOTOS . '/' . $nome)) {
        throw new RuntimeException('upload');
    }

    return $nome;
}

$model = new Compra();

switch ($acao) {
    case 'criar':
        if ($viagemId <= 0 || !$model->viagemPertence($viagemId, $usuarioId)) {
            redir($origem, $viagemId, 'erro=sem_viagem');
        }

        $loja        = trim((string) ($_POST['loja'] ?? ''));
        $loja        = $loja === '' ? null : mb_substr($loja, 0, 160);
        $observacoes = trim((string) ($_POST['observacoes'] ?? ''));
        $observacoes = $observacoes === '' ? null : mb_substr($observacoes, 0, 500);
        $dataCompra  = dataValida($_POST['data_compra'] ?? null) ?? date('Y-m-d');

        [$moeda, $cotacao] = moedaCotacao($viagemId, (string) ($_POST['moeda'] ?? 'BRL'), normalizarValor($_POST['cotacao'] ?? null));

        // Itens do bloco (arrays paralelos).
        $produtos   = $_POST['produto'] ?? [];
        $marcas     = $_POST['marca'] ?? [];
        $categorias = $_POST['categoria'] ?? [];
        $qtds       = $_POST['quantidade'] ?? [];
        $valores    = $_POST['valor'] ?? [];

        if (!is_array($produtos) || count($produtos) === 0) {
            redir($origem, $viagemId, 'erro=item');
        }

        $itens = [];
        $n     = count($produtos);
        for ($i = 0; $i < $n; $i++) {
            $produto   = trim((string) ($produtos[$i] ?? ''));
            $categoria = trim((string) ($categorias[$i] ?? ''));
            $marca     = trim((string) ($marcas[$i] ?? ''));
            $qtd       = max(1, (int) ($qtds[$i] ?? 1));
            $valor     = normalizarValor($valores[$i] ?? null);

            if ($produto === '' || $categoria === '') {
                continue;
            }
            if ($valor === null || $valor <= 0) {
                continue;
            }

            $itens[] = [
                'produto'    => mb_substr($produto, 0, 160),
                'categoria'  => mb_substr($categoria, 0, 40),
                'marca'      => $marca === '' ? null : mb_substr($marca, 0, 80),
                'quantidade' => $qtd,
                'valor'      => round($valor, 2),
            ];
        }

        if (count($itens) === 0) {
            redir($origem, $viagemId, 'erro=valor');
        }

        try {
            $model->criar($viagemId, $usuarioId, $itens, $moeda, $cotacao, $dataCompra, $loja, $observacoes);
        } catch (Throwable $e) {
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, 'msg=compra');
        break;

    case 'atualizar':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            redir($origem, $viagemId, 'erro=campos');
        }

        $produto     = trim((string) ($_POST['produto'] ?? ''));
        $categoria   = trim((string) ($_POST['categoria'] ?? ''));
        $categoria   = mb_substr($categoria, 0, 40);
        $valor       = normalizarValor($_POST['valor'] ?? null);
        $marca       = trim((string) ($_POST['marca'] ?? ''));
        $marca       = $marca === '' ? null : mb_substr($marca, 0, 80);
        $qtd         = max(1, (int) ($_POST['quantidade'] ?? 1));
        $loja        = trim((string) ($_POST['loja'] ?? ''));
        $loja        = $loja === '' ? null : mb_substr($loja, 0, 160);
        $observacoes = trim((string) ($_POST['observacoes'] ?? ''));
        $observacoes = $observacoes === '' ? null : mb_substr($observacoes, 0, 500);
        $dataCompra  = dataValida($_POST['data_compra'] ?? null) ?? date('Y-m-d');

        if ($produto === '' || $categoria === '') {
            redir($origem, $viagemId, 'erro=campos');
        }
        if ($valor === null || $valor <= 0) {
            redir($origem, $viagemId, 'erro=valor');
        }

        if ($viagemId <= 0 || !$model->viagemPertence($viagemId, $usuarioId)) {
            redir($origem, $viagemId, 'erro=sem_viagem');
        }

        [$moeda, $cotacao] = moedaCotacao($viagemId, (string) ($_POST['moeda'] ?? 'BRL'), normalizarValor($_POST['cotacao'] ?? null));

        try {
            $model->atualizar($id, $usuarioId, $categoria, $produto, $marca, $qtd, $valor, $moeda, $cotacao, $dataCompra, $loja, $observacoes);
        } catch (Throwable $e) {
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, 'msg=atualizado');
        break;

    case 'excluir':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            redir($origem, $viagemId, 'erro=campos');
        }

        $resultado = ['deleted' => false, 'arquivos' => []];
        try {
            $resultado = $model->excluir($id, $usuarioId);
            foreach ($resultado['arquivos'] as $arquivo) {
                $caminho = DIR_FOTOS . '/' . $arquivo;
                if (is_file($caminho)) {
                    unlink($caminho);
                }
            }
        } catch (Throwable $e) {
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, $resultado['deleted'] ? 'msg=excluido' : 'erro=campos');
        break;

    case 'upload_foto':
        $compraId = (int) ($_POST['compra_id'] ?? 0);
        if ($compraId <= 0 || !isset($_FILES['imagem'])) {
            redir($origem, $viagemId, 'erro=campos');
        }

        try {
            $arquivo = salvarFoto($_FILES['imagem']);
        } catch (RuntimeException $e) {
            redir($origem, $viagemId, 'erro=' . $e->getMessage());
        }

        try {
            $ok = $model->adicionarFoto($compraId, $usuarioId, $arquivo);
        } catch (Throwable $e) {
            @unlink(DIR_FOTOS . '/' . $arquivo);
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, $ok ? 'msg=foto' : 'erro=campos');
        break;

    case 'excluir_foto':
        $fotoId = (int) ($_POST['id'] ?? 0);
        if ($fotoId <= 0) {
            redir($origem, $viagemId, 'erro=campos');
        }

        $arquivo = null;
        try {
            $arquivo = $model->excluirFoto($fotoId, $usuarioId);
            if ($arquivo !== null && is_file(DIR_FOTOS . '/' . $arquivo)) {
                unlink(DIR_FOTOS . '/' . $arquivo);
            }
        } catch (Throwable $e) {
            redir($origem, $viagemId, 'erro=sistema');
        }

        redir($origem, $viagemId, $arquivo !== null ? 'msg=foto_removida' : 'erro=campos');
        break;

    default:
        redir($origem, $viagemId, '');
}