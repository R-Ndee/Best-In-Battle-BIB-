<?php
// api/points.php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action         = $_POST['action']         ?? '';
$tournament_id  = (int) ($_POST['tournament_id']  ?? 0);
$participant_id = (int) ($_POST['participant_id'] ?? 0);
$delta          = (int) ($_POST['delta']          ?? 0);
$user_id        = (int) $_SESSION['user_id'];
$role           = $_SESSION['role'];

if ($action === 'update' && $tournament_id && $participant_id) {
    // Cek apakah user adalah organizer turnamen ini
    $t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT organizer_id FROM tournaments WHERE id = $tournament_id AND status = 'ongoing' LIMIT 1"));
    if (!$t) {
        echo json_encode(['success' => false, 'error' => 'Turnamen tidak valid']);
        exit;
    }

    $is_organizer = ((int)$t['organizer_id'] === $user_id) || $role === 'admin';
    if (!$is_organizer) {
        echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
        exit;
    }

    // Update poin (minimal 0)
    mysqli_query($conn, "UPDATE point_scores
                          SET points = GREATEST(0, points + $delta), update_at = NOW()
                          WHERE tournament_id = $tournament_id AND participant_id = $participant_id");

    $new = mysqli_fetch_assoc(mysqli_query($conn, "SELECT points FROM point_scores WHERE tournament_id = $tournament_id AND participant_id = $participant_id"));
    echo json_encode(['success' => true, 'new_points' => (int)($new['points'] ?? 0)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action tidak dikenal']);
