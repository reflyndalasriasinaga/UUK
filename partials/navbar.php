<?php

$halamanAktif = basename($_SERVER['PHP_SELF'] ?? '');
$usernameAdmin = $_SESSION['username'];
$menuNavigasi = [
    'kelola_kelas.php' => 'Kelola Kelas',
    'index.php' => 'Kelola Siswa',
    'simulasi_tap.php' => 'Simulasi Tap Kartu',
    'rekap_presensi.php' => 'Rekap Presensi',
];
?>
<nav class="top-navbar" aria-label="Navigasi utama">
    <a href="index.php" class="nav-brand">
        Presensi RFID
        <span class="sub-brand">SMK TI Airlangga</span>
    </a>

    <div class="nav-links">
        <?php foreach ($menuNavigasi as $file => $label): ?>
            <?php
            $aktif = $halamanAktif === $file
                || ($file === 'index.php' && $halamanAktif === 'kelola_siswa.php');
            ?>
            <a
                href="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>"
                class="nav-link<?= $aktif ? ' active' : '' ?>"
                <?= $aktif ? 'aria-current="page"' : '' ?>
            >
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="nav-user">
        <span>Halo, <strong><?= htmlspecialchars($usernameAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
        <form method="POST" action="logout.php" class="nav-logout-form" onsubmit="return confirm('Yakin ingin logout?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-logout-nav">Logout</button>
        </form>
    </div>
</nav>
