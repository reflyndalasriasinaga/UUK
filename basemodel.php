<?php

require_once __DIR__ . '/database.php';

class BaseModel
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
    }
}

class User extends BaseModel
{
    private ?int $id = null;
    private string $username = '';

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT id, username, created_at FROM users");
        return $stmt->fetchAll();
    }

    public function login(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
            ];
        }

        return null;
    }
}