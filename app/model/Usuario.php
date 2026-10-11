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
}
