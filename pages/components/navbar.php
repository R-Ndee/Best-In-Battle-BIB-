<?php
// pages/components/navbar.php
// Requires: session_start() sudah dipanggil sebelum include ini
// Uses: $_SESSION['user_id'], $_SESSION['role'], $_SESSION['username']

$current_role = $_SESSION['role'] ?? 'guest';
$current_username = $_SESSION['username'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? 0;

// Helper: hitung selisih waktu human-readable
if (!function_exists('human_time_diff')) {
    function human_time_diff($datetime)
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return "Baru saja";
        if ($diff < 3600) return floor($diff / 60) . " menit lalu";
        if ($diff < 86400) return floor($diff / 3600) . " jam lalu";
        return floor($diff / 86400) . " hari lalu";
    }
}

// Ambil jumlah notif belum dibaca (hanya kalau sudah login)
$unread_count = 0;
if ($current_user_id && isset($conn)) {
    $notif_query = "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = $current_user_id AND is_read = 0";
    $notif_result = mysqli_query($conn, $notif_query);
    if ($notif_result) {
        $unread_count = (int) mysqli_fetch_assoc($notif_result)['cnt'];
    }
}


// Hitung base path ke root
$script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$root   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$rel    = str_replace($root, '', $script);
$depth  = substr_count(trim($rel, '/'), '/');
$base   = '/';


// Tentukan halaman aktif dari URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<nav class="navbar">
    <div class="container">
        <!-- Brand -->
        <a href="<?= $base ?>index.php" class="navbar-brand">
            <div class="logo-icon">BIB</div>
            <span>BEST IN <span class="accent">BATTLE</span></span>
        </a>

        <!-- Desktop Nav -->
        <ul class="navbar-nav">
            <li><a href="<?= $base ?>index.php" class="<?= $current_page === 'index' ? 'active' : '' ?>">Beranda</a>
            <li><a href="<?= $base ?>pages/about.php" class="<?= $current_page === 'about' ? 'active' : '' ?>">Tim</a></li>
            </li>

            <?php if ($current_role === 'member'): ?>
                <li><a href="<?= $base ?>pages/member/dashboard.php"
                        class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="<?= $base ?>pages/tournament/proposal.php"
                        class="<?= $current_page === 'proposal' ? 'active' : '' ?>">Buat Turnamen</a></li>
            <?php endif; ?>

            <?php if ($current_role === 'admin'): ?>
                <li><a href="<?= $base ?>pages/admin/dashboard.php"
                        class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="<?= $base ?>pages/admin/proposals.php"
                        class="<?= $current_page === 'proposals' ? 'active' : '' ?>">Proposal</a></li>
                <li><a href="<?= $base ?>pages/admin/users.php"
                        class="<?= $current_page === 'users' ? 'active' : '' ?>">Pengguna</a></li>
            <?php endif; ?>
        </ul>

        <!-- Right side -->
        <div class="navbar-right">
            <!-- Theme toggle -->
            <button class="theme-toggle" id="themeToggle" title="Ganti tema" aria-label="Toggle tema">
                <span id="themeIcon">🌙</span>
            </button>

            <?php if ($current_role !== 'guest'): ?>
                <!-- Notifikasi -->
                <div style="position:relative;">
                    <button class="notif-btn" id="notifBtn" aria-label="Notifikasi" aria-expanded="false">
                        🔔
                        <?php if ($unread_count > 0): ?>
                            <span class="notif-dot"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Dropdown notif -->
                    <div class="notif-dropdown" id="notifDropdown" role="dialog" aria-label="Panel notifikasi">
                        <div class="notif-header">
                            <h4>
                                Notifikasi
                                <?php if ($unread_count > 0): ?>
                                    <span class="notif-badge"><?= $unread_count ?> Baru</span>
                                <?php endif; ?>
                            </h4>
                            <?php if ($unread_count > 0): ?>
                                <a href="<?= $base ?>api/notifications.php?action=read_all"
                                    style="font-size:0.72rem;font-weight:700;color:var(--accent);">Tandai Semua</a>
                            <?php endif; ?>
                        </div>

                        <div style="max-height:300px;overflow-y:auto;" class="custom-scrollbar">
                            <?php
                            if ($current_user_id && isset($conn)) {
                                $n_query = "SELECT * FROM notifications WHERE user_id = $current_user_id ORDER BY created_at DESC LIMIT 10";
                                $n_result = mysqli_query($conn, $n_query);
                                if ($n_result && mysqli_num_rows($n_result) > 0) {
                                    while ($n = mysqli_fetch_assoc($n_result)) {
                                        $cls = $n['is_read'] ? '' : 'unread';
                                        $ind = $n['is_read'] ? 'read' : 'unread';
                                        $time_diff = human_time_diff($n['created_at']);
                                        echo "<a href='{$base}api/notifications.php?action=read&id={$n['id']}' class='notif-item $cls' style='text-decoration:none;display:flex;'>
                                            <div class='notif-indicator $ind'></div>
                                            <div>
                                                <div class='notif-text'>" . htmlspecialchars($n['message']) . "</div>
                                                <div class='notif-time'>$time_diff</div>
                                            </div>
                                            </a>";
                                            }
                                } else {
                                    echo "<div class='notif-item'><div class='notif-text' style='color:var(--text-dim);'>Tidak ada notifikasi.</div></div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- User avatar + nama -->
                <a href="<?= $base ?>pages/member/profile.php" style="display:flex;align-items:center;gap:0.6rem;text-decoration:none;">
                    <div style="text-align:right;display:none;" class="show-md">
                        <div style="font-size:0.82rem;font-weight:700;color:var(--text);">
                            <?= htmlspecialchars($current_username) ?></div>
                        <div style="font-size:0.7rem;color:var(--accent);"><?= ucfirst($current_role) ?></div>
                    </div>
                    <div class="user-avatar"><?= strtoupper(substr($current_username, 0, 1)) ?></div>
                </a>

                <!-- Logout -->
                <a href="<?= $base ?>pages/auth/logout.php" class="btn btn-sm btn-danger" title="Logout">↩</a>

            <?php else: ?>
                <a href="<?= $base ?>pages/auth/login.php" class="btn btn-primary btn-sm">Masuk / Daftar</a>
            <?php endif; ?>

            <!-- Hamburger mobile -->
            <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">☰</button>
        </div>
    </div>
</nav>

<!-- Mobile Nav -->
<div class="mobile-nav" id="mobileNav">
    <a href="<?= $base ?>index.php">🏠 Beranda</a>
    <a href="<?= $base ?>pages/about.php">👥 Tim Pengembang</a>

    <?php if ($current_role === 'member'): ?>
        <a href="<?= $base ?>pages/member/dashboard.php">📊 Dashboard</a>
        <a href="<?= $base ?>pages/tournament/proposal.php">➕ Buat Turnamen</a>
        <a href="<?= $base ?>pages/member/profile.php">👤 Profil Saya</a>
    <?php endif; ?>

    <?php if ($current_role === 'admin'): ?>
        <a href="<?= $base ?>pages/admin/dashboard.php">📊 Dashboard Admin</a>
        <a href="<?= $base ?>pages/admin/proposals.php">📋 Kelola Proposal</a>
        <a href="<?= $base ?>pages/admin/users.php">👥 Kelola User</a>
    <?php endif; ?>

    <?php if ($current_role !== 'guest'): ?>
        <a href="<?= $base ?>pages/auth/logout.php" style="color:var(--accent-red);">↩ Logout</a>
    <?php else: ?>
        <a href="<?= $base ?>pages/auth/login.php" style="background:var(--accent);color:#0C0F0E;border-color:var(--accent);">Masuk /
            Daftar</a>
    <?php endif; ?>
</div>
