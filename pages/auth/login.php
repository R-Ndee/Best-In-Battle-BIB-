<?php
require_once '../../config/database.php';
session_start();

// Kalau sudah login, redirect
if (isset($_SESSION['user_id'])) {
header('Location: ' . ($_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../member/dashboard.php'));    exit;
}

// Proses POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Email dan password wajib diisi.';
        header('Location: login.php');
        exit;
    }

    $email_safe = mysqli_real_escape_string($conn, $email);
    $query      = "SELECT * FROM users WHERE email = '$email_safe' LIMIT 1";
    $result     = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['success']  = 'Selamat datang, ' . $user['username'] . '!';
            header('Location: ' . ($user['role'] === 'admin' ? '../admin/dashboard.php' : '../member/dashboard.php'));
            exit;
        }
    }

    $_SESSION['error'] = 'Email atau password salah.';
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="page-fade">

<?php include '../components/navbar.php'; ?>

<div class="auth-page">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <div class="auth-logo-icon">BIB</div>
            <h1>Selamat Datang</h1>
            <p>Masuk untuk lanjutkan ke dashboard</p>
        </div>

        <?php include '../components/alert.php'; ?>

        <!-- Tab Login / Register -->
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="switchTab('login')">Masuk</button>
            <button class="auth-tab" onclick="window.location='register.php'">Daftar</button>
        </div>

        <!-- Form Login -->
        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <div class="form-control-icon">
                    <span class="icon">✉️</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="player@bib.com"
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
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-password" onclick="togglePass()" aria-label="Tampilkan password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.5rem;">
                Masuk ke Dashboard →
            </button>
        </form>

        <!-- Divider admin -->
        <div class="auth-divider">Akses Administrator</div>

        <a href="<?= $base ?>pages/auth/login_admin.php" class="admin-login-btn">
            🛡️ Login sebagai Admin
        </a>

    </div>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
<script>
function togglePass() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
