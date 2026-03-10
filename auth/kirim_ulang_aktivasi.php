<?php
include "../config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../vendor/autoload.php";

$email = trim($_GET['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: login.php");
    exit;
}

$email_esc = mysqli_real_escape_string($koneksi, $email);

$q_user = mysqli_query($koneksi, "
    select id_pengguna, nama_lengkap, is_verified
    from pengguna
    where email = '$email_esc'
    limit 1
");

if (!$q_user || mysqli_num_rows($q_user) === 0) {
    header("Location: login.php");
    exit;
}

$user = mysqli_fetch_assoc($q_user);

if ((int)$user['is_verified'] === 1) {
    header("Location: login.php?pesan=aktivasi_berhasil");
    exit;
}

$id_pengguna = (int)$user['id_pengguna'];

// nonaktifkan token lama
mysqli_query($koneksi, "
    update email_tokens
    set digunakan = 1
    where id_pengguna = $id_pengguna
    and jenis = 'aktivasi'
    and digunakan = 0
");

// buat token baru
$token = bin2hex(random_bytes(32));
$expired_at = date('Y-m-d H:i:s', time() + 3600);

mysqli_query($koneksi, "
    insert into email_tokens (id_pengguna, token, jenis, expired_at)
    values ($id_pengguna, '$token', 'aktivasi', '$expired_at')
");

// kirim email
$link = "https://toko-aksesoris.infinityfreeapp.com/auth/verifikasi_email.php?token=" . urlencode($token);

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'tugastes77@gmail.com';
$mail->Password = 'eets wvuv cexb rtfy';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

$mail->setFrom('tugastes77@gmail.com', 'Toko Aksesoris');
$mail->addAddress($email);

$mail->isHTML(true);
$mail->Subject = 'Aktivasi Ulang Akun Anda';
$mail->Body = '
<div style="background:#f3f4f6;padding:40px">
    <div style="max-width:520px;margin:auto;background:#fff;border-radius:12px;padding:24px">
        <h2 style="text-align:center">Aktivasi Ulang Akun</h2>
        <p>Halo <b>'.htmlspecialchars($user['nama_lengkap']).'</b>,</p>
        <p>Silakan klik tombol berikut untuk mengaktifkan akun Anda:</p>
        <p style="text-align:center;margin:20px 0">
            <a href="'.$link.'" style="background:#111827;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">
                Aktivasi Akun
            </a>
        </p>
        <p style="font-size:12px;color:#6b7280">Link berlaku 1 jam.</p>
    </div>
</div>';

$mail->send();

header("Location: login.php?pesan=aktivasi_dikirim");
exit;
