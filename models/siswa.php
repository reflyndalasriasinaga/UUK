<?php

require_once dirname(__DIR__) . '/basemodel.php';

class Siswa extends BaseModel
{
    private ?int $idSiswa = null;
    private string $nisn = '';
    private string $nama = '';
    private ?int $idKelas = null;
    private ?string $alamat = null;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
    }

    public function getIdSiswa(): ?int
    {
        return $this->idSiswa;
    }

    public function setIdSiswa(?int $idSiswa): void
    {
        $this->idSiswa = $idSiswa;
    }

    public function getNisn(): string
    {
        return $this->nisn;
    }

    public function setNisn(string $nisn): void
    {
        $this->nisn = trim($nisn);
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    public function setNama(string $nama): void
    {
        $this->nama = trim($nama);
    }

    public function getIdKelas(): ?int
    {
        return $this->idKelas;
    }

    public function setIdKelas(?int $idKelas): void
    {
        $this->idKelas = $idKelas;
    }

    public function getAlamat(): ?string
    {
        return $this->alamat;
    }

    public function setAlamat(?string $alamat): void
    {
        $clean = $alamat !== null ? trim($alamat) : '';
        $this->alamat = $clean === '' ? null : $clean;
    }

    public function getAll(): array
    {
        $sql = "SELECT s.id_siswa, s.nis AS nisn, s.nama, s.id_kelas, s.alamat, s.created_at, k.nama_kelas
                FROM siswa s
                JOIN kelas k ON s.id_kelas = k.id_kelas
                ORDER BY s.nama ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function search(string $keyword = '', ?int $idKelas = null): array
    {
        $sql = "SELECT s.id_siswa, s.nis AS nisn, s.nama, s.id_kelas, s.alamat, s.created_at, k.nama_kelas
                FROM siswa s
                JOIN kelas k ON s.id_kelas = k.id_kelas
                WHERE 1=1";
        $params = [];

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $sql .= " AND s.nama LIKE :kw_nama";
            $params[':kw_nama'] = '%' . $keyword . '%';
        }

        if ($idKelas !== null && $idKelas > 0) {
            $sql .= " AND s.id_kelas = :id_kelas";
            $params[':id_kelas'] = $idKelas;
        }

        $sql .= " ORDER BY s.nama ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $idSiswa): ?array
    {
        $sql = "SELECT id_siswa, nis AS nisn, nama, id_kelas, alamat, created_at
                FROM siswa
                WHERE id_siswa = :id_siswa
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_siswa' => $idSiswa]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function existsByNisn(string $nisn, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT id_siswa FROM siswa WHERE nis = :nisn AND id_siswa != :id_siswa LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nisn'     => trim($nisn),
                ':id_siswa' => $excludeId,
            ]);
        } else {
            $sql = "SELECT id_siswa FROM siswa WHERE nis = :nisn LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nisn' => trim($nisn),
            ]);
        }

        return (bool) $stmt->fetch();
    }

    public function create(string $nisn, string $nama, int $idKelas, ?string $alamat): bool
    {
        $this->setNisn($nisn);
        $this->setNama($nama);
        $this->setIdKelas($idKelas);
        $this->setAlamat($alamat);

        $sql = "INSERT INTO siswa (nis, nama, id_kelas, alamat)
                VALUES (:nisn, :nama, :id_kelas, :alamat)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':nisn'     => $this->nisn,
            ':nama'     => $this->nama,
            ':id_kelas' => $this->idKelas,
            ':alamat'   => $this->alamat,
        ]);
    }

    public function update(int $idSiswa, string $nisn, string $nama, int $idKelas, ?string $alamat): bool
    {
        $this->setIdSiswa($idSiswa);
        $this->setNisn($nisn);
        $this->setNama($nama);
        $this->setIdKelas($idKelas);
        $this->setAlamat($alamat);

        $sql = "UPDATE siswa
                SET nis = :nisn,
                    nama = :nama,
                    id_kelas = :id_kelas,
                    alamat = :alamat
                WHERE id_siswa = :id_siswa";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':nisn'     => $this->nisn,
            ':nama'     => $this->nama,
            ':id_kelas' => $this->idKelas,
            ':alamat'   => $this->alamat,
            ':id_siswa' => $this->idSiswa,
        ]);
    }

    public function delete(int $idSiswa): bool
    {
        $sql = "DELETE FROM siswa WHERE id_siswa = :id_siswa";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id_siswa' => $idSiswa]);
    }
}