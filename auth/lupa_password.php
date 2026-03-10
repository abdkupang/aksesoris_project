<?php
include "../config.php";
            require "../vendor/autoload.php";
            use PHPMailer\PHPMailer\PHPMailer;

$email = "";
$pesan_error = "";
$pesan_sukses = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? "");

    if ($email === "") {
        $pesan_error = "email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "format email tidak valid.";
    } else {
        $email_esc = mysqli_real_escape_string($koneksi, $email);

        $q = mysqli_query($koneksi, "
            SELECT id_pengguna, nama_lengkap
            FROM pengguna
            WHERE email = '$email_esc'
            LIMIT 1
        ");

if ($q && mysqli_num_rows($q) === 1) {
    $user = mysqli_fetch_assoc($q);

    $token = bin2hex(random_bytes(32));

    // nonaktifkan token reset lama
    mysqli_query($koneksi, "
        UPDATE email_tokens
        SET digunakan = 1
        WHERE id_pengguna = {$user['id_pengguna']}
          AND jenis = 'reset_password'
          AND digunakan = 0
    ");

    // simpan token baru
    mysqli_query($koneksi, "
        INSERT INTO email_tokens (id_pengguna, token, jenis, expired_at)
        VALUES (
            {$user['id_pengguna']},
            '$token',
            'reset_password',
            DATE_ADD(NOW(), INTERVAL 1 HOUR)
        )
    ");

    // kirim email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tugastes77@gmail.com';
        $mail->Password   = 'eets wvuv cexb rtfy';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('tugastes77@gmail.com', 'Toko Aksesoris');
        $mail->addAddress($email, $user['nama_lengkap']);

        $link = "https://toko-aksesoris.infinityfreeapp.com/auth/reset_password.php?token=" . urlencode($token);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Kata Sandi Akun Anda';
$mail->Body = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">
    <div style="max-width:520px;margin:40px auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
        
        <h2 style="margin:0 0 8px 0;color:#111827;text-align:center;">
            Reset Kata Sandi
        </h2>

        <p style="margin:0 0 16px 0;color:#6b7280;text-align:center;font-size:13px;">
            Permintaan reset kata sandi akun Anda
        </p>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">

        <p style="font-size:14px;color:#1f2937;margin-bottom:12px;">
            Halo <strong>'.htmlspecialchars($user["nama_lengkap"]).'</strong>,
        </p>

        <p style="font-size:14px;color:#1f2937;line-height:1.6;margin-bottom:16px;">
            Kami menerima permintaan untuk mereset kata sandi akun Anda.
            Silakan klik tombol di bawah ini untuk melanjutkan.
        </p>

        <div style="text-align:center;margin:24px 0;">
            <a href="'.$link.'"
               style="background:#111827;color:#ffffff;text-decoration:none;
                      padding:12px 20px;border-radius:6px;
                      display:inline-block;font-size:14px;font-weight:bold;">
                Reset Kata Sandi
            </a>
        </div>

        <p style="font-size:12px;color:#6b7280;line-height:1.5;">
            Link ini berlaku selama <strong>1 jam</strong>.
            Jika Anda tidak merasa melakukan permintaan ini, silakan abaikan email ini.
        </p>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">

        <p style="font-size:11px;color:#9ca3af;text-align:center;">
            © '.date("Y").' Toko Aksesoris. Semua hak dilindungi.
        </p>

    </div>
</body>
</html>
';
        $mail->send();
    } catch (Exception $e) {
        // sengaja dikosongkan
    }

    $pesan_sukses = "jika email terdaftar, link reset telah dikirim.";
} else {
    // EMAIL TIDAK DITEMUKAN
    $pesan_error = "email tidak terdaftar di sistem.";
}

    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>lupa password</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-3 sm:px-0">

<div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200
            px-4 sm:px-6 py-5 sm:py-6">

<h1 class="text-base sm:text-xl font-semibold text-gray-800 mb-1 text-center flex items-center justify-center gap-1">
    <i class="fa-solid fa-key text-gray-700"></i>
    lupa kata sandi
</h1>

<p class="text-[11px] sm:text-xs text-gray-500 mb-4 text-center">
    masukkan email untuk menerima link reset password.
</p>

<?php if ($pesan_error): ?>
<div
    id="alertMsg"
    class="mb-3 rounded-md bg-red-100 border border-red-300
           px-3 sm:px-4 py-2 text-[11px] sm:text-sm
           text-red-800 flex items-center gap-2"
>
  <i class="fa-solid fa-circle-xmark"></i>
        <?php echo htmlspecialchars($pesan_error); ?>
    </div>
<?php endif; ?>

<?php if ($pesan_sukses): ?>
    <div id="alertMsg"
        class="mb-3 rounded-md bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        <?php echo htmlspecialchars($pesan_sukses); ?>
    </div>
<?php endif; ?>
<script>
    (function () {
        var alertBox = document.getElementById("alertMsg");
        if (alertBox) {
            alertBox.style.transition = "opacity 0.5s ease";

            setTimeout(function () {
                alertBox.style.opacity = "0";
                setTimeout(function () {
                    alertBox.classList.add("hidden");
                }, 500);
            }, 3000);
        }
    })();
</script>


<form method="post" class="space-y-3">
    <div>
        <label class="block text-[11px] sm:text-xs font-medium text-gray-700 mb-1">
            <i class="fa-solid fa-envelope mr-1 text-gray-600"></i>
            email
        </label>

        <input
            type="email"
            name="email"
            value="<?php echo htmlspecialchars($email); ?>"
            class="w-full px-3 py-2 rounded-md border border-gray-300
                   text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
            required
        >
    </div>

<button
    type="submit"
    class="w-full mt-2 px-4 py-2 rounded-md
           bg-gray-900 text-white text-sm font-medium
           hover:bg-gray-800 flex items-center justify-center gap-2"
>
    <i class="fa-solid fa-paper-plane"></i>
    kirim link reset
</button>

    </form>

    <p class="mt-4 text-xs text-gray-500 text-center">
        <a href="login.php" class="text-gray-800 hover:underline">
            kembali ke login
        </a>
    </p>

</div>
</body>

</html>
