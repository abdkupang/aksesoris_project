<?php
include "../config.php";

// jika sudah login, langsung arahkan sesuai role
if (isset($_SESSION['id_pengguna']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'pembeli') {
        header("Location: ../toko.php");
        exit;
    } elseif ($_SESSION['role'] === 'penjual') {
        header("Location: ../penjual/index.php");
        exit;
    }
}

// ambil pesan dari query string
$pesan_get   = isset($_GET['pesan']) ? $_GET['pesan'] : "";
$redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : "";

// variabel form
$email       = "";
$pesan_error = "";

// proses login saat form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email'] ?? "");
    $kata_sandi  = $_POST['kata_sandi'] ?? "";
    $redirect_to = $_POST['redirect'] ?? "";

    if ($email === "" || $kata_sandi === "") {
        $pesan_error = "email dan kata sandi wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "format email tidak valid.";
    } else {
        $email_esc = mysqli_real_escape_string($koneksi, $email);
$sql_user = "
    select id_pengguna, nama_lengkap, email, kata_sandi, role, is_verified
    from pengguna
    where email = '$email_esc'
    limit 1
";
        $q_user    = mysqli_query($koneksi, $sql_user);

        if (!$q_user) {
            $pesan_error = "terjadi kesalahan saat mengambil data pengguna: " . mysqli_error($koneksi);
        } elseif (mysqli_num_rows($q_user) === 0) {
            $pesan_error = "email atau kata sandi salah.";
        } else {
            $user = mysqli_fetch_assoc($q_user);

            // verifikasi password
if (!password_verify($kata_sandi, $user['kata_sandi'])) {
    $pesan_error = "email atau kata sandi salah.";
} elseif ((int)$user['is_verified'] === 0) {

    // akun belum aktivasi
    header(
        "Location: login.php?pesan=akun_belum_aktif&email=" .
        urlencode($user['email'])
    );
    exit;

} else {

                // set sesi
// password benar, cek status verifikasi
if ((int)$user['is_verified'] !== 1) {

    // cek apakah masih ada token aktivasi yang valid
    $id_pengguna = (int)$user['id_pengguna'];

    $q_token = mysqli_query($koneksi, "
        select expired_at
        from email_tokens
        where id_pengguna = $id_pengguna
        and jenis = 'aktivasi'
        and digunakan = 0
        order by dibuat_pada desc
        limit 1
    ");

    if ($q_token && mysqli_num_rows($q_token) > 0) {
        $t = mysqli_fetch_assoc($q_token);

        if (strtotime($t['expired_at']) < time()) {
            // token ada tapi sudah kedaluwarsa
            header("Location: login.php?pesan=aktivasi_kadaluarsa&email=" . urlencode($user['email']));
            exit;
        }
    }

    // token masih valid atau belum klik
    header("Location: login.php?pesan=akun_belum_aktif&email=" . urlencode($user['email']));
    exit;
}

// ============================
// AKUN SUDAH AKTIF → LOGIN
// ============================
$_SESSION['id_pengguna']  = $user['id_pengguna'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['role']         = $user['role'];

                // sanitasi redirect sederhana (hindari url eksternal)
                $redirect_clean = "";
                if (!empty($redirect_to) && strpos($redirect_to, "://") === false && strpos($redirect_to, "\n") === false) {
                    $redirect_clean = $redirect_to;
                }

                if ($user['role'] === 'pembeli') {
                    if ($redirect_clean !== "") {
                        header("Location: " . $redirect_clean);
                    } else {
                        header("Location: ../toko.php");
                    }
                    exit;
                } elseif ($user['role'] === 'penjual') {
                    header("Location: ../penjual/index.php");
                    exit;
                } else {
                    // role tidak dikenal, lempar ke toko sebagai default
                    header("Location: ../toko.php");
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>login - toko aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <!-- FONT AWESOME -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-200 px-6 py-6">
    <a
    href="../toko.php"
    class="inline-flex items-center justify-center gap-2 w-full mb-3 px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-100"
>
    <i class="fa-solid fa-store"></i>
    kembali ke halaman toko
</a>

    <h1 class="text-xl font-semibold text-gray-800 mb-1 text-center">
        <i class="fa-solid fa-right-to-bracket mr-1"></i> login akun
    </h1>
    <p class="text-xs text-gray-500 mb-4 text-center">
        masuk untuk melanjutkan belanja atau mengelola toko.
    </p>
<?php if ($pesan_get === "akun_belum_aktif"): ?>
    <div 
        class="alert-msg mb-3 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-2 text-sm text-yellow-900 flex items-center gap-2">
        <i class="fa-solid fa-envelope-circle-check"></i>
        akun belum diaktivasi. silakan cek email untuk aktivasi.
    </div>
<?php endif; ?>

<?php if ($pesan_get === "registrasi_perlu_aktivasi"): ?>
    <div 
        class="alert-msg mb-3 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-2 text-sm text-yellow-900 flex items-center gap-2">
        <i class="fa-solid fa-envelope"></i>
        akun berhasil didaftarkan. silakan cek email untuk aktivasi.
    </div>
<?php endif; ?>

<?php if ($pesan_get === "aktivasi_kadaluarsa"): ?>
    <div 
        class="alert-msg mb-3 rounded-md bg-red-100 border border-red-300 px-4 py-2 text-sm text-red-800">
        link aktivasi sudah kedaluwarsa.
        <a
            href="kirim_ulang_aktivasi.php?email=<?php echo urlencode($_GET['email'] ?? ''); ?>"
            class="font-semibold underline ml-1"
        >
            kirim ulang email aktivasi
        </a>
    </div>
<?php endif; ?>

<?php if ($pesan_get === "aktivasi_dikirim"): ?>
    <div
        class="alert-msg mb-3 rounded-md bg-blue-100 border border-blue-300 px-4 py-2 text-sm text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-paper-plane"></i>
        email aktivasi berhasil dikirim ulang. silakan cek inbox.
    </div>
<?php endif; ?>

<?php if ($pesan_get === "aktivasi_berhasil"): ?>
    <div 
        class="alert-msg mb-3 rounded-md bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        email berhasil diaktivasi. silakan login ke akun anda.
    </div>
<?php endif; ?>


    <!-- pesan sukses -->
    <?php if ($pesan_get === "registrasi_berhasil"): ?>
        <div 
            class="alert-msg mb-3 rounded-md bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            registrasi berhasil, silakan login.
        </div>
    <?php endif; ?>
    <?php if ($pesan_get === "reset_berhasil"): ?>
<div class="alert-msg mb-3 bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800">
    kata sandi berhasil diperbarui. silakan login.
</div>
<?php endif; ?>


    <!-- pesan login dulu -->
    <?php if ($pesan_get === "login_dulu"): ?>
        <div
            class="alert-msg mb-3 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-2 text-sm text-yellow-900 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            silakan login terlebih dahulu untuk mengakses keranjang atau melakukan pemesanan.
        </div>
    <?php endif; ?>

    <!-- pesan error -->
    <?php if ($pesan_error !== ""): ?>
        <div
            class="alert-msg mb-3 rounded-md bg-red-100 border border-red-300 px-4 py-2 text-sm text-red-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-xmark"></i>
            <?php echo htmlspecialchars($pesan_error); ?>
        </div>
    <?php endif; ?>


<script>
(function () {
    var alerts = document.querySelectorAll(".alert-msg");

    if (alerts.length === 0) {
        return;
    }

    alerts.forEach(function (alert) {
        alert.style.transition = "opacity 0.5s ease";

        setTimeout(function () {
            alert.style.opacity = "0";

            setTimeout(function () {
                alert.style.display = "none";
            }, 500);

        }, 3000);
    });
})();
</script>



    <form method="post" class="space-y-3">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_to); ?>">

        <!-- email -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                <i class="fa-solid fa-envelope mr-1 text-gray-600"></i> email
            </label>
            <input
                type="email"
                name="email"
                value="<?php echo htmlspecialchars($email); ?>"
                class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                required
            >
        </div>

        <!-- password + toggle -->
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                <i class="fa-solid fa-lock mr-1 text-gray-600"></i> kata sandi
            </label>

            <div class="relative">
                <input
                    id="passwordInput"
                    type="password"
                    name="kata_sandi"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
                <!-- tombol intip -->
                <button
                    type="button"
                    id="togglePass"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700"
                    tabindex="-1"
                >
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <button
            type="submit"
            class="w-full mt-2 px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 flex items-center justify-center gap-2"
        >
            <i class="fa-solid fa-unlock-keyhole"></i> login
        </button>
    </form>
<p class="mt-2 text-xs text-gray-500 text-center">
    <a
        href="lupa_password.php"
        class="text-gray-700 hover:underline flex items-center justify-center gap-1"
    >
        <i class="fa-solid fa-key"></i>
        lupa kata sandi?
    </a>
</p>

    <p class="mt-4 text-xs text-gray-500 text-center">
        belum punya akun?
        <a href="daftar.php" class="text-gray-800 font-medium hover:underline">
            daftar sebagai pembeli <i class="fa-solid fa-user-plus ml-1"></i>
        </a>
    </p>
</div>

<!-- SCRIPT TOGGLE PASSWORD -->
<script>
    const togglePass = document.getElementById("togglePass");
    const pwd = document.getElementById("passwordInput");
    const icon = togglePass.querySelector("i");

    togglePass.addEventListener("click", function () {
        if (pwd.type === "password") {
            pwd.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            pwd.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    });
</script>


</body>
</html>
