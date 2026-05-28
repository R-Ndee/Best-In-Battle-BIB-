<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/auth/login.php');
    exit;
}
if ($_SESSION['role'] === 'admin') {
    header('Location: create_admin.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']             ?? '');
    $description      = trim($_POST['description']      ?? '');
    $mode             = $_POST['mode']             ?? 'bracket';
    $participant_type = $_POST['participant_type'] ?? 'individual';
    $sets_per_match   = null;

    if (empty($name)) {
        $_SESSION['error'] = 'Nama turnamen wajib diisi.';
        header('Location: proposal.php');
        exit;
    }

    if (!in_array($mode, ['bracket', 'poin'])) $mode = 'bracket';
    if (!in_array($participant_type, ['individual', 'team'])) $participant_type = 'individual';

    if ($mode === 'bracket') {
        $sets = (int) ($_POST['sets_per_match'] ?? 1);
        if (!in_array($sets, [1, 3, 5])) $sets = 1;
        $sets_per_match = $sets;
    }

    $name_safe = mysqli_real_escape_string($conn, $name);
    $desc_safe = mysqli_real_escape_string($conn, $description);
    $sets_val  = $sets_per_match !== null ? $sets_per_match : 'NULL';

    $insert = "INSERT INTO tournaments (name, description, mode, participant_type, sets_per_match, organizer_id, status)
               VALUES ('$name_safe', '$desc_safe', '$mode', '$participant_type', $sets_val, $user_id, 'pending')";

    if (mysqli_query($conn, $insert)) {
        // Notif ke semua admin
        $admins = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin'");
        while ($a = mysqli_fetch_assoc($admins)) {
            $notif_msg  = mysqli_real_escape_string($conn, "Proposal turnamen baru '$name' menunggu review Anda.");
            $notif_link = mysqli_real_escape_string($conn, "pages/admin/proposals.php");
            mysqli_query($conn, "INSERT INTO notifications (user_id, message, link) VALUES ({$a['id']}, '$notif_msg', '$notif_link')");
        }
        $_SESSION['success'] = 'Proposal berhasil diajukan! Tunggu persetujuan Admin.';
        header('Location: ../../pages/member/dashboard.php');
    } else {
        $_SESSION['error'] = 'Gagal menyimpan proposal. Coba lagi.';
        header('Location: proposal.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Proposal Turnamen — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/tournament.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>

<div class="container page-wrap" style="max-width:780px;">
    <?php include '../components/alert.php'; ?>

    <div class="page-header">
        <div class="page-header-info">
            <div class="page-label">Member</div>
            <h1>Ajukan Proposal Turnamen</h1>
            <p>Lengkapi form berikut. Admin akan meninjau dan menyetujui proposalmu.</p>
        </div>
        <a href="../../pages/member/dashboard.php" class="btn btn-secondary btn-sm">← Kembali</a>
    </div>

    <form method="POST" action="proposal.php" id="proposalForm">

        <!-- Nama -->
        <div class="form-section">
            <div class="form-section-title">📝 Info Dasar</div>
            <div class="form-group">
                <label class="form-label" for="name">Nama Turnamen <span style="color:var(--accent-red)">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Contoh: Kejuaraan Nusantara Series #1"
                       maxlength="100" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="description">Deskripsi Turnamen</label>
                <textarea id="description" name="description" class="form-control" rows="4"
                          placeholder="Jelaskan detail, aturan, atau cerita di balik kompetisi ini..."
                          maxlength="2000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <div class="form-hint"><span id="desc_count">0</span>/2000 karakter</div>
            </div>
        </div>

        <!-- Mode & Format -->
        <div class="form-section">
            <div class="form-section-title">⚙️ Pengaturan Turnamen</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Sistem Pertandingan</label>
                    <div class="radio-group">
                        <div class="radio-card">
                            <input type="radio" id="mode_bracket" name="mode" value="bracket"
                                   <?= ($_POST['mode'] ?? 'bracket') === 'bracket' ? 'checked' : '' ?>
                                   onchange="toggleSetsField()">
                            <label for="mode_bracket">⚔️ Bracket</label>
                        </div>
                        <div class="radio-card">
                            <input type="radio" id="mode_poin" name="mode" value="poin"
                                   <?= ($_POST['mode'] ?? '') === 'poin' ? 'checked' : '' ?>
                                   onchange="toggleSetsField()">
                            <label for="mode_poin">📊 Mode Poin</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Format Partisipan</label>
                    <div class="radio-group">
                        <div class="radio-card">
                            <input type="radio" id="type_individual" name="participant_type" value="individual"
                                   <?= ($_POST['participant_type'] ?? 'individual') === 'individual' ? 'checked' : '' ?>>
                            <label for="type_individual">👤 Individu</label>
                        </div>
                        <div class="radio-card">
                            <input type="radio" id="type_team" name="participant_type" value="team"
                                   <?= ($_POST['participant_type'] ?? '') === 'team' ? 'checked' : '' ?>>
                            <label for="type_team">👥 Tim</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sets (hanya bracket) -->
        <div class="form-section sets-toggle" id="setsSection">
            <div class="form-section-title">🎮 Jumlah Set per Match</div>
            <p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1.25rem;">
                Tentukan berapa set yang dimainkan di setiap pertandingan. Jumlah harus ganjil agar ada pemenang pasti.
            </p>
            <div class="radio-group">
                <?php
                $sets_options = [
                    1 => 'Bo1 — Satu Set',
                    3 => 'Bo3 — Tiga Set',
                    5 => 'Bo5 — Lima Set',
                ];
                $selected_sets = (int) ($_POST['sets_per_match'] ?? 1);
                foreach ($sets_options as $val => $label):
                ?>
                    <div class="radio-card">
                        <input type="radio" id="sets_<?= $val ?>" name="sets_per_match" value="<?= $val ?>"
                               <?= $selected_sets === $val ? 'checked' : '' ?>>
                        <label for="sets_<?= $val ?>"><?= $label ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:0.5rem;">
            <a href="../../pages/member/dashboard.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary btn-lg">
                📤 Kirim Proposal
            </button>
        </div>
    </form>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
<script>
// Tampilkan/sembunyikan field sets
function toggleSetsField() {
    const isBracket = document.getElementById('mode_bracket').checked;
    const section   = document.getElementById('setsSection');
    section.classList.toggle('visible', isBracket);
}

// Init saat load
document.addEventListener('DOMContentLoaded', function () {
    toggleSetsField();

    // Counter deskripsi
    const desc     = document.getElementById('description');
    const counter  = document.getElementById('desc_count');
    function updateCount() { counter.textContent = desc.value.length; }
    desc.addEventListener('input', updateCount);
    updateCount();
});
</script>
</body>
</html>
