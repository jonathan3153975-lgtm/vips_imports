<?php
// =====================================================================
// VIP Imports — Model: UsuarioConfig
// Guarda, por usuário, o token do link público da loja e as credenciais
// do bot do Telegram (token, segredo do webhook e grupo de divulgação).
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class UsuarioConfig
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = (new Connection())->PDOconn();
    }

    /**
     * Garante que o usuário tenha uma linha de configuração e devolve-a.
     */
    public function garantir(int $usuarioId): array
    {
        $cfg = $this->obter($usuarioId);
        if ($cfg !== null) {
            return $cfg;
        }

        $token = $this->novoToken();
        $stmt = $this->conn->prepare(
            'INSERT INTO usuario_config (usuario_id, loja_token) VALUES (:u, :t)'
        );
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':t', $token, PDO::PARAM_STR);

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            // Corrida: outra requisição criou a linha — apenas relê.
        }

        return $this->obter($usuarioId) ?? [
            'usuario_id' => $usuarioId,
            'loja_token' => $token,
            'loja_nome' => null,
            'loja_logo' => null,
            'telegram_bot_token' => null,
            'telegram_webhook_secret' => null,
            'telegram_group_chat_id' => null,
        ];
    }

    /**
     * Linha de configuração do usuário (ou null).
     */
    public function obter(int $usuarioId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM usuario_config WHERE usuario_id = :u LIMIT 1'
        );
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cfg === false ? null : $cfg;
    }

    /**
     * Salva as credenciais do Telegram. Campos vazios viram NULL.
     */
    public function salvarTelegram(
        int $usuarioId,
        ?string $botToken,
        ?string $segredo,
        ?string $grupoChatId
    ): bool {
        $this->garantir($usuarioId);

        $stmt = $this->conn->prepare(
            'UPDATE usuario_config
                SET telegram_bot_token = :t,
                    telegram_webhook_secret = :s,
                    telegram_group_chat_id = :g
              WHERE usuario_id = :u'
        );
        $stmt->bindValue(':t', $this->ouNull($botToken), $botToken ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':s', $this->ouNull($segredo), $segredo ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':g', $this->ouNull($grupoChatId), $grupoChatId ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    /**
     * Gera (e grava) um novo token de link público.
     */
    public function renovarToken(int $usuarioId): string
    {
        $this->garantir($usuarioId);
        $token = $this->novoToken();

        $stmt = $this->conn->prepare(
            'UPDATE usuario_config SET loja_token = :t WHERE usuario_id = :u'
        );
        $stmt->bindValue(':t', $token, PDO::PARAM_STR);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $token;
    }

    /**
     * Salva o nome e o logotipo da loja (logotipo null remove o atual).
     */
    public function salvarLoja(int $usuarioId, ?string $nome, ?string $logo): bool
    {
        $this->garantir($usuarioId);

        $nome = $this->ouNull($nome);
        $logo = $this->ouNull($logo);

        $stmt = $this->conn->prepare(
            'UPDATE usuario_config SET loja_nome = :n, loja_logo = :l WHERE usuario_id = :u'
        );
        $stmt->bindValue(':n', $nome, $nome === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':l', $logo, $logo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':u', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    /**
     * Localiza a configuração pelo token público (link da loja).
     */
    public function buscarPorToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT uc.*, u.nome AS usuario_nome
               FROM usuario_config uc
               INNER JOIN usuarios u ON u.id = uc.usuario_id
              WHERE uc.loja_token = :t
              LIMIT 1'
        );
        $stmt->bindValue(':t', $token, PDO::PARAM_STR);
        $stmt->execute();

        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cfg === false ? null : $cfg;
    }

    /**
     * Localiza a configuração pelo segredo do webhook do Telegram.
     */
    public function buscarPorWebhookSecret(string $segredo): ?array
    {
        $segredo = trim($segredo);
        if ($segredo === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT * FROM usuario_config
              WHERE telegram_webhook_secret = :s
                AND telegram_webhook_secret IS NOT NULL
              LIMIT 1'
        );
        $stmt->bindValue(':s', $segredo, PDO::PARAM_STR);
        $stmt->execute();

        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cfg === false ? null : $cfg;
    }

    private function novoToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function ouNull(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }
}
