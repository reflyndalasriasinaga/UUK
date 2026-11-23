<?php
class Database {
    private string $host = 'localhost';
    private string $db = 'aplikasi presensi siswa';
    private string $user = 'root';
    private string $pass = '';
    private PDO $pdo;

    public function __construct() {
        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $this->user, $this->pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    // Getter: meminjamkan koneksi
    public function getConnection(): PDO {
        return $this->pdo;
    }
}