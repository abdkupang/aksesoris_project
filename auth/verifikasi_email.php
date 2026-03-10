<?php
include "../config.php";

$token = $_GET['token'] ?? '';

if ($token === '') {
    die("token tidak valid.");
}

$token_esc = mysqli_real_escape_string($koneksi, $token);

$sql = "
    select et.id_token, et.id_pengguna, et.expired_at, et.digunakan
    from email_tokens et
    where et.token = '$token_esc'
    and et.jenis = 'aktivasi'
    limit 1
";

$q = mysqli_query($koneksi, $sql);

if (!$q || mysqli_num_rows($q) === 0) {
    die("token tidak ditemukan.");
}

$data = mysqli_fetch_assoc($q);

if ($data['digunakan'] == 1) {
    die("token sudah digunakan.");
}

if (strtotime($data['expired_at']) < time()) {
    die("token sudah kedaluwarsa.");
}

mysqli_begin_transaction($koneksi);

try {
    mysqli_query($koneksi, "
        update pengguna
        set is_verified = 1,
            email_verified_at = now()
        where id_pengguna = {$data['id_pengguna']}
    ");

    mysqli_query($koneksi, "
        update email_tokens
        set digunakan = 1
        where id_token = {$data['id_token']}
    ");

    mysqli_commit($koneksi);

    header("Location: login.php?pesan=aktivasi_berhasil");
    exit;

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    die("aktivasi gagal.");
}
