<?php
$host     = 'localhost';
$db_name  = 'jaldb';
$username = 'root';
$password = '';

$conn = mysqli_connect($host, $username, $password, $db_name);

if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}
?>