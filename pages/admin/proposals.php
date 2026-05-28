<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id = (int) $_SESSION['user_id'];

// Approve proposal
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $t_id = (int) $_GET['id'];
    mysqli_query($conn, "UPDATE tournaments SET status = 'open' WHERE id = $t_id AND status = 'pending'");

    // Notifikasi ke organizer
    $t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tournaments WHERE id = $t_id"));
    if ($t) {
        $msg = mysqli_real_escape_string($conn, "Proposal turnamen '{$t['name']}' disetujui! Turnamen kini berstatus Open.");
        $link = mysqli_real_escape_string($conn, "pages/tournament/manage.php?id={$t['id']}");
        mysqli_query($conn, "INSERT INTO notifications (user_id, message, link) VALUES ({$t['organizer_id']}, '$msg', '$link')");
    }

    $_SESSION['success'] = 'Proposal berhasil disetujui.';
    header('Location: proposals.php');
    exit;
}

// Reject proposal
if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['id'])) {
    $t_id = (int) $_GET['id'];
    mysqli_query($conn, "UPDATE tournaments SET status = 'rejected' WHERE id = $t_id AND status = 'pending'");

    $t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tournaments WHERE id = $t_id"));
    if ($t) {
        $msg = mysqli_real_escape_string($conn, "Proposal turnamen '{$t['name']}' ditolak oleh Admin.");
        $link = mysqli_real_escape_string($conn, "pages/tournament/detail.php?id={$t['id']}");
        mysqli_query($conn, "INSERT INTO notifications (user_id, message, link) VALUES ({$t['organizer_id']}, '$msg', '$link')");
    }

    $_SESSION['info'] = 'Proposal ditolak.';
    header('Location: proposals.php');
    exit;
}

// Query proposals
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'open', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters))
    $filter = 'pending';

$where = $filter === 'all' ? '' : "WHERE t.status = '$filter'";
$query = "SELECT t.*, u.username AS organizer_name
          FROM tournaments t
          JOIN users u ON t.organizer_id = u.id
          $where
          ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $query);
$proposals = [];
if ($result) {
    while ($r = mysqli_fetch_assoc($result))
        $proposals[] = $r;
}

$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM tournaments WHERE status = 'pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="id" data-theme="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Proposal — BIB Admin</title>
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
                <li><a href="<?= $base ?>pages/admin/proposals.php" class="active"><span class="nav-icon">📋</span>
                        Kelola Proposal
                        <?php if ($pending_count > 0): ?>
                            <span class="badge badge-pending"
                                style="margin-left:auto;font-size:0.65rem;"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </a></li>
                <li><a href="<?= $base ?>pages/admin/users.php"><span class="nav-icon">👥</span> Kelola Pengguna</a>
                </li>
            </ul>
            <div class="admin-sidebar-section" style="margin-top:1.5rem;">Aksi Cepat</div>
            <ul class="admin-sidebar-nav">
                <li><a href="<?= $base ?>pages/tournament/create_admin.php"><span class="nav-icon">➕</span> Buat
                        Turnamen</a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <?php include '../components/alert.php'; ?>

            <div class="page-header">
                <div class="page-header-info">
                    <div class="page-label">Administrasi</div>
                    <h1>Kelola Proposal Turnamen</h1>
                    <p>Tinjau, setujui, atau tolak pengajuan dari member.</p>
                </div>
                <a href="<?= $base ?>pages/tournament/create_admin.php" class="btn btn-primary">+ Buat Langsung</a>
            </div>

            <!-- Filter tabs -->
            <div style="display:flex;gap:0.5rem;margin-bottom:2rem;flex-wrap:wrap;">
                <?php
                $filters = ['pending' => 'Menunggu', 'open' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'];
                foreach ($filters as $key => $label):
                    ?>
                    <a href="?filter=<?= $key ?>"
                        class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $label ?>
                        <?php if ($key === 'pending' && $pending_count > 0): ?>
                            <span
                                style="background:rgba(0,0,0,0.2);padding:1px 6px;border-radius:10px;font-size:0.7rem;"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($proposals)): ?>
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h3>Tidak Ada Proposal <?= $filter === 'pending' ? 'Menunggu' : '' ?></h3>
                    <p>Semua proposal sudah ditangani.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                    <?php foreach ($proposals as $p):
                        $status_map = [
                            'pending' => ['label' => 'Menunggu Review', 'class' => 'badge-pending'],
                            'open' => ['label' => 'Disetujui', 'class' => 'badge-success'],
                            'ongoing' => ['label' => 'Berlangsung', 'class' => 'badge-ongoing'],
                            'finished' => ['label' => 'Selesai', 'class' => 'badge-finished'],
                            'rejected' => ['label' => 'Ditolak', 'class' => 'badge-danger'],
                        ];
                        $s = $status_map[$p['status']] ?? ['label' => $p['status'], 'class' => ''];
                        ?>
                        <div class="proposal-card">
                            <div class="proposal-card-head">
                                <div>
                                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.25rem;">
                                        Diajukan oleh <strong><?= htmlspecialchars($p['organizer_name']) ?></strong>
                                        · <?= date('d M Y H:i', strtotime($p['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                            </div>

                            <?php if ($p['description']): ?>
                                <div class="proposal-desc"><?= nl2br(htmlspecialchars($p['description'])) ?></div>
                            <?php endif; ?>

                            <div class="proposal-meta-row">
                                <span class="proposal-meta-item">
                                    ⚙️ <?= $p['mode'] === 'bracket' ? 'Bracket Single Elim' : 'Mode Poin' ?>
                                </span>
                                <span class="proposal-meta-item">
                                    👥 <?= $p['participant_type'] === 'team' ? 'Format Tim' : 'Individu' ?>
                                </span>
                                <?php if ($p['mode'] === 'bracket' && $p['sets_per_match']): ?>
                                    <span class="proposal-meta-item">🎮 Bo<?= $p['sets_per_match'] ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($p['status'] === 'pending'): ?>
                                <div class="proposal-actions">
                                    <a href="?action=approve&id=<?= $p['id'] ?>" class="btn btn-primary"
                                        data-confirm="Setujui proposal '<?= htmlspecialchars($p['name']) ?>'?">
                                        ✓ Setujui
                                    </a>
                                    <a href="?action=reject&id=<?= $p['id'] ?>" class="btn btn-danger"
                                        data-confirm="Tolak proposal '<?= htmlspecialchars($p['name']) ?>'?">
                                        ✕ Tolak
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="text-align:right;">
                                    <a href="<?= $base ?>pages/tournament/detail.php?id=<?= $p['id'] ?>"
                                        class="btn btn-secondary btn-sm">Lihat Turnamen →</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php include '../components/footer.php'; ?>
    <script src="../../assets/js/global.js"></script>
</body>

</html>