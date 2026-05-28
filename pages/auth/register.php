<?php
require_once '../../config/database.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/member/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username']         ?? '');
    $email           = trim($_POST['email']            ?? '');
    $password        = trim($_POST['password']         ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');

    // Validasi
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'Semua field wajib diisi.';
        header('Location: register.php');
        exit;
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        $_SESSION['error'] = 'Username harus antara 3-50 karakter.';
        header('Location: register.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Format email tidak valid.';
        header('Location: register.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password minimal 6 karakter.';
        header('Location: register.php');
        exit;
    }

    if ($password !== $password_confirm) {
        $_SESSION['error'] = 'Konfirmasi password tidak cocok.';
        header('Location: register.php');
        exit;
    }

    // Cek duplikat
    $username_safe = mysqli_real_escape_string($conn, $username);
    $email_safe    = mysqli_real_escape_string($conn, $email);

    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email_safe' OR username = '$username_safe' LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = 'Email atau username sudah terdaftar.';
        header('Location: register.php');
        exit;
    }

    // Insert
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $insert = "INSERT INTO users (username, email, password, role) VALUES ('$username_safe', '$email_safe', '$hashed', 'member')";

    if (mysqli_query($conn, $insert)) {
        $new_id = mysqli_insert_id($conn);
        $_SESSION['user_id']  = $new_id;
        $_SESSION['username'] = $username;
        $_SESSION['role']     = 'member';
        $_SESSION['success']  = 'Akun berhasil dibuat. Selamat datang, ' . $username . '!';
        header('Location: /pages/member/dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = 'Registrasi gagal. Silakan coba lagi.';
        header('Location: register.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="page-fade">

<?php include '../components/navbar.php'; ?>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-logo">
            <div class="auth-logo-icon">BIB</div>
            <h1>Buat Akun Baru</h1>
            <p>Bergabung dan mulai kompetisi pertamamu</p>
        </div>

        <?php include '../components/alert.php'; ?>

        <div class="auth-tabs">
            <button class="auth-tab" onclick="window.location='login.php'">Masuk</button>
            <button class="auth-tab active">Daftar</button>
        </div>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="form-control-icon">
                    <span class="icon">👤</span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="nama_pengguna"
                        required
                        minlength="3"
                        maxlength="50"
                        autocomplete="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                </div>
                <div class="form-hint">3-50 karakter, tanpa spasi</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <div class="form-control-icon">
                    <span class="icon">✉️</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="kamu@email.com"
                        required
                        autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="form-control-icon input-password-wrap">
                    <span class="icon">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Min. 6 karakter"
                        required
                        minlength="6"
                        autocomplete="new-password"
                    >
                    <button type="button" class="toggle-password" onclick="togglePass('password')" aria-label="Tampilkan password">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Konfirmasi Password</label>
                <div class="form-control-icon input-password-wrap">
                    <span class="icon">🔒</span>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        class="form-control"
                        placeholder="Ulangi password"
                        required
                        autocomplete="new-password"
                    >
                    <button type="button" class="toggle-password" onclick="togglePass('password_confirm')" aria-label="Tampilkan password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.5rem;">
                Buat Akun Member →
            </button>
        </form>

        <p style="text-align:center;margin-top:1.25rem;font-size:0.82rem;color:var(--text-dim);">
            Sudah punya akun?
            <a href="/pages/auth/login.php" style="color:var(--accent);font-weight:600;">Masuk di sini</a>
        </p>
    </div>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
<script>
function togglePass(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
