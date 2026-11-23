<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/models/Kelas.php';

if (!class_exists('Kelas')) {
    throw new RuntimeException('Class Kelas tidak ditemukan pada models/Kelas.php.');
}

$kelasModel = new Kelas();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$namaKelasInput = '';
$errorNamaKelas = '';
$pesanSukses = '';
$pesanError = '';

if ($editId !== null && $editId > 0) {
    $dataEdit = $kelasModel->findById($editId);
    if ($dataEdit) {
        $namaKelasInput = $dataEdit['nama_kelas'];
    } else {
        $editId = null;
    }
}

if (isset($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    if ($hapusId > 0) {
        if ($kelasModel->hasStudents($hapusId)) {
            $pesanError = 'Kelas tidak dapat dihapus karena masih memiliki siswa. Pindahkan siswa ke kelas lain terlebih dahulu.';
        } else {
            try {
                if ($kelasModel->delete($hapusId)) {
                    $pesanSukses = 'Data kelas berhasil dihapus.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1451) {
                    $pesanError = 'Kelas tidak dapat dihapus karena masih digunakan oleh data siswa. Pindahkan siswa ke kelas lain terlebih dahulu.';
                } else {
                    $pesanError = 'Gagal menghapus kelas. Silakan coba lagi.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? 'tambah';
    $namaKelasInput = trim($_POST['nama_kelas'] ?? '');
    $postId = !empty($_POST['id']) ? (int) $_POST['id'] : null;

    if ($aksi === 'edit' && $postId !== null) {
        $editId = $postId;
    }

    if ($namaKelasInput === '') {
        $errorNamaKelas = 'Nama kelas wajib diisi.';
    } elseif ($kelasModel->existsByNama($namaKelasInput, $aksi === 'edit' ? $postId : null)) {
        $errorNamaKelas = 'Nama kelas sudah ada.';
    }

    if ($errorNamaKelas === '') {
        try {
            if ($aksi === 'edit' && $postId !== null) {
                $kelasModel->update($postId, $namaKelasInput);
                $pesanSukses = 'Data kelas berhasil diperbarui.';
                $editId = null;
                $namaKelasInput = '';
            } else {
                $kelasModel->create($namaKelasInput);
                $pesanSukses = 'Data kelas berhasil ditambahkan.';
                $namaKelasInput = '';
            }
        } catch (PDOException $e) {
            $pesanError = 'Gagal menyimpan data kelas.';
        }
    }
}

$daftarKelas = $kelasModel->getAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Kelas - Presensi RFID</title>
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
            max-width: 820px;
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
        .form-group {
            margin-bottom: 14px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 14px;
        }
        input[type="text"] {
            width: 100%;
            max-width: 360px;
            padding: 9px 12px;
            border: 1px solid #d8b4fe;
            border-radius: 8px;
            box-sizing: border-box;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        <h2><?= $editId !== null ? 'Edit Kelas' : 'Kelola Kelas' ?></h2>
    </div>

    <?php if ($pesanSukses !== ''): ?>
        <div class="alert-sukses"><?= htmlspecialchars($pesanSukses, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($pesanError !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($pesanError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="kelola_kelas.php">
        <input type="hidden" name="aksi" value="<?= $editId !== null ? 'edit' : 'tambah' ?>">
        <?php if ($editId !== null): ?>
            <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="nama_kelas">Nama Kelas</label>
            <input
                type="text"
                id="nama_kelas"
                name="nama_kelas"
                placeholder="Contoh: XI PPLG"
                value="<?= htmlspecialchars($namaKelasInput, ENT_QUOTES, 'UTF-8') ?>"
            >
            <?php if ($errorNamaKelas !== ''): ?>
                <div class="error"><?= htmlspecialchars($errorNamaKelas, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <button type="submit"><?= $editId !== null ? 'Simpan Perubahan' : 'Tambah Kelas' ?></button>
        <?php if ($editId !== null): ?>
            <a href="kelola_kelas.php" class="btn btn-batal">Batal</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th style="width: 60px;">No</th>
                <th>Nama Kelas</th>
                <th style="width: 170px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($daftarKelas)): ?>
                <tr>
                    <td colspan="3">Belum ada data kelas.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftarKelas as $index => $kelas): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="kelola_kelas.php?edit=<?= (int) $kelas['id_kelas'] ?>" class="btn btn-edit">Edit</a>
                            <a
                                href="kelola_kelas.php?hapus=<?= (int) $kelas['id_kelas'] ?>"
                                class="btn btn-hapus"
                                onclick="return confirm('Yakin ingin menghapus kelas ini?')"
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