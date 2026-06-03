<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/auth/login.php');
    exit;
}

$t_id    = (int) ($_GET['id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'];

if (!$t_id) { header('Location: index.php'); exit; }

$t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tournaments WHERE id = $t_id AND status = 'ongoing' LIMIT 1"));
if (!$t) {
    $_SESSION['error'] = 'Turnamen tidak ditemukan atau belum dimulai.';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}

$is_organizer = ((int)$t['organizer_id'] === $user_id) || $role === 'admin';

// Selesaikan turnamen mode poin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finish_tournament_poin') {
    if (!$is_organizer) { header("Location: /pages/tournament/scoring.php?id=$t_id"); exit; }
    mysqli_query($conn, "UPDATE tournaments SET status = 'finished' WHERE id = $t_id");
    $_SESSION['success'] = 'Turnamen selesai!';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}

// ============================================================
// MODE POIN: load papan skor
// ============================================================
$point_scores = [];
if ($t['mode'] === 'point') {
    $ps_q = "SELECT ps.*, p.display_name
             FROM point_scores ps
             JOIN participants p ON ps.participant_id = p.id
             WHERE ps.tournament_id = $t_id
             ORDER BY ps.points DESC, ps.update_at ASC";
    $ps_r = mysqli_query($conn, $ps_q);
    while ($r = mysqli_fetch_assoc($ps_r)) $point_scores[] = $r;
}

// ============================================================
// MODE BRACKET: load match ongoing
// ============================================================
$current_match  = null;
$set_history    = [];
$current_set    = null;

if ($t['mode'] === 'bracket') {
    $match_q = "SELECT m.*, pa.display_name AS name_a, pb.display_name AS name_b
                FROM matches m
                LEFT JOIN participants pa ON m.participant_a_id = pa.id
                LEFT JOIN participants pb ON m.participant_b_id = pb.id
                WHERE m.tournament_id = $t_id AND m.status = 'ongoing'
                ORDER BY m.round ASC, m.match_order ASC
                LIMIT 1";
    $match_r = mysqli_query($conn, $match_q);
    if ($match_r) $current_match = mysqli_fetch_assoc($match_r);

    if ($current_match) {
        // Set history (finished)
        $sh_q = "SELECT s.*, pa.display_name AS winner_name
                 FROM sets s
                 LEFT JOIN participants pa ON s.winner_id = pa.id
                 WHERE s.match_id = {$current_match['id']} AND s.status = 'finished'
                 ORDER BY s.set_number ASC";
        $sh_r = mysqli_query($conn, $sh_q);
        while ($r = mysqli_fetch_assoc($sh_r)) $set_history[] = $r;

        // Set sedang berjalan
        $cs_q = "SELECT * FROM sets WHERE match_id = {$current_match['id']} AND status = 'ongoing' ORDER BY set_number DESC LIMIT 1";
        $cs_r = mysqli_query($conn, $cs_q);
        if ($cs_r) $current_set = mysqli_fetch_assoc($cs_r);

        // Kalau belum ada set ongoing, buat set pertama
        if (!$current_set && $current_match) {
            $next_set_num = count($set_history) + 1;
            mysqli_query($conn, "INSERT INTO sets (match_id, set_number, score_a, score_b, status) VALUES ({$current_match['id']}, $next_set_num, 0, 0, 'ongoing')");
            $current_set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM sets WHERE match_id = {$current_match['id']} AND status = 'ongoing' LIMIT 1"));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Scoring — <?= htmlspecialchars($t['name']) ?></title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/scoring.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>

<div class="container page-wrap">
    <?php include '../components/alert.php'; ?>

    <div class="page-header">
        <div class="page-header-info">
            <div class="page-label badge badge-live">● Live</div>
            <h1><?= htmlspecialchars($t['name']) ?></h1>
            <p><?= $t['mode'] === 'bracket' ? '⚔️ Live Scoring Match' : '📊 Papan Skor Digital' ?></p>
        </div>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <a href="<?= $base ?>pages/tournament/manage.php?id=<?= $t_id ?>" class="btn btn-secondary btn-sm">⚙️ Panel Kontrol</a>
            <a href="<?= $base ?>pages/tournament/detail.php?id=<?= $t_id ?>" class="btn btn-secondary btn-sm">← Detail</a>
        </div>
    </div>

    <?php if ($t['mode'] === 'point'): ?>
    <!-- ======================================================
         MODE POIN
    ====================================================== -->

    <?php if ($is_organizer): ?>
    <!-- Kontrol increment -->
    <div class="card mb-3" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.5rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <span style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);">Besaran Poin:</span>
            <div class="increment-selector" id="incrementSelector">
                <?php foreach ([1, 5, 10, 50] as $step): ?>
                    <button class="increment-btn <?= $step === 1 ? 'active' : '' ?>"
                            onclick="setStep(<?= $step ?>, this)">
                        <?= $step ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="finish_tournament_poin">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Selesaikan turnamen? Pemenang ditentukan dari skor tertinggi.">
                🏁 Selesaikan Turnamen
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="point-board-grid" id="pointBoard">
        <?php foreach ($point_scores as $i => $ps): ?>
            <div class="point-card <?= $i === 0 ? 'rank-1' : '' ?>" id="card_<?= $ps['participant_id'] ?>">
                <?php if ($i === 0): ?>
                    <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent),var(--accent-2));"></div>
                <?php endif; ?>
                <div class="point-rank">
                    <?= $i === 0 ? '🏆 ' : ($i === 1 ? '🥈 ' : ($i === 2 ? '🥉 ' : '')) ?>
                    Peringkat #<?= $i + 1 ?>
                </div>
                <div class="point-team-name"><?= htmlspecialchars($ps['display_name']) ?></div>
                <div class="point-score-display" id="score_<?= $ps['participant_id'] ?>"><?= $ps['points'] ?></div>

                <?php if ($is_organizer): ?>
                    <div class="point-btns">
                        <button class="btn btn-danger" onclick="updatePoint(<?= $ps['participant_id'] ?>, -1)">−</button>
                        <button class="btn btn-primary" onclick="updatePoint(<?= $ps['participant_id'] ?>, 1)">+</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ======================================================
         MODE BRACKET
    ====================================================== -->

    <?php if (!$current_match): ?>
        <div class="empty-state">
            <div class="icon">🏆</div>
            <h3>Tidak ada match aktif</h3>
            <p>Semua match sudah selesai atau belum ada yang dimulai.</p>
            <a href="manage.php?id=<?= $t_id ?>" class="btn btn-primary btn-sm">Ke Panel Kontrol</a>
        </div>
    <?php else: ?>
        <div class="scoring-layout">
            <!-- Main scoring card -->
            <div class="live-match-card">
                <div class="live-match-header">
                    <div>
                        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--accent-2);">
                            ● Live — Ronde <?= $current_match['round'] ?> · Match <?= $current_match['match_order'] ?>
                        </div>
                        <?php if ($current_set): ?>
                            <div style="font-size:0.85rem;color:var(--text-muted);margin-top:0.2rem;">
                                Set <?= $current_set['set_number'] ?> dari <?= $t['sets_per_match'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_organizer && $current_set): ?>
                        <button class="btn btn-secondary btn-sm" onclick="finishSet()" id="finishSetBtn">
                            ✓ Selesaikan Set <?= $current_set['set_number'] ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="live-match-body">
                    <div class="vs-row">
                        <!-- Player A -->
                        <div class="player-panel">
                            <div class="player-name"><?= htmlspecialchars($current_match['name_a'] ?? 'TBD') ?></div>
                            <div class="player-score" id="scoreA"><?= $current_set ? $current_set['score_a'] : 0 ?></div>
                            <?php if ($is_organizer && $current_set): ?>
                                <div class="player-score-btns">
                                    <button class="score-big-btn sub" onclick="scoreAction('a', -1)">−</button>
                                    <button class="score-big-btn add" onclick="scoreAction('a', 1)">+</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="vs-badge">VS</div>

                        <!-- Player B -->
                        <div class="player-panel">
                            <div class="player-name"><?= htmlspecialchars($current_match['name_b'] ?? 'TBD') ?></div>
                            <div class="player-score" id="scoreB"><?= $current_set ? $current_set['score_b'] : 0 ?></div>
                            <?php if ($is_organizer && $current_set): ?>
                                <div class="player-score-btns">
                                    <button class="score-big-btn sub" onclick="scoreAction('b', -1)">−</button>
                                    <button class="score-big-btn add" onclick="scoreAction('b', 1)">+</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Win counter -->
                    <?php if ($t['sets_per_match'] > 1):
                        $wins_a = 0; $wins_b = 0;
                        foreach ($set_history as $sh) {
                            if ($sh['winner_id'] == $current_match['participant_a_id']) $wins_a++;
                            else $wins_b++;
                        }
                    ?>
                        <div style="text-align:center;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:0.75rem;margin-top:0.5rem;">
                            <span style="font-size:0.78rem;color:var(--text-muted);">Kemenangan Set: </span>
                            <strong style="color:var(--accent);"><?= $wins_a ?></strong>
                            <span style="color:var(--text-dim);"> — </span>
                            <strong style="color:var(--accent);"><?= $wins_b ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Set history sidebar -->
            <div>
                <?php if (!empty($set_history)): ?>
                    <div class="set-history mb-2">
                        <div class="set-history-header">Riwayat Set</div>
                        <?php foreach ($set_history as $sh): ?>
                            <div class="set-row">
                                <span class="set-label">Set <?= $sh['set_number'] ?></span>
                                <span class="set-score"><?= $sh['score_a'] ?> — <?= $sh['score_b'] ?></span>
                                <span class="set-winner-name">🏆 <?= htmlspecialchars($sh['winner_name'] ?? '') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Info match -->
                <div class="card card-sm">
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.75rem;">Info Match</div>
                    <div style="font-size:0.875rem;color:var(--text-muted);display:flex;flex-direction:column;gap:0.4rem;">
                        <div>Format: <strong style="color:var(--text);">Bo<?= $t['sets_per_match'] ?> (<?= $t['sets_per_match'] ?> set)</strong></div>
                        <div>Ronde: <strong style="color:var(--text);"><?= $current_match['round'] ?></strong></div>
                        <div>Match: <strong style="color:var(--text);">#<?= $current_match['match_order'] ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; // end mode bracket ?>

</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
<script>
// ============================================================
// MODE POIN JS
// ============================================================
let currentStep = 1;
const tournamentId  = <?= $t_id ?>;
const isOrganizer   = <?= $is_organizer ? 'true' : 'false' ?>;

function setStep(step, btn) {
    currentStep = step;
    document.querySelectorAll('.increment-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function updatePoint(participantId, direction) {
    if (!isOrganizer) return;
    const delta = direction * currentStep;

    fetch('../../api/points.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&tournament_id=${tournamentId}&participant_id=${participantId}&delta=${delta}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('score_' + participantId).textContent = data.new_points;
            // Re-sort kartu berdasarkan skor baru (refresh halaman simple)
            setTimeout(() => location.reload(), 800);
        }
    })
    .catch(err => console.error('Error:', err));
}

// ============================================================
// MODE BRACKET JS
// ============================================================
const matchId  = <?= $current_match ? $current_match['id'] : 'null' ?>;
const setId    = <?= $current_set ? $current_set['id'] : 'null' ?>;

function scoreAction(player, delta) {
    if (!isOrganizer || !setId) return;
    const scoreEl = document.getElementById('score' + player.toUpperCase());

    fetch('../../api/sets.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=score&set_id=${setId}&player=${player}&delta=${delta}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            scoreEl.textContent = data.new_score;
        }
    })
    .catch(err => console.error('Error:', err));
}

function finishSet() {
    if (!isOrganizer || !setId || !matchId) return;
    const sa = parseInt(document.getElementById('scoreA').textContent);
    const sb = parseInt(document.getElementById('scoreB').textContent);

    if (sa === sb) {
        alert('Skor seri! Tentukan pemenang dulu.');
        return;
    }

    const btn = document.getElementById('finishSetBtn');
    if (btn) btn.disabled = true;

    fetch('../../api/sets.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=finish_set&set_id=${setId}&match_id=${matchId}&tournament_id=${tournamentId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.tournament_finished) {
            alert('🏆 Turnamen selesai! Selamat kepada juara!');
            window.location.href = 'detail.php?id=' + tournamentId;
        } else {
            location.reload();
        }
    })
    .catch(err => {
        console.error('Error:', err);
        if (btn) btn.disabled = false;
    });
}
</script>
</body>
</html>
