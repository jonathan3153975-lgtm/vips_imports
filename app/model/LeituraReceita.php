<?php
// =====================================================================
// VIP Imports — Model: Leitura de nota fiscal/recibo por IA (Gemini)
// Envia a imagem (base64) para a API Gemini e devolve os dados
// estruturados (descricao, valor, moeda, data, categoria) em JSON.
// =====================================================================

class LeituraReceita
{
    private ?array $env = null;

    public const MIMES_PERMITIDAS = ['image/jpeg', 'image/png', 'image/webp'];
    public const TAMANHO_MAXIMO   = 2097152; // 2 MB

    public const CATEGORIAS_COMPRA = ['calca', 'casaco', 'jaqueta', 'tenis', 'outros'];

    private const PROMPT = <<<'PROMPT'
Você é um assistente especializado em extrair dados de notas fiscais, recibos e comprovantes de viagem (EUA/Brasil).

Analise a imagem e retorne APENAS JSON válido, sem texto extra, neste formato exato:
{
  "descricao": "resumo curto da compra",
  "valor": 0.00,
  "moeda": "BRL ou USD",
  "data": "AAAA-MM-DD",
  "categoria": "passagem | estadia | alimentacao | outros"
}

Regras:
1. valor = TOTAL pago pelo cliente (usando ponto como separador decimal).
2. moeda: USD se a nota estiver em dólar; senão BRL.
3. data: use a data de emissão da nota; se não houver, deixe vazio ("").
4. categoria:
   - passagem: passagens aéreas, ônibus, trem, Uber/táxi, pedágio;
   - estadia: hotel, Airbnb, hostel, aluguel;
   - alimentacao: restaurante, mercado, lanchonete, delivery;
   - outros: qualquer coisa fora das acima.
5. descricao: máximo 60 caracteres.
6. Se não conseguir ler o valor total, use 0.00.
PROMPT;

    private const PROMPT_ITENS = <<<'PROMPT'
Você é um assistente especializado em compras de viagem (roupas, calçados e acessórios).

Analise a imagem de uma nota fiscal/cupom de loja de roupas e retorne APENAS JSON válido, sem texto extra, neste formato exato:
{
  "loja": "nome da loja se visível, senão vazio",
  "moeda": "USD se a nota estiver em dólar, senão BRL",
  "data": "AAAA-MM-DD (data de emissão; vazio se não houver)",
  "itens": [
    { "produto": "nome do produto", "marca": "marca se identificável", "categoria": "calca | casaco | jaqueta | tenis | outros", "quantidade": 1, "valor_total": 0.00 },
    { ... uma linha para CADA produto comprado ... }
  ]
}

Regras:
1. Um item por LINHA/coluna do cupom (cada produto comprado), inclusive repetidos.
2. valor_total = total pago pela linha considerando a quantidade.
   Ex.: "3x Camiseta Polo 45,00" => quantidade 3 e valor_total 135.00.
3. categoria (inferir do produto):
   - calca: calças, jeans, bermuda, shorts;
   - casaco: casacos, sobretudos, jaquetas grossas... (jaquetas leves podem ir em jaqueta);
   - jaqueta: jaquetas, blazers, moletons, hoodies;
   - tenis: tênis, sapatos, chinelos, botas, sandálias;
   - outros: camisetas, camisas, vestidos, roupas íntimas, meias, acessórios, bonés, bolsas e demais.
4. marca: extraia do nome (nike, adidas, puma, levis, etc.); senão "".
5. produto: máximo 60 caracteres, sem o preço.
6. Se não conseguir ler o valor de uma linha, use valor_total 0.00 e quantidade 1.
7. Ignore linhas de subtotal, desconto, imposto e "total a pagar" — elas NÃO geram itens.
8. Se a nota tiver desconto geral, distribua proporcionalmente nos itens, mantendo o total igual ao pago.
PROMPT;

    private const PROMPT_PRODUTO = <<<'PROMPT'
Você é um assistente de loja de roupas, calçados e acessórios importados.

Analise as FOTOS DO PRODUTO enviadas e retorne APENAS JSON válido, sem texto extra, neste formato exato:
{
  "produto": "nome curto do produto",
  "marca": "marca se identificável nas fotos, senão vazio",
  "categoria": "calca | casaco | jaqueta | tenis | outros",
  "descricao": "descrição de venda atrativa em português (4 a 7 frases curtas)"
}

Regras:
1. produto: máximo 60 caracteres, sem preço (ex.: "Calça jeans slim", "Tênis running Nike").
2. marca: extraia de etiquetas, tags, logos ou etiquetas visíveis; senão "".
3. categoria (inferir do produto):
   - calca: calças, jeans, bermudas, shorts;
   - casaco: casacos, sobretudos, jaquetas grossas;
   - jaqueta: jaquetas, blazers, moletons, hoodies;
   - tenis: tênis, sapatos, chinelos, botas, sandálias;
   - outros: camisetas, camisas, vestidos, acessórios, bonés, bolsas, eletrônicos e demais.
4. descricao: destaque cor, material/modelo, estado de conservação aparente, características visíveis e sugestão de uso; máximo 400 caracteres.
5. Se não houver fotos legíveis, retorne produto e descricao vazios.
PROMPT;

    /**
     * Envia a imagem ao Gemini e retorna os dados normalizados.
     * Retorna null se a API não estiver configurada.
     * Lança exceção com mensagem amigável em caso de falha da API.
     *
     * @return array{descricao:?string,valor:float,moeda:string,data:?string,categoria:string}|null
     */
    public function ler(string $base64, string $mime): ?array
    {
        $chave = $this->envValor('GEMINI_API_KEY');
        if ($chave === null || $chave === '') {
            return null;
        }

        $modelo = $this->envValor('GEMINI_MODEL') ?: 'gemini-2.5-flash';
        $url    = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($modelo)
            . ':generateContent?key=' . rawurlencode($chave);

        $corpo = [
            'contents' => [
                [
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                        ['text' => self::PROMPT],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'       => 0,
                'responseMimeType'  => 'application/json',
            ],
        ];

        $resposta = $this->httpPostJson($url, $corpo);
        if ($resposta === null) {
            throw new RuntimeException('Não foi possível contatar o serviço de IA (Gemini).');
        }

        $dados = json_decode($resposta, true);

        $erroApi = $dados['error']['message'] ?? null;
        if ($erroApi !== null) {
            throw new RuntimeException('Erro da API Gemini: ' . $erroApi);
        }

        if (!isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
            throw new RuntimeException('A IA não retornou dados legíveis da imagem.');
        }

        $texto = $dados['candidates'][0]['content']['parts'][0]['text'];
        $json  = $this->extrairJson($texto);

        if ($json === null) {
            throw new RuntimeException('A IA não retornou JSON válido.');
        }

        return $this->normalizar($json);
    }

    /**
     * Lê uma nota de compras (roupas/calçados) e retorna os itens em bloco.
     *
     * @return array{loja:?string,moeda:string,data:?string,itens:array[]}|null
     */
    public function lerItens(string $base64, string $mime): ?array
    {
        $chave = $this->envValor('GEMINI_API_KEY');
        if ($chave === null || $chave === '') {
            return null;
        }

        $modelo = $this->envValor('GEMINI_MODEL') ?: 'gemini-2.5-flash';
        $url    = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($modelo)
            . ':generateContent?key=' . rawurlencode($chave);

        $corpo = [
            'contents' => [
                [
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                        ['text' => self::PROMPT_ITENS],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0,
                'responseMimeType' => 'application/json',
            ],
        ];

        $resposta = $this->httpPostJson($url, $corpo);
        if ($resposta === null) {
            throw new RuntimeException('Não foi possível contatar o serviço de IA (Gemini).');
        }

        $dados = json_decode($resposta, true);

        $erroApi = $dados['error']['message'] ?? null;
        if ($erroApi !== null) {
            throw new RuntimeException('Erro da API Gemini: ' . $erroApi);
        }

        if (!isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
            throw new RuntimeException('A IA não retornou dados legíveis da imagem.');
        }

        $texto = $dados['candidates'][0]['content']['parts'][0]['text'];
        $json  = $this->extrairJson($texto);

        if ($json === null) {
            throw new RuntimeException('A IA não retornou JSON válido.');
        }

        return $this->normalizarItens($json);
    }

    /**
     * Lê fotos de um produto e retorna nome, marca, categoria e descrição.
     * Envia até 4 imagens simultaneamente ao Gemini.
     *
     * @param array{base64:string,mime:string}[] $imagens
     * @return array{produto:string,marca:?string,categoria:string,descricao:?string}|null
     */
    public function lerDescricao(array $imagens): ?array
    {
        $chave = $this->envValor('GEMINI_API_KEY');
        if ($chave === null || $chave === '') {
            return null;
        }

        $modelo = $this->envValor('GEMINI_MODEL') ?: 'gemini-2.5-flash';
        $url    = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($modelo)
            . ':generateContent?key=' . rawurlencode($chave);

        $parts = [];
        foreach (array_slice($imagens, 0, 4) as $imagem) {
            $parts[] = ['inline_data' => [
                'mime_type' => (string) ($imagem['mime'] ?? 'image/jpeg'),
                'data'      => (string) $imagem['base64'],
            ]];
        }
        $parts[] = ['text' => self::PROMPT_PRODUTO];

        $corpo = [
            'contents' => [
                ['parts' => $parts],
            ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        $resposta = $this->httpPostJson($url, $corpo);
        if ($resposta === null) {
            throw new RuntimeException('Não foi possível contatar o serviço de IA (Gemini).');
        }

        $dados = json_decode($resposta, true);

        $erroApi = $dados['error']['message'] ?? null;
        if ($erroApi !== null) {
            throw new RuntimeException('Erro da API Gemini: ' . $erroApi);
        }

        if (!isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
            throw new RuntimeException('A IA não retornou dados legíveis das fotos.');
        }

        $texto = $dados['candidates'][0]['content']['parts'][0]['text'];
        $json  = $this->extrairJson($texto);

        if ($json === null) {
            throw new RuntimeException('A IA não retornou JSON válido.');
        }

        return $this->normalizarProduto($json);
    }

    private function normalizarProduto(array $j): array
    {
        $produto = trim((string) ($j['produto'] ?? ''));
        $produto = mb_substr($produto, 0, 160);

        $marca = trim((string) ($j['marca'] ?? ''));
        $marca = $marca === '' ? null : mb_substr($marca, 0, 80);

        $categoria = strtolower(trim((string) ($j['categoria'] ?? 'outros')));
        $categoria = in_array($categoria, self::CATEGORIAS_COMPRA, true) ? $categoria : 'outros';

        $descricao = trim((string) ($j['descricao'] ?? ''));
        $descricao = $descricao === '' ? null : mb_substr($descricao, 0, 500);

        return [
            'produto'    => $produto,
            'marca'      => $marca,
            'categoria'  => $categoria,
            'descricao'  => $descricao,
        ];
    }

    // -------------------------------------------------------------

    private function normalizarItens(array $j): array
    {
        $moeda = strtoupper((string) ($j['moeda'] ?? 'BRL'));
        $moeda = in_array($moeda, ['BRL', 'USD'], true) ? $moeda : 'BRL';

        $data = trim((string) ($j['data'] ?? ''));
        $data = $this->dataValida($data) ? $data : null;

        $loja = trim((string) ($j['loja'] ?? ''));
        $loja = $loja === '' ? null : mb_substr($loja, 0, 160);

        $itens = [];
        foreach (($j['itens'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $produto = trim((string) ($item['produto'] ?? ''));
            if ($produto === '') {
                continue;
            }

            $marca = trim((string) ($item['marca'] ?? ''));
            $marca = $marca === '' ? null : mb_substr($marca, 0, 80);

            $categoria = strtolower(trim((string) ($item['categoria'] ?? 'outros')));
            $categoria = in_array($categoria, self::CATEGORIAS_COMPRA, true) ? $categoria : 'outros';

            $valor = max(0.0, (float) ($item['valor_total'] ?? 0));
            $qtd   = max(1, (int) ($item['quantidade'] ?? 1));

            $itens[] = [
                'produto'    => mb_substr($produto, 0, 160),
                'marca'      => $marca,
                'categoria'  => $categoria,
                'quantidade' => $qtd,
                'valor'      => round($valor, 2),
            ];
        }

        if (count($itens) === 0) {
            throw new RuntimeException('Nenhum item identificado na imagem.');
        }

        return [
            'loja'  => $loja,
            'moeda' => $moeda,
            'data'  => $data,
            'itens' => $itens,
        ];
    }

    private function normalizar(array $j): array
    {
        $categorias = ['passagem', 'estadia', 'alimentacao', 'outros'];

        $valor     = max(0.0, (float) ($j['valor'] ?? 0));
        $moeda     = strtoupper((string) ($j['moeda'] ?? 'BRL'));
        $moeda     = in_array($moeda, ['BRL', 'USD'], true) ? $moeda : 'BRL';
        $categoria = (string) ($j['categoria'] ?? '');
        $categoria = in_array($categoria, $categorias, true) ? $categoria : 'outros';
        $data      = trim((string) ($j['data'] ?? ''));
        $data      = $this->dataValida($data) ? $data : null;
        $descricao = trim((string) ($j['descricao'] ?? ''));

        return [
            'descricao' => $descricao === '' ? null : mb_substr($descricao, 0, 200),
            'valor'     => round($valor, 2),
            'moeda'     => $moeda,
            'data'      => $data,
            'categoria' => $categoria,
        ];
    }

    private function dataValida(string $data): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        return $dt !== false && $dt->format('Y-m-d') === $data;
    }

    /**
     * Aceita JSON puro ou entre marcas de código (```json ... ```).
     */
    private function extrairJson(string $texto): ?array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto);
        $texto = preg_replace('/\s*```$/', '', $texto);

        $decodificado = json_decode($texto, true);
        if (is_array($decodificado)) {
            return $decodificado;
        }

        return null;
    }

    private function httpPostJson(string $url, array $corpo): ?string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão curl não disponível no servidor.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($corpo),
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify(),
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify() ? 2 : 0,
        ]);

        $corpo   = curl_exec($ch);
        $http    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro    = curl_error($ch);
        curl_close($ch);

        if (($corpo === false || $corpo === '') && $erro !== '') {
            throw new RuntimeException('Falha de conexão com a API Gemini: ' . $erro);
        }

        if ($http < 200 || $http >= 300) {
            // Devolve o corpo para o chamador interpretar o erro da API.
            return (string) $corpo;
        }

        return (string) $corpo;
    }

    private function sslVerify(): bool
    {
        if (!array_key_exists('GEMINI_SSL_VERIFY', $this->envCol())) {
            return true;
        }
        return filter_var($this->envValor('GEMINI_SSL_VERIFY'), FILTER_VALIDATE_BOOLEAN);
    }

    private function envValor(string $chave): ?string
    {
        $env = $this->envCol();
        return array_key_exists($chave, $env) ? (string) $env[$chave] : null;
    }

    private function envCol(): array
    {
        if ($this->env !== null) {
            return $this->env;
        }

        $caminho = dirname(__DIR__, 2) . '/.env';
        $this->env = is_file($caminho) ? (parse_ini_file($caminho) ?: []) : [];

        return $this->env;
    }
}