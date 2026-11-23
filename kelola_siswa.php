<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Presensi.php';
require_once __DIR__ . '/models/siswa.php';

$kelasModel = new Kelas();
$presensiModel = new Presensi();
$siswaModel = new Siswa();

$daftarKelas = $kelasModel->getAll();
$zonaWaktu = new DateTimeZone('Asia/Makassar');
$waktuLokal = new DateTimeImmutable('now', $zonaWaktu);
$tanggalHariIni = $waktuLokal->format('Y-m-d');
$namaBulan = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];
$tanggalHariIniTampilan = $waktuLokal->format('d') . ' '
    . $namaBulan[(int) $waktuLokal->format('n')] . ' '
    . $waktuLokal->format('Y');

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$nisnInput = '';
$namaInput = '';
$idKelasInput = '';
$alamatInput = '';

$errors = [
    'nisn'     => '',
    'nama'     => '',
    'id_kelas' => '',
    'alamat'   => '',
];
$pesanSukses = '';
$pesanError = '';

if ($editId !== null && $editId > 0) {
    $dataEdit = $siswaModel->findById($editId);
    if ($dataEdit) {
        $nisnInput    = $dataEdit['nisn'];
        $namaInput    = $dataEdit['nama'];
        $idKelasInput = (string) $dataEdit['id_kelas'];
        $alamatInput  = $dataEdit['alamat'] ?? '';
    } else {
        $editId = null;
    }
}

if (isset($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    if ($hapusId > 0) {
        try {
            if ($siswaModel->delete($hapusId)) {
                $pesanSukses = 'Data siswa berhasil dihapus.';
            }
        } catch (PDOException $e) {
            $pesanError = 'Gagal menghapus data siswa.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? 'tambah';

    if ($aksi === 'absen') {
        $idSiswaAbsen = (int) ($_POST['id_siswa_absen'] ?? 0);
        $statusAbsen = $_POST['status_absen'] ?? '';
        $keteranganInput = $_POST['keterangan_absen'] ?? '';
        $keteranganAbsen = is_string($keteranganInput) ? trim($keteranganInput) : '';
        $token = $_POST['csrf_token'] ?? '';
        $tanggalAbsen = $_POST['tanggal_absen'] ?? '';
        $jumlahKarakterKeterangan = preg_match_all('/./us', $keteranganAbsen, $karakterKeterangan);

        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $pesanError = 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.';
        } elseif (!is_string($tanggalAbsen) || !hash_equals($tanggalHariIni, $tanggalAbsen)) {
            $pesanError = 'Tanggal absen sudah berubah. Muat ulang halaman untuk memakai tanggal WITA hari ini.';
        } elseif ($idSiswaAbsen <= 0 || $siswaModel->findById($idSiswaAbsen) === null) {
            $pesanError = 'Silakan pilih siswa yang valid.';
        } elseif (!is_string($statusAbsen) || !in_array($statusAbsen, ['Hadir', 'Izin', 'Sakit', 'Alpha'], true)) {
            $pesanError = 'Status absensi tidak valid.';
        } elseif (!is_string($keteranganInput)) {
            $pesanError = 'Keterangan tidak valid.';
        } elseif ($jumlahKarakterKeterangan === false || $jumlahKarakterKeterangan > 255) {
            $pesanError = 'Keterangan maksimal 255 karakter.';
        } else {
            try {
                $presensiModel->saveForDate(
                    $idSiswaAbsen,
                    $tanggalHariIni,
                    $statusAbsen,
                    $keteranganAbsen !== '' ? $keteranganAbsen : null
                );
                $pesanSukses = 'Absensi siswa berhasil disimpan untuk ' . $tanggalHariIniTampilan . ' (WITA).';
            } catch (PDOException $e) {
                $pesanError = 'Gagal menyimpan data absensi.';
            }
        }
    } else {
        $postId = !empty($_POST['id_siswa']) ? (int) $_POST['id_siswa'] : null;

        if ($aksi === 'edit' && $postId !== null) {
            $editId = $postId;
        }

        $nisnInput    = trim($_POST['nisn'] ?? '');
        $namaInput    = trim($_POST['nama'] ?? '');
        $idKelasInput = trim($_POST['id_kelas'] ?? '');
        $alamatInput  = trim($_POST['alamat'] ?? '');

        if ($nisnInput === '') {
            $errors['nisn'] = 'NISN wajib diisi.';
        } elseif (!preg_match('/\A[0-9]{10}\z/', $nisnInput)) {
            $errors['nisn'] = 'NISN harus terdiri dari 10 digit angka.';
        } elseif ($siswaModel->existsByNisn($nisnInput, $aksi === 'edit' ? $postId : null)) {
            $errors['nisn'] = 'NISN sudah terdaftar. Silakan gunakan NISN lain.';
        }

        if ($namaInput === '') {
            $errors['nama'] = 'Nama siswa wajib diisi.';
        }

        $idKelasInt = (int) $idKelasInput;
        if ($idKelasInput === '' || $idKelasInt <= 0) {
            $errors['id_kelas'] = 'Kelas wajib dipilih.';
        } elseif ($kelasModel->findById($idKelasInt) === null) {
            $errors['id_kelas'] = 'Pilihan kelas tidak valid.';
        }

        $adaError = false;
        foreach ($errors as $err) {
            if ($err !== '') {
                $adaError = true;
                break;
            }
        }

        if (!$adaError) {
            try {
                if ($aksi === 'edit' && $postId !== null) {
                    $siswaModel->update($postId, $nisnInput, $namaInput, $idKelasInt, $alamatInput);
                    $pesanSukses = 'Data siswa berhasil diperbarui.';
                    $editId = null;
                    $nisnInput = '';
                    $namaInput = '';
                    $idKelasInput = '';
                    $alamatInput = '';
                } else {
                    $siswaModel->create($nisnInput, $namaInput, $idKelasInt, $alamatInput);
                    $pesanSukses = 'Data siswa berhasil ditambahkan.';
                    $nisnInput = '';
                    $namaInput = '';
                    $idKelasInput = '';
                    $alamatInput = '';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errors['nisn'] = 'NISN sudah terdaftar. Silakan gunakan NISN lain.';
                } else {
                    $pesanError = 'Terjadi kesalahan saat menyimpan data siswa.';
                }
            }
        }
    }
}

$cariKeyword = trim($_GET['cari'] ?? '');
$filterKelas = isset($_GET['kelas']) && $_GET['kelas'] !== '' ? (int) $_GET['kelas'] : null;
$semuaSiswa = $siswaModel->getAll();
$daftarSiswa = $siswaModel->search($cariKeyword, $filterKelas);
$daftarAbsenHariIni = $presensiModel->getByDate($tanggalHariIni);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Siswa - Presensi RFID</title>
    <link rel="stylesheet" href="assets/navbar.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fdf2f8;
            margin: 0;
            padding: 24px;
            color: #1f2937;
        }
        .container {
            max-width: 980px;
            margin: 0 auto;
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.1);
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }
        h2, h3 {
            margin-top: 0;
            color: #6d28d9;
        }
        .page-header h2 {
            margin-bottom: 0;
        }
        .form-group {
            margin-bottom: 14px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 14px;
        }
        input[type="text"],
        select,
        textarea {
            width: 100%;
            max-width: 420px;
            padding: 9px 12px;
            border: 1px solid #d8b4fe;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        button, .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            background: #8b5cf6;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            display: inline-block;
        }
        .btn-batal {
            background: #9ca3af;
            margin-left: 6px;
        }
        .btn-edit {
            background: #ec4899;
            padding: 6px 10px;
            font-size: 13px;
            margin-right: 4px;
        }
        .btn-hapus {
            background: #e11d48;
            padding: 6px 10px;
            font-size: 13px;
        }
        .error {
            color: #be123c;
            font-size: 13px;
            margin-top: 5px;
        }
        .alert-sukses {
            background: #dcfce7;
            color: #166534;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-error {
            background: #ffe4e6;
            color: #9f1239;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .attendance-section {
            padding: 18px;
            margin-bottom: 24px;
            border: 1px solid #e9d5ff;
            border-radius: 10px;
            background: #faf5ff;
        }
        .attendance-date {
            margin: -4px 0 16px;
            color: #5b21b6;
            font-weight: bold;
        }
        .search-box {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 22px 0 12px;
            align-items: center;
        }
        .search-box input[type="text"],
        .search-box select {
            max-width: 240px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        th {
            background: #f5f3ff;
            color: #4c1d95;
        }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<div class="container">
    <div class="page-header">
        <h2><?= $editId !== null ? 'Edit Siswa' : 'Kelola Siswa' ?></h2>
    </div>

    <?php if ($pesanSukses !== ''): ?>
        <div class="alert-sukses"><?= htmlspecialchars($pesanSukses, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($pesanError !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($pesanError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="attendance-section">
        <h3>Absensi Hari Ini</h3>
        <p class="attendance-date">
            <?= htmlspecialchars($tanggalHariIniTampilan, ENT_QUOTES, 'UTF-8') ?> (WITA / Samarinda)
        </p>

        <?php if (empty($semuaSiswa)): ?>
            <p>Tambahkan data siswa terlebih dahulu sebelum mencatat absensi.</p>
        <?php else: ?>
            <form method="POST" action="index.php">
                <input type="hidden" name="aksi" value="absen">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tanggal_absen" value="<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="id_siswa_absen">Siswa</label>
                    <select id="id_siswa_absen" name="id_siswa_absen" required>
                        <option value="">-- Pilih Siswa --</option>
                        <?php foreach ($semuaSiswa as $siswa): ?>
                            <option value="<?= (int) $siswa['id_siswa'] ?>">
                                <?= htmlspecialchars($siswa['nama'] . ' (' . $siswa['nisn'] . ') - ' . $siswa['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status_absen">Status</label>
                    <select id="status_absen" name="status_absen" required>
                        <option value="Hadir">Hadir</option>
                        <option value="Izin">Izin</option>
                        <option value="Sakit">Sakit</option>
                        <option value="Alpha">Alpha</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="keterangan_absen">Keterangan (Opsional)</label>
                    <textarea id="keterangan_absen" name="keterangan_absen" rows="2" maxlength="255"></textarea>
                </div>

                <button type="submit">Simpan Absensi</button>
            </form>
        <?php endif; ?>

        <h3>Rekap Absensi Hari Ini</h3>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarAbsenHariIni)): ?>
                    <tr><td colspan="6">Belum ada absensi untuk hari ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($daftarAbsenHariIni as $index => $absen): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($absen['nisn'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($absen['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($absen['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($absen['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($absen['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <form method="POST" action="index.php">
        <input type="hidden" name="aksi" value="<?= $editId !== null ? 'edit' : 'tambah' ?>">
        <?php if ($editId !== null): ?>
            <input type="hidden" name="id_siswa" value="<?= (int) $editId ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="nisn">NISN (10 digit) *</label>
            <input
                type="text"
                id="nisn"
                name="nisn"
                placeholder="Contoh: 0081234567"
                value="<?= htmlspecialchars($nisnInput, ENT_QUOTES, 'UTF-8') ?>"
            >
            <?php if ($errors['nisn'] !== ''): ?>
                <div class="error"><?= htmlspecialchars($errors['nisn'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="nama">Nama Lengkap *</label>
            <input
                type="text"
                id="nama"
                name="nama"
                placeholder="Masukkan nama siswa"
                value="<?= htmlspecialchars($namaInput, ENT_QUOTES, 'UTF-8') ?>"
            >
            <?php if ($errors['nama'] !== ''): ?>
                <div class="error"><?= htmlspecialchars($errors['nama'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="id_kelas">Kelas *</label>
            <select id="id_kelas" name="id_kelas">
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($daftarKelas as $kelas): ?>
                    <option
                        value="<?= (int) $kelas['id_kelas'] ?>"
                        <?= ($idKelasInput === (string) $kelas['id_kelas']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($errors['id_kelas'] !== ''): ?>
                <div class="error"><?= htmlspecialchars($errors['id_kelas'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="alamat">Alamat (Opsional)</label>
            <textarea
                id="alamat"
                name="alamat"
                rows="3"
                placeholder="Boleh dikosongkan"
            ><?= htmlspecialchars($alamatInput, ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php if ($errors['alamat'] !== ''): ?>
                <div class="error"><?= htmlspecialchars($errors['alamat'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <button type="submit"><?= $editId !== null ? 'Simpan Perubahan' : 'Tambah Siswa' ?></button>
        <?php if ($editId !== null): ?>
            <a href="index.php" class="btn btn-batal">Batal</a>
        <?php endif; ?>
    </form>

    <form method="GET" action="index.php" class="search-box">
        <input
            type="text"
            name="cari"
            placeholder="Cari nama siswa..."
            value="<?= htmlspecialchars($cariKeyword, ENT_QUOTES, 'UTF-8') ?>"
        >
        <select name="kelas">
            <option value="">Semua Kelas</option>
            <?php foreach ($daftarKelas as $kelas): ?>
                <option
                    value="<?= (int) $kelas['id_kelas'] ?>"
                    <?= ($filterKelas === (int) $kelas['id_kelas']) ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Cari</button>
        <?php if ($cariKeyword !== '' || $filterKelas !== null): ?>
            <a href="index.php" class="btn btn-batal">Reset</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th>NISN</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Alamat</th>
                <th style="width: 150px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($daftarSiswa)): ?>
                <tr>
                    <td colspan="6">Data siswa tidak ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftarSiswa as $i => $siswa): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($siswa['nisn'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($siswa['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($siswa['alamat'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="index.php?edit=<?= (int) $siswa['id_siswa'] ?>" class="btn btn-edit">Edit</a>
                            <a
                                href="index.php?hapus=<?= (int) $siswa['id_siswa'] ?>"
                                class="btn btn-hapus"
                                onclick="return confirm('Yakin ingin menghapus data siswa ini?')"
                            >Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>