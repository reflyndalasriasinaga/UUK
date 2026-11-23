<?php

require_once dirname(__DIR__) . '/database.php';

class Presensi
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
    }

    public function saveForDate(int $idSiswa, string $tanggal, string $status, ?string $keterangan): bool
    {
        $statement = $this->db->prepare(
            'INSERT INTO presensi (id_siswa, tanggal, status, keterangan)
             VALUES (:id_siswa, :tanggal, :status, :keterangan)
             ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)'
        );

        return $statement->execute([
            'id_siswa' => $idSiswa,
            'tanggal' => $tanggal,
            'status' => $status,
            'keterangan' => $keterangan,
        ]);
    }

    public function getByDate(string $tanggal): array
    {
        $statement = $this->db->prepare(
            'SELECT p.id_presensi, p.id_siswa, p.tanggal, p.status, p.keterangan,
                    s.nis AS nisn, s.nama, k.nama_kelas
             FROM presensi p
             INNER JOIN siswa s ON s.id_siswa = p.id_siswa
             INNER JOIN kelas k ON k.id_kelas = s.id_kelas
             WHERE p.tanggal = :tanggal
             ORDER BY s.nama ASC'
        );
        $statement->execute(['tanggal' => $tanggal]);

        return $statement->fetchAll();
    }

    public function findBySiswaAndTanggal(int $idSiswa, string $tanggal): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id_presensi, id_siswa, tanggal, status, keterangan, dicatat_pada
             FROM presensi
             WHERE id_siswa = :id_siswa AND tanggal = :tanggal
             LIMIT 1'
        );
        $statement->execute([
            ':id_siswa' => $idSiswa,
            ':tanggal'  => $tanggal,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function tapByNisn(string $nisn, string $tanggal, string $status = 'Hadir', ?string $keterangan = null): array
    {
        $statement = $this->db->prepare(
            'SELECT s.id_siswa, s.nis AS nisn, s.nama, k.nama_kelas
             FROM siswa s
             JOIN kelas k ON s.id_kelas = k.id_kelas
             WHERE s.nis = :nisn
             LIMIT 1'
        );
        $statement->execute(['nisn' => trim($nisn)]);
        $siswa = $statement->fetch();

        if (!$siswa) {
            return [
                'ok'      => false,
                'type'    => 'error',
                'message' => 'NISN tidak terdaftar pada data siswa.',
            ];
        }

        $idSiswa = (int) $siswa['id_siswa'];
        $sudahAbsen = $this->findBySiswaAndTanggal($idSiswa, $tanggal);

        if ($sudahAbsen !== null) {
            return [
                'ok'         => false,
                'type'       => 'already_absent',
                'message'    => 'Siswa atas nama ' . $siswa['nama'] . ' sudah absen hari ini (' . $tanggal . ') dengan status ' . $sudahAbsen['status'] . '.',
                'siswa'      => $siswa,
                'tanggal'    => $tanggal,
                'status'     => $sudahAbsen['status'],
                'keterangan' => $sudahAbsen['keterangan'],
            ];
        }

        $saved = $this->saveForDate($idSiswa, $tanggal, $status, $keterangan);

        if (!$saved) {
            return [
                'ok'      => false,
                'type'    => 'error',
                'message' => 'Gagal mencatat presensi tap kartu.',
            ];
        }

        return [
            'ok'         => true,
            'type'       => 'success',
            'message'    => "Tap kartu berhasil dicatat untuk tanggal {$tanggal}.",
            'siswa'      => $siswa,
            'tanggal'    => $tanggal,
            'status'     => $status,
            'keterangan' => $keterangan,
        ];
    }

    public function getRekap(?string $tanggal = null, ?int $idKelas = null, ?string $status = null, string $keyword = ''): array
    {
        $sql = 'SELECT p.id_presensi, p.id_siswa, p.tanggal, p.status, p.keterangan, p.dicatat_pada,
                       s.nis AS nisn, s.nama, k.nama_kelas
                FROM presensi p
                JOIN siswa s ON p.id_siswa = s.id_siswa
                JOIN kelas k ON s.id_kelas = k.id_kelas
                WHERE 1=1';
        $params = [];

        if ($tanggal !== null && $tanggal !== '') {
            $sql .= ' AND p.tanggal = :tanggal';
            $params[':tanggal'] = $tanggal;
        }

        if ($idKelas !== null && $idKelas > 0) {
            $sql .= ' AND s.id_kelas = :id_kelas';
            $params[':id_kelas'] = $idKelas;
        }

        $allowedStatus = ['Hadir', 'Izin', 'Sakit', 'Alpha'];
        if ($status !== null && $status !== '') {
            if (!in_array($status, $allowedStatus, true)) {
                throw new InvalidArgumentException('Status presensi tidak valid.');
            }

            $sql .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $sql .= ' AND (s.nama LIKE :kw_nama OR s.nis LIKE :kw_nisn)';
            $like = '%' . $keyword . '%';
            $params[':kw_nama'] = $like;
            $params[':kw_nisn'] = $like;
        }

        $sql .= ' ORDER BY p.tanggal DESC, p.dicatat_pada DESC, s.nama ASC';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteById(int $idPresensi): bool
    {
        $statement = $this->db->prepare('DELETE FROM presensi WHERE id_presensi = :id_presensi');
        $statement->execute([':id_presensi' => $idPresensi]);

        return $statement->rowCount() > 0;
    }
}