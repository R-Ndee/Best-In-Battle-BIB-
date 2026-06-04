<?php
require_once '../../config/database.php';
session_start();

// Hapus turnamen
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int) $_GET['id'];
    // Hapus berurutan: sets → matches → point_scores → participants → notifications → tournaments
    mysqli_query($conn, "DELETE FROM sets WHERE match_id IN (SELECT id FROM matches WHERE tournament_id = $del_id)");
    mysqli_query($conn, "DELETE FROM matches WHERE tournament_id = $del_id");
    mysqli_query($conn, "DELETE FROM point_scores WHERE tournament_id = $del_id");
    mysqli_query($conn, "DELETE FROM participants WHERE tournament_id = $del_id");
    mysqli_query($conn, "DELETE FROM notifications WHERE message LIKE '%' AND link LIKE '%id=$del_id%'");
    mysqli_query($conn, "DELETE FROM tournaments WHERE id = $del_id");
    $_SESSION['success'] = 'Turnamen berhasil dihapus.';
    header('Location: /pages/admin/dashboard.php');
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /pages/auth/login.php');
    exit;
}

// Stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_tournaments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments"))['c'];
$active_tournaments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments WHERE status IN ('open','ongoing')"))['c'];
$pending_proposals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments WHERE status = 'pending'"))['c'];

// Turnamen terbaru
$recent_query = "SELECT t.*, u.username AS organizer_name FROM tournaments t JOIN users u ON t.organizer_id = u.id ORDER BY t.created_at DESC LIMIT 8";
$recent_result = mysqli_query($conn, $recent_query);
$recent = [];
if ($recent_result) {
    while ($r = mysqli_fetch_assoc($recent_result))
        $recent[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>

<body class="page-fade">

    <?php include '../components/navbar.php'; ?>

    <div class="admin-layout">

        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-section">Menu Admin</div>
            <ul class="admin-sidebar-nav">
                <li><a href="<?= $base ?>pages/admin/dashboard.php" class="active"><span class="nav-icon">📊</span>
                        Dashboard</a></li>
                <li><a href="<?= $base ?>pages/admin/proposals.php"><span class="nav-icon">📋</span> Kelola Proposal
                        <?php if ($pending_proposals > 0): ?>
                            <span class="badge badge-pending"
                                style="margin-left:auto;font-size:0.65rem;"><?= $pending_proposals ?></span>
                        <?php endif; ?>
                    </a></li>
                <li><a href="<?= $base ?>pages/admin/users.php"><span class="nav-icon">👥</span> Kelola Pengguna</a>
                </li>
            </ul>
            <div class="admin-sidebar-section" style="margin-top:1.5rem;">Aksi Cepat</div>
            <ul class="admin-sidebar-nav">
                <li><a href="<?= $base ?>pages/tournament/create_admin.php"><span class="nav-icon">➕</span> Buat
                        Turnamen</a></li>
                <li><a href="<?= $base ?>index.php"><span class="nav-icon">🏠</span> Lihat Beranda</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="admin-content">
            <?php include '../components/alert.php'; ?>

            <div class="page-header">
                <div class="page-header-info">
                    <div class="page-label">Administrator</div>
                    <h1>Dashboard Admin</h1>
                    <p>Ringkasan statistik platform BIB.</p>
                </div>
                <a href="<?= $base ?>pages/tournament/create_admin.php" class="btn btn-primary">
                    + Buat Turnamen Langsung
                </a>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Pengguna</div>
                    <div class="stat-value accent"><?= number_format($total_users) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Turnamen</div>
                    <div class="stat-value"><?= number_format($total_tournaments) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Turnamen Aktif</div>
                    <div class="stat-value accent-2"><?= number_format($active_tournaments) ?></div>
                </div>
                <div class="stat-card"
                    style="<?= $pending_proposals > 0 ? 'border-color:rgba(251,191,36,0.4);' : '' ?>">
                    <div class="stat-label">Proposal Pending</div>
                    <div class="stat-value <?= $pending_proposals > 0 ? '' : 'accent' ?>"
                        style="<?= $pending_proposals > 0 ? 'color:#FBB024;' : '' ?>">
                        <?= number_format($pending_proposals) ?>
                    </div>
                    <?php if ($pending_proposals > 0): ?>
                        <a href="<?= $base ?>pages/admin/proposals.php"
                            style="font-size:0.75rem;color:var(--accent);margin-top:0.25rem;display:block;">Review sekarang
                            →</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Tournaments -->
            <div class="section-title mb-2">
                <h2>Semua Turnamen</h2>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Turnamen</th>
                            <th>Mode</th>
                            <th>Organizer</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:var(--text-dim);padding:2rem;">Belum ada
                                    turnamen.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $status_map = [
                                'pending' => ['label' => 'Pending', 'class' => 'badge-pending'],
                                'open' => ['label' => 'Buka Daftar', 'class' => 'badge-success'],
                                'ongoing' => ['label' => 'Berlangsung', 'class' => 'badge-ongoing'],
                                'finished' => ['label' => 'Selesai', 'class' => 'badge-finished'],
                                'rejected' => ['label' => 'Ditolak', 'class' => 'badge-danger'],
                            ];
                            foreach ($recent as $t):
                                $s = $status_map[$t['status']] ?? ['label' => $t['status'], 'class' => ''];
                                ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></td>
                                    <td><?= $t['mode'] === 'bracket' ? '⚔️ Bracket' : '📊 Poin' ?></td>
                                    <td style="color:var(--text-muted);"><?= htmlspecialchars($t['organizer_name']) ?></td>
                                    <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
                                    <td style="color:var(--text-dim);font-size:0.82rem;">
                                        <?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                    <td style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                                        <a href="/pages/tournament/detail.php?id=<?= $t['id'] ?>"
                                            class="btn btn-secondary btn-sm">Lihat</a>
                                        <?php if (in_array($t['status'], ['pending', 'open'])): ?>
                                            <a href="/pages/tournament/edit.php?id=<?= $t['id'] ?>"
                                                class="btn btn-outline btn-sm">✏️</a>
                                        <?php endif; ?>
                                        <a href="/pages/admin/dashboard.php?action=delete&id=<?= $t['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            data-confirm="Hapus turnamen '<?= htmlspecialchars($t['name']) ?>'? Semua data akan hilang permanen.">🗑️</a>
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