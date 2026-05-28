<?php
// api/sets.php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action        = $_POST['action']        ?? '';
$set_id        = (int) ($_POST['set_id']        ?? 0);
$match_id      = (int) ($_POST['match_id']      ?? 0);
$tournament_id = (int) ($_POST['tournament_id'] ?? 0);
$player        = $_POST['player']        ?? '';
$delta         = (int) ($_POST['delta']         ?? 0);
$user_id       = (int) $_SESSION['user_id'];
$role          = $_SESSION['role'];

// Helper: cek organizer
function check_organizer($conn, $tournament_id, $user_id, $role) {
    $t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT organizer_id FROM tournaments WHERE id = $tournament_id LIMIT 1"));
    return $t && ((int)$t['organizer_id'] === $user_id || $role === 'admin');
}

// ============================================================
// UPDATE SKOR SET
// ============================================================
if ($action === 'score' && $set_id && in_array($player, ['a', 'b'])) {
    $set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.*, m.tournament_id FROM sets s JOIN matches m ON s.match_id = m.id WHERE s.id = $set_id AND s.status = 'ongoing' LIMIT 1"));
    if (!$set) { echo json_encode(['success' => false, 'error' => 'Set tidak ditemukan']); exit; }

    if (!check_organizer($conn, $set['tournament_id'], $user_id, $role)) {
        echo json_encode(['success' => false, 'error' => 'Akses ditolak']); exit;
    }

    $col      = $player === 'a' ? 'score_a' : 'score_b';
    $current  = (int) $set[$col];
    $new_val  = max(0, $current + $delta);

    mysqli_query($conn, "UPDATE sets SET $col = $new_val WHERE id = $set_id");
    echo json_encode(['success' => true, 'new_score' => $new_val]);
    exit;
}

// ============================================================
// SELESAIKAN SET
// ============================================================
if ($action === 'finish_set' && $set_id && $match_id && $tournament_id) {
    if (!check_organizer($conn, $tournament_id, $user_id, $role)) {
        echo json_encode(['success' => false, 'error' => 'Akses ditolak']); exit;
    }

    $set   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM sets WHERE id = $set_id AND status = 'ongoing' LIMIT 1"));
    $match = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM matches WHERE id = $match_id LIMIT 1"));
    $t     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tournaments WHERE id = $tournament_id LIMIT 1"));

    if (!$set || !$match || !$t) { echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']); exit; }

    $score_a = (int) $set['score_a'];
    $score_b = (int) $set['score_b'];

    if ($score_a === $score_b) { echo json_encode(['success' => false, 'error' => 'Skor seri']); exit; }

    // Tentukan pemenang set
    $set_winner_id = $score_a > $score_b ? $match['participant_a_id'] : $match['participant_b_id'];
    mysqli_query($conn, "UPDATE sets SET winner_id = $set_winner_id, status = 'finished' WHERE id = $set_id");

    // Hitung total kemenangan set
    $sets_per_match = (int) $t['sets_per_match'];
    $needed_to_win  = (int) ceil($sets_per_match / 2);

    $wins_a = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM sets WHERE match_id = $match_id AND winner_id = {$match['participant_a_id']} AND status = 'finished'"))['c'];
    $wins_b = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM sets WHERE match_id = $match_id AND winner_id = {$match['participant_b_id']} AND status = 'finished'"))['c'];

    $match_winner_id = null;
    if ($wins_a >= $needed_to_win) $match_winner_id = $match['participant_a_id'];
    if ($wins_b >= $needed_to_win) $match_winner_id = $match['participant_b_id'];

    if ($match_winner_id) {
        // Match selesai
        mysqli_query($conn, "UPDATE matches SET winner_id = $match_winner_id, status = 'finished' WHERE id = $match_id");

        // Auto-advance: cari match di ronde berikutnya dengan slot kosong
        $next_round = (int) $match['round'] + 1;
        $match_order = (int) $match['match_order'];
        $next_match_order = (int) ceil($match_order / 2);
        $slot = ($match_order % 2 === 1) ? 'participant_a_id' : 'participant_b_id';

        $next_match = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM matches WHERE tournament_id = $tournament_id AND round = $next_round AND match_order = $next_match_order LIMIT 1"));

        if ($next_match) {
            mysqli_query($conn, "UPDATE matches SET $slot = $match_winner_id WHERE id = {$next_match['id']}");

            // Kalau kedua slot terisi, set jadi live
            $nm = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM matches WHERE id = {$next_match['id']}"));
            if ($nm['participant_a_id'] && $nm['participant_b_id']) {
                mysqli_query($conn, "UPDATE matches SET status = 'ongoing' WHERE id = {$next_match['id']}");
            }
        } else {
            // Tidak ada ronde berikutnya — turnamen selesai
            mysqli_query($conn, "UPDATE tournaments SET status = 'finished' WHERE id = $tournament_id");
            echo json_encode(['success' => true, 'tournament_finished' => true, 'winner_id' => $match_winner_id]);
            exit;
        }

        // Cek apakah ada match berikutnya yang bisa dimulai di ronde yang sama
        $next_in_round = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM matches WHERE tournament_id = $tournament_id AND status = 'pending' AND participant_a_id IS NOT NULL AND participant_b_id IS NOT NULL ORDER BY round ASC, match_order ASC LIMIT 1"));
        if ($next_in_round) {
            mysqli_query($conn, "UPDATE matches SET status = 'ongoing' WHERE id = {$next_in_round['id']}");
        }

        echo json_encode(['success' => true, 'match_finished' => true, 'winner_id' => $match_winner_id]);
    } else {
        // Match belum selesai, buat set baru
        $next_set_num = (int) $set['set_number'] + 1;
        mysqli_query($conn, "INSERT INTO sets (match_id, set_number, score_a, score_b, status) VALUES ($match_id, $next_set_num, 0, 0, 'ongoing')");
        echo json_encode(['success' => true, 'match_finished' => false, 'next_set' => $next_set_num]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action tidak dikenal']);
