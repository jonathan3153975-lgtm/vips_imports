<?php
// =====================================================================
// VIP Imports — Model: Telegram
// Comunicação com a Bot API do Telegram: enviar mensagens/fotos para um
// grupo e registrar/remover o webhook do bot.
// =====================================================================

class Telegram
{
    private ?string $token;

    public function __construct(?string $token)
    {
        $this->token = ($token === null || trim($token) === '') ? null : trim($token);
    }

    public function configurado(): bool
    {
        return $this->token !== null;
    }

    public function enviarMensagem(string $chatId, string $texto): bool
    {
        $res = $this->chamar('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $texto,
            'parse_mode' => 'HTML',
        ]);
        return $res !== null && ($res['ok'] ?? false) === true;
    }

    public function enviarFoto(string $chatId, string $fotoUrl, string $legenda = ''): bool
    {
        $params = ['chat_id' => $chatId, 'photo' => $fotoUrl, 'parse_mode' => 'HTML'];
        if ($legenda !== '') {
            $params['caption'] = $legenda;
        }
        $res = $this->chamar('sendPhoto', $params);
        return $res !== null && ($res['ok'] ?? false) === true;
    }

    /**
     * Registra a URL do webhook com o segredo (validado pelo Telegram).
     *
     * @return array{ok:bool,mensagem:string}
     */
    public function definirWebhook(string $url, string $segredo): array
    {
        $res = $this->chamar('setWebhook', [
            'url'             => $url,
            'secret_token'    => $segredo,
            'allowed_updates' => json_encode(['message']),
        ]);

        if ($res === null) {
            return ['ok' => false, 'mensagem' => 'Sem resposta da API do Telegram.'];
        }

        $ok = (bool) ($res['ok'] ?? false);
        $mensagem = $res['description'] ?? ($ok ? 'Webhook registrado.' : 'Falha ao registrar o webhook.');

        return ['ok' => $ok, 'mensagem' => (string) $mensagem];
    }

    /**
     * @return array{ok:bool,mensagem:string}
     */
    public function infoWebhook(): array
    {
        $res = $this->chamar('getWebhookInfo', []);
        if ($res === null) {
            return ['ok' => false, 'mensagem' => 'Sem resposta da API do Telegram.'];
        }
        return ['ok' => true, 'mensagem' => json_encode($res['result'] ?? [], JSON_UNESCAPED_UNICODE)];
    }

    public function removerWebhook(): bool
    {
        $res = $this->chamar('deleteWebhook', []);
        return $res !== null && ($res['ok'] ?? false) === true;
    }

    /**
     * Executa um método da Bot API e devolve o JSON decodificado (ou null).
     */
    private function chamar(string $metodo, array $params): ?array
    {
        if (!$this->configurado()) {
            return null;
        }
        if (!function_exists('curl_init')) {
            return null;
        }

        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $metodo;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify(),
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify() ? 2 : 0,
        ]);

        $corpo = curl_exec($ch);
        $erro  = curl_error($ch);
        curl_close($ch);

        if ($corpo === false || $corpo === '') {
            return null;
        }

        $json = json_decode((string) $corpo, true);
        return is_array($json) ? $json : null;
    }

    private function sslVerify(): bool
    {
        $env = is_file(dirname(__DIR__, 2) . '/.env')
            ? (parse_ini_file(dirname(__DIR__, 2) . '/.env') ?: [])
            : [];

        if (!array_key_exists('TELEGRAM_SSL_VERIFY', $env)) {
            return true;
        }
        return filter_var($env['TELEGRAM_SSL_VERIFY'], FILTER_VALIDATE_BOOLEAN);
    }
}
