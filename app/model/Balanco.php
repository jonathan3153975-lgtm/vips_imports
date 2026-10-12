<?php
// =====================================================================
// VIP Imports — Model: Balanço
// Relatório financeiro consolidado por viagem.
//
// Regras:
//   Receita  = itens vendidos (loja_produtos.vendido = 1), valor de venda.
//   Custo    = compras de mercadorias + gastos da viagem.
//   Resultado= Receita − Custo total.
//
// Filtros:
//   - Por período (data de cada transação: venda, compra e gasto).
//   - Por viagens selecionadas (tem prioridade sobre o período).
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Balanco
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = (new Connection())->PDOconn();
    }

    /**
     * Viagens do usuário (para o seletor de filtro).
     */
    public function listarViagens(int $usuarioId): array
    {
        $sql = 'SELECT id, destino, data_inicial, data_final, status
                FROM viagens
                WHERE usuario_id = :u
                ORDER BY data_inicial DESC, id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Monta o relatório de balanço.
     *
     * @param int      $usuarioId
     * @param ?string  $dataIni   AAAA-MM-DD (opcional)
     * @param ?string  $dataFim   AAAA-MM-DD (opcional)
     * @param int[]    $viagemIds Viagens selecionadas (opcional; tem prioridade)
     *
     * @return array{linhas:array,totais:array,filtros:array}
     */
    public function relatorio(int $usuarioId, ?string $dataIni = null, ?string $dataFim = null, array $viagemIds = []): array
    {
        $dataIni = $this->dataValida($dataIni);
        $dataFim = $this->dataValida($dataFim);

        $viagemIds = array_values(array_unique(array_filter(array_map('intval', $viagemIds), fn($v) => $v > 0)));
        $porViagem = count($viagemIds) > 0;

        // Viagens selecionadas têm prioridade: só aplica período se nada foi selecionado.
        $aplicarPeriodo = !$porViagem;
        $ini = $aplicarPeriodo ? $dataIni : null;
        $fim = $aplicarPeriodo ? $dataFim : null;
        if ($ini !== null && $fim !== null && $ini > $fim) {
            [$ini, $fim] = [$fim, $ini];
        }

        $viagens = [];
        foreach ($this->listarViagens($usuarioId) as $v) {
            $viagens[(int) $v['id']] = $v;
        }

        $vendas  = $this->vendasPorViagem($usuarioId, $viagemIds, $ini, $fim);
        $compras = $this->comprasPorViagem($usuarioId, $viagemIds, $ini, $fim);
        $gastos  = $this->gastosPorViagem($usuarioId, $viagemIds, $ini, $fim);

        $linhas = [];
        foreach ($viagens as $vid => $v) {
            if ($porViagem && !in_array($vid, $viagemIds, true)) {
                continue;
            }

            $receita = (float) ($vendas[$vid]['receita'] ?? 0);
            $qtd     = (int)   ($vendas[$vid]['qtd'] ?? 0);
            $custo   = (float) ($compras[$vid] ?? 0);
            $gasto   = (float) ($gastos[$vid] ?? 0);

            // Sem movimento: só entra se a viagem foi escolhida explicitamente.
            if (!$porViagem && $receita === 0.0 && $custo === 0.0 && $gasto === 0.0) {
                continue;
            }

            $custoTotal = round($custo + $gasto, 2);
            $resultado  = round($receita - $custoTotal, 2);

            $linhas[] = [
                'viagem_id'      => $vid,
                'destino'        => $v['destino'],
                'data_inicial'   => $v['data_inicial'],
                'data_final'     => $v['data_final'],
                'status'         => $v['status'],
                'qtd_vendas'     => $qtd,
                'receita'        => round($receita, 2),
                'custo_compras'  => round($custo, 2),
                'gastos'         => round($gasto, 2),
                'custo_total'    => $custoTotal,
                'resultado'      => $resultado,
                'margem'         => $receita > 0 ? round($resultado / $receita * 100, 1) : null,
            ];
        }

        usort($linhas, fn($a, $b) => strcmp((string) $b['data_inicial'], (string) $a['data_inicial'])
            ?: ($b['viagem_id'] <=> $a['viagem_id']));

        $totais = [
            'qtd_vendas'    => 0,
            'receita'       => 0.0,
            'custo_compras' => 0.0,
            'gastos'        => 0.0,
            'custo_total'   => 0.0,
            'resultado'     => 0.0,
            'margem'        => null,
        ];
        foreach ($linhas as $l) {
            $totais['qtd_vendas']    += $l['qtd_vendas'];
            $totais['receita']       += $l['receita'];
            $totais['custo_compras'] += $l['custo_compras'];
            $totais['gastos']        += $l['gastos'];
            $totais['custo_total']   += $l['custo_total'];
            $totais['resultado']     += $l['resultado'];
        }
        $totais = array_map(fn($v) => is_float($v) ? round($v, 2) : $v, $totais);
        $totais['margem'] = $totais['receita'] > 0
            ? round($totais['resultado'] / $totais['receita'] * 100, 1)
            : null;

        return [
            'linhas'  => $linhas,
            'totais'  => $totais,
            'filtros' => [
                'data_ini' => $aplicarPeriodo ? $dataIni : null,
                'data_fim' => $aplicarPeriodo ? $dataFim : null,
                'viagens'  => $porViagem ? $viagemIds : [],
            ],
        ];
    }

    private function dataValida(?string $data): ?string
    {
        $data = trim((string) $data);
        if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return null;
        }
        return $data;
    }

    /**
     * Receita e quantidade de itens vendidos, por viagem.
     *
     * @return array<int,array{qtd:int,receita:float}>
     */
    private function vendasPorViagem(int $usuarioId, array $viagemIds, ?string $ini, ?string $fim): array
    {
        $sql = 'SELECT c.viagem_id AS vid, COUNT(*) AS qtd, COALESCE(SUM(p.preco), 0) AS receita
                FROM loja_produtos p
                INNER JOIN compras c ON c.id = p.compra_id
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u AND p.vendido = 1';

        $params = [':u' => $usuarioId];
        $sql .= $this->filtrosComuns('DATE(p.vendido_em)', $viagemIds, $ini, $fim, $params);
        $sql .= ' GROUP BY c.viagem_id';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $mapa = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mapa[(int) $r['vid']] = ['qtd' => (int) $r['qtd'], 'receita' => (float) $r['receita']];
        }

        return $mapa;
    }

    /** Custo de mercadorias (compras) por viagem. */
    private function comprasPorViagem(int $usuarioId, array $viagemIds, ?string $ini, ?string $fim): array
    {
        $sql = 'SELECT c.viagem_id AS vid, COALESCE(SUM(c.valor_brl * c.quantidade), 0) AS custo
                FROM compras c
                INNER JOIN viagens v ON v.id = c.viagem_id
                WHERE v.usuario_id = :u';

        $params = [':u' => $usuarioId];
        $sql .= $this->filtrosComuns('c.data_compra', $viagemIds, $ini, $fim, $params);
        $sql .= ' GROUP BY c.viagem_id';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $this->mapaValores($stmt, 'vid', 'custo');
    }

    /** Gastos da viagem, por viagem. */
    private function gastosPorViagem(int $usuarioId, array $viagemIds, ?string $ini, ?string $fim): array
    {
        $sql = 'SELECT g.viagem_id AS vid, COALESCE(SUM(g.valor_brl), 0) AS gasto
                FROM gastos g
                INNER JOIN viagens v ON v.id = g.viagem_id
                WHERE v.usuario_id = :u';

        $params = [':u' => $usuarioId];
        $sql .= $this->filtrosComuns('g.data_gasto', $viagemIds, $ini, $fim, $params);
        $sql .= ' GROUP BY g.viagem_id';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $this->mapaValores($stmt, 'vid', 'gasto');
    }

    /**
     * Fragmento WHERE comum (viagens selecionadas e/ou período).
     * Preenche $params com os placeholders usados.
     */
    private function filtrosComuns(string $colunaData, array $viagemIds, ?string $ini, ?string $fim, array &$params): string
    {
        $sql = '';

        if (count($viagemIds) > 0) {
            $phs = [];
            foreach ($viagemIds as $i => $id) {
                $chave = ':vid' . $i;
                $phs[] = $chave;
                $params[$chave] = $id;
            }
            $sql .= ' AND v.id IN (' . implode(',', $phs) . ')';
        } elseif ($ini !== null || $fim !== null) {
            if ($ini !== null) {
                $sql .= " AND $colunaData >= :ini";
                $params[':ini'] = $ini;
            }
            if ($fim !== null) {
                $sql .= " AND $colunaData <= :fim";
                $params[':fim'] = $fim;
            }
        }

        return $sql;
    }

    private function mapaValores(PDOStatement $stmt, string $colChave, string $colValor): array
    {
        $mapa = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mapa[(int) $r[$colChave]] = (float) $r[$colValor];
        }
        return $mapa;
    }
}
