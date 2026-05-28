<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');

    if (empty($username) || empty($email)) {
        $_SESSION['error'] = 'Username dan email wajib diisi.';
        header('Location: profile.php');
        exit;
    }

    // Cek duplikat (exclude diri sendiri)
    $u_safe = mysqli_real_escape_string($conn, $username);
    $e_safe = mysqli_real_escape_string($conn, $email);
    $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE (username='$u_safe' OR email='$e_safe') AND id != $user_id LIMIT 1"));
    if ($dup) {
        $_SESSION['error'] = 'Username atau email sudah dipakai akun lain.';
        header('Location: profile.php');
        exit;
    }

    // Update password kalau diisi
    $pass_sql = '';
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) < 6) {
            $_SESSION['error'] = 'Password minimal 6 karakter.';
            header('Location: profile.php');
            exit;
        }
        if ($_POST['password'] !== ($_POST['password_confirm'] ?? '')) {
            $_SESSION['error'] = 'Konfirmasi password tidak cocok.';
            header('Location: profile.php');
            exit;
        }
        $hashed   = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pass_sql = ", password = '$hashed'";
    }

    mysqli_query($conn, "UPDATE users SET username='$u_safe', email='$e_safe'$pass_sql WHERE id=$user_id");
    $_SESSION['username'] = $username;
    $_SESSION['success']  = 'Profil berhasil diperbarui.';
    header('Location: profile.php');
    exit;
}

// Load data user
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1"));

// Stats
$joined_count   = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM participants WHERE user_id=$user_id AND status='approved'"))['c'];
$managed_count  = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments WHERE organizer_id=$user_id AND status NOT IN ('rejected','pending')"))['c'];
$finished_count = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments WHERE organizer_id=$user_id AND status='finished'"))['c'];

// Notifikasi terbaru
$notifs = [];
$nr = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($nr)) $notifs[] = $r;
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>

<div class="container page-wrap">
    <?php include '../components/alert.php'; ?>

    <div class="page-header">
        <div class="page-header-info">
            <div class="page-label">Akun Saya</div>
            <h1>Profil Pengguna</h1>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">← Dashboard</a>
    </div>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:1.5rem;" class="profile-grid">

        <!-- Sidebar profil -->
        <div>
            <div class="card" style="text-align:center;padding:2rem 1.5rem;margin-bottom:1rem;">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-2));margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:2rem;font-weight:700;color:#0C0F0E;border:3px solid var(--accent);box-shadow:0 0 20px rgba(0,200,150,0.25);">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <h2 style="margin-bottom:0.25rem;"><?= htmlspecialchars($user['username']) ?></h2>
                <div style="color:var(--accent-2);font-size:0.82rem;margin-bottom:0.5rem;"><?= htmlspecialchars($user['email']) ?></div>
                <span class="badge <?= $user['role'] === 'admin' ? 'badge-ongoing' : 'badge-finished' ?>">
                    <?= $user['role'] === 'admin' ? '🛡️ Admin' : '👤 Member' ?>
                </span>
                <div style="margin-top:1rem;font-size:0.75rem;color:var(--text-dim);">
                    Bergabung <?= date('d M Y', strtotime($user['created_at'])) ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="card card-sm">
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:1rem;">Statistik</div>
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Turnamen Diikuti</span>
                        <strong style="color:var(--accent);"><?= $joined_count ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Turnamen Dikelola</span>
                        <strong style="color:var(--accent-2);"><?= $managed_count ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Turnamen Selesai</span>
                        <strong style="color:var(--text);"><?= $finished_count ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- Edit profil -->
            <div class="card">
                <h3 style="margin-bottom:1.5rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border);">Edit Profil</h3>
                <form method="POST" action="profile.php">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control"
                                   value="<?= htmlspecialchars($user['username']) ?>" required minlength="3" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password Baru <span style="color:var(--text-dim);font-weight:400;">(kosongkan jika tidak ingin ubah)</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" minlength="6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi password baru">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:0.5rem;">
                        <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                    </div>
                </form>
            </div>

            <!-- Notifikasi terbaru -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid var(--border);">
                    <h3>Notifikasi Terbaru</h3>
                    <?php $unread = array_filter($notifs, fn($n) => !$n['is_read']); ?>
                    <?php if (!empty($unread)): ?>
                        <a href="../../api/notifications.php?action=read_all" class="btn btn-secondary btn-sm">Tandai Semua Dibaca</a>
                    <?php endif; ?>
                </div>
                <?php if (empty($notifs)): ?>
                    <div style="text-align:center;padding:1.5rem;color:var(--text-dim);">Tidak ada notifikasi.</div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:0;">
                        <?php foreach ($notifs as $n): ?>
                            <a href="../../api/notifications.php?action=read&id=<?= $n['id'] ?>"
                               style="display:flex;gap:0.75rem;padding:0.875rem 0;border-bottom:1px solid var(--border);text-decoration:none;transition:var(--transition);"
                               class="<?= !$n['is_read'] ? '' : '' ?>">
                                <div style="width:8px;height:8px;border-radius:50%;margin-top:6px;flex-shrink:0;background:<?= !$n['is_read'] ? 'var(--accent)' : 'var(--border)' ?>;"></div>
                                <div>
                                    <div style="font-size:0.85rem;color:<?= !$n['is_read'] ? 'var(--text)' : 'var(--text-muted)' ?>;font-weight:<?= !$n['is_read'] ? '600' : '400' ?>;">
                                        <?= htmlspecialchars($n['message']) ?>
                                    </div>
                                    <div style="font-size:0.72rem;color:var(--text-dim);margin-top:3px;">
                                        <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
<style>
@media (max-width: 768px) {
    .profile-grid { grid-template-columns: 1fr !important; }
}
</style>
</body>
</html>
