<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/auth/login.php');
    exit;
}

$t_id    = (int) ($_GET['id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'];

if (!$t_id) {
    header('Location: /index.php');
    exit;
}

$t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tournaments WHERE id = $t_id LIMIT 1"));
if (!$t) {
    $_SESSION['error'] = 'Turnamen tidak ditemukan.';
    header('Location: /index.php');
    exit;
}

// Hanya organizer atau admin
$is_organizer = ((int)$t['organizer_id'] === $user_id) || $role === 'admin';
if (!$is_organizer) {
    $_SESSION['error'] = 'Akses ditolak.';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}

// Hanya bisa edit kalau status pending atau open
if (!in_array($t['status'], ['pending', 'open'])) {
    $_SESSION['error'] = 'Turnamen yang sudah berjalan tidak bisa diedit.';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}

// Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $_SESSION['error'] = 'Nama turnamen wajib diisi.';
        header("Location: /pages/tournament/edit.php?id=$t_id");
        exit;
    }

    $name_safe = mysqli_real_escape_string($conn, $name);
    $desc_safe = mysqli_real_escape_string($conn, $description);

    mysqli_query($conn, "UPDATE tournaments SET name='$name_safe', description='$desc_safe' WHERE id=$t_id");

    $_SESSION['success'] = 'Turnamen berhasil diperbarui.';
    header("Location: /pages/tournament/detail.php?id=$t_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Turnamen — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/tournament.css">
</head>
<body class="page-fade">
<?php include '../components/navbar.php'; ?>

<div class="container page-wrap" style="max-width:680px;">
    <?php include '../components/alert.php'; ?>

    <div class="page-header">
        <div class="page-header-info">
            <div class="page-label">Edit Turnamen</div>
            <h1>Ubah Info Turnamen</h1>
            <p>Hanya nama dan deskripsi yang bisa diubah.</p>
        </div>
        <a href="/pages/tournament/detail.php?id=<?= $t_id ?>" class="btn btn-secondary btn-sm">← Kembali</a>
    </div>

    <div class="form-section">
        <form method="POST" action="/pages/tournament/edit.php?id=<?= $t_id ?>">

            <div class="form-group">
                <label class="form-label">Nama Turnamen <span style="color:var(--accent-red)">*</span></label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($t['name']) ?>"
                       maxlength="100" required>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="5"
                          maxlength="2000"><?= htmlspecialchars($t['description'] ?? '') ?></textarea>
            </div>

            <!-- Info yang tidak bisa diubah -->
            <div style="margin-top:1.5rem;padding:1rem 1.25rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);">
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-dim);margin-bottom:0.75rem;">
                    Info yang tidak bisa diubah
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem;font-size:0.82rem;color:var(--text-muted);">
                    <span>Mode: <strong style="color:var(--text);"><?= $t['mode'] === 'bracket' ? 'Bracket' : 'Mode Poin' ?></strong></span>
                    <span>Format: <strong style="color:var(--text);"><?= ucfirst($t['participant_type']) ?></strong></span>
                    <?php if ($t['sets_per_match']): ?>
                        <span>Set: <strong style="color:var(--text);">Bo<?= $t['sets_per_match'] ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem;">
                <a href="/pages/tournament/detail.php?id=<?= $t_id ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include '../components/footer.php'; ?>
<script src="../../assets/js/global.js"></script>
</body>
</html>