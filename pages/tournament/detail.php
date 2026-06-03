<?php
require_once '../../config/database.php';
session_start();

$t_id = (int) ($_GET['id'] ?? 0);
if (!$t_id) {
    header('Location: /pages/auth/login.php');
    exit;
}

// Query turnamen
$t_query = "SELECT t.*, u.username AS organizer_name FROM tournaments t JOIN users u ON t.organizer_id = u.id WHERE t.id = $t_id LIMIT 1";
$t_result = mysqli_query($conn, $t_query);
if (!$t_result || mysqli_num_rows($t_result) === 0) {
    $_SESSION['error'] = 'Turnamen tidak ditemukan.';
    header('Location: index.php');
    exit;
}
$tournament = mysqli_fetch_assoc($t_result);

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'guest';

// Apakah user adalah organizer turnamen ini?
$is_organizer = ($user_id === (int) $tournament['organizer_id']) || $role === 'admin';

// Apakah user sudah mendaftar?
$already_registered = false;
$my_registration = null;
if ($user_id) {
    $reg_q = "SELECT * FROM participants WHERE tournament_id = $t_id AND user_id = $user_id LIMIT 1";
    $reg_r = mysqli_query($conn, $reg_q);
    if ($reg_r && mysqli_num_rows($reg_r) > 0) {
        $already_registered = true;
        $my_registration = mysqli_fetch_assoc($reg_r);
    }
}

// Proses pendaftaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!$user_id) {
        $_SESSION['error'] = 'Harus login untuk mendaftar.';
        header("Location: /pages/tournament/detail.php?id=$t_id");
        exit;
    }
    if ($is_organizer) {
        $_SESSION['error'] = 'Organizer tidak bisa mendaftar di turnamen sendiri.';
        header("Location: /pages/tournament/detail.php?id=$t_id");
        exit;
    }
    if ($already_registered) {
        $_SESSION['error'] = 'Kamu sudah mendaftar di turnamen ini.';
        header("Location: /pages/tournament/detail.php?id=$t_id");
        exit;
    }
    if ($tournament['status'] !== 'open') {
        $_SESSION['error'] = 'Pendaftaran sudah ditutup.';
        header("Location: /pages/tournament/detail.php?id=$t_id");
        exit;
    }

    $display_name = trim($_POST['display_name'] ?? $_SESSION['username']);
    if (empty($display_name))
        $display_name = $_SESSION['username'];
    $display_safe = mysqli_real_escape_string($conn, $display_name);

    mysqli_query($conn, "INSERT INTO participants (tournament_id, user_id, display_name, status) VALUES ($t_id, $user_id, '$display_safe', 'pending')");

    // Notif ke organizer
    $username_safe = mysqli_real_escape_string($conn, $_SESSION['username']);
    $t_name_safe = mysqli_real_escape_string($conn, $tournament['name']);
    // Notif ke organizer
    $msg = mysqli_real_escape_string($conn, "Peserta baru '{$_SESSION['username']}' mendaftar di turnamen '{$tournament['name']}'.");
    $link = mysqli_real_escape_string($conn, "pages/tournament/manage.php?id=$t_id");
    mysqli_query($conn, "INSERT INTO notifications (user_id, message, link) VALUES ({$tournament['organizer_id']}, '$msg', '$link')");

    $_SESSION['success'] = 'Pendaftaran berhasil! Tunggu konfirmasi dari Organizer.';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}

// Query peserta approved
$participants_q = "SELECT p.*, u.username FROM participants p JOIN users u ON p.user_id = u.id WHERE p.tournament_id = $t_id AND p.status = 'approved' ORDER BY p.created_at ASC";
$participants_r = mysqli_query($conn, $participants_q);
$participants = [];
if ($participants_r) {
    while ($r = mysqli_fetch_assoc($participants_r))
        $participants[] = $r;
}

// ============================================================
// HASIL TURNAMEN (hanya kalau finished)
// ============================================================
$hasil_poin = [];
$hasil_bracket = [];

if ($tournament['status'] === 'finished') {

    if ($tournament['mode'] === 'point') {
        $hp_q = "SELECT ps.points, p.display_name, u.username
                 FROM point_scores ps
                 JOIN participants p ON ps.participant_id = p.id
                 JOIN users u ON p.user_id = u.id
                 WHERE ps.tournament_id = $t_id
                 ORDER BY ps.points DESC, ps.update_at ASC";
        $hp_r = mysqli_query($conn, $hp_q);
        while ($r = mysqli_fetch_assoc($hp_r))
            $hasil_poin[] = $r;

    } else {
        $hb_q = "SELECT m.id AS match_id, m.round, m.match_order, m.status AS match_status,
                        pa.display_name AS name_a, pb.display_name AS name_b,
                        pw.display_name AS winner_name,
                        m.participant_a_id, m.participant_b_id, m.winner_id
                 FROM matches m
                 LEFT JOIN participants pa ON m.participant_a_id = pa.id
                 LEFT JOIN participants pb ON m.participant_b_id = pb.id
                 LEFT JOIN participants pw ON m.winner_id = pw.id
                 WHERE m.tournament_id = $t_id
                 ORDER BY m.round ASC, m.match_order ASC";
        $hb_r = mysqli_query($conn, $hb_q);
        while ($r = mysqli_fetch_assoc($hb_r)) {
            $sets_q = "SELECT s.set_number, s.score_a, s.score_b, pw2.display_name AS set_winner
                       FROM sets s
                       LEFT JOIN participants pw2 ON s.winner_id = pw2.id
                       WHERE s.match_id = {$r['match_id']} AND s.status = 'finished'
                       ORDER BY s.set_number ASC";
            $sets_r = mysqli_query($conn, $sets_q);
            $r['sets'] = [];
            while ($sr = mysqli_fetch_assoc($sets_r))
                $r['sets'][] = $sr;
            $hasil_bracket[$r['round']][] = $r;
        }
    }
}

$mode_label = $tournament['mode'] === 'bracket' ? '⚔️ Bracket Single Elimination' : '📊 Mode Poin';
?>
<!DOCTYPE html>
<html lang="id" data-theme="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tournament['name']) ?> — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/tournament.css">
</head>

<body class="page-fade">
    <?php include '../components/navbar.php'; ?>

    <div class="container page-wrap">
        <?php include '../components/alert.php'; ?>

        <!-- Hero -->
        <div class="tournament-hero">
            <div class="tournament-hero-inner">
                <div class="tournament-hero-icon">
                    <?= $tournament['mode'] === 'bracket' ? '⚔️' : '📊' ?>
                </div>
                <div class="tournament-hero-info">
                    <div class="page-label"><?= $mode_label ?></div>
                    <h1><?= htmlspecialchars($tournament['name']) ?></h1>

                    <?php if ($tournament['description']): ?>
                        <p class="desc"><?= nl2br(htmlspecialchars($tournament['description'])) ?></p>
                    <?php endif; ?>

                    <div class="tournament-tags">
                        <?php
                        $status_map = [
                            'pending' => ['label' => 'Pending', 'class' => 'badge-pending'],
                            'open' => ['label' => 'Buka Daftar', 'class' => 'badge-success'],
                            'ongoing' => ['label' => 'Berlangsung', 'class' => 'badge-ongoing badge-live'],
                            'finished' => ['label' => 'Selesai', 'class' => 'badge-finished'],
                            'rejected' => ['label' => 'Ditolak', 'class' => 'badge-danger'],
                        ];
                        $s = $status_map[$tournament['status']] ?? ['label' => $tournament['status'], 'class' => ''];
                        ?>
                        <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
                        <span class="tournament-tag">
                            <?= $tournament['participant_type'] === 'team' ? '👥 Tim' : '👤 Individu' ?>
                        </span>
                        <?php if ($tournament['mode'] === 'bracket' && $tournament['sets_per_match']): ?>
                            <span class="tournament-tag">🎮 Bo<?= $tournament['sets_per_match'] ?></span>
                        <?php endif; ?>
                        <span class="tournament-tag">🏅 <?= count($participants) ?> peserta</span>
                    </div>
                </div>

                <!-- CTA daftar / panel -->
                <div style="display:flex;flex-direction:column;gap:0.75rem;flex-shrink:0;">
                    <?php if ($is_organizer): ?>
                        <a href="<?= $base ?>pages/tournament/manage.php?id=<?= $t_id ?>" class="btn btn-primary">
                            ⚙️ Panel Organizer
                        </a>
                        <?php if ($tournament['status'] === 'ongoing'): ?>
                            <a href="<?= $base ?>pages/tournament/scoring.php?id=<?= $t_id ?>" class="btn btn-outline">
                                🎯 Live Scoring
                            </a>
                        <?php endif; ?>
                    <?php elseif ($user_id && !$already_registered && $tournament['status'] === 'open'): ?>
                        <button class="btn btn-primary"
                            onclick="document.getElementById('registerModal').style.display='flex'">
                            + Daftar Sekarang
                        </button>
                    <?php elseif ($already_registered): ?>
                        <div style="text-align:center;">
                            <?php if ($my_registration['status'] === 'pending'): ?>
                                <span class="badge badge-pending">⏳ Menunggu Konfirmasi</span>
                            <?php elseif ($my_registration['status'] === 'approved'): ?>
                                <span class="badge badge-success">✓ Terdaftar</span>
                            <?php else: ?>
                                <span class="badge badge-danger">✕ Ditolak</span>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$user_id && $tournament['status'] === 'open'): ?>
                        <a href="<?= $base ?>pages/auth/login.php" class="btn btn-primary">Login untuk Daftar</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info grid -->
            <div class="tournament-info-grid">
                <div class="info-item">
                    <div class="label">Organizer</div>
                    <div class="value">👤 <?= htmlspecialchars($tournament['organizer_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Mode</div>
                    <div class="value"><?= $tournament['mode'] === 'bracket' ? 'Bracket' : 'Mode Poin' ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Format Peserta</div>
                    <div class="value"><?= ucfirst($tournament['participant_type']) ?></div>
                </div>
                <?php if ($tournament['mode'] === 'bracket' && $tournament['sets_per_match']): ?>
                    <div class="info-item">
                        <div class="label">Set per Match</div>
                        <div class="value">Bo<?= $tournament['sets_per_match'] ?> (<?= $tournament['sets_per_match'] ?> set)
                        </div>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="label">Dibuat</div>
                    <div class="value"><?= date('d M Y', strtotime($tournament['created_at'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Daftar Peserta -->
        <div class="section-title mb-2">
            <h2>Peserta Terdaftar (<?= count($participants) ?>)</h2>
        </div>

        <?php if (empty($participants)): ?>
            <div class="empty-state" style="padding:2rem;">
                <div class="icon">👥</div>
                <h3>Belum Ada Peserta</h3>
                <p>Belum ada peserta yang dikonfirmasi.</p>
            </div>
        <?php else: ?>
            <div class="participants-grid">
                <?php foreach ($participants as $i => $p): ?>
                    <div class="participant-item">
                        <div class="participant-avatar"><?= strtoupper(substr($p['display_name'], 0, 2)) ?></div>
                        <div class="participant-info">
                            <div class="name"><?= htmlspecialchars($p['display_name']) ?></div>
                            <div class="sub"><?= htmlspecialchars($p['username']) ?></div>
                        </div>
                        <div style="margin-left:auto;font-family:var(--font-display);font-size:0.85rem;color:var(--text-dim);">
                            #<?= $i + 1 ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($tournament['status'] === 'finished'): ?>
            <div style="margin-top:3rem;">

                <!-- ===== JUDUL SECTION ===== -->
                <div style="text-align:center;margin-bottom:2.5rem;">
                    <div
                        style="display:inline-flex;align-items:center;gap:0.75rem;background:rgba(0,200,150,0.08);border:1px solid rgba(0,200,150,0.25);border-radius:20px;padding:0.4rem 1.25rem;margin-bottom:1rem;">
                        <span
                            style="width:8px;height:8px;border-radius:50%;background:var(--accent);display:inline-block;"></span>
                        <span
                            style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--accent);">Turnamen
                            Selesai</span>
                    </div>
                    <h2 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.5rem);color:var(--text);">Hasil
                        &
                        Klasemen Akhir</h2>
                </div>

                <?php if ($tournament['mode'] === 'point' && !empty($hasil_poin)): ?>
                    <!-- ===================================================
                MODE POIN: PODIUM + TABEL RANKING
                =================================================== -->

                    <!-- Podium visual -->
                    <?php
                    $p1 = $hasil_poin[0] ?? null;
                    $p2 = $hasil_poin[1] ?? null;
                    $p3 = $hasil_poin[2] ?? null;
                    ?>
                    <div
                        style="display:flex;align-items:flex-end;justify-content:center;gap:1.5rem;margin:0 auto 3rem;max-width:700px;flex-wrap:wrap;padding:0 1rem;">
                        <!-- Runner-up (kiri) -->
                        <?php if ($p2): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:180px;">
                                <div style="font-size:2rem;margin-bottom:0.5rem;">🥈</div>
                                <div
                                    style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;text-align:center;">
                                    <?= htmlspecialchars($p2['display_name']) ?>
                                </div>
                                <div
                                    style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--accent-2);margin-bottom:0.75rem;">
                                    <?= number_format($p2['points']) ?> pts
                                </div>
                                <div
                                    style="width:100%;height:130px;background:linear-gradient(180deg,rgba(0,229,255,0.12),var(--surface));border-top:3px solid var(--accent-2);border-radius:var(--radius-md) var(--radius-md) 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:0.75rem;">
                                    <span
                                        style="font-family:var(--font-display);font-size:3rem;font-weight:900;color:rgba(0,229,255,0.08);">2</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Juara 1 (tengah, lebih tinggi) -->
                        <?php if ($p1): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:200px;">
                                <div
                                    style="font-size:2.5rem;margin-bottom:0.5rem;filter:drop-shadow(0 0 12px rgba(0,200,150,0.6));">
                                    🏆
                                </div>
                                <div
                                    style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;text-align:center;">
                                    <?= htmlspecialchars($p1['display_name']) ?>
                                </div>
                                <div
                                    style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--accent);margin-bottom:0.75rem;">
                                    <?= number_format($p1['points']) ?> pts
                                </div>
                                <div
                                    style="width:100%;height:180px;background:linear-gradient(180deg,rgba(0,200,150,0.15),var(--surface));border-top:3px solid var(--accent);border-radius:var(--radius-md) var(--radius-md) 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:0.75rem;box-shadow:0 -8px 32px rgba(0,200,150,0.15);">
                                    <span
                                        style="font-family:var(--font-display);font-size:4rem;font-weight:900;color:rgba(0,200,150,0.08);">1</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Peringkat 3 (kanan) -->
                        <?php if ($p3): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:160px;">
                                <div style="font-size:1.8rem;margin-bottom:0.5rem;">🥉</div>
                                <div
                                    style="font-family:var(--font-display);font-size:0.95rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;text-align:center;">
                                    <?= htmlspecialchars($p3['display_name']) ?>
                                </div>
                                <div
                                    style="font-family:var(--font-display);font-size:0.95rem;font-weight:700;color:#a855f7;margin-bottom:0.75rem;">
                                    <?= number_format($p3['points']) ?> pts
                                </div>
                                <div
                                    style="width:100%;height:95px;background:linear-gradient(180deg,rgba(168,85,247,0.1),var(--surface));border-top:3px solid #a855f7;border-radius:var(--radius-md) var(--radius-md) 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:0.75rem;">
                                    <span
                                        style="font-family:var(--font-display);font-size:2.5rem;font-weight:900;color:rgba(168,85,247,0.08);">3</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tabel ranking lengkap -->
                    <div class="section-title mb-2">
                        <h2>Klasemen Lengkap</h2>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:60px;">Rank</th>
                                    <th>Peserta</th>
                                    <th style="text-align:right;">Poin Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasil_poin as $i => $hp): ?>
                                    <tr style="<?= $i === 0 ? 'background:rgba(0,200,150,0.04);' : '' ?>">
                                        <td>
                                            <span
                                                style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:<?= $i === 0 ? 'var(--accent)' : ($i === 1 ? 'var(--accent-2)' : ($i === 2 ? '#a855f7' : 'var(--text-dim)')) ?>;">
                                                <?= $i === 0 ? '🏆' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '#' . ($i + 1))) ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:<?= $i < 3 ? '700' : '400' ?>;">
                                            <?= htmlspecialchars($hp['display_name']) ?>
                                            <span
                                                style="font-size:0.75rem;color:var(--text-dim);margin-left:0.5rem;"><?= htmlspecialchars($hp['username']) ?></span>
                                        </td>
                                        <td
                                            style="text-align:right;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:<?= $i === 0 ? 'var(--accent)' : 'var(--text)' ?>;">
                                            <?= number_format($hp['points']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($tournament['mode'] === 'bracket' && !empty($hasil_bracket)): ?>
                    <!-- ===================================================
                MODE BRACKET: PODIUM + REKAP PER RONDE
                =================================================== -->

                    <?php
                    // Cari juara: winner dari match di ronde tertinggi
                    $max_round = max(array_keys($hasil_bracket));
                    $final_match = $hasil_bracket[$max_round][0] ?? null;
                    $juara1 = $final_match['winner_name'] ?? null;
                    // Runner-up = yang kalah di final
                    $juara2 = null;
                    if ($final_match) {
                        $juara2 = ($final_match['winner_id'] == $final_match['participant_a_id'])
                            ? $final_match['name_b']
                            : $final_match['name_a'];
                    }
                    // Juara 3: tidak ada di single elimination (keduanya yang kalah di SF)
                    // Ambil match semi final (round $max_round - 1) kalau ada
                    $sf_losers = [];
                    if ($max_round > 1 && isset($hasil_bracket[$max_round - 1])) {
                        foreach ($hasil_bracket[$max_round - 1] as $sf) {
                            if ($sf['winner_id']) {
                                $sf_losers[] = ($sf['winner_id'] == $sf['participant_a_id']) ? $sf['name_b'] : $sf['name_a'];
                            }
                        }
                    }

                    // Label ronde
                    $round_labels = [];
                    for ($r = 1; $r <= $max_round; $r++) {
                        if ($r === $max_round)
                            $round_labels[$r] = 'Grand Final';
                        elseif ($r === $max_round - 1)
                            $round_labels[$r] = 'Semi Final';
                        elseif ($r === $max_round - 2)
                            $round_labels[$r] = 'Quarter Final';
                        else
                            $round_labels[$r] = 'Babak ' . $r;
                    }
                    ?>

                    <!-- Podium bracket -->
                    <div
                        style="display:flex;align-items:flex-end;justify-content:center;gap:1.5rem;margin:0 auto 3rem;max-width:700px;flex-wrap:wrap;padding:0 1rem;">

                        <?php if ($juara2): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:180px;">
                                <div style="font-size:2rem;margin-bottom:0.5rem;">🥈</div>
                                <div
                                    style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;text-align:center;">
                                    <?= htmlspecialchars($juara2) ?>
                                </div>
                                <div style="font-size:0.78rem;color:var(--accent-2);font-weight:600;margin-bottom:0.75rem;">
                                    Runner-up
                                </div>
                                <div
                                    style="width:100%;height:130px;background:linear-gradient(180deg,rgba(0,229,255,0.12),var(--surface));border-top:3px solid var(--accent-2);border-radius:var(--radius-md) var(--radius-md) 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:0.75rem;">
                                    <span
                                        style="font-family:var(--font-display);font-size:3rem;font-weight:900;color:rgba(0,229,255,0.08);">2</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($juara1): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:200px;">
                                <div
                                    style="font-size:2.5rem;margin-bottom:0.5rem;filter:drop-shadow(0 0 12px rgba(0,200,150,0.6));">
                                    🏆
                                </div>
                                <div
                                    style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;text-align:center;">
                                    <?= htmlspecialchars($juara1) ?>
                                </div>
                                <div style="font-size:0.78rem;color:var(--accent);font-weight:600;margin-bottom:0.75rem;">🥇 Juara 1
                                </div>
                                <div
                                    style="width:100%;height:180px;background:linear-gradient(180deg,rgba(0,200,150,0.15),var(--surface));border-top:3px solid var(--accent);border-radius:var(--radius-md) var(--radius-md) 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:0.75rem;box-shadow:0 -8px 32px rgba(0,200,150,0.15);">
                                    <span
                                        style="font-family:var(--font-display);font-size:4rem;font-weight:900;color:rgba(0,200,150,0.08);">1</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($sf_losers)): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:160px;">
                                <div style="font-size:1.8rem;margin-bottom:0.5rem;">🥉</div>
                                <div
                                    style="font-family:var(--font-display);font-size:0.95rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;text-align:center;">
                                    <?= htmlspecialchars($sf_losers[0]) ?>
                                </div>
                                <div style="font-size:0.75rem;color:#a855f7;font-weight:600;margin-bottom:0.75rem;">Semi Finalis
                                </div>
                                <div
                                    style="width:100%;height:95px;background:linear-gradient(180deg,rgba(168,85,247,0.1),var(--surface));border-top:3px solid #a855f7;border-radius:var(--radius-md) var(--radius-md) 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:0.75rem;">
                                    <span
                                        style="font-family:var(--font-display);font-size:2.5rem;font-weight:900;color:rgba(168,85,247,0.08);">3</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rekap per ronde -->
                    <div class="section-title mb-2">
                        <h2>Rekap Pertandingan</h2>
                    </div>

                    <?php foreach ($hasil_bracket as $round => $matches): ?>
                        <div style="margin-bottom:2rem;">
                            <!-- Label ronde -->
                            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                                <div
                                    style="height:2px;width:32px;background:linear-gradient(90deg,var(--accent),var(--accent-2));border-radius:1px;">
                                </div>
                                <span
                                    style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:<?= $round === $max_round ? 'var(--accent)' : 'var(--text-muted)' ?>;">
                                    <?= $round_labels[$round] ?? "Babak $round" ?>
                                </span>
                                <?php if ($round === $max_round): ?>
                                    <span
                                        style="font-size:0.65rem;font-weight:700;background:rgba(0,200,150,0.1);border:1px solid rgba(0,200,150,0.25);color:var(--accent);padding:2px 8px;border-radius:20px;">FINAL</span>
                                <?php endif; ?>
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1rem;">
                                <?php foreach ($matches as $m): ?>
                                    <div
                                        style="background:var(--surface);border:1px solid <?= $round === $max_round ? 'rgba(0,200,150,0.3)' : 'var(--border)' ?>;border-radius:var(--radius-lg);overflow:hidden;<?= $round === $max_round ? 'box-shadow:0 0 24px rgba(0,200,150,0.08);' : '' ?>">

                                        <!-- Match header -->
                                        <div
                                            style="padding:0.6rem 1rem;background:var(--surface-2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                                            <span
                                                style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-dim);">
                                                Match <?= $m['match_order'] ?>
                                            </span>
                                            <?php if ($m['match_status'] === 'finished'): ?>
                                                <span
                                                    style="font-size:0.65rem;font-weight:700;color:var(--accent);background:rgba(0,200,150,0.1);padding:2px 8px;border-radius:20px;">Selesai</span>
                                            <?php else: ?>
                                                <span style="font-size:0.65rem;color:var(--text-dim);">—</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Peserta baris atas -->
                                        <div
                                            style="padding:0.7rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:0.5rem;<?= $m['winner_id'] == $m['participant_a_id'] ? 'background:rgba(0,200,150,0.05);' : '' ?>">
                                            <span
                                                style="font-size:0.875rem;font-weight:<?= $m['winner_id'] == $m['participant_a_id'] ? '700' : '400' ?>;color:<?= $m['winner_id'] == $m['participant_a_id'] ? 'var(--text)' : 'var(--text-muted)' ?>;">
                                                <?= htmlspecialchars($m['name_a'] ?? 'BYE') ?>
                                            </span>
                                            <?php if ($m['winner_id'] == $m['participant_a_id']): ?>
                                                <span style="font-size:0.7rem;color:var(--accent);">🏆 Menang</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Peserta baris bawah -->
                                        <div
                                            style="padding:0.7rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;<?= $m['winner_id'] == $m['participant_b_id'] ? 'background:rgba(0,200,150,0.05);' : '' ?>">
                                            <span
                                                style="font-size:0.875rem;font-weight:<?= $m['winner_id'] == $m['participant_b_id'] ? '700' : '400' ?>;color:<?= $m['winner_id'] == $m['participant_b_id'] ? 'var(--text)' : 'var(--text-muted)' ?>;">
                                                <?= htmlspecialchars($m['name_b'] ?? 'BYE') ?>
                                            </span>
                                            <?php if ($m['winner_id'] == $m['participant_b_id']): ?>
                                                <span style="font-size:0.7rem;color:var(--accent);">🏆 Menang</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Set history (kalau ada) -->
                                        <?php if (!empty($m['sets'])): ?>
                                            <div style="border-top:1px solid var(--border);padding:0.6rem 1rem;background:var(--bg);">
                                                <div
                                                    style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-dim);margin-bottom:0.5rem;">
                                                    Detail Set</div>
                                                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
                                                    <?php foreach ($m['sets'] as $s): ?>
                                                        <div
                                                            style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0.25rem 0.6rem;font-size:0.75rem;">
                                                            <span style="color:var(--text-dim);">Set <?= $s['set_number'] ?>:</span>
                                                            <strong style="color:var(--text);margin-left:0.25rem;"><?= $s['score_a'] ?> —
                                                                <?= $s['score_b'] ?></strong>
                                                            <?php if ($s['set_winner']): ?>
                                                                <span
                                                                    style="color:var(--accent);margin-left:0.25rem;">(<?= htmlspecialchars($s['set_winner']) ?>)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>
        <?php endif; // end status finished ?>
    </div>

    <!-- Modal Daftar -->
    <div id="registerModal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:300;align-items:center;justify-content:center;padding:1rem;">
        <div class="card" style="max-width:440px;width:100%;position:relative;">
            <button onclick="document.getElementById('registerModal').style.display='none'"
                style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:var(--text-muted);font-size:1.3rem;cursor:pointer;">✕</button>
            <h3 style="margin-bottom:1.5rem;">Daftar ke <?= htmlspecialchars($tournament['name']) ?></h3>
            <form method="POST" action="detail.php?id=<?= $t_id ?>">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label class="form-label">
                        <?= $tournament['participant_type'] === 'team' ? 'Nama Tim' : 'Nama Tampil' ?>
                    </label>
                    <input type="text" name="display_name" class="form-control"
                        placeholder="<?= $tournament['participant_type'] === 'team' ? 'Nama tim kamu' : ($_SESSION['username'] ?? 'Nama') ?>"
                        value="<?= $tournament['participant_type'] === 'individual' ? htmlspecialchars($_SESSION['username'] ?? '') : '' ?>"
                        required>
                    <div class="form-hint">
                        <?= $tournament['participant_type'] === 'team' ? 'Nama tim yang akan tampil di bracket/papan skor.' : 'Nama yang akan tampil di turnamen.' ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Konfirmasi Pendaftaran →</button>
            </form>
        </div>
    </div>

    <?php include '../components/footer.php'; ?>
    <script src="../../assets/js/global.js"></script>
</body>

</html>