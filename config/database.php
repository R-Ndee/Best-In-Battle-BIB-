<?php
define('BASE_URL', '');

$host     = 'sql113.infinityfree.com';
$db_name  = 'if0_42037972_jaldb';
$username = 'if0_42037972';
$password = 'ndeesuajah';

$conn = mysqli_connect($host, $username, $password, $db_name);

if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
?>