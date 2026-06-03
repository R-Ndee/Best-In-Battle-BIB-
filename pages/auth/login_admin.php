<?php
require_once '../../config/database.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/admin/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $email_safe = mysqli_real_escape_string($conn, $email);
    $query      = "SELECT * FROM users WHERE email = '$email_safe' AND role = 'admin' LIMIT 1";
    $result     = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = 'admin';
            $_SESSION['success']  = 'Login admin berhasil.';
            header('Location: /pages/admin/dashboard.php');
            exit;
        }
    }

    $_SESSION['error'] = 'Kredensial admin tidak valid.';
    header('Location: login_admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="auth-logo-icon" style="background:linear-gradient(135deg,var(--accent-2),#7c3aed);">🛡️</div>
            <h1>Admin Login</h1>
            <p>Akses khusus administrator sistem</p>
        </div>
        <?php include '../components/alert.php'; ?>
        <form method="POST" action="login_admin.php">
            <div class="form-group">
                <label class="form-label">Email Admin</label>
                <div class="form-control-icon">
                    <span class="icon">✉️</span>
                    <input type="email" name="email" class="form-control" placeholder="admin@bib.com" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="form-control-icon">
                    <span class="icon">🔒</span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn btn-full btn-lg" style="background:var(--accent-2);color:#0C0F0E;">
                🛡️ Masuk sebagai Admin
            </button>
        </form>
        <div style="text-align:center;margin-top:1rem;">
            <a href="<?= $base ?>pages/auth/login.php" style="font-size:0.82rem;color:var(--text-muted);">← Kembali ke Login Biasa</a>
        </div>
    </div>
</div>
<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
</body>
</html>
