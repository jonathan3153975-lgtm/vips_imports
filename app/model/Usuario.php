<?php
// =====================================================================
// VIP Imports — Model: Usuarios
// =====================================================================

require_once dirname(__DIR__, 2) . '/database/conexao.php';

class Usuario
{
    private PDO $conn;

    public function __construct()
    {
        $conexao    = new Connection();
        $this->conn = $conexao->PDOconn();
    }

    /**
     * Busca um usuário pelo e-mail. Retorna null se não encontrar.
     */
    public function buscarPorEmail(string $email): ?array
    {
        $sql = 'SELECT id, nome, email, senha, perfil, ativo
                FROM usuarios
                WHERE email = :email
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario === false ? null : $usuario;
    }

    /**
     * Registra a data/hora do último acesso bem-sucedido.
     */
    public function registrarUltimoAcesso(int $id): bool
    {
        $sql = 'UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Busca um usuário pelo id (dados exibidos no perfil).
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT id, nome, email, perfil, ativo, ultimo_acesso, criado_em
                FROM usuarios
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario === false ? null : $usuario;
    }

    /**
     * Verifica se o e-mail já pertence a outro usuário.
     */
    public function emailEmUso(string $email, int $ignorarId = 0): bool
    {
        $sql = 'SELECT 1 FROM usuarios WHERE email = :email AND id <> :id LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':id', $ignorarId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Atualiza nome e e-mail do usuário.
     */
    public function atualizarDados(int $id, string $nome, string $email): bool
    {
        $sql = 'UPDATE usuarios SET nome = :n, email = :e WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':n', mb_substr(trim($nome), 0, 120), PDO::PARAM_STR);
        $stmt->bindValue(':e', mb_substr(trim($email), 0, 160), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Confere a senha atual do usuário.
     */
    public function senhaConfere(int $id, string $senha): bool
    {
        $stmt = $this->conn->prepare('SELECT senha FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $hash = $stmt->fetchColumn();
        return $hash !== false && password_verify($senha, (string) $hash);
    }

    /**
     * Define uma nova senha (armazenada com hash).
     */
    public function atualizarSenha(int $id, string $novaSenha): bool
    {
        $sql = 'UPDATE usuarios SET senha = :s WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':s', password_hash($novaSenha, PASSWORD_DEFAULT), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Lista todos os usuários (gestão do master).
     */
    public function listar(): array
    {
        $sql = 'SELECT id, nome, email, perfil, ativo, ultimo_acesso, criado_em
                FROM usuarios
                ORDER BY FIELD(perfil, "master", "admin", "usuario"), nome ASC';

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um novo usuário. Lança exceção se o e-mail já existir.
     */
    public function criar(string $nome, string $email, string $senha, string $perfil = 'usuario', int $ativo = 1): int
    {
        $sql = 'INSERT INTO usuarios (nome, email, senha, perfil, ativo)
                VALUES (:n, :e, :s, :p, :a)';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':n', mb_substr(trim($nome), 0, 120), PDO::PARAM_STR);
        $stmt->bindValue(':e', mb_substr(trim($email), 0, 160), PDO::PARAM_STR);
        $stmt->bindValue(':s', password_hash($senha, PASSWORD_DEFAULT), PDO::PARAM_STR);
        $stmt->bindValue(':p', $perfil, PDO::PARAM_STR);
        $stmt->bindValue(':a', $ativo, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Ativa ou bloqueia o acesso de um usuário.
     */
    public function definirAtivo(int $id, int $ativo): bool
    {
        $stmt = $this->conn->prepare('UPDATE usuarios SET ativo = :a WHERE id = :id');
        $stmt->bindValue(':a', $ativo, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
