<?php
include "../config.php";

$token = $_GET['token'] ?? "";
$token_esc = mysqli_real_escape_string($koneksi, $token);
$pesan_error = "";
$pesan_sukses = "";
$pesan_get = $_GET['pesan'] ?? '';


$q = mysqli_query($koneksi, "
    SELECT et.id_token, et.id_pengguna
    FROM email_tokens et
    WHERE et.token = '$token_esc'
      AND et.jenis = 'reset_password'
      AND et.digunakan = 0
      AND et.expired_at > NOW()
    LIMIT 1
");


if (!$q || mysqli_num_rows($q) === 0) {
    die("token tidak valid atau sudah kadaluarsa.");
}

$row = mysqli_fetch_assoc($q);
$id_pengguna = (int)$row['id_pengguna'];
$id_token    = (int)$row['id_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password'] ?? "";
    $pass2 = $_POST['konfirmasi'] ?? "";

    if ($pass1 === "" || $pass2 === "") {
        $pesan_error = "kata sandi wajib diisi.";
    } elseif ($pass1 !== $pass2) {
        $pesan_error = "konfirmasi kata sandi tidak cocok.";
    } elseif (strlen($pass1) < 6) {
        $pesan_error = "kata sandi minimal 6 karakter.";
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);

        mysqli_begin_transaction($koneksi);
        try {
            mysqli_query($koneksi, "
                UPDATE pengguna
                SET kata_sandi = '$hash'
                WHERE id_pengguna = $id_pengguna
            ");

            mysqli_query($koneksi, "
UPDATE email_tokens
SET digunakan = 1
WHERE id_token = $id_token

            ");

            mysqli_commit($koneksi);
            header("Location: login.php?pesan=reset_berhasil");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan_error = "gagal reset kata sandi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>reset password</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200 px-6 py-6">

    <h1 class="text-xl font-semibold text-gray-800 mb-1 text-center">
        <i class="fa-solid fa-lock mr-1"></i>
        buat kata sandi baru
    </h1>

    <p class="text-xs text-gray-500 mb-4 text-center">
        pastikan kata sandi baru mudah diingat dan aman.
    </p>

    <?php if ($pesan_error): ?>
        <div id="alertMsg" class="mb-3 rounded-md bg-red-100 border border-red-300 px-4 py-2 text-sm text-red-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-xmark"></i>
            <?php echo htmlspecialchars($pesan_error); ?>
        </div>
    <?php endif; ?>
    <?php if ($pesan_get === "link_reset_dikirim"): ?>
    <div id="alertMsg"
        class="mb-3 rounded-md bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        link reset kata sandi telah dikirim. silakan cek email anda.
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
            <label class="block text-xs font-medium text-gray-700 mb-1">
                kata sandi baru
            </label>
            <input
                type="password"
                name="password"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                konfirmasi kata sandi
            </label>
            <input
                type="password"
                name="konfirmasi"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
        </div>

        <button
            type="submit"
            class="w-full mt-2 px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 flex items-center justify-center gap-2"
        >
            <i class="fa-solid fa-check"></i>
            reset password
        </button>
    </form>

</div>
</body>

</html>
