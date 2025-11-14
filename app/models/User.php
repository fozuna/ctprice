<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

class User
{
    public int $id;
    public string $nome;
    public string $email;
    public string $senha_hash;
    public string $role; // admin, rh, viewer
    public string $created_at;

    public static function findByEmail(string $email): ?self
    {
        $sql = 'SELECT * FROM usuarios WHERE email = ? LIMIT 1';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::map($data) : null;
    }

    public static function verifyPassword(self $user, string $password): bool
    {
        return password_verify($password, $user->senha_hash);
    }

    public static function create(string $nome, string $email, string $senha_hash, string $role = 'viewer'): int
    {
        $sql = 'INSERT INTO usuarios (nome, email, senha_hash, role) VALUES (?,?,?,?)';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$nome, $email, $senha_hash, $role]);
        return (int)Database::conn()->lastInsertId();
    }

    private static function map(array $data): self
    {
        $u = new self();
        $u->id = (int)$data['id'];
        $u->nome = $data['nome'];
        $u->email = $data['email'];
        $u->senha_hash = $data['senha_hash'];
        $u->role = $data['role'];
        $u->created_at = $data['created_at'];
        return $u;
    }
}