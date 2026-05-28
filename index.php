<?php
require_once 'config/database.php';
session_start();

// Query turnamen publik (open & ongoing)
$query  = "SELECT t.*, u.username AS organizer_name,
                  (SELECT COUNT(*) FROM participants p WHERE p.tournament_id = t.id AND p.status = 'approved') AS participant_count
           FROM tournaments t
           JOIN users u ON t.organizer_id = u.id
           WHERE t.status IN ('open','ongoing')
           ORDER BY t.created_at DESC
           LIMIT 6";
$result = mysqli_query($conn, $query);
$tournaments = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $tournaments[] = $row;
    }
}

$logged_in = isset($_SESSION['user_id']);
$role      = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIB — Best In Battle | Platform Manajemen Turnamen</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        /* Landing-only styles */
        .hero {
            min-height: calc(100vh - 72px);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(0,200,150,0.06) 1px, transparent 0);
            background-size: 28px 28px;
            mask-image: linear-gradient(to bottom, black 40%, transparent 100%);
            pointer-events: none;
        }

        .hero-glow {
            position: absolute;
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(0,200,150,0.07) 0%, transparent 65%);
            top: -200px; left: -200px;
            pointer-events: none;
        }

        .hero-glow-2 {
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(0,229,255,0.05) 0%, transparent 65%);
            bottom: -100px; right: -100px;
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 700px;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1rem;
            border: 1px solid rgba(0,229,255,0.3);
            background: rgba(0,229,255,0.08);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent-2);
            margin-bottom: 1.5rem;
        }

        .hero-chip::before {
            content:'';
            width:6px;height:6px;border-radius:50%;
            background:var(--accent-2);
            animation: pulse-dot 2s infinite;
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 700;
            line-height: 1.05;
            color: var(--text);
            margin-bottom: 1.25rem;
            letter-spacing: 0.02em;
        }

        .hero-title .highlight {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 1.05rem;
            color: var(--text-muted);
            line-height: 1.7;
            max-width: 560px;
            margin-bottom: 2.5rem;
        }

        .hero-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Features section */
        .features {
            padding: 5rem 0;
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 3rem;
        }

        .feature-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            border-color: rgba(0,200,150,0.35);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }

        .feature-icon {
            width: 48px; height: 48px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
        }

        .feature-card h3 {
            font-size: 1.05rem;
            color: var(--text);
            margin-bottom: 0.6rem;
        }

        .feature-card p {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.65;
        }

        /* Tournaments section */
        .tournaments-section { padding: 5rem 0; }

        .tournament-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
            margin-top: 2rem;
        }

        .t-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
            transition: all 0.25s ease;
            text-decoration: none;
        }

        .t-card:hover {
            border-color: rgba(0,200,150,0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            color: inherit;
        }

        .t-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .t-card-name {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
        }

        .t-card-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .t-card-meta span::before { content: '• '; }
        .t-card-meta span:first-child::before { content: ''; }

        .t-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }

        .center-section {
            text-align: center;
            padding: 5rem 0;
        }

        @media (max-width: 768px) {
            .hero { min-height: auto; padding: 4rem 0 3rem; }
            .hero-title { font-size: 2.2rem; }
            .hero-cta { flex-direction: column; }
            .hero-cta .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body class="page-fade">

<?php include 'pages/components/navbar.php'; ?>

<!-- HERO -->
<section class="hero">
    <div class="hero-glow"></div>
    <div class="hero-glow-2"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-chip">Platform Manajemen Turnamen</div>
            <h1 class="hero-title">
                Satu Platform.<br>
                <span class="highlight">Semua Kompetisi.</span>
            </h1>
            <p class="hero-sub">
                Dari turnamen Esports dengan sistem Bracket Single Elimination hingga
                Kuis Cerdas Cermat dengan Papan Skor Digital. Kelola, ikuti, dan menangkan.
            </p>
            <div class="hero-cta">
                <?php if (!$logged_in): ?>
                    <a href="<?= $base ?>pages/auth/register.php" class="btn btn-primary btn-lg">Mulai Gratis →</a>
                    <a href="<?= $base ?>pages/auth/login.php" class="btn btn-secondary btn-lg">Masuk</a>
                <?php elseif ($role === 'member'): ?>
                    <a href="<?= $base ?>pages/member/dashboard.php" class="btn btn-primary btn-lg">Dashboard Saya →</a>
                    <a href="<?= $base ?>pages/tournament/proposal.php" class="btn btn-secondary btn-lg">+ Buat Turnamen</a>
                <?php elseif ($role === 'admin'): ?>
                    <a href="<?= $base ?>pages/admin/dashboard.php" class="btn btn-primary btn-lg">Admin Panel →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features">
    <div class="container">
        <div style="text-align:center;max-width:560px;margin:0 auto;">
            <div class="page-label" style="justify-content:center;">Fitur Utama</div>
            <h2>Dirancang untuk <span class="text-accent">Setiap Arena</span></h2>
            <p style="color:var(--text-muted);margin-top:0.75rem;line-height:1.7;">
                Sistem manajemen yang fleksibel untuk berbagai jenis kompetisi.
            </p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(0,200,150,0.12);border:1px solid rgba(0,200,150,0.25);">⚔️</div>
                <h3>Bracket Single Elimination</h3>
                <p>Atur pertandingan gugur otomatis. Pemenang auto-advance ke ronde berikutnya. Dukung format Bo1, Bo3, hingga Bo5.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(0,229,255,0.1);border:1px solid rgba(0,229,255,0.2);">📊</div>
                <h3>Papan Skor Mode Poin</h3>
                <p>Papan skor digital real-time dengan auto-ranking. Cocok untuk kuis, cerdas cermat, atau game show berbasis poin.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(168,85,247,0.1);border:1px solid rgba(168,85,247,0.2);">🛡️</div>
                <h3>Role Berbasis Konteks</h3>
                <p>Member otomatis jadi Organizer di turnamen yang dikelolanya. Tidak perlu dashboard terpisah — kontrol langsung dari halaman turnamen.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.2);">🔔</div>
                <h3>Notifikasi Real-time</h3>
                <p>Proposal disetujui, peserta baru masuk, hasil pertandingan — semua masuk notifikasi langsung ke akunmu.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(255,69,96,0.1);border:1px solid rgba(255,69,96,0.2);">👥</div>
                <h3>Daftar Tim atau Individu</h3>
                <p>Setiap turnamen bisa diikuti secara individu maupun tim. Nama tim ditentukan saat pendaftaran.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(0,200,150,0.08);border:1px solid rgba(0,200,150,0.2);">⚙️</div>
                <h3>Dua Jalur Pembuatan</h3>
                <p>Member submit proposal → Admin approve, atau Admin buat langsung. Fleksibel sesuai kebutuhan penyelenggara.</p>
            </div>
        </div>
    </div>
</section>

<!-- TURNAMEN AKTIF -->
<section class="tournaments-section">
    <div class="container">
        <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
            <div>
                <div class="page-label">Sedang Berlangsung</div>
                <h2>Turnamen Aktif</h2>
            </div>
            <?php if ($logged_in && $role === 'member'): ?>
                <a href="<?= $base ?>pages/tournament/proposal.php" class="btn btn-primary">+ Buat Turnamen</a>
            <?php endif; ?>
        </div>

        <?php if (empty($tournaments)): ?>
            <div class="empty-state">
                <div class="icon">🏆</div>
                <h3>Belum Ada Turnamen Aktif</h3>
                <p>Jadilah yang pertama membuat turnamen di platform ini.</p>
                <?php if ($logged_in): ?>
                    <a href="<?= $base ?>pages/tournament/proposal.php" class="btn btn-primary">Buat Sekarang</a>
                <?php else: ?>
                    <a href="<?= $base ?>pages/auth/register.php" class="btn btn-primary">Daftar & Mulai</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="tournament-list-grid">
                <?php foreach ($tournaments as $t): ?>
                    <a href="<?= $base ?>pages/tournament/detail.php?id=<?= $t['id'] ?>" class="t-card">
                        <div class="t-card-head">
                            <div class="t-card-name"><?= htmlspecialchars($t['name']) ?></div>
                            <?php
                            $status_map = [
                                'open'    => ['label' => 'Buka Daftar', 'class' => 'badge-success'],
                                'ongoing' => ['label' => 'Berlangsung', 'class' => 'badge-ongoing badge-live'],
                            ];
                            $s = $status_map[$t['status']] ?? ['label' => ucfirst($t['status']), 'class' => 'badge-finished'];
                            ?>
                            <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                        </div>

                        <?php if ($t['description']): ?>
                            <p style="font-size:0.82rem;color:var(--text-muted);line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                <?= htmlspecialchars($t['description']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="t-card-meta">
                            <span><?= $t['mode'] === 'bracket' ? '⚔️ Bracket' : '📊 Poin' ?></span>
                            <span><?= $t['participant_type'] === 'team' ? '👥 Tim' : '👤 Individu' ?></span>
                            <?php if ($t['mode'] === 'bracket' && $t['sets_per_match']): ?>
                                <span>Bo<?= $t['sets_per_match'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="t-card-footer">
                            <span style="font-size:0.78rem;color:var(--text-dim);">
                                by <?= htmlspecialchars($t['organizer_name']) ?>
                            </span>
                            <span style="font-size:0.78rem;color:var(--text-muted);">
                                <?= $t['participant_count'] ?> peserta
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA BAWAH -->
<?php if (!$logged_in): ?>
<section class="center-section" style="background:var(--surface);border-top:1px solid var(--border);">
    <div class="container">
        <h2 style="margin-bottom:0.75rem;">Siap Memulai?</h2>
        <p style="color:var(--text-muted);max-width:480px;margin:0 auto 2rem;">
            Daftar gratis dan buat turnamen pertamamu dalam hitungan menit.
        </p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
            <a href="<?= $base ?>pages/auth/register.php" class="btn btn-primary btn-lg">Daftar Sekarang</a>
            <a href="<?= $base ?>pages/auth/login.php" class="btn btn-secondary btn-lg">Sudah Punya Akun</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'pages/components/footer.php'; ?>

<script src="assets/js/global.js"></script>
</body>
</html>
