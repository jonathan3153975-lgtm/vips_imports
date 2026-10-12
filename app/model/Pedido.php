<?php
// =====================================================================
// VIP Imports — Model: Pedido
// Pedidos recebidos pela loja virtual (link público) e pelo Telegram.
// Cada pedido tem um cliente (nome + contato) e um ou mais itens.
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Pedido
{
    public const STATUS = ['novo', 'confirmado', 'concluido', 'cancelado'];

    public const ROTULOS = [
        'novo'       => 'Novo',
        'confirmado' => 'Confirmado',
        'concluido'  => 'Concluído',
        'cancelado'  => 'Cancelado',
    ];

    private PDO $conn;

    public function __construct()
    {
        $this->conn = (new Connection())->PDOconn();
    }

    /**
     * Cria um pedido a partir de itens {produto_id, quantidade}.
     * Os itens precisam pertencer ao usuário e estar à venda.
     * Devolve o id do pedido ou null se inválido.
     */
    public function criar(
        int $usuarioId,
        string $nome,
        string $contato,
        ?string $observacao,
        array $itens,
        string $origem = 'loja'
    ): ?int {
        $nome     = trim($nome);
        $contato  = trim($contato);
        $origem   = in_array($origem, ['loja', 'telegram'], true) ? $origem : 'loja';

        if ($usuarioId <= 0 || $nome === '' || $contato === '' || count($itens) === 0) {
            return null;
        }

        $normalizados = [];
        foreach ($itens as $item) {
            $produtoId = (int) ($item['produto_id'] ?? 0);
            $qtd       = max(1, (int) ($item['quantidade'] ?? 1));
            if ($produtoId <= 0) {
                continue;
            }
            if (!isset($normalizados[$produtoId])) {
                $normalizados[$produtoId] = 0;
            }
            $normalizados[$produtoId] += $qtd;
        }

        if (count($normalizados) === 0) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($normalizados), '?'));
        $sql = "SELECT id, produto, preco
                FROM loja_produtos
                WHERE usuario_id = ? AND vendido = 0 AND reservado = 0 AND id IN ($placeholders)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_merge([$usuarioId], array_keys($normalizados)));

        $produtos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $produtos[(int) $p['id']] = $p;
        }

        if (count($produtos) === 0) {
            return null;
        }

        $total = 0.0;
        foreach ($produtos as $p) {
            $total += (float) $p['preco'] * $normalizados[(int) $p['id']];
        }

        $this->conn->beginTransaction();
        try {
            $ins = $this->conn->prepare(
                'INSERT INTO pedidos
                    (usuario_id, cliente_nome, cliente_contato, observacao, total, status, origem)
                 VALUES (:u, :n, :c, :o, :t, :s, :g)'
            );
            $ins->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $ins->bindValue(':n', mb_substr($nome, 0, 120), PDO::PARAM_STR);
            $ins->bindValue(':c', mb_substr($contato, 0, 160), PDO::PARAM_STR);
            $obs = $observacao === null ? null : mb_substr(trim($observacao), 0, 500);
            $ins->bindValue(':o', $obs === '' ? null : $obs, $obs ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $ins->bindValue(':t', round($total, 2));
            $ins->bindValue(':s', 'novo', PDO::PARAM_STR);
            $ins->bindValue(':g', $origem, PDO::PARAM_STR);
            $ins->execute();

            $pedidoId = (int) $this->conn->lastInsertId();

            $itemStmt = $this->conn->prepare(
                'INSERT INTO pedido_itens (pedido_id, produto_id, produto, preco, quantidade)
                 VALUES (:p, :pid, :nome, :preco, :qtd)'
            );
            foreach ($produtos as $p) {
                $itemStmt->bindValue(':p', $pedidoId, PDO::PARAM_INT);
                $itemStmt->bindValue(':pid', (int) $p['id'], PDO::PARAM_INT);
                $itemStmt->bindValue(':nome', $p['produto'], PDO::PARAM_STR);
                $itemStmt->bindValue(':preco', (float) $p['preco']);
                $itemStmt->bindValue(':qtd', $normalizados[(int) $p['id']], PDO::PARAM_INT);
                $itemStmt->execute();
            }

            // Reserva os itens no catálogo: saem da loja até o vendedor
            // confirmar a venda (-> vendido) ou cancelar (-> liberado).
            $idsReservar = array_map('intval', array_keys($produtos));
            if (count($idsReservar) > 0) {
                $phRes = implode(',', array_fill(0, count($idsReservar), '?'));
                $reserva = $this->conn->prepare(
                    "UPDATE loja_produtos
                        SET reservado = 1, reservado_em = NOW(), reservado_pedido_id = ?
                      WHERE usuario_id = ? AND vendido = 0 AND id IN ($phRes)"
                );
                $reserva->execute(array_merge([$pedidoId, $usuarioId], $idsReservar));
            }

            $this->conn->commit();
            return $pedidoId;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Pedidos do usuário (com os itens), opcionalmente por status.
     */
    public function listarPorUsuario(int $usuarioId, ?string $status = null): array
    {
        $sql = 'SELECT id, cliente_nome, cliente_contato, observacao, total, status, origem,
                       criado_em, atualizado_em
                FROM pedidos
                WHERE usuario_id = :u';
        $params = [':u' => $usuarioId];

        if ($status !== null && in_array($status, self::STATUS, true)) {
            $sql .= ' AND status = :s';
            $params[':s'] = $status;
        }

        $sql .= ' ORDER BY criado_em DESC, id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($pedidos) === 0) {
            return [];
        }

        $ids = array_map('intval', array_column($pedidos, 'id'));
        $itens = $this->itensDosPedidos($ids);

        foreach ($pedidos as &$pedido) {
            $pedido['total'] = (float) $pedido['total'];
            $pedido['itens'] = $itens[(int) $pedido['id']] ?? [];
        }
        unset($pedido);

        return $pedidos;
    }

    /**
     * Itens agrupados por pedido.
     */
    private function itensDosPedidos(array $pedidoIds): array
    {
        if (count($pedidoIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
        $stmt = $this->conn->prepare(
            "SELECT pedido_id, produto_id, produto, preco, quantidade
               FROM pedido_itens
              WHERE pedido_id IN ($placeholders)
              ORDER BY id"
        );
        $stmt->execute($pedidoIds);

        $agrupados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $item['preco']      = (float) $item['preco'];
            $item['quantidade'] = (int) $item['quantidade'];
            $agrupados[(int) $item['pedido_id']][] = $item;
        }

        return $agrupados;
    }

    public function atualizarStatus(int $pedidoId, int $usuarioId, string $status): bool
    {
        if (!in_array($status, self::STATUS, true)) {
            return false;
        }

        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare(
                'UPDATE pedidos SET status = :s WHERE id = :id AND usuario_id = :u'
            );
            $stmt->bindValue(':s', $status, PDO::PARAM_STR);
            $stmt->bindValue(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
            $stmt->execute();

            $ok = $stmt->rowCount() > 0;

            if ($ok && $status === 'concluido') {
                // Venda confirmada: os itens reservados por este pedido viram vendidos.
                $this->conn->prepare(
                    'UPDATE loja_produtos
                        SET vendido = 1, vendido_em = NOW(),
                            reservado = 0, reservado_em = NULL, reservado_pedido_id = NULL
                      WHERE usuario_id = :u AND reservado_pedido_id = :p AND vendido = 0'
                )->execute([':u' => $usuarioId, ':p' => $pedidoId]);
            } elseif ($ok && $status === 'cancelado') {
                // Pedido cancelado: os itens voltam a ficar disponíveis na loja.
                $this->conn->prepare(
                    'UPDATE loja_produtos
                        SET reservado = 0, reservado_em = NULL, reservado_pedido_id = NULL
                      WHERE usuario_id = :u AND reservado_pedido_id = :p AND vendido = 0'
                )->execute([':u' => $usuarioId, ':p' => $pedidoId]);
            }

            $this->conn->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function contarNovos(int $usuarioId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM pedidos WHERE usuario_id = :u AND status = 'novo'"
        );
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
