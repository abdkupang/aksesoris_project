<?php
// file: pesanan_batal.php
include "config.php";

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_SESSION['id_pengguna']) ||
    $_SESSION['role'] !== 'pembeli'
) {
    header("Location: pesanan_saya.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$id_pesanan  = (int) ($_POST['id_pesanan'] ?? 0);

if ($id_pesanan <= 0) {
    header("Location: pesanan_saya.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDASI PESANAN
|--------------------------------------------------------------------------
*/
$sql_pesanan = "
    SELECT
        p.id_pesanan,
        p.status_pesanan,
        p.dibuat_pada
    FROM pesanan p
    WHERE p.id_pesanan = $id_pesanan
      AND p.id_pengguna = $id_pengguna
    LIMIT 1
";

$q_pesanan = mysqli_query($koneksi, $sql_pesanan);
$pesanan   = mysqli_fetch_assoc($q_pesanan);

if (!$pesanan) {
    header("Location: pesanan_saya.php?pesan=pesanan_tidak_ditemukan");
    exit;
}

if ($pesanan['status_pesanan'] !== 'menunggu_pembayaran') {
    header("Location: pesanan_saya.php?pesan=status_tidak_bisa_batal");
    exit;
}

if (strtotime($pesanan['dibuat_pada']) < time() - 86400) {
    header("Location: pesanan_saya.php?pesan=lewat_batas_waktu");
    exit;
}

/*
|--------------------------------------------------------------------------
| TRANSACTION: BATALKAN PESANAN + KEMBALIKAN STOK
|--------------------------------------------------------------------------
*/
mysqli_begin_transaction($koneksi);

try {

    // 1. Ambil item pesanan
    $sql_items = "
        SELECT id_produk, jumlah
        FROM pesanan_item
        WHERE id_pesanan = $id_pesanan
    ";

    $q_items = mysqli_query($koneksi, $sql_items);
    if (!$q_items) {
        throw new Exception(mysqli_error($koneksi));
    }

    // 2. Kembalikan stok per produk
    while ($item = mysqli_fetch_assoc($q_items)) {

        $id_produk = (int) $item['id_produk'];
        $jumlah    = (int) $item['jumlah'];

        $sql_update_stok = "
            UPDATE produk
            SET stok = stok + $jumlah
            WHERE id_produk = $id_produk
        ";

        if (!mysqli_query($koneksi, $sql_update_stok)) {
            throw new Exception(mysqli_error($koneksi));
        }
    }

    // 3. Update status pesanan
    $sql_update_pesanan = "
        UPDATE pesanan
        SET status_pesanan = 'dibatalkan'
        WHERE id_pesanan = $id_pesanan
          AND id_pengguna = $id_pengguna
    ";

    if (!mysqli_query($koneksi, $sql_update_pesanan)) {
        throw new Exception(mysqli_error($koneksi));
    }

    // 4. Commit
    mysqli_commit($koneksi);

    header("Location: pesanan_saya.php?pesan=pesanan_dibatalkan");
    exit;

} catch (Exception $e) {

    mysqli_rollback($koneksi);
    die("Gagal membatalkan pesanan: " . $e->getMessage());
}
