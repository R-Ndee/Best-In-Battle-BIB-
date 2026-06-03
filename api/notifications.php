<?php
// api/notifications.php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: pages/auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';
$notif_id = (int) ($_GET['id'] ?? 0);

// Tandai satu notifikasi sebagai dibaca, lalu redirect
if ($action === 'read' && $notif_id) {
    $res   = mysqli_query($conn, "SELECT link FROM notifications WHERE id = $notif_id AND user_id = $user_id");
    $notif = mysqli_fetch_assoc($res);
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id");

    // link dari DB sudah relative dari root project, tambah ../
    $redirect = (!empty($notif['link']))
        ? '../' . $notif['link']
        : '../pages/member/dashboard.php';

    header("Location: $redirect");
    exit;
}

if ($action === 'read_all') {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    $ref = $_SERVER['HTTP_REFERER'] ?? '../pages/member/dashboard.php';
    header("Location: $ref");
    exit;
}

header('Location: /pages/member/dashboard.php');
exit;