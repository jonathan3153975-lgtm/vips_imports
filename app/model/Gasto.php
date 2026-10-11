<?php
// =====================================================================
// VIP Imports — Model: Gastos
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Gasto
{
    public const CATEGORIAS = ['passagem', 'estadia', 'alimentacao', 'outros'];
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
     * Lista os gastos de uma viagem (respeitando a posse da viagem).
     */
    public function listarPorViagem(int $viagemId, int $usuarioId): array
    {
        $sql = 'SELECT g.id, g.categoria, g.descricao, g.valor, g.moeda, g.cotacao, g.valor_brl, g.data_gasto
                FROM gastos g
                INNER JOIN viagens v ON v.id = g.viagem_id
                WHERE g.viagem_id = :v AND v.usuario_id = :u
                ORDER BY g.data_gasto DESC, g.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrar(
        int $viagemId,
        string $categoria,
        ?string $descricao,
        float $valor,
        string $dataGasto,
        string $moeda = 'BRL',
        float $cotacao = 1.0
    ): int {
        if (!in_array($moeda, self::MOEDAS, true)) {
            $moeda = 'BRL';
        }
        if ($moeda === 'BRL' || $cotacao <= 0) {
            $cotacao = 1.0;
        }

        $valorBrl = round($valor * $cotacao, 2);

        $sql = 'INSERT INTO gastos (viagem_id, categoria, descricao, valor, moeda, cotacao, valor_brl, data_gasto)
                VALUES (:v, :c, :d, :vl, :m, :ct, :vb, :dt)';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':c', $categoria, PDO::PARAM_STR);
        $stmt->bindValue(':d', $descricao, $descricao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':vl', $valor);
        $stmt->bindValue(':m', $moeda, PDO::PARAM_STR);
        $stmt->bindValue(':ct', $cotacao);
        $stmt->bindValue(':vb', $valorBrl);
        $stmt->bindValue(':dt', $dataGasto, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function excluir(int $id, int $usuarioId): bool
    {
        $sql = 'DELETE g
                FROM gastos g
                INNER JOIN viagens v ON v.id = g.viagem_id
                WHERE g.id = :id AND v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Soma dos gastos por categoria. Sempre retorna todas as categorias,
     * inclusive as sem lançamento (valor 0).
     */
    public function totaisPorCategoria(int $viagemId, int $usuarioId): array
    {
        $sql = 'SELECT g.categoria, COALESCE(SUM(g.valor_brl), 0) AS total
                FROM gastos g
                INNER JOIN viagens v ON v.id = g.viagem_id
                WHERE g.viagem_id = :v AND v.usuario_id = :u
                GROUP BY g.categoria';

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
        $sql = 'SELECT COALESCE(SUM(g.valor_brl), 0)
                FROM gastos g
                INNER JOIN viagens v ON v.id = g.viagem_id
                WHERE g.viagem_id = :v AND v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':v', $viagemId, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
    }
}
