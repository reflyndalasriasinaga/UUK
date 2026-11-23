<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Presensi.php';

$zonaWaktu = new DateTimeZone('Asia/Makassar');
$tanggalHariIni = (new DateTimeImmutable('today', $zonaWaktu))->format('Y-m-d');
$allowedStatus = ['Hadir', 'Izin', 'Sakit', 'Alpha'];

$kelasModel = new Kelas();
$presensiModel = new Presensi();
$daftarKelas = $kelasModel->getAll();

$pesanSukses = $_SESSION['flash_sukses'] ?? '';
unset($_SESSION['flash_sukses']);
$pesanError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    $idInput = $_POST['id_presensi'] ?? '';
    $idPresensi = is_string($idInput) && ctype_digit($idInput) ? (int) $idInput : 0;

    if ($aksi !== 'hapus_log') {
        $pesanError = 'Aksi tidak valid.';
    } elseif (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $pesanError = 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($idPresensi <= 0) {
        $pesanError = 'ID log presensi tidak valid.';
    } else {
        try {
            if ($presensiModel->deleteById($idPresensi)) {
                $_SESSION['flash_sukses'] = 'Log presensi berhasil dihapus.';
                $query = http_build_query($_GET);
                header('Location: rekap_presensi.php' . ($query !== '' ? '?' . $query : ''));
                exit;
            }

            $pesanError = 'Log presensi tidak ditemukan atau sudah dihapus.';
        } catch (PDOException $e) {
            $pesanError = 'Terjadi kesalahan saat menghapus log presensi.';
        }
    }
}

$filterTanggalInput = $_GET['tanggal'] ?? $tanggalHariIni;
$filterKelasInput = $_GET['kelas'] ?? '';
$filterStatusInput = $_GET['status'] ?? '';
$cariInput = $_GET['cari'] ?? '';
$filterTanggal = $tanggalHariIni;
$filterKelas = null;
$filterStatus = '';
$cariKeyword = '';
$filterValid = true;

if (!is_string($filterTanggalInput)) {
    $filterValid = false;
    $pesanError = $pesanError ?: 'Format tanggal filter tidak valid.';
} else {
    $filterTanggalInput = trim($filterTanggalInput);
    if ($filterTanggalInput === '') {
        $filterTanggal = '';
    } elseif (
        preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $filterTanggalInput) === 1
        && checkdate(
            (int) substr($filterTanggalInput, 5, 2),
            (int) substr($filterTanggalInput, 8, 2),
            (int) substr($filterTanggalInput, 0, 4)
        )
    ) {
        $filterTanggal = $filterTanggalInput;
    } else {
        $filterValid = false;
        $pesanError = $pesanError ?: 'Tanggal filter tidak valid.';
    }
}

if (!is_string($filterKelasInput)) {
    $filterValid = false;
    $pesanError = $pesanError ?: 'Filter kelas tidak valid.';
} elseif (trim($filterKelasInput) !== '') {
    $idKelasFilter = trim($filterKelasInput);
    if (
        !ctype_digit($idKelasFilter)
        || (int) $idKelasFilter <= 0
        || $kelasModel->findById((int) $idKelasFilter) === null
    ) {
        $filterValid = false;
        $pesanError = $pesanError ?: 'Kelas yang dipilih tidak valid.';
    } else {
        $filterKelas = (int) $idKelasFilter;
    }
}

if (!is_string($filterStatusInput)) {
    $filterValid = false;
    $pesanError = $pesanError ?: 'Filter status tidak valid.';
} elseif (trim($filterStatusInput) !== '') {
    $filterStatusInput = trim($filterStatusInput);
    if (!in_array($filterStatusInput, $allowedStatus, true)) {
        $filterValid = false;
        $pesanError = $pesanError ?: 'Status presensi yang dipilih tidak valid.';
    } else {
        $filterStatus = $filterStatusInput;
    }
}

if (!is_string($cariInput)) {
    $filterValid = false;
    $pesanError = $pesanError ?: 'Kata pencarian tidak valid.';
} else {
    $cariKeyword = trim($cariInput);
}

$dataRekap = $filterValid
    ? $presensiModel->getRekap(
        $filterTanggal !== '' ? $filterTanggal : null,
        $filterKelas,
        $filterStatus !== '' ? $filterStatus : null,
        $cariKeyword
    )
    : [];

$parameterSemuaTanggal = $_GET;
$parameterSemuaTanggal['tanggal'] = '';
$urlSemuaTanggal = 'rekap_presensi.php?' . http_build_query($parameterSemuaTanggal);
$parameterHariIni = $_GET;
$parameterHariIni['tanggal'] = $tanggalHariIni;
$urlHariIni = 'rekap_presensi.php?' . http_build_query($parameterHariIni);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Presensi - Presensi RFID</title>
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
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }
        h2 {
            margin-top: 0;
            margin-bottom: 0;
            color: #6d28d9;
        }
        .meta {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
        }
        .filter-box {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
            align-items: center;
        }
        input[type="text"],
        input[type="date"],
        select {
            padding: 8px 11px;
            border: 1px solid #d8b4fe;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
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
        .btn-reset {
            background: #9ca3af;
        }
        .btn-hapus {
            background: #e11d48;
            padding: 6px 10px;
            font-size: 13px;
        }
        .alert-sukses {
            background: #dcfce7;
            color: #166534;
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
        .badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-Hadir { background: #dcfce7; color: #166534; }
        .badge-Izin  { background: #fef3c7; color: #92400e; }
        .badge-Sakit { background: #e0e7ff; color: #3730a3; }
        .badge-Alpha { background: #ffe4e6; color: #9f1239; }
        .badge-unknown { background: #e5e7eb; color: #374151; }
        .inline-form {
            display: inline;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            min-width: 900px;
        }
        @media (max-width: 700px) {
            body {
                padding: 12px;
            }
            .container {
                padding: 16px;
            }
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<div class="container">
    <div class="page-header">
        <h2>Rekap Presensi</h2>
    </div>
    <div class="meta">Zona waktu: WITA (Asia/Makassar) &bull; Total log tampil: <strong><?= count($dataRekap) ?></strong></div>

    <?php if ($pesanSukses !== ''): ?>
        <div class="alert-sukses"><?= htmlspecialchars($pesanSukses, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($pesanError !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($pesanError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="GET" action="rekap_presensi.php" class="filter-box">
        <input
            type="date"
            name="tanggal"
            value="<?= htmlspecialchars($filterTanggal, ENT_QUOTES, 'UTF-8') ?>"
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
        <select name="status">
            <option value="">Semua Status</option>
            <?php foreach ($allowedStatus as $st): ?>
                <option value="<?= $st ?>" <?= ($filterStatus === $st) ? 'selected' : '' ?>>
                    <?= $st ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input
            type="text"
            name="cari"
            placeholder="Cari nama atau NISN..."
            value="<?= htmlspecialchars($cariKeyword, ENT_QUOTES, 'UTF-8') ?>"
        >
        <button type="submit">Filter</button>
        <a href="<?= htmlspecialchars($urlSemuaTanggal, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-reset">Semua Tanggal</a>
        <a href="<?= htmlspecialchars($urlHariIni, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-reset">Hari Ini</a>
    </form>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th>Tanggal</th>
                <th>Waktu Catat</th>
                <th>NISN</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Status</th>
                <th>Keterangan</th>
                <th style="width: 90px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataRekap)): ?>
                <tr>
                    <td colspan="9">Belum ada log presensi sesuai filter.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dataRekap as $i => $row): ?>
                    <?php $kelasBadge = in_array($row['status'], $allowedStatus, true) ? 'badge-' . $row['status'] : 'badge-unknown'; ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['dicatat_pada'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['nisn'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge <?= $kelasBadge ?>">
                                <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form
                                method="POST"
                                action="rekap_presensi.php?<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-form"
                                onsubmit="return confirm('Yakin ingin menghapus log presensi ini?')"
                            >
                                <input type="hidden" name="aksi" value="hapus_log">
                                <input type="hidden" name="id_presensi" value="<?= (int) $row['id_presensi'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-hapus">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>