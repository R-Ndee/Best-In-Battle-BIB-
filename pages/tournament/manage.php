<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/auth/login.php');
    exit;
}

$t_id = (int) ($_GET['id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!$t_id) {
    header('Location: index.php');
    exit;
}

// Load turnamen
$t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tournaments WHERE id = $t_id LIMIT 1"));
if (!$t) {
    $_SESSION['error'] = 'Turnamen tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Hanya organizer atau admin yang bisa akses
$is_organizer = ((int) $t['organizer_id'] === $user_id) || $role === 'admin';
if (!$is_organizer) {
    $_SESSION['error'] = 'Akses ditolak.';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}

// ============================================================
// AKSI POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Approve peserta
    if ($action === 'approve_participant') {
        $p_id = (int) $_POST['participant_id'];
        mysqli_query($conn, "UPDATE participants SET status = 'approved' WHERE id = $p_id AND tournament_id = $t_id");

        // Notif ke peserta
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.*, t.name AS t_name FROM participants p JOIN tournaments t ON p.tournament_id = t.id WHERE p.id = $p_id"));
        if ($p) {
            $msg = mysqli_real_escape_string($conn, "Pendaftaranmu di turnamen '{$p['t_name']}' telah disetujui oleh Organizer!");
            $link = mysqli_real_escape_string($conn, "pages/tournament/detail.php?id=$t_id");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message, link) VALUES ({$p['user_id']}, '$msg', '$link')");
        }
        $_SESSION['success'] = 'Peserta disetujui.';
    }

    // Hapus / reject peserta
    if ($action === 'reject_participant') {
        $p_id = (int) $_POST['participant_id'];
        mysqli_query($conn, "DELETE FROM participants WHERE id = $p_id AND tournament_id = $t_id");
        $_SESSION['info'] = 'Peserta dihapus.';
    }

    // Generate / randomize bracket
    if ($action === 'generate_bracket') {
        if ($t['mode'] !== 'bracket') {
            $_SESSION['error'] = 'Hanya mode bracket yang bisa generate bracket.';
        } else {
            // Hapus matches ronde 1 lama (kalau ada, tapi turnamen belum mulai)
            mysqli_query($conn, "DELETE FROM matches WHERE tournament_id = $t_id");

            // Ambil peserta approved, acak
            $p_result = mysqli_query($conn, "SELECT id FROM participants WHERE tournament_id = $t_id AND status = 'approved' ORDER BY RAND()");
            $p_ids = [];
            while ($row = mysqli_fetch_assoc($p_result))
                $p_ids[] = (int) $row['id'];

            if (count($p_ids) < 2) {
                $_SESSION['error'] = 'Minimal 2 peserta untuk generate bracket.';
            } else {
                // Buat pasangan ronde 1 (bye kalau ganjil)
                $match_order = 1;
                $round1_matches = 0;

                for ($i = 0; $i < count($p_ids); $i += 2) {
                    $pa = $p_ids[$i];
                    $pb = isset($p_ids[$i + 1]) ? $p_ids[$i + 1] : 'NULL';
                    mysqli_query($conn, "INSERT INTO matches (tournament_id, round, match_order, participant_a_id, participant_b_id, status)
                         VALUES ($t_id, 1, $match_order, $pa, $pb, 'pending')");

                    if ($pb === 'NULL') {
                        $bye_id = mysqli_insert_id($conn);
                        mysqli_query($conn, "UPDATE matches SET winner_id = $pa, status = 'finished' WHERE id = $bye_id");
                    }
                    $round1_matches++;
                    $match_order++;
                }

                // Generate placeholder match untuk ronde berikutnya
                $total_participants = count($p_ids);
                $total_rounds = (int) ceil(log($total_participants, 2));

                for ($round = 2; $round <= $total_rounds; $round++) {
                    $matches_in_round = (int) pow(2, $total_rounds - $round);
                    for ($m = 1; $m <= $matches_in_round; $m++) {
                        mysqli_query($conn, "INSERT INTO matches (tournament_id, round, match_order, status)
                             VALUES ($t_id, $round, $m, 'pending')");
                    }
                }

                $_SESSION['success'] = 'Bracket berhasil di-generate! Acak ulang atau mulai turnamen.';
            }
        }
    }

    // Mulai turnamen
    if ($action === 'start_tournament') {
        if ($t['mode'] === 'bracket') {
            $match_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM matches WHERE tournament_id = $t_id"))['c'];
            if ($match_count === 0) {
                $_SESSION['error'] = 'Generate bracket dulu sebelum memulai.';
            } else {
                mysqli_query($conn, "UPDATE tournaments SET status = 'ongoing' WHERE id = $t_id");

                // Ambil match pertama dulu, baru update by ID
                $first_match = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT id FROM matches
                     WHERE tournament_id = $t_id
                     AND status = 'pending'
                     AND participant_a_id IS NOT NULL
                     AND participant_b_id IS NOT NULL
                     ORDER BY round ASC, match_order ASC
                     LIMIT 1"
                ));
                if ($first_match) {
                    mysqli_query($conn, "UPDATE matches SET status = 'ongoing' WHERE id = {$first_match['id']}");
                }

                $_SESSION['success'] = 'Turnamen dimulai! Bracket terkunci.';
            }
        } else {
            // Mode poin: insert semua peserta ke point_scores
            $approved = mysqli_query($conn, "SELECT id FROM participants WHERE tournament_id = $t_id AND status = 'approved'");
            while ($p = mysqli_fetch_assoc($approved)) {
                mysqli_query($conn, "INSERT IGNORE INTO point_scores (tournament_id, participant_id, points) VALUES ($t_id, {$p['id']}, 0)");
            }
            mysqli_query($conn, "UPDATE tournaments SET status = 'ongoing' WHERE id = $t_id");
            $_SESSION['success'] = 'Turnamen dimulai! Papan skor aktif.';
        }
    }

    header("Location: /pages/tournament/manage.php?id=$t_id");
    exit;
}

// ============================================================
// LOAD DATA
// ============================================================
// Peserta pending
$pending_participants = [];
$pr = mysqli_query($conn, "SELECT p.*, u.username FROM participants p JOIN users u ON p.user_id = u.id WHERE p.tournament_id = $t_id AND p.status = 'pending' ORDER BY p.created_at ASC");
while ($r = mysqli_fetch_assoc($pr))
    $pending_participants[] = $r;

// Peserta approved
$approved_participants = [];
$ar = mysqli_query($conn, "SELECT p.*, u.username FROM participants p JOIN users u ON p.user_id = u.id WHERE p.tournament_id = $t_id AND p.status = 'approved' ORDER BY p.created_at ASC");
while ($r = mysqli_fetch_assoc($ar))
    $approved_participants[] = $r;

// Bracket (untuk preview)
$bracket_rounds = [];
if ($t['mode'] === 'bracket') {
    $mr = mysqli_query($conn, "SELECT m.*, pa.display_name AS name_a, pb.display_name AS name_b, pw.display_name AS winner_name
                               FROM matches m
                               LEFT JOIN participants pa ON m.participant_a_id = pa.id
                               LEFT JOIN participants pb ON m.participant_b_id = pb.id
                               LEFT JOIN participants pw ON m.winner_id = pw.id
                               WHERE m.tournament_id = $t_id
                               ORDER BY m.round ASC, m.match_order ASC");
    while ($r = mysqli_fetch_assoc($mr)) {
        $bracket_rounds[$r['round']][] = $r;
    }
}

$can_start = $t['status'] === 'open' && count($approved_participants) >= 2;
$can_bracket = $t['mode'] === 'bracket' && $t['status'] === 'open' && count($approved_participants) >= 2;
?>
<!DOCTYPE html>
<html lang="id" data-theme="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Organizer — <?= htmlspecialchars($t['name']) ?></title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/tournament.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>

<body class="page-fade">
    <?php include '../components/navbar.php'; ?>

    <div class="container page-wrap">
        <?php include '../components/alert.php'; ?>

        <div class="page-header">
            <div class="page-header-info">
                <div class="page-label">Panel Organizer</div>
                <h1><?= htmlspecialchars($t['name']) ?></h1>
                <p>
                    <?= $t['mode'] === 'bracket' ? '⚔️ Bracket' : '📊 Mode Poin' ?> •
                    <?= ucfirst($t['participant_type']) ?> •
                    <?php
                    $sm = ['open' => ['Buka Daftar', 'badge-success'], 'ongoing' => ['Berlangsung', 'badge-ongoing'], 'finished' => ['Selesai', 'badge-finished'], 'pending' => ['Pending', 'badge-pending']];
                    [$slabel, $sclass] = $sm[$t['status']] ?? [ucfirst($t['status']), ''];
                    echo "<span class='badge $sclass'>$slabel</span>";
                    ?>
                </p>
            </div>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="<?= $base ?>pages/tournament/detail.php?id=<?= $t_id ?>" class="btn btn-secondary btn-sm">←
                    Lihat Detail</a>
                <?php if ($t['status'] === 'ongoing'): ?>
                    <a href="<?= $base ?>pages/tournament/scoring.php?id=<?= $t_id ?>" class="btn btn-primary btn-sm">🎯
                        Live Scoring →</a>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;" class="manage-grid">

            <!-- Peserta Pending -->
            <div class="manage-panel">
                <div class="manage-panel-header">
                    <h3>Pendaftaran Masuk <span class="badge badge-pending"
                            style="margin-left:0.5rem;"><?= count($pending_participants) ?></span></h3>
                </div>
                <?php if (empty($pending_participants)): ?>
                    <div style="padding:2rem;text-align:center;color:var(--text-dim);">Tidak ada pendaftaran baru.</div>
                <?php else: ?>
                    <?php foreach ($pending_participants as $p): ?>
                        <div
                            style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;border-bottom:1px solid var(--border);gap:0.75rem;flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:600;color:var(--text);"><?= htmlspecialchars($p['display_name']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($p['username']) ?>
                                </div>
                            </div>
                            <div style="display:flex;gap:0.5rem;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="approve_participant">
                                    <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">✓ Setujui</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject_participant">
                                    <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Hapus pendaftaran <?= htmlspecialchars($p['display_name']) ?>?">✕</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Peserta Approved -->
            <div class="manage-panel">
                <div class="manage-panel-header">
                    <h3>Peserta Dikonfirmasi <span class="badge badge-success"
                            style="margin-left:0.5rem;"><?= count($approved_participants) ?></span></h3>
                </div>
                <?php if (empty($approved_participants)): ?>
                    <div style="padding:2rem;text-align:center;color:var(--text-dim);">Belum ada peserta terkonfirmasi.
                    </div>
                <?php else: ?>
                    <?php foreach ($approved_participants as $i => $p): ?>
                        <div
                            style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1.25rem;border-bottom:1px solid var(--border);gap:0.5rem;">
                            <div style="display:flex;align-items:center;gap:0.6rem;">
                                <span style="font-size:0.72rem;color:var(--text-dim);min-width:20px;">#<?= $i + 1 ?></span>
                                <div>
                                    <div style="font-weight:600;font-size:0.875rem;color:var(--text);">
                                        <?= htmlspecialchars($p['display_name']) ?>
                                    </div>
                                    <div style="font-size:0.72rem;color:var(--text-muted);">
                                        <?= htmlspecialchars($p['username']) ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($t['status'] === 'open'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject_participant">
                                    <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Hapus <?= htmlspecialchars($p['display_name']) ?> dari turnamen?">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Generate Bracket -->
        <?php if ($can_bracket): ?>
            <div class="card mb-3">
                <h3 style="margin-bottom:0.75rem;">⚔️ Generate Bracket</h3>
                <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.25rem;">
                    Acak susunan bracket dari <?= count($approved_participants) ?> peserta yang terdaftar.
                    Bisa di-generate ulang berkali-kali sebelum turnamen dimulai.
                </p>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="generate_bracket">
                    <button type="submit" class="btn btn-outline">🔀 Generate / Acak Ulang Bracket</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Preview Bracket -->
        <?php if (!empty($bracket_rounds) && $t['mode'] === 'bracket'): ?>
            <div class="section-title mb-2">
                <h2>Preview Bracket</h2>
            </div>
            <div style="overflow-x:auto;margin-bottom:2rem;">
                <div
                    style="display:flex;gap:2rem;min-width:max-content;padding:1rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-xl);">
                    <?php foreach ($bracket_rounds as $round => $matches): ?>
                        <div style="min-width:200px;">
                            <div
                                style="text-align:center;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin-bottom:1rem;padding:0.25rem 0.75rem;background:var(--surface-2);border-radius:20px;border:1px solid var(--border);">
                                Ronde <?= $round ?>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                                <?php foreach ($matches as $m): ?>
                                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;
                                            <?= $m['status'] === 'ongoing' ? 'border-color:var(--accent-2);box-shadow:0 0 12px rgba(0,229,255,0.15);' : '' ?>
                                            <?= $m['status'] === 'finished' ? 'opacity:0.75;' : '' ?>">
                                        <?php
                                        $rows = [
                                            [$m['name_a'] ?? 'TBD', $m['winner_id'] == $m['participant_a_id'] && $m['winner_id']],
                                            [$m['name_b'] ?? 'BYE', $m['winner_id'] == $m['participant_b_id'] && $m['winner_id']],
                                        ];
                                        foreach ($rows as [$name, $is_winner]):
                                            ?>
                                            <div style="padding:0.5rem 0.75rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;
                                                    <?= $is_winner ? 'background:rgba(0,200,150,0.06);' : '' ?>">
                                                <span
                                                    style="font-size:0.8rem;font-weight:600;color:<?= $name === 'TBD' || $name === 'BYE' ? 'var(--text-dim)' : 'var(--text)' ?>;">
                                                    <?= htmlspecialchars($name) ?>
                                                </span>
                                                <?php if ($is_winner): ?><span
                                                        style="color:var(--accent);font-size:0.75rem;">🏆</span><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mulai Turnamen -->
        <?php if ($can_start): ?>
            <div
                style="background:rgba(0,200,150,0.06);border:1px solid rgba(0,200,150,0.3);border-radius:var(--radius-xl);padding:1.75rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                <div>
                    <h3 style="color:var(--accent);margin-bottom:0.35rem;">🚀 Siap Memulai?</h3>
                    <p style="color:var(--text-muted);font-size:0.875rem;">
                        <?= $t['mode'] === 'bracket' ? 'Bracket akan terkunci setelah turnamen dimulai.' : 'Semua peserta akan masuk ke papan skor.' ?>
                        <?= count($approved_participants) ?> peserta siap bertanding.
                    </p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="start_tournament">
                    <button type="submit" class="btn btn-primary btn-lg"
                        data-confirm="Mulai turnamen? Aksi ini tidak bisa dibatalkan.">
                        ▶ Mulai Turnamen
                    </button>
                </form>
            </div>
        <?php elseif ($t['status'] === 'ongoing'): ?>
            <div class="alert alert-info">
                ℹ️ Turnamen sedang berlangsung.
                <a href="<?= $base ?>scoring.php?id=<?= $t_id ?>"
                    style="color:var(--accent-2);font-weight:700;margin-left:0.5rem;">Buka Live Scoring →</a>
            </div>
        <?php elseif ($t['status'] === 'finished'): ?>
            <div class="alert alert-success">✓ Turnamen sudah selesai.</div>
        <?php elseif ($t['status'] === 'open' && count($approved_participants) < 2): ?>
            <div class="alert alert-warning">
                ⚠️ Butuh minimal 2 peserta terkonfirmasi untuk memulai turnamen.
            </div>
        <?php endif; ?>

    </div>

    <?php include '../components/footer.php'; ?>
    <script src="../../assets/js/global.js"></script>
    <style>
        @media (max-width: 768px) {
            .manage-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</body>

</html>