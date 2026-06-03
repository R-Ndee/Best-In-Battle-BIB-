<?php

require_once '../../config/database.php';
session_start();

// Proteksi halaman
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/auth/login.php');
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header('Location: /pages/admin/dashboard.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];


// Proteksi halaman
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/auth/login.php');
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header('Location: /pages/admin/dashboard.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Turnamen yang diikuti (sebagai peserta approved)
$joined_query = "SELECT t.*, p.display_name, p.status AS reg_status,
                        u.username AS organizer_name
                 FROM participants p
                 JOIN tournaments t ON p.tournament_id = t.id
                 JOIN users u ON t.organizer_id = u.id
                 WHERE p.user_id = $user_id
                   AND p.status = 'approved'
                 ORDER BY t.created_at DESC
                 LIMIT 10";
$joined_result = mysqli_query($conn, $joined_query);
$joined = [];
if ($joined_result) {
    while ($r = mysqli_fetch_assoc($joined_result)) $joined[] = $r;
}

// Turnamen yang dikelola sebagai organizer
$managed_query = "SELECT t.*,
                         (SELECT COUNT(*) FROM participants p WHERE p.tournament_id = t.id AND p.status = 'approved') AS participant_count
                  FROM tournaments t
                  WHERE t.organizer_id = $user_id
                    AND t.status != 'rejected'
                  ORDER BY t.created_at DESC
                  LIMIT 10";
$managed_result = mysqli_query($conn, $managed_query);
$managed = [];
if ($managed_result) {
    while ($r = mysqli_fetch_assoc($managed_result)) $managed[] = $r;
}

// Proposal pending milik user
$proposal_query = "SELECT * FROM tournaments WHERE organizer_id = $user_id AND status = 'pending' ORDER BY created_at DESC";
$proposal_result = mysqli_query($conn, $proposal_query);
$proposals = [];
if ($proposal_result) {
    while ($r = mysqli_fetch_assoc($proposal_result)) $proposals[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body class="page-fade">

<?php include '../components/navbar.php'; ?>


<div class="container page-wrap">
    <?php include '../components/alert.php'; ?>

    <!-- Header -->
    <div class="page-header">
        <div class="page-header-info">
            <div class="page-label">Member Dashboard</div>
            <h1>Halo, <?= htmlspecialchars($_SESSION['username']) ?> 👋</h1>
            <p>Pantau semua aktivitas dan kompetisi kamu di sini.</p>
        </div>
        <a href="<?= $base ?>pages/tournament/proposal.php" class="btn btn-primary">
            + Ajukan Proposal Turnamen
        </a>
    </div>

    <!-- Proposal Pending -->
    <?php if (!empty($proposals)): ?>
        <div class="section-title mb-2"><h2>Status Proposal Kamu</h2></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-bottom:2.5rem;">
            <?php foreach ($proposals as $p): ?>
                <div class="card card-sm" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:700;color:var(--text);margin-bottom:0.25rem;">
                            <?= htmlspecialchars($p['name']) ?>
                        </div>
                        <div style="font-size:0.78rem;color:var(--text-muted);">
                            <?= $p['mode'] === 'bracket' ? 'Bracket' : 'Mode Poin' ?>
                        </div>
                    </div>
                    <span class="badge badge-pending">⏳ Menunggu Review</span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Turnamen yang Dikelola -->
    <div class="section-title" style="margin-bottom:1rem;">
        <h2>Turnamen yang Saya Kelola</h2>
    </div>

    <?php if (empty($managed)): ?>
        <div class="empty-state" style="padding:2.5rem 1rem;margin-bottom:2.5rem;">
            <div class="icon">🏆</div>
            <h3>Belum Ada Turnamen</h3>
            <p>Kamu belum mengelola turnamen apa pun. Ajukan proposal atau tunggu Admin menyetujuinya.</p>
            <a href="<?= $base ?>pages/tournament/proposal.php" class="btn btn-primary btn-sm">+ Buat Proposal</a>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.875rem;margin-bottom:2.5rem;">
            <?php foreach ($managed as $t):
                $status_map = [
                    'pending'  => ['label' => 'Menunggu', 'class' => 'badge-pending'],
                    'open'     => ['label' => 'Buka Daftar', 'class' => 'badge-success'],
                    'ongoing'  => ['label' => 'Berlangsung', 'class' => 'badge-ongoing badge-live'],
                    'finished' => ['label' => 'Selesai', 'class' => 'badge-finished'],
                    'rejected' => ['label' => 'Ditolak', 'class' => 'badge-danger'],
                ];
                $s = $status_map[$t['status']] ?? ['label' => ucfirst($t['status']), 'class' => ''];
            ?>
                <div class="organizer-card">
                    <div class="organizer-card-info">
                        <h3><?= htmlspecialchars($t['name']) ?></h3>
                        <div class="meta">
                            <span><?= $t['mode'] === 'bracket' ? '⚔️ Bracket' : '📊 Mode Poin' ?></span>
                            <span>👥 <?= $t['participant_count'] ?> peserta</span>
                            <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                        </div>
                    </div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <a href="<?= $base ?>pages/tournament/detail.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Detail</a>
                        <?php if (in_array($t['status'], ['open', 'ongoing'])): ?>
                            <a href="<?= $base ?>pages/tournament/manage.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Panel Kontrol →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Turnamen yang Diikuti -->
    <div class="section-title" style="margin-bottom:1rem;">
        <h2>Turnamen yang Saya Ikuti</h2>
    </div>

    <?php if (empty($joined)): ?>
        <div class="empty-state" style="padding:2.5rem 1rem;">
            <div class="icon">🎮</div>
            <h3>Belum Ikut Turnamen</h3>
            <p>Cari turnamen yang menarik dan daftarkan dirimu.</p>
            <a href="<?= $base ?>index.php" class="btn btn-outline btn-sm">Jelajahi Turnamen</a>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
            <?php foreach ($joined as $t):
                $status_map = [
                    'open'     => ['label' => 'Buka Daftar', 'class' => 'badge-success'],
                    'ongoing'  => ['label' => 'Berlangsung', 'class' => 'badge-ongoing badge-live'],
                    'finished' => ['label' => 'Selesai', 'class' => 'badge-finished'],
                ];
                $s = $status_map[$t['status']] ?? ['label' => ucfirst($t['status']), 'class' => ''];
            ?>
                <div class="tournament-card">
                    <div class="tournament-card-head">
                        <div class="tournament-card-title"><?= htmlspecialchars($t['name']) ?></div>
                        <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                    </div>
                    <div class="tournament-card-meta">
                        <span>👤 <?= htmlspecialchars($t['display_name']) ?></span>
                        <span>by <?= htmlspecialchars($t['organizer_name']) ?></span>
                    </div>
                    <div class="tournament-card-footer">
                        <span style="font-size:0.78rem;color:var(--text-dim);">
                            <?= $t['mode'] === 'bracket' ? '⚔️ Bracket' : '📊 Mode Poin' ?>
                        </span>
                        <a href="<?= $base ?>pages/tournament/detail.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Lihat →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
</body>
</html>
