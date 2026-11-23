<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Presensi.php';

$kelasModel = new Kelas();
$presensiModel = new Presensi();
$daftarKelas = $kelasModel->getAll();
$tanggalHariIni = (new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d');
$allowedStatus = ['Hadir', 'Izin', 'Sakit', 'Alpha'];

$nisnInput = '';
$statusInput = 'Hadir';
$keteranganInput = '';
$errorNisn = '';
$pesanSukses = '';
$pesanInfo = '';
$pesanError = '';
$infoTap = null;
$pesanFilterError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisnRaw = $_POST['nisn'] ?? '';
    $nisnInput = is_string($nisnRaw) ? trim($nisnRaw) : '';
    $statusInput = $_POST['status'] ?? 'Hadir';
    $keteranganRaw = $_POST['keterangan'] ?? '';
    $keteranganInput = is_string($keteranganRaw) ? trim($keteranganRaw) : '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $pesanError = 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!is_string($nisnRaw) || $nisnInput === '') {
        $errorNisn = 'NISN wajib diisi / di-tap.';
    } elseif (!preg_match('/\A[0-9]{10}\z/', $nisnInput)) {
        $errorNisn = 'NISN harus terdiri dari 10 digit angka.';
    } elseif (!is_string($statusInput) || !in_array($statusInput, $allowedStatus, true)) {
        $pesanError = 'Status presensi tidak valid.';
    } elseif (!is_string($keteranganRaw)) {
        $pesanError = 'Keterangan tidak valid.';
    } elseif (mb_strlen($keteranganInput) > 255) {
        $pesanError = 'Keterangan maksimal 255 karakter.';
    } else {
        $keteranganUntukDisimpan = $keteranganInput === '' ? null : $keteranganInput;

        try {
            $hasil = $presensiModel->tapByNisn(
                $nisnInput,
                $tanggalHariIni,
                $statusInput,
                $keteranganUntukDisimpan
            );

            if ($hasil['type'] === 'already_absent') {
                $pesanInfo = $hasil['message'];
                $infoTap = $hasil;
            } elseif (!$hasil['ok']) {
                $pesanError = $hasil['message'];
            } else {
                $pesanSukses = $hasil['message'];
                $infoTap = $hasil;
                $nisnInput = '';
                $statusInput = 'Hadir';
                $keteranganInput = '';
            }
        } catch (PDOException $e) {
            $pesanError = 'Terjadi kesalahan saat memproses presensi.';
        }
    }
}

$filterKelasInput = $_GET['kelas'] ?? '';
$filterStatusInput = $_GET['filter_status'] ?? '';
$cariInput = $_GET['cari'] ?? '';
$filterKelas = null;
$filterStatus = '';
$cariKeyword = '';
$filterValid = true;

if (!is_string($filterKelasInput)) {
    $filterValid = false;
    $pesanFilterError = 'Filter kelas tidak valid.';
} elseif (trim($filterKelasInput) !== '') {
    $idKelasFilter = trim($filterKelasInput);
    if (
        !ctype_digit($idKelasFilter)
        || (int) $idKelasFilter <= 0
        || $kelasModel->findById((int) $idKelasFilter) === null
    ) {
        $filterValid = false;
        $pesanFilterError = 'Kelas yang dipilih tidak valid.';
    } else {
        $filterKelas = (int) $idKelasFilter;
    }
}

if (!is_string($filterStatusInput)) {
    $filterValid = false;
    $pesanFilterError = $pesanFilterError ?: 'Filter status tidak valid.';
} elseif (trim($filterStatusInput) !== '') {
    $filterStatusInput = trim($filterStatusInput);
    if (!in_array($filterStatusInput, $allowedStatus, true)) {
        $filterValid = false;
        $pesanFilterError = $pesanFilterError ?: 'Status presensi yang dipilih tidak valid.';
    } else {
        $filterStatus = $filterStatusInput;
    }
}

if (!is_string($cariInput)) {
    $filterValid = false;
    $pesanFilterError = $pesanFilterError ?: 'Kata pencarian tidak valid.';
} else {
    $cariKeyword = trim($cariInput);
}

$dataPresensiHariIni = $filterValid
    ? $presensiModel->getRekap($tanggalHariIni, $filterKelas, $filterStatus ?: null, $cariKeyword)
    : [];
$queryTap = http_build_query($_GET);
$actionTap = 'simulasi_tap.php' . ($queryTap !== '' ? '?' . $queryTap : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulasi Tap Kartu - Presensi RFID</title>
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
            max-width: 1020px;
            margin: 0 auto;
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.1);
        }
        h2 {
            margin-top: 0;
            color: #6d28d9;
        }
        .meta {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
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
        select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d8b4fe;
            border-radius: 8px;
            box-sizing: border-box;
        }
        button {
            padding: 9px 16px;
            border: none;
            border-radius: 8px;
            background: #8b5cf6;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
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
            margin-bottom: 14px;
            font-size: 14px;
        }
        .alert-info {
            background: #fef3c7;
            color: #92400e;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 14px;
        }
        .alert-error {
            background: #ffe4e6;
            color: #9f1239;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 14px;
        }
        .card-hasil {
            margin-top: 18px;
            padding: 14px;
            border-radius: 10px;
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
            font-size: 14px;
        }
        .tap-panel {
            max-width: 640px;
            margin-bottom: 28px;
        }
        .filter-section {
            padding-top: 20px;
            border-top: 1px solid #e9d5ff;
        }
        .filter-section h3 {
            margin: 0 0 6px;
            color: #6d28d9;
        }
        .filter-help {
            margin: 0 0 14px;
            color: #6b7280;
            font-size: 13px;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 14px;
        }
        .filter-form input[type="text"],
        .filter-form select {
            width: auto;
            min-width: 150px;
            flex: 1 1 180px;
        }
        .filter-reset {
            color: #6b7280;
            font-size: 14px;
            text-decoration: none;
        }
        .filter-reset:hover {
            text-decoration: underline;
        }
        .attendance-table-wrap {
            overflow-x: auto;
        }
        .attendance-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }
        .attendance-table th,
        .attendance-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
        }
        .attendance-table th {
            background: #f5f3ff;
            color: #4c1d95;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-Hadir { background: #dcfce7; color: #166534; }
        .badge-Izin { background: #fef3c7; color: #92400e; }
        .badge-Sakit { background: #e0e7ff; color: #3730a3; }
        .badge-Alpha { background: #ffe4e6; color: #9f1239; }
        .badge-unknown { background: #e5e7eb; color: #374151; }
        @media (max-width: 720px) {
            body {
                padding: 12px;
            }
            .container {
                padding: 16px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<div class="container">
    <div class="tap-panel">
    <h2>Simulasi Tap Kartu RFID</h2>
    <div class="meta">Tanggal WITA: <strong><?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?></strong></div>

    <?php if ($pesanSukses !== ''): ?>
        <div class="alert-sukses"><?= htmlspecialchars($pesanSukses, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($pesanInfo !== ''): ?>
        <div class="alert-info"><?= htmlspecialchars($pesanInfo, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($pesanError !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($pesanError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($actionTap, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="nisn">NISN Kartu</label>
            <input
                type="text"
                id="nisn"
                name="nisn"
                inputmode="numeric"
                maxlength="10"
                pattern="[0-9]{10}"
                required
                autofocus
                placeholder="Masukkan atau scan 10 digit NISN"
                value="<?= htmlspecialchars($nisnInput, ENT_QUOTES, 'UTF-8') ?>"
            >
            <?php if ($errorNisn !== ''): ?>
                <div class="error"><?= htmlspecialchars($errorNisn, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpha'] as $status): ?>
                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $statusInput === $status ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="keterangan">Keterangan (Opsional)</label>
            <input
                type="text"
                id="keterangan"
                name="keterangan"
                maxlength="255"
                placeholder="Contoh: Tap gerbang pagi"
                value="<?= htmlspecialchars($keteranganInput, ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <button type="submit">Tap Kartu</button>
    </form>

    <?php if ($infoTap !== null): ?>
        <div class="card-hasil">
            <div><strong>Nama:</strong> <?= htmlspecialchars($infoTap['siswa']['nama'], ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>NISN:</strong> <?= htmlspecialchars($infoTap['siswa']['nisn'], ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Kelas:</strong> <?= htmlspecialchars($infoTap['siswa']['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Tanggal:</strong> <?= htmlspecialchars($infoTap['tanggal'], ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars($infoTap['status'], ENT_QUOTES, 'UTF-8') ?></div>
            <div><strong>Keterangan:</strong> <?= htmlspecialchars($infoTap['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>
    </div>

    <section class="filter-section">
        <h3>Presensi Hari Ini</h3>
        <p class="filter-help">
            <?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?> (WITA/Samarinda)
        </p>

        <?php if ($pesanFilterError !== ''): ?>
            <div class="alert-error"><?= htmlspecialchars($pesanFilterError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="GET" action="simulasi_tap.php" class="filter-form">
            <input
                type="text"
                name="cari"
                placeholder="Cari nama atau NISN..."
                value="<?= htmlspecialchars($cariKeyword, ENT_QUOTES, 'UTF-8') ?>"
            >
            <select name="kelas">
                <option value="">Semua Kelas</option>
                <?php foreach ($daftarKelas as $kelas): ?>
                    <option
                        value="<?= (int) $kelas['id_kelas'] ?>"
                        <?= $filterKelas === (int) $kelas['id_kelas'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="filter_status">
                <option value="">Semua Status</option>
                <?php foreach ($allowedStatus as $status): ?>
                    <option
                        value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $filterStatus === $status ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Cari / Filter</button>
            <?php if ($cariKeyword !== '' || $filterKelas !== null || $filterStatus !== ''): ?>
                <a href="simulasi_tap.php" class="filter-reset">Reset</a>
            <?php endif; ?>
        </form>

        <div class="attendance-table-wrap">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Waktu Catat</th>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataPresensiHariIni)): ?>
                        <tr>
                            <td colspan="7">Tidak ada presensi yang cocok dengan pencarian/filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dataPresensiHariIni as $index => $row): ?>
                            <?php $badgeClass = in_array($row['status'], $allowedStatus, true) ? 'badge-' . $row['status'] : 'badge-unknown'; ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($row['dicatat_pada'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['nisn'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>