<?php

require_once dirname(__DIR__) . '/database.php';

class Kelas
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->getConnection();
    }

    public function getAll(): array
    {
        $statement = $this->db->query('SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC');
        return $statement->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id_kelas, nama_kelas FROM kelas WHERE id_kelas = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();

        return $data ?: null;
    }

    public function existsByNama(string $nama, ?int $excludeId = null): bool
    {
        $query = 'SELECT COUNT(*) FROM kelas WHERE nama_kelas = :nama';
        $parameters = ['nama' => $nama];

        if ($excludeId !== null) {
            $query .= ' AND id_kelas <> :id';
            $parameters['id'] = $excludeId;
        }

        $statement = $this->db->prepare($query);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(string $nama): bool
    {
        $statement = $this->db->prepare('INSERT INTO kelas (nama_kelas) VALUES (:nama)');
        return $statement->execute(['nama' => $nama]);
    }

    public function update(int $id, string $nama): bool
    {
        $statement = $this->db->prepare(
            'UPDATE kelas SET nama_kelas = :nama WHERE id_kelas = :id'
        );
        return $statement->execute([
            'nama' => $nama,
            'id' => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM kelas WHERE id_kelas = :id');
        return $statement->execute(['id' => $id]);
    }

    public function hasStudents(int $id): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM siswa WHERE id_kelas = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }
}
