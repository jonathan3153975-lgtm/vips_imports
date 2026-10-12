<?php
// =====================================================================
// VIP Imports — Model: Compras
// Cada lançamento é UM item comprado (produto). A leitura de uma nota
// fiscal por IA cria vários lançamentos de uma só vez ("bloco").
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Compra
{
    public const CATEGORIAS = ['calca', 'casaco', 'jaqueta', 'tenis', 'outros'];
    public const MOEDAS     = ['BRL', 'USD'];

    private PDO $conn;

    public function __construct()
    {
        $conexao    = new Connection();
        $this->conn = $conexao->PDOconn();
    }

    /**
     * Confirma que a viagem pertence ao usuário (segurança / posse).
     */
    public function viagemPertence(int $viagemId, int $usuarioId): bool
    {
        $sql  = 'SELECT 1 FROM viagens WHERE id = :v AND usuario_id = :u LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Lista os itens comprados de uma viagem (respeitando a posse).
     */
    public function listarPorViagem(int $viagemId, int $usuarioId): array
    {
        $sql = 'SELECT c.id, c.viagem_id, c.categoria, c.produto, c.marca, c.quantidade,
                       c.valor, c.moeda, c.cotacao, c.valor_brl, c.loja, c.data_compra, c.observacoes, c.publicada,
                       p.vendido, p.preco AS preco_venda
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                LEFT JOIN loja_produtos p ON p.compra_id = c.id
                WHERE c.viagem_id = :v AND v.usuario_id = :u
                ORDER BY c.data_compra DESC, c.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as &$item) {
            $item['custo_unitario'] = $item['quantidade'] > 0
                ? round((float) $item['valor'] / (int) $item['quantidade'], 2)
                : (float) $item['valor'];
            $item['publicada']   = (bool) $item['publicada'];
            $item['vendido']     = (bool) $item['vendido'];
            $item['preco_venda'] = $item['preco_venda'] !== null ? (float) $item['preco_venda'] : null;
        }
        unset($item);

        // Fotos de todos os itens da viagem (evita N+1).
        $fotos = $this->fotosDaViagem($viagemId, $usuarioId);
        foreach ($itens as &$item) {
            $item['fotos'] = $fotos[$item['id']] ?? [];
        }
        unset($item);

        return $itens;
    }

    /**
     * Fotos agrupadas por item (id da compra => array de linhas).
     */
    public function fotosDaViagem(int $viagemId, int $usuarioId): array
    {
        $sql = 'SELECT f.id, f.compra_id, f.arquivo
                FROM compra_fotos f
                INNER JOIN compras c ON c.id = f.compra_id
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.viagem_id = :v AND v.usuario_id = :u
                ORDER BY f.id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $agrupadas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $foto) {
            $agrupadas[(int) $foto['compra_id']][] = $foto;
        }

        return $agrupadas;
    }

    public function listarFotosCompra(int $compraId, int $usuarioId): array
    {
        $sql = 'SELECT f.id, f.arquivo
                FROM compra_fotos f
                INNER JOIN compras c ON c.id = f.compra_id
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE f.compra_id = :c AND v.usuario_id = :u
                ORDER BY f.id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':c', $compraId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra vários itens de uma vez (bloco da nota fiscal).
     *
     * @param array{categoria:string,produto:string,marca:?string,quantidade:int,valor:float}[] $itens
     */
    public function criar(
        int $viagemId,
        int $usuarioId,
        array $itens,
        string $moeda,
        float $cotacao,
        string $dataCompra,
        ?string $loja,
        ?string $observacoes
    ): int {
        if (!in_array($moeda, self::MOEDAS, true)) {
            $moeda = 'BRL';
        }
        if ($moeda === 'BRL' || $cotacao <= 0) {
            $cotacao = 1.0;
        }

        $sql = 'INSERT INTO compras
                    (viagem_id, categoria, produto, marca, quantidade, valor, moeda, cotacao, valor_brl, loja, data_compra, observacoes)
                VALUES
                    (:v, :c, :p, :m, :q, :vl, :md, :ct, :vb, :lj, :dt, :ob)';
        $stmt = $this->conn->prepare($sql);

        $this->conn->beginTransaction();

        try {
            foreach ($itens as $item) {
                $valor    = (float) $item['valor'];
                $valorBrl = round($valor * $cotacao, 2);

                $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
                $stmt->bindValue(':c', $item['categoria'], PDO::PARAM_STR);
                $stmt->bindValue(':p', $item['produto'], PDO::PARAM_STR);
                $stmt->bindValue(':m', $item['marca'], $item['marca'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':q', $item['quantidade'], PDO::PARAM_INT);
                $stmt->bindValue(':vl', $valor);
                $stmt->bindValue(':md', $moeda, PDO::PARAM_STR);
                $stmt->bindValue(':ct', $cotacao);
                $stmt->bindValue(':vb', $valorBrl);
                $stmt->bindValue(':lj', $loja, $loja === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':dt', $dataCompra, PDO::PARAM_STR);
                $stmt->bindValue(':ob', $observacoes, $observacoes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->execute();
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return 0; // bloco: sem id único a retornar
    }

    public function buscarPorId(int $id, int $usuarioId): ?array
    {
        $sql = 'SELECT c.id, c.viagem_id, c.categoria, c.produto, c.marca, c.quantidade,
                       c.valor, c.moeda, c.cotacao, c.valor_brl, c.loja, c.data_compra, c.observacoes
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.id = :id AND v.usuario_id = :u
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item === false ? null : $item;
    }

    public function atualizar(
        int $id,
        int $usuarioId,
        string $categoria,
        string $produto,
        ?string $marca,
        int $quantidade,
        float $valor,
        string $moeda,
        float $cotacao,
        string $dataCompra,
        ?string $loja,
        ?string $observacoes
    ): bool {
        if (!in_array($moeda, self::MOEDAS, true)) {
            $moeda = 'BRL';
        }
        if ($moeda === 'BRL' || $cotacao <= 0) {
            $cotacao = 1.0;
        }

        $valorBrl = round($valor * $cotacao, 2);

        $sql = 'UPDATE compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                SET c.categoria = :cat, c.produto = :prod, c.marca = :marca, c.quantidade = :q,
                    c.valor = :vl, c.moeda = :md, c.cotacao = :ct, c.valor_brl = :vb,
                    c.loja = :lj, c.data_compra = :dt, c.observacoes = :ob
                WHERE c.id = :id AND v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':cat', $categoria, PDO::PARAM_STR);
        $stmt->bindValue(':prod', $produto, PDO::PARAM_STR);
        $stmt->bindValue(':marca', $marca, $marca === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':q', $quantidade, PDO::PARAM_INT);
        $stmt->bindValue(':vl', $valor);
        $stmt->bindValue(':md', $moeda, PDO::PARAM_STR);
        $stmt->bindValue(':ct', $cotacao);
        $stmt->bindValue(':vb', $valorBrl);
        $stmt->bindValue(':lj', $loja, $loja === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':dt', $dataCompra, PDO::PARAM_STR);
        $stmt->bindValue(':ob', $observacoes, $observacoes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Exclui o item e seus arquivos de foto.
     * Retorna ['deleted' => bool, 'arquivos' => string[] com os arquivos removidos].
     */
    public function excluir(int $id, int $usuarioId): array
    {
        $fotos = $this->listarFotosCompra($id, $usuarioId);
        $arquivos = array_column($fotos, 'arquivo');

        $sql = 'DELETE c
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.id = :id AND v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return ['deleted' => $stmt->rowCount() > 0, 'arquivos' => $arquivos];
    }

    public function adicionarFoto(int $compraId, int $usuarioId, string $arquivo): bool
    {
        $sql = 'INSERT INTO compra_fotos (compra_id, arquivo)
                SELECT c.id, :arq
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.id = :c AND v.usuario_id = :u
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':arq', $arquivo, PDO::PARAM_STR);
        $stmt->bindValue(':c', $compraId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Exclui uma foto e retorna o nome do arquivo para remoção física.
     */
    public function excluirFoto(int $fotoId, int $usuarioId): ?string
    {
        $sql = 'SELECT f.arquivo
                FROM compra_fotos f
                INNER JOIN compras c ON c.id = f.compra_id
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE f.id = :id AND v.usuario_id = :u
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $fotoId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $arquivo = $stmt->fetchColumn();
        if ($arquivo === false) {
            return null;
        }

        $del = $this->conn->prepare('DELETE FROM compra_fotos WHERE id = :id');
        $del->bindValue(':id', $fotoId, PDO::PARAM_INT);
        $del->execute();

        return (string) $arquivo;
    }

    /**
     * Somas por categoria (todas sempre presentes, valor 0 se vazias).
     */
    public function totaisPorCategoria(int $viagemId, int $usuarioId): array
    {
        $sql = 'SELECT c.categoria, COALESCE(SUM(c.valor_brl * c.quantidade), 0) AS total
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.viagem_id = :v AND v.usuario_id = :u
                GROUP BY c.categoria';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $totais = array_fill_keys(self::CATEGORIAS, 0.0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $totais[$linha['categoria']] = (float) $linha['total'];
        }

        return $totais;
    }

    public function totalPorViagem(int $viagemId, int $usuarioId): float
    {
        $sql = 'SELECT COALESCE(SUM(c.valor_brl * c.quantidade), 0)
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.viagem_id = :v AND v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
    }

    /**
     * Total investido em compras por todas as viagens do usuário (painel).
     */
    public function totalComprado(int $usuarioId): float
    {
        $sql = 'SELECT COALESCE(SUM(c.valor_brl * c.quantidade), 0)
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
    }

    public function contar(int $usuarioId): int
    {
        $sql = 'SELECT COUNT(*)
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function contarMarcas(int $viagemId, int $usuarioId): int
    {
        $sql = 'SELECT COUNT(DISTINCT c.marca)
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.viagem_id = :v AND v.usuario_id = :u AND c.marca IS NOT NULL AND c.marca <> ""';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Marcas já usadas pelo usuário (para sugestão em novos lançamentos).
     */
    public function listarMarcas(int $usuarioId): array
    {
        $sql = 'SELECT DISTINCT c.marca
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u AND c.marca IS NOT NULL AND c.marca <> ""
                ORDER BY c.marca';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('strval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'marca'));
    }

    /**
     * Categorias já usadas pelo usuário (para sugestão em novos lançamentos).
     */
    public function listarCategorias(int $usuarioId): array
    {
        $sql = 'SELECT DISTINCT c.categoria
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u AND c.categoria IS NOT NULL AND c.categoria <> ""
                ORDER BY c.categoria';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('strval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'categoria'));
    }
}