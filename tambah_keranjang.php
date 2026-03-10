<?php
include "config.php";

// hanya menerima metode post
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: toko.php");
    exit;
}

// cek login dan role
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    // belum login → minta login dulu
    header("Location: login.php?pesan=login_dulu&redirect=toko.php");
    exit;
}

if ($_SESSION['role'] !== 'pembeli') {
    // jika penjual atau role lain mencoba pesan
    header("Location: toko.php?pesan=bukan_pembeli");
    exit;
}

$id_pengguna = (int)$_SESSION['id_pengguna'];

// ambil data dari form
$id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
$jumlah    = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 1;
$redirect  = isset($_POST['redirect']) ? trim($_POST['redirect']) : "";

// validasi jumlah
if ($jumlah <= 0) {
    $jumlah = 1;
}

if ($id_produk <= 0) {
    header("Location: toko.php?pesan=produk_tidak_valid");
    exit;
}

// ambil data produk
$sql_produk = "
    select id_produk, nama_produk, harga, stok, status_produk
    from produk
    where id_produk = $id_produk
    limit 1
";
$q_produk = mysqli_query($koneksi, $sql_produk);

if (!$q_produk || mysqli_num_rows($q_produk) === 0) {
    header("Location: toko.php?pesan=produk_tidak_ditemukan");
    exit;
}

$produk = mysqli_fetch_assoc($q_produk);

// cek status produk dan stok
if ($produk['status_produk'] !== 'aktif' || $produk['stok'] <= 0) {
    header("Location: toko.php?pesan=produk_tidak_dapat_dipesan");
    exit;
}

// opsional: batasi jumlah agar tidak melebihi stok
if ($jumlah > $produk['stok']) {
    $jumlah = (int)$produk['stok'];
}

// cek apakah pembeli sudah punya keranjang
$sql_keranjang = "
    select id_keranjang
    from keranjang
    where id_pengguna = $id_pengguna
    limit 1
";
$q_keranjang = mysqli_query($koneksi, $sql_keranjang);

if ($q_keranjang && mysqli_num_rows($q_keranjang) > 0) {
    $row_keranjang = mysqli_fetch_assoc($q_keranjang);
    $id_keranjang  = (int)$row_keranjang['id_keranjang'];
} else {
    // belum ada keranjang → buat baru
    $sql_buat_keranjang = "
        insert into keranjang (id_pengguna)
        values ($id_pengguna)
    ";
    $q_buat = mysqli_query($koneksi, $sql_buat_keranjang);

    if (!$q_buat) {
        die("gagal membuat keranjang: " . mysqli_error($koneksi));
    }

    $id_keranjang = (int)mysqli_insert_id($koneksi);
}

// cek apakah produk sudah ada di keranjang_item
$sql_item = "
    select id_keranjang_item, jumlah
    from keranjang_item
    where id_keranjang = $id_keranjang
    and id_produk = $id_produk
    limit 1
";
$q_item = mysqli_query($koneksi, $sql_item);

$harga_saat_ini = $produk['harga'];

if ($q_item && mysqli_num_rows($q_item) > 0) {
    // sudah ada → update jumlah
    $item         = mysqli_fetch_assoc($q_item);
    $jumlah_baru  = (int)$item['jumlah'] + $jumlah;

    // opsional: tidak boleh lebih dari stok
    if ($jumlah_baru > $produk['stok']) {
        $jumlah_baru = (int)$produk['stok'];
    }

    $sql_update_item = "
        update keranjang_item
        set jumlah = $jumlah_baru,
            harga_saat_ini = $harga_saat_ini,
            diperbarui_pada = now()
        where id_keranjang_item = " . (int)$item['id_keranjang_item'] . "
    ";
    $q_update = mysqli_query($koneksi, $sql_update_item);

    if (!$q_update) {
        die("gagal mengupdate keranjang: " . mysqli_error($koneksi));
    }
} else {
    // belum ada → insert baris baru
    $sql_insert_item = "
        insert into keranjang_item (id_keranjang, id_produk, jumlah, harga_saat_ini)
        values ($id_keranjang, $id_produk, $jumlah, $harga_saat_ini)
    ";
    $q_insert_item = mysqli_query($koneksi, $sql_insert_item);

    if (!$q_insert_item) {
        die("gagal menambahkan ke keranjang: " . mysqli_error($koneksi));
    }
}

// tentukan redirect (sanitize sederhana, hindari url eksternal)
$redirect_url = "toko.php?pesan=tambah_sukses";

if (!empty($redirect) && strpos($redirect, "://") === false && strpos($redirect, "\n") === false) {
    // jika redirect sudah punya query, jangan double '?'
    if (strpos($redirect, 'pesan=') === false) {
        $joiner       = (strpos($redirect, '?') === false) ? '?' : '&';
        $redirect_url = $redirect . $joiner . "pesan=tambah_sukses";
    } else {
        $redirect_url = $redirect;
    }
}

header("Location: " . $redirect_url);
exit;
