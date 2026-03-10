<?php
include "../config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../vendor/autoload.php";

// jika sudah login, tidak perlu daftar lagi
if (isset($_SESSION['id_pengguna'])) {
    header("Location: toko.php");
    exit;
}

$nama_lengkap    = "";
$email           = "";
$no_hp           = "";
$alamat          = "";
$pesan_error     = "";
$pesan_sukses    = "";

// proses ketika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap    = trim($_POST['nama_lengkap'] ?? "");
    $email           = trim($_POST['email'] ?? "");
    $kata_sandi      = $_POST['kata_sandi'] ?? "";
    $konfirmasi_sandi= $_POST['konfirmasi_sandi'] ?? "";
    $no_hp           = trim($_POST['no_hp'] ?? "");
    $alamat          = trim($_POST['alamat'] ?? "");

    // validasi sederhana
    if ($nama_lengkap === "" || $email === "" || $kata_sandi === "" || $konfirmasi_sandi === "") {
        $pesan_error = "semua field yang wajib harus diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "format email tidak valid.";
    } elseif ($kata_sandi !== $konfirmasi_sandi) {
        $pesan_error = "konfirmasi kata sandi tidak cocok.";
    } else {
        // cek email sudah digunakan atau belum
        $email_esc = mysqli_real_escape_string($koneksi, $email);
        $sql_cek   = "select id_pengguna from pengguna where email = '$email_esc' limit 1";
        $q_cek     = mysqli_query($koneksi, $sql_cek);

        if (!$q_cek) {
            $pesan_error = "terjadi kesalahan saat mengecek email: " . mysqli_error($koneksi);
        } elseif (mysqli_num_rows($q_cek) > 0) {
            $pesan_error = "email sudah terdaftar, silakan gunakan email lain.";
        } else {
    // sanitasi & hash password
    $nama_esc   = mysqli_real_escape_string($koneksi, $nama_lengkap);
    $email_esc  = mysqli_real_escape_string($koneksi, $email);
    $no_hp_esc  = mysqli_real_escape_string($koneksi, $no_hp);
    $alamat_esc = mysqli_real_escape_string($koneksi, $alamat);
    $hash_sandi = password_hash($kata_sandi, PASSWORD_DEFAULT);

    mysqli_begin_transaction($koneksi);

    try {
        $sql_insert = "
            insert into pengguna (
                nama_lengkap,
                email,
                kata_sandi,
                role,
                no_hp,
                alamat,
                is_verified
            ) values (
                '$nama_esc',
                '$email_esc',
                '$hash_sandi',
                'pembeli',
                '$no_hp_esc',
                '$alamat_esc',
                0
            )
        ";

        if (!mysqli_query($koneksi, $sql_insert)) {
            throw new Exception("gagal menyimpan data pengguna: " . mysqli_error($koneksi));
        }

        $id_pengguna = (int) mysqli_insert_id($koneksi);

    // generate token aktivasi
    $token      = bin2hex(random_bytes(32));
    $expired_at = date('Y-m-d H:i:s', time() + 3600); // 1 jam

    $token_esc = mysqli_real_escape_string($koneksi, $token);

    $sql_token = "
        insert into email_tokens (
            id_pengguna, token, jenis, expired_at
        ) values (
            $id_pengguna,
            '$token_esc',
            'aktivasi',
            '$expired_at'
        )
    ";

    if (!mysqli_query($koneksi, $sql_token)) {
        throw new Exception("gagal menyimpan token aktivasi.");
    }

    // ===============================
    // KIRIM EMAIL AKTIVASI
    // ===============================
    $link = "https://toko-aksesoris.infinityfreeapp.com/auth/verifikasi_email.php?token=" . urlencode($token);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tugastes77@gmail.com';
    $mail->Password   = 'eets wvuv cexb rtfy';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('tugastes77@gmail.com', 'Toko Aksesoris');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Aktivasi Akun Anda';

    $mail->Body = '
    <div style="background:#f3f4f6;padding:40px 0;font-family:Arial">
        <div style="max-width:520px;margin:auto;background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
            <h2 style="text-align:center;color:#111827">Aktivasi Akun</h2>
            <p style="text-align:center;color:#6b7280;font-size:13px">
                selangkah lagi untuk mengaktifkan akun Anda
            </p>
            <hr style="margin:20px 0;border:0;border-top:1px solid #e5e7eb">
            <p style="font-size:14px;color:#1f2937">
                Halo <strong>'.htmlspecialchars($nama_lengkap).'</strong>,
            </p>
            <p style="font-size:14px;color:#1f2937">
                Terima kasih telah mendaftar. Silakan klik tombol di bawah ini untuk mengaktifkan akun Anda.
            </p>
            <div style="text-align:center;margin:24px 0">
                <a href="'.$link.'" style="
                    background:#111827;
                    color:#fff;
                    padding:12px 20px;
                    border-radius:6px;
                    text-decoration:none;
                    font-weight:bold;
                    font-size:14px
                ">Aktivasi Akun</a>
            </div>
            <p style="font-size:12px;color:#6b7280">
                Link ini berlaku selama 1 jam.
            </p>
            <p style="font-size:11px;color:#9ca3af;text-align:center">
                © '.date("Y").' Toko Aksesoris
            </p>
        </div>
    </div>';

    $mail->send();

    mysqli_commit($koneksi);

header("Location: login.php?pesan=registrasi_perlu_aktivasi");
exit;


} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $pesan_error = $e->getMessage();
}

        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>registrasi pembeli - toko aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200 px-6 py-6">
    <a
    href="../toko.php"
    class="inline-flex items-center justify-center gap-2 w-full mb-3 px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-100"
>
    <i class="fa-solid fa-store"></i>
    kembali ke halaman toko
</a>

    <h1 class="text-xl font-semibold text-gray-800 mb-1 text-center flex items-center justify-center gap-2">
        <i class="fa-solid fa-user-plus text-gray-700"></i>
        registrasi pembeli
    </h1>

    <p class="text-xs text-gray-500 mb-4 text-center">
        buat akun baru untuk dapat menggunakan keranjang dan membuat pesanan.
    </p>

    <?php if ($pesan_error !== ""): ?>
        <div
            id="alertError"
            class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-2 text-sm text-red-800 flex items-center gap-2"
        >
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($pesan_error); ?>
        </div>

        <script>
            (function () {
                var err = document.getElementById('alertError');
                if (err) {
                    err.style.transition = "opacity 0.5s ease";

                    setTimeout(function () {
                        err.style.opacity = "0";
                        setTimeout(function () {
                            err.classList.add("hidden");
                        }, 500);
                    }, 3000);
                }
            })();
        </script>
    <?php endif; ?>


    <form method="post" class="space-y-3">

        <!-- nama lengkap -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                <i class="fa-solid fa-id-card text-gray-600"></i>
                nama lengkap <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="nama_lengkap"
                value="<?php echo htmlspecialchars($nama_lengkap); ?>"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
        </div>

        <!-- email -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                <i class="fa-solid fa-envelope text-gray-600"></i>
                email <span class="text-red-500">*</span>
            </label>
            <input
                type="email"
                name="email"
                value="<?php echo htmlspecialchars($email); ?>"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
        </div>

        <!-- kata sandi -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                <i class="fa-solid fa-lock text-gray-600"></i>
                kata sandi <span class="text-red-500">*</span>
            </label>
            <input
                type="password"
                name="kata_sandi"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
            <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                <i class="fa-solid fa-circle-info"></i>
                minimal 6 karakter (disarankan kombinasi huruf dan angka).
            </p>
        </div>

        <!-- konfirmasi kata sandi -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                <i class="fa-solid fa-key text-gray-600"></i>
                konfirmasi kata sandi <span class="text-red-500">*</span>
            </label>
            <input
                type="password"
                name="konfirmasi_sandi"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
        </div>

        <!-- no hp -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                <i class="fa-solid fa-phone text-gray-600"></i>
                no hp
            </label>
            <input
                type="text"
                name="no_hp"
                value="<?php echo htmlspecialchars($no_hp); ?>"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
            >
        </div>

        <!-- alamat -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                <i class="fa-solid fa-location-dot text-gray-600"></i>
                alamat
            </label>
            <textarea
                name="alamat"
                rows="3"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
            ><?php echo htmlspecialchars($alamat); ?></textarea>
        </div>

        <button
            type="submit"
            class="w-full mt-2 px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 flex items-center justify-center gap-2"
        >
            <i class="fa-solid fa-user-check"></i>
            daftar sekarang
        </button>
    </form>

    <p class="mt-4 text-xs text-gray-500 text-center">
        sudah punya akun?
        <a href="login.php" class="text-gray-800 font-medium hover:underline flex items-center justify-center gap-1">
            <i class="fa-solid fa-right-to-bracket"></i>
            login di sini
        </a>
    </p>
</div>


</body>
</html>
