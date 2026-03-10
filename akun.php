<?php
// file: akun.php (khusus pembeli)
include "config.php";

// hanya pembeli yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: auth/login.php?pesan=login_dulu&redirect=akun.php");
    exit;
}

if ($_SESSION['role'] !== 'pembeli') {
    // jika penjual, arahkan ke panel penjual
    header("Location: penjual/akun.php");
    exit;
}

$id_pengguna  = (int)$_SESSION['id_pengguna'];
$nama_pembeli = $_SESSION['nama_lengkap'] ?? "pembeli";

$pesan_sukses = "";
$pesan_error  = "";

// proses aksi perubahan profil / password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    // update profil
    if ($aksi === 'update_profil') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? "");
        $email        = trim($_POST['email'] ?? "");
        $no_hp        = trim($_POST['no_hp'] ?? "");
        $alamat       = trim($_POST['alamat'] ?? "");

        if ($nama_lengkap === "" || $email === "") {
            $pesan_error = "nama lengkap dan email wajib diisi.";
        } else {
            $nama_esc   = mysqli_real_escape_string($koneksi, $nama_lengkap);
            $email_esc  = mysqli_real_escape_string($koneksi, $email);
            $no_hp_esc  = mysqli_real_escape_string($koneksi, $no_hp);
            $alamat_esc = mysqli_real_escape_string($koneksi, $alamat);

            // cek email unik (tidak boleh sama dengan pengguna lain)
            $sql_cek_email = "
                select id_pengguna
                from pengguna
                where email = '$email_esc'
                  and id_pengguna <> $id_pengguna
                limit 1
            ";
            $q_cek_email = mysqli_query($koneksi, $sql_cek_email);
            if ($q_cek_email && mysqli_num_rows($q_cek_email) > 0) {
                $pesan_error = "email sudah digunakan oleh pengguna lain.";
            } else {
                $sql_update = "
                    update pengguna
                    set nama_lengkap = '$nama_esc',
                        email        = '$email_esc',
                        no_hp        = '$no_hp_esc',
                        alamat       = '$alamat_esc'
                    where id_pengguna = $id_pengguna
                    limit 1
                ";
                if (mysqli_query($koneksi, $sql_update)) {
                    $pesan_sukses            = "profil akun berhasil diperbarui.";
                    $_SESSION['nama_lengkap'] = $nama_lengkap;
                    $_SESSION['email']        = $email;
                } else {
                    $pesan_error = "gagal memperbarui profil: " . mysqli_error($koneksi);
                }
            }
        }
    }

    // ganti password
    if ($aksi === 'ganti_password') {
        $password_lama = $_POST['password_lama'] ?? "";
        $password_baru = $_POST['password_baru'] ?? "";
        $password_konf = $_POST['password_konfirmasi'] ?? "";

        if ($password_lama === "" || $password_baru === "" || $password_konf === "") {
            $pesan_error = "semua field password wajib diisi.";
        } elseif ($password_baru !== $password_konf) {
            $pesan_error = "konfirmasi password baru tidak cocok.";
        } elseif (strlen($password_baru) < 6) {
            $pesan_error = "password baru minimal 6 karakter.";
        } else {
            // ambil password lama dari db
            $sql_pwd = "
                select kata_sandi
                from pengguna
                where id_pengguna = $id_pengguna
                limit 1
            ";
            $q_pwd = mysqli_query($koneksi, $sql_pwd);
            if (!$q_pwd || mysqli_num_rows($q_pwd) === 0) {
                $pesan_error = "akun tidak ditemukan.";
            } else {
                $row_pwd = mysqli_fetch_assoc($q_pwd);
                $hash_db = $row_pwd['kata_sandi'];

                if (!password_verify($password_lama, $hash_db)) {
                    $pesan_error = "password lama tidak sesuai.";
                } else {
                    $hash_baru = password_hash($password_baru, PASSWORD_BCRYPT);
                    $hash_esc  = mysqli_real_escape_string($koneksi, $hash_baru);

                    $sql_update_pwd = "
                        update pengguna
                        set kata_sandi = '$hash_esc'
                        where id_pengguna = $id_pengguna
                        limit 1
                    ";
                    if (mysqli_query($koneksi, $sql_update_pwd)) {
                        $pesan_sukses = "password berhasil diperbarui.";
                    } else {
                        $pesan_error = "gagal memperbarui password: " . mysqli_error($koneksi);
                    }
                }
            }
        }
    }
}

// ambil data akun
$sql_akun = "
    select
        id_pengguna,
        nama_lengkap,
        email,
        no_hp,
        alamat,
        role,
        dibuat_pada,
        diperbarui_pada
    from pengguna
    where id_pengguna = $id_pengguna
    limit 1
";
$q_akun = mysqli_query($koneksi, $sql_akun);
if (!$q_akun || mysqli_num_rows($q_akun) === 0) {
    die("akun tidak ditemukan.");
}
$akun = mysqli_fetch_assoc($q_akun);

$dibuat_pada     = date('d-m-Y H:i', strtotime($akun['dibuat_pada']));
$diperbarui_pada = date('d-m-Y H:i', strtotime($akun['diperbarui_pada']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>akun saya - toko aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include "layout/navbar.php"; ?>

    <main class="max-w-6xl mx-auto px-4 py-6">
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-circle text-gray-700"></i>
            akun saya
        </h1>
        <p class="text-sm text-gray-500 flex items-center gap-1">
            <i class="fa-solid fa-gear text-gray-600"></i>
            kelola informasi akun dan password untuk pembeli di toko aksesoris.
        </p>
    </div>

    <?php if ($pesan_sukses !== ""): ?>
        <div class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <?php echo htmlspecialchars($pesan_sukses); ?>
        </div>
    <?php endif; ?>

    <?php if ($pesan_error !== ""): ?>
        <div class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-xmark"></i>
            <?php echo htmlspecialchars($pesan_error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div>
                <p class="text-sm text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-user text-gray-600"></i>
                    login sebagai
                </p>
                <p class="text-base font-semibold text-gray-800">
                    <?php echo htmlspecialchars($akun['nama_lengkap']); ?>
                </p>
                <p class="text-xs text-gray-400 flex items-center gap-1">
                    <i class="fa-solid fa-envelope text-gray-500"></i>
                    email: <?php echo htmlspecialchars($akun['email']); ?>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    onclick="openProfilModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2"
                >
                    <i class="fa-solid fa-pen"></i>
                    edit profil
                </button>
                <button
                    type="button"
                    onclick="openPasswordModal()"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-2"
                >
                    <i class="fa-solid fa-key"></i>
                    ganti password
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-700">

            <div>
                <p class="text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-phone text-gray-600"></i>
                    no hp
                </p>
                <p class="font-semibold"><?php echo htmlspecialchars($akun['no_hp']); ?></p>
            </div>

            <div>
                <p class="text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-user-shield text-gray-600"></i>
                    role
                </p>
                <p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-900 text-white text-[11px] font-medium gap-1">
                        <i class="fa-solid fa-id-badge text-[10px]"></i>
                        <?php echo htmlspecialchars($akun['role']); ?>
                    </span>
                </p>
            </div>

            <div class="md:col-span-2">
                <p class="text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-location-dot text-gray-600"></i>
                    alamat
                </p>
                <p class="font-semibold whitespace-pre-line">
                    <?php echo htmlspecialchars($akun['alamat']); ?>
                </p>
            </div>

            <div>
                <p class="text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-calendar-plus text-gray-600"></i>
                    akun dibuat
                </p>
                <p><?php echo $dibuat_pada; ?></p>
            </div>

            <div>
                <p class="text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-clock-rotate-left text-gray-600"></i>
                    terakhir diperbarui
                </p>
                <p><?php echo $diperbarui_pada; ?></p>
            </div>

        </div>
    </div>

    <p class="text-[11px] text-gray-400 flex items-center gap-1">
        <i class="fa-solid fa-lightbulb text-yellow-500"></i>
        tips: pastikan email dan nomor hp selalu terbaru agar mudah dihubungi jika ada kendala pesanan.
    </p>
</main>


    <!-- overlay -->
    <div id="overlayAkun" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-40"></div>

    <!-- modal edit profil -->
    <div id="modalProfil" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        
        <!-- HEADER MODAL -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-gray-700"></i>
                edit profil
            </h2>
            <button type="button" onclick="closeProfilModal()" class="text-gray-500 text-lg hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="update_profil">

            <!-- NAMA LENGKAP -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-user text-gray-600"></i>
                    nama lengkap <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="nama_lengkap"
                    value="<?php echo htmlspecialchars($akun['nama_lengkap']); ?>"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- EMAIL -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-envelope text-gray-600"></i>
                    email <span class="text-red-500">*</span>
                </label>
                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($akun['email']); ?>"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- NO HP -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-phone text-gray-600"></i>
                    no hp
                </label>
                <input
                    type="text"
                    name="no_hp"
                    value="<?php echo htmlspecialchars($akun['no_hp']); ?>"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
            </div>

            <!-- ALAMAT -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-location-dot text-gray-600"></i>
                    alamat
                </label>
                <textarea
                    name="alamat"
                    rows="3"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                ><?php echo htmlspecialchars($akun['alamat']); ?></textarea>
            </div>

            <!-- TOMBOL AKSI -->
            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeProfilModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-xmark"></i>
                    batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-check"></i>
                    simpan perubahan
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- modal ganti password -->
    <div id="modalPassword" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">

        <!-- HEADER -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-key text-gray-700"></i>
                ganti password
            </h2>
            <button type="button" onclick="closePasswordModal()" class="text-gray-500 text-lg hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="ganti_password">

            <!-- PASSWORD LAMA -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-keyhole text-gray-600"></i>
                    password lama
                </label>
                <input
                    type="password"
                    name="password_lama"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- PASSWORD BARU -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-lock text-gray-600"></i>
                    password baru (min. 6 karakter)
                </label>
                <input
                    type="password"
                    name="password_baru"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- KONFIRMASI PASSWORD -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-check-double text-gray-600"></i>
                    konfirmasi password baru
                </label>
                <input
                    type="password"
                    name="password_konfirmasi"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- TOMBOL AKSI -->
            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closePasswordModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-xmark"></i>
                    batal
                </button>

                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-key"></i>
                    simpan password
                </button>
            </div>
        </form>
    </div>
</div>


    <script>
        const overlayAkun    = document.getElementById('overlayAkun');
        const modalProfil    = document.getElementById('modalProfil');
        const modalPassword  = document.getElementById('modalPassword');

        function showOverlayAkun() {
            overlayAkun.classList.remove('hidden');
            overlayAkun.classList.add('flex');
        }
        function hideOverlayAkun() {
            overlayAkun.classList.remove('flex');
            overlayAkun.classList.add('hidden');
        }

        function openProfilModal() {
            modalProfil.classList.remove('hidden');
            modalProfil.classList.add('flex');
            showOverlayAkun();
        }
        function closeProfilModal() {
            modalProfil.classList.remove('flex');
            modalProfil.classList.add('hidden');
            hideOverlayAkun();
        }

        function openPasswordModal() {
            modalPassword.classList.remove('hidden');
            modalPassword.classList.add('flex');
            showOverlayAkun();
        }
        function closePasswordModal() {
            modalPassword.classList.remove('flex');
            modalPassword.classList.add('hidden');
            hideOverlayAkun();
        }
    </script>
</body>
</html>
