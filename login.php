<?php
session_start();

if (!empty($_SESSION['id']) && !empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/database.php';

$username = '';
$errors = [
    'username' => '',
    'password' => '',
    'login'    => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    }
    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    }

    if ($errors['username'] === '' && $errors['password'] === '') {
        try {
            $database = new Database();
            $statement = $database->getConnection()->prepare(
                'SELECT id, username, password FROM users WHERE username = :username LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $dataUser = $statement->fetch();

            if ($dataUser && password_verify($password, $dataUser['password'])) {
                session_regenerate_id(true);
                $_SESSION['id'] = (int) $dataUser['id'];
                $_SESSION['username'] = $dataUser['username'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            }

            $errors['login'] = 'Username atau password salah.';
        } catch (Throwable $exception) {
            $errors['login'] = 'Login gagal. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 24px;
            background:
                radial-gradient(circle at 12% 15%, rgba(236, 72, 153, 0.18), transparent 30%),
                radial-gradient(circle at 88% 85%, rgba(124, 58, 237, 0.2), transparent 32%),
                linear-gradient(135deg, #fdf2f8, #f5f3ff 55%, #ede9fe);
        }
        .box {
            position: relative;
            width: min(100%, 400px);
            padding: 34px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 18px 50px rgba(91, 33, 182, 0.15);
        }
        .box::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 6px;
            background: linear-gradient(90deg, #ec4899, #8b5cf6, #6366f1);
            content: "";
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 26px;
            color: #6d28d9;
            font-size: 14px;
            font-weight: bold;
        }
        .brand-mark {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: #fff;
            letter-spacing: 0.5px;
        }
        .brand small {
            display: block;
            margin-top: 3px;
            color: #9ca3af;
            font-size: 11px;
            font-weight: normal;
        }
        h1 {
            margin: 0 0 7px;
            color: #1f2937;
            font-size: 25px;
        }
        .intro {
            margin: 0 0 24px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
        }
        .field {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 7px;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #ddd6fe;
            border-radius: 10px;
            outline: none;
            background: #fff;
            color: #1f2937;
            font: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.13);
        }
        button {
            width: 100%;
            margin-top: 4px;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(100deg, #ec4899, #8b5cf6);
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(124, 58, 237, 0.25);
        }
        .alert-error {
            margin-bottom: 16px;
            padding: 11px 13px;
            border: 1px solid #fecdd3;
            border-radius: 10px;
            background: #fff1f2;
            color: #be123c;
            font-size: 13px;
        }
        .error {
            color: #be123c;
            font-size: 12px;
            margin-top: 4px;
        }
        .footer-note {
            margin: 22px 0 0;
            color: #9ca3af;
            font-size: 12px;
            text-align: center;
        }
        @media (max-width: 420px) {
            .box {
                padding: 28px 22px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <div class="box">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">RF</div>
            <div>
                Presensi RFID
                <small>SMK TI Airlangga</small>
            </div>
        </div>

        <h1>Login Admin</h1>
        <p class="intro">Masuk untuk mengelola data siswa dan presensi.</p>

        <?php if ($errors['login'] !== ''): ?>
            <div class="alert-error" role="alert"><?= htmlspecialchars($errors['login'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="username"
                    required
                    autofocus
                >
                <?php if ($errors['username'] !== ''): ?>
                    <div class="error" role="alert"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
                <?php if ($errors['password'] !== ''): ?>
                    <div class="error" role="alert"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <button type="submit">Masuk</button>
        </form>

        <p class="footer-note">Sistem Informasi Presensi Siswa</p>
    </div>
</body>
</html>