<?php
// =====================================================================
// VIP Imports — Model: Viagens
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Viagem
{
    private PDO $conn;

    public function __construct()
    {
        $conexao    = new Connection();
        $this->conn = $conexao->PDOconn();
    }

    /**
     * Lista as viagens do usuário com o total de gastos de cada uma.
     */
    public function listarTodas(int $usuarioId): array
    {
        $sql = 'SELECT v.id, v.destino, v.data_inicial, v.data_final, v.status,
                       COALESCE(SUM(g.valor_brl), 0) AS total_gasto
                FROM viagens v
                LEFT JOIN gastos g ON g.viagem_id = v.id
                WHERE v.usuario_id = :usuario
                GROUP BY v.id
                ORDER BY v.status = "aberta" DESC, v.data_inicial DESC, v.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * As últimas viagens registradas (para o painel).
     */
    public function listarRecentes(int $usuarioId, int $limite = 5): array
    {
        $sql = 'SELECT v.id, v.destino, v.data_inicial, v.data_final, v.status,
                       COALESCE(SUM(g.valor_brl), 0) AS total_gasto
                FROM viagens v
                LEFT JOIN gastos g ON g.viagem_id = v.id
                WHERE v.usuario_id = :usuario
                GROUP BY v.id
                ORDER BY v.data_inicial DESC, v.id DESC
                LIMIT ' . (int) $limite;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(int $usuarioId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM viagens WHERE usuario_id = :u');
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id, int $usuarioId): ?array
    {
        $sql = 'SELECT id, destino, data_inicial, data_final, status
                FROM viagens
                WHERE id = :id AND usuario_id = :u
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $viagem = $stmt->fetch(PDO::FETCH_ASSOC);

        return $viagem === false ? null : $viagem;
    }

    /**
     * Retorna a viagem em aberto do usuário (se houver).
     */
    public function viagemAberta(int $usuarioId): ?array
    {
        $sql = "SELECT id, destino, data_inicial, data_final, status
                FROM viagens
                WHERE usuario_id = :u AND status = 'aberta'
                ORDER BY data_inicial DESC, id DESC
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $viagem = $stmt->fetch(PDO::FETCH_ASSOC);

        return $viagem === false ? null : $viagem;
    }

    /**
     * Cria uma nova viagem em aberto. Encerra automaticamente as demais
     * viagens abertas do usuário (regra: uma única viagem em aberto).
     */
    public function criar(int $usuarioId, string $destino, string $dataInicial, ?string $dataFinal): int
    {
        $this->conn->beginTransaction();

        try {
            $fechar = $this->conn->prepare(
                "UPDATE viagens
                 SET status = 'encerrada', data_final = COALESCE(data_final, CURDATE())
                 WHERE usuario_id = :u AND status = 'aberta'"
            );
            $fechar->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $fechar->execute();

            $sql  = 'INSERT INTO viagens (usuario_id, destino, data_inicial, data_final, status)
                     VALUES (:u, :d, :di, :df, :s)';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':d', $destino, PDO::PARAM_STR);
            $stmt->bindValue(':di', $dataInicial, PDO::PARAM_STR);
            $stmt->bindValue(':df', $dataFinal, $dataFinal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':s', 'aberta', PDO::PARAM_STR);
            $stmt->execute();

            $id = (int) $this->conn->lastInsertId();
            $this->conn->commit();

            return $id;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function atualizar(int $id, int $usuarioId, string $destino, string $dataInicial, ?string $dataFinal): bool
    {
        $sql = 'UPDATE viagens
                SET destino = :d, data_inicial = :di, data_final = :df
                WHERE id = :id AND usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':d', $destino, PDO::PARAM_STR);
        $stmt->bindValue(':di', $dataInicial, PDO::PARAM_STR);
        $stmt->bindValue(':df', $dataFinal, $dataFinal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Encerra a viagem. Se nenhuma data final for informada, usa a já
     * cadastrada ou a data atual.
     */
    public function finalizar(int $id, int $usuarioId, ?string $dataFinal): bool
    {
        $sql = "UPDATE viagens
                SET status = 'encerrada', data_final = COALESCE(:df, data_final, CURDATE())
                WHERE id = :id AND usuario_id = :u AND status = 'aberta'";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':df', $dataFinal, $dataFinal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Total de gastos de todas as viagens do usuário (painel), em BRL.
     */
    public function totalGastos(int $usuarioId): float
    {
        $sql = 'SELECT COALESCE(SUM(g.valor_brl), 0)
                FROM gastos g
                INNER JOIN viagens v ON v.id = g.viagem_id
                WHERE v.usuario_id = :u';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
    }
}
