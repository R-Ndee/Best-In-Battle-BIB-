<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /pages/auth/login.php');
    exit;
}

$admin_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']             ?? '');
    $description      = trim($_POST['description']      ?? '');
    $mode             = $_POST['mode']             ?? 'bracket';
    $participant_type = $_POST['participant_type'] ?? 'individual';
    $sets_per_match   = null;

    if (empty($name)) {
        $_SESSION['error'] = 'Nama turnamen wajib diisi.';
        header('Location: create_admin.php');
        exit;
    }

    if (!in_array($mode, ['bracket', 'point'])) $mode = 'bracket';
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
               VALUES ('$name_safe', '$desc_safe', '$mode', '$participant_type', $sets_val, $admin_id, 'open')";

    if (mysqli_query($conn, $insert)) {
        $new_id = mysqli_insert_id($conn);
        $_SESSION['success'] = "Turnamen '$name' berhasil dibuat dan langsung aktif!";
        header("Location: /pages/tournament/detail.php?id=$new_id");
    } else {
        $_SESSION['error'] = 'Gagal membuat turnamen.';
        header('Location: create_admin.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Turnamen Langsung — BIB Admin</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/tournament.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>

<div class="container page-wrap" style="max-width:780px;">
    <?php include '../components/alert.php'; ?>

    <div class="page-header">
        <div class="page-header-info">
            <div class="page-label">Admin — Tanpa Approval</div>
            <h1>Buat Turnamen Langsung</h1>
            <p>Turnamen langsung berstatus <strong>Open</strong> tanpa melalui proses approval.</p>
        </div>
        <a href="../../pages/admin/dashboard.php" class="btn btn-secondary btn-sm">← Kembali</a>
    </div>

    <form method="POST" action="create_admin.php">

        <div class="form-section">
            <div class="form-section-title">📝 Info Dasar</div>
            <div class="form-group">
                <label class="form-label" for="name">Nama Turnamen <span style="color:var(--accent-red)">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Nama turnamen" maxlength="100" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="description">Deskripsi</label>
                <textarea id="description" name="description" class="form-control" rows="4"
                          placeholder="Deskripsi turnamen (opsional)..."
                          maxlength="2000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">⚙️ Pengaturan</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Sistem Pertandingan</label>
                    <div class="radio-group">
                        <div class="radio-card">
                            <input type="radio" id="mode_bracket" name="mode" value="bracket"
                                   <?= ($_POST['mode'] ?? 'bracket') === 'bracket' ? 'checked' : '' ?>
                                   onchange="toggleSets()">
                            <label for="mode_bracket">⚔️ Bracket</label>
                        </div>
                        <div class="radio-card">
                            <input type="radio" id="mode_poin" name="mode" value="point"
                                   <?= ($_POST['mode'] ?? '') === 'point' ? 'checked' : '' ?>
                                   onchange="toggleSets()">
                            <label for="mode_poin">📊 Mode Poin</label>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Format Partisipan</label>
                    <div class="radio-group">
                        <div class="radio-card">
                            <input type="radio" id="type_ind" name="participant_type" value="individual"
                                   <?= ($_POST['participant_type'] ?? 'individual') === 'individual' ? 'checked' : '' ?>>
                            <label for="type_ind">👤 Individu</label>
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

        <div class="form-section sets-toggle" id="setsSection">
            <div class="form-section-title">🎮 Jumlah Set per Match</div>
            <div class="radio-group">
                <?php foreach ([1 => 'Bo1 — Satu Set', 3 => 'Bo3 — Tiga Set', 5 => 'Bo5 — Lima Set'] as $v => $l): ?>
                    <div class="radio-card">
                        <input type="radio" id="sets_<?= $v ?>" name="sets_per_match" value="<?= $v ?>"
                               <?= ((int)($_POST['sets_per_match'] ?? 1)) === $v ? 'checked' : '' ?>>
                        <label for="sets_<?= $v ?>"><?= $l ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Admin info box -->
        <div class="alert alert-info">
            ℹ️ Kamu login sebagai <strong>Admin</strong>. Turnamen ini akan langsung berstatus <strong>Open</strong> dan kamu otomatis menjadi Organizer.
        </div>

        <div style="display:flex;justify-content:flex-end;gap:1rem;">
            <a href="../../pages/admin/dashboard.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary btn-lg">🚀 Buat & Aktifkan Turnamen</button>
        </div>
    </form>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
<script>
function toggleSets() {
    const isBracket = document.getElementById('mode_bracket').checked;
    document.getElementById('setsSection').classList.toggle('visible', isBracket);
}
document.addEventListener('DOMContentLoaded', toggleSets);
</script>
</body>
</html>
