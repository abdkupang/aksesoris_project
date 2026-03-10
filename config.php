<?php
// konfigurasi database
$host       = "localhost";
$user       = "root";          // ganti jika diperlukan
$pass       = "";              // ganti jika ada password
$db         = "db_penjualan_aksesoris";

// membuat koneksi
$koneksi = mysqli_connect($host, $user, $pass, $db);

// pengecekan koneksi
if (!$koneksi) {
    die("gagal terhubung ke database: " . mysqli_connect_error());
}

// aktifkan session untuk seluruh halaman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
