<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$query  = "SELECT u.*,
                  (SELECT COUNT(*) FROM participants p WHERE p.user_id = u.id AND p.status = 'approved') AS tournaments_joined,
                  (SELECT COUNT(*) FROM tournaments t WHERE t.organizer_id = u.id AND t.status != 'rejected') AS tournaments_managed
           FROM users u
           ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $query);
$users  = [];
if ($result) {
    while ($r = mysqli_fetch_assoc($result)) $users[] = $r;
}

$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments WHERE status = 'pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna — BIB Admin</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-section">Menu Admin</div>
        <ul class="admin-sidebar-nav">
            <li><a href="<?= $base ?>pages/admin/dashboard.php"><span class="nav-icon">📊</span> Dashboard</a></li>
            <li><a href="<?= $base ?>pages/admin/proposals.php"><span class="nav-icon">📋</span> Kelola Proposal
                <?php if ($pending_count > 0): ?>
                    <span class="badge badge-pending" style="margin-left:auto;font-size:0.65rem;"><?= $pending_count ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="<?= $base ?>pages/admin/users.php" class="active"><span class="nav-icon">👥</span> Kelola Pengguna</a></li>
        </ul>
    </aside>

    <main class="admin-content">
        <?php include '../components/alert.php'; ?>

        <div class="page-header">
            <div class="page-header-info">
                <div class="page-label">Administrasi</div>
                <h1>Kelola Pengguna</h1>
                <p>Daftar semua akun terdaftar di platform.</p>
            </div>
            <div style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--accent);">
                <?= count($users) ?> <span style="font-size:0.9rem;color:var(--text-muted);font-family:var(--font-body);">pengguna</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Turnamen Diikuti</th>
                        <th>Turnamen Dikelola</th>
                        <th>Bergabung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:2rem;">Tidak ada pengguna.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                            <tr>
                                <td style="color:var(--text-dim);"><?= $i + 1 ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:0.6rem;">
                                        <div style="width:32px;height:32px;border-radius:50%;background:var(--surface-2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:var(--accent);">
                                            <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                        </div>
                                        <span style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></span>
                                    </div>
                                </td>
                                <td style="color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="user-role-<?= $u['role'] ?>">
                                        <?= $u['role'] === 'admin' ? '🛡️ Admin' : '👤 Member' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:600;">
                                    <?= $u['tournaments_joined'] ?>
                                </td>
                                <td style="text-align:center;font-weight:600;">
                                    <?= $u['tournaments_managed'] ?>
                                </td>
                                <td style="color:var(--text-dim);font-size:0.82rem;">
                                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
</body>
</html>
