<?php
// =====================================================================
// VIP Imports — Model: Loja
// Gerencia a publicação de itens comprados na "loja" (vitrine). Ao
// publicar, o item NÃO é excluído de compras — apenas marcado como
// publicada, deixando de aparecer nas listagens de compra.
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';
require_once dirname(__DIR__) . '/model/Compra.php';
require_once dirname(__DIR__) . '/model/Gasto.php';

class Loja
{
    private PDO $conn;

    public function __construct()
    {
        $conexao    = new Connection();
        $this->conn = $conexao->PDOconn();
    }

    /**
     * Itens comprados ainda não publicados, de todas as viagens do usuário.
     * Inclui as fotos da galeria do item (para pré-visualização).
     */
    public function disponiveisParaPublicar(int $usuarioId): array
    {
        $sql = 'SELECT c.id, c.viagem_id, c.categoria, c.produto, c.marca, c.quantidade,
                       c.valor, c.moeda, c.cotacao, c.valor_brl, c.data_compra, c.loja,
                       v.destino AS viagem_destino
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u AND c.publicada = 0
                ORDER BY c.data_compra DESC, c.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($itens) === 0) {
            return [];
        }

        $ids = array_map('intval', array_column($itens, 'id'));
        $fotos = $this->fotosDosItens($ids, $usuarioId);

        foreach ($itens as &$item) {
            $item['fotos']   = $fotos[(int) $item['id']] ?? [];
            $item['valor']   = (float) $item['valor'];
            $item['valor_brl'] = (float) $item['valor_brl'];
        }
        unset($item);

        return $itens;
    }

    /**
     * Preço sugerido de venda dos itens (para cobrir TODOS os custos da
     * viagem — gastos + compras — proporcionalmente ao custo de cada item,
     * com 20% de lucro).
     *
     * custo da viagem = gastos totais + custo de TODAS as compras da viagem
     * parte do item   = custo_sc do item / custo total das compras × custo da viagem
     * sugerido        = parte do item × 1,2
     *
     * @return array<int, array{parte:float, sugerido:float}> por id de compra
     */
    public function precosSugeridos(array $compraIds, int $usuarioId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $compraIds))));
        if (count($ids) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT c.id, c.viagem_id, c.quantidade, c.valor_brl
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = ? AND c.id IN ($placeholders)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_merge([$usuarioId], $ids));

        $itens  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($itens) === 0) {
            return [];
        }

        $porViagem = [];
        foreach ($itens as $item) {
            $porViagem[(int) $item['viagem_id']][] = $item;
        }

        $compraModel = new Compra();
        $gastoModel  = new Gasto();

        $resultado = [];
        foreach ($porViagem as $viagemId => $itensDaViagem) {
            $comprasTotal = (float) $compraModel->totalPorViagem($viagemId, $usuarioId);
            if ($comprasTotal <= 0) {
                continue;
            }

            $custoViagem = $comprasTotal + (float) $gastoModel->totalPorViagem($viagemId, $usuarioId);

            foreach ($itensDaViagem as $item) {
                $custoItem  = (float) $item['valor_brl'] * max(1, (int) $item['quantidade']);
                $parteBase  = $custoItem / $comprasTotal * $custoViagem;
                $resultado[(int) $item['id']] = [
                    'parte'    => round($parteBase, 2),
                    'sugerido' => round($parteBase * 1.2, 2),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Fotos de um único item, validando a posse (usado pela IA).
     */
    public function fotosDoItem(int $compraId, int $usuarioId): array
    {
        return $this->fotosDosItens([$compraId], $usuarioId)[$compraId] ?? [];
    }

    /**
     * Fotos (id + arquivo) agrupadas por compra, validando a posse.
     */
    private function fotosDosItens(array $compraIds, int $usuarioId): array
    {
        if (count($compraIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($compraIds), '?'));
        $sql = "SELECT f.id, f.compra_id, f.arquivo
                FROM compra_fotos f
                INNER JOIN compras c ON c.id = f.compra_id
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = ? AND f.compra_id IN ($placeholders)
                ORDER BY f.id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_merge([$usuarioId], $compraIds));

        $agrupadas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $foto) {
            $agrupadas[(int) $foto['compra_id']][] = [
                'id'      => (int) $foto['id'],
                'arquivo' => $foto['arquivo'],
            ];
        }

        return $agrupadas;
    }

    /**
     * Produtos publicados na loja (com foto de capa quando houver).
     */
    public function publicados(int $usuarioId): array
    {
        $sql = 'SELECT p.id, p.compra_id, p.produto, p.marca, p.categoria, p.descricao,
                       p.preco, p.preco_sugerido, p.publicado_em,
                       p.vendido, p.vendido_em,
                       c.quantidade, c.valor, c.moeda, c.valor_brl,
                       v.destino AS viagem_destino
                FROM loja_produtos p
                INNER JOIN compras c ON c.id = p.compra_id
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE p.usuario_id = :u
                ORDER BY p.publicado_em DESC, p.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($itens) === 0) {
            return [];
        }

        $ids  = array_map('intval', array_column($itens, 'compra_id'));
        $fotos = $this->fotosDosItens($ids, $usuarioId);

        foreach ($itens as &$item) {
            $item['fotos'] = $fotos[(int) $item['compra_id']] ?? [];
            $item['preco'] = (float) $item['preco'];
            $item['preco_sugerido'] = $item['preco_sugerido'] !== null
                ? (float) $item['preco_sugerido'] : null;
            $item['vendido']    = (bool) $item['vendido'];
            $item['custo_item'] = round((float) $item['valor_brl'] * max(1, (int) $item['quantidade']), 2);
        }
        unset($item);

        return $itens;
    }

    /**
     * Marca/desmarca um produto publicado como vendido (só para o dono).
     */
    public function marcarVendido(int $produtoId, int $usuarioId, bool $vendido): bool
    {
        $sql = 'UPDATE loja_produtos
                SET vendido = :v, vendido_em = IF(:vv, NOW(), NULL)
                WHERE id = :id AND usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $vendido ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':vv', $vendido ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $produtoId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Publica um item: cria o produto na loja e marca a compra como publicada.
     */
    public function publicar(
        int $usuarioId,
        int $compraId,
        string $produto,
        ?string $marca,
        string $categoria,
        ?string $descricao,
        float $preco,
        ?float $precoSugerido = null
    ): bool {
        $compra = $this->compraPublicavel($compraId, $usuarioId);
        if ($compra === null) {
            return false;
        }

        $this->conn->beginTransaction();

        try {
            $sql = 'INSERT INTO loja_produtos
                        (usuario_id, compra_id, produto, marca, categoria, descricao, preco, preco_sugerido)
                    VALUES
                        (:u, :cp, :p, :m, :c, :d, :pr, :ps)';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':cp', $compraId, PDO::PARAM_INT);
            $stmt->bindValue(':p', mb_substr($produto, 0, 160), PDO::PARAM_STR);
            $stmt->bindValue(':m', $marca, $marca === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':c', mb_substr($categoria, 0, 40), PDO::PARAM_STR);
            $stmt->bindValue(':d', $descricao, $descricao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':pr', $preco);
            $stmt->bindValue(':ps', $precoSugerido, $precoSugerido === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            $upd = $this->conn->prepare(
                'UPDATE compras c
                 INNER JOIN viagens v ON v.id = c.viagem_id
                 SET c.publicada = 1
                 WHERE c.id = :id AND v.usuario_id = :u'
            );
            $upd->bindValue(':id', $compraId, PDO::PARAM_INT);
            $upd->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $upd->execute();

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Remove um produto da loja e devolve o item para a listagem de compras.
     */
    public function remover(int $produtoId, int $usuarioId): bool
    {
        $sql = 'SELECT p.compra_id
                FROM loja_produtos p
                WHERE p.id = :id AND p.usuario_id = :u
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $produtoId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $compraId = $stmt->fetchColumn();
        if ($compraId === false) {
            return false;
        }

        $this->conn->beginTransaction();

        try {
            $this->conn->prepare('DELETE FROM loja_produtos WHERE id = :id')
                ->execute([':id' => $produtoId]);

            $upd = $this->conn->prepare(
                'UPDATE compras c
                 INNER JOIN viagens v ON v.id = c.viagem_id
                 SET c.publicada = 0
                 WHERE c.id = :id AND v.usuario_id = :u'
            );
            $upd->bindValue(':id', (int) $compraId, PDO::PARAM_INT);
            $upd->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $upd->execute();

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Confirma que a compra pertence ao usuário e ainda não foi publicada.
     */
    private function compraPublicavel(int $compraId, int $usuarioId): ?array
    {
        $sql = 'SELECT c.id, c.produto, c.marca, c.categoria
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE c.id = :id AND v.usuario_id = :u AND c.publicada = 0
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $compraId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $compra = $stmt->fetch(PDO::FETCH_ASSOC);
        return $compra === false ? null : $compra;
    }

    /**
     * Total de produtos publicados (badge do menu).
     */
    public function contarPublicados(int $usuarioId): int
    {
        $sql = 'SELECT COUNT(*)
                FROM loja_produtos
                WHERE usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}