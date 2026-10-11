<?php
// =====================================================================
// VIP Imports — Model: Cotação de moedas
// Fonte: BCB / PTAX (Banco Central do Brasil)
// Cache diário na tabela `cotacoes` para não consultar a API a cada request.
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Cotacao
{
    public const MOEDA_PADRAO = 'USD';
    public const FONTE        = 'BCB/PTAX';
    public const URL_PTAX     = 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)';

    private PDO $conn;
    private ?array $env = null;

    public function __construct()
    {
        $conexao    = new Connection();
        $this->conn = $conexao->PDOconn();
    }

    /**
     * Obtém a cotação do dólar do dia (cache -> BCB).
     * Retorna null se indisponível.
     */
    public function obterUsd(): ?array
    {
        $hoje = date('Y-m-d');

        $cache = $this->cacheDoDia($hoje);
        if ($cache !== null) {
            return $cache;
        }

        $ptax = $this->buscarPtaxUsd();
        if ($ptax === null) {
            return null;
        }

        $this->salvar($hoje, $ptax['data_hora'], $ptax['compra'], $ptax['venda']);

        return [
            'moeda'         => self::MOEDA_PADRAO,
            'cotacao'       => $ptax['venda'],
            'compra'        => $ptax['compra'],
            'venda'         => $ptax['venda'],
            'data_consulta' => $hoje,
            'data_hora'     => $ptax['data_hora'],
            'fonte'         => self::FONTE,
        ];
    }

    private function cacheDoDia(string $dataConsulta): ?array
    {
        $sql  = 'SELECT moeda, data_consulta, data_hora_ptax, valor_compra, valor_venda, fonte
                 FROM cotacoes
                 WHERE moeda = :m AND data_consulta = :d
                 LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':m', self::MOEDA_PADRAO, PDO::PARAM_STR);
        $stmt->bindValue(':d', $dataConsulta, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'moeda'         => $row['moeda'],
            'cotacao'       => (float) $row['valor_venda'],
            'compra'        => (float) $row['valor_compra'],
            'venda'         => (float) $row['valor_venda'],
            'data_consulta' => $row['data_consulta'],
            'data_hora'     => $row['data_hora_ptax'],
            'fonte'         => $row['fonte'],
        ];
    }

    private function salvar(string $dataConsulta, ?string $dataHora, float $compra, float $venda): void
    {
        $sql = 'INSERT INTO cotacoes (moeda, data_consulta, data_hora_ptax, valor_compra, valor_venda, fonte)
                VALUES (:m, :d, :dh, :c, :v, :f)
                ON DUPLICATE KEY UPDATE
                    data_hora_ptax = VALUES(data_hora_ptax),
                    valor_compra   = VALUES(valor_compra),
                    valor_venda    = VALUES(valor_venda),
                    fonte          = VALUES(fonte)';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':m', self::MOEDA_PADRAO, PDO::PARAM_STR);
        $stmt->bindValue(':d', $dataConsulta, PDO::PARAM_STR);
        $stmt->bindValue(':dh', $dataHora, $dataHora === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':c', $compra);
        $stmt->bindValue(':v', $venda);
        $stmt->bindValue(':f', self::FONTE, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Consulta o último fechamento PTAX do dólar nos últimos dias.
     */
    private function buscarPtaxUsd(): ?array
    {
        $inicial = (new DateTime('today'))->modify('-10 day')->format('m-d-Y');
        $final   = (new DateTime('today'))->format('m-d-Y');

        $url = self::URL_PTAX
            . "?@dataInicial='{$inicial}'&@dataFinalCotacao='{$final}'&\$format=json";

        $resposta = $this->httpGet($url);
        if ($resposta === null) {
            return null;
        }

        $dados = json_decode($resposta, true);
        if (!isset($dados['value']) || !is_array($dados['value']) || count($dados['value']) === 0) {
            return null;
        }

        $ultimo = end($dados['value']);
        if (!isset($ultimo['cotacaoCompra'], $ultimo['cotacaoVenda'])) {
            return null;
        }

        $dataHora = isset($ultimo['dataHoraCotacao'])
            ? substr((string) $ultimo['dataHoraCotacao'], 0, 19)
            : null;

        return [
            'compra'    => (float) $ultimo['cotacaoCompra'],
            'venda'     => (float) $ultimo['cotacaoVenda'],
            'data_hora' => $dataHora,
        ];
    }

    private function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify(),
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify() ? 2 : 0,
        ]);

        $corpo = curl_exec($ch);
        $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($corpo === false || $http < 200 || $http >= 300) {
            return null;
        }

        return (string) $corpo;
    }

    /**
     * Verificação SSL configurável via .env (BCB_SSL_VERIFY).
     * Alguns ambientes (proxy corporativo) exigem desativar.
     */
    private function sslVerify(): bool
    {
        if ($this->env === null) {
            $caminho   = dirname(__DIR__, 2) . '/.env';
            $this->env = is_file($caminho) ? (parse_ini_file($caminho) ?: []) : [];
        }

        if (!array_key_exists('BCB_SSL_VERIFY', $this->env)) {
            return true;
        }

        return filter_var($this->env['BCB_SSL_VERIFY'], FILTER_VALIDATE_BOOLEAN);
    }
}
