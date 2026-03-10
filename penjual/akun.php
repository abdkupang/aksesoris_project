<?php
// file: penjual/akun.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/akun.php");
    exit;
}

if ($_SESSION['role'] !== 'penjual') {
    header("Location: ../toko.php");
    exit;
}

$id_pengguna  = (int)$_SESSION['id_pengguna'];
$nama_penjual = $_SESSION['nama_lengkap'] ?? "penjual";

$pesan_sukses = "";
$pesan_error  = "";

// proses aksi update profil
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
            $nama_esc  = mysqli_real_escape_string($koneksi, $nama_lengkap);
            $email_esc = mysqli_real_escape_string($koneksi, $email);
            $no_hp_esc = mysqli_real_escape_string($koneksi, $no_hp);
            $alamat_esc= mysqli_real_escape_string($koneksi, $alamat);

            // cek email unik
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
                    $pesan_sukses         = "profil akun berhasil diperbarui.";
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

                // verifikasi password lama
                if (!password_verify($password_lama, $hash_db)) {
                    $pesan_error = "password lama tidak sesuai.";
                } else {
                    $hash_baru = password_hash($password_baru, PASSWORD_BCRYPT);

                    $hash_esc = mysqli_real_escape_string($koneksi, $hash_baru);
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

// ambil data akun terbaru
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

// format tanggal
$dibuat_pada     = date('d-m-Y H:i', strtotime($akun['dibuat_pada']));
$diperbarui_pada = date('d-m-Y H:i', strtotime($akun['diperbarui_pada']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>pengaturan akun - panel penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex min-h-screen">
        <!-- sidebar -->
<?php include "sidebar.php"; ?>

<div
    id="sidebarOverlay"
    class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"
    onclick="toggleSidebar()"
></div>

        <!-- konten utama -->
<main class="flex-1 pt-[72px]">
<!-- header atas -->
<header
    id="mainHeader"
    class="bg-white shadow-sm fixed top-0 left-0 md:left-64 right-0 z-30 transition-all duration-300"
>   <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">

        <div class="flex items-center gap-3">

            <!-- hamburger mobile -->
            <button
                type="button"
                onclick="toggleSidebar()"
                class="md:hidden text-gray-700 text-xl"
                aria-label="Toggle sidebar"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

<!-- judul -->
<div class="leading-tight">
    <h1 class="flex items-center gap-2 font-semibold text-gray-800
               text-sm sm:text-base md:text-lg">
        <i class="fa-solid fa-user-gear text-gray-700
                  text-base sm:text-lg"></i>
        pengaturan akun
    </h1>

    <!-- deskripsi hanya tampil md ke atas -->
    <p class="hidden md:flex text-xs text-gray-500 items-center gap-1 mt-0.5">
        <i class="fa-solid fa-sliders text-gray-500"></i>
        kelola informasi akun penjual dan ganti password jika diperlukan.
    </p>
</div>

        </div>

        <!-- tombol aksi -->
        <div class="flex items-center gap-2">

            <!-- edit profil -->
            <button
                type="button"
                onclick="openProfilModal()"
                class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"
                title="edit profil"
            >
                <i class="fa-solid fa-user-pen"></i>
                <span class="hidden md:inline ml-1 text-xs">edit profil</span>
            </button>

            <!-- ganti password -->
            <button
                type="button"
                onclick="openPasswordModal()"
                class="inline-flex items-center justify-center px-3 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800"
                title="ganti password"
            >
                <i class="fa-solid fa-key"></i>
                <span class="hidden md:inline ml-1 text-xs">ganti password</span>
            </button>

        </div>

    </div>
</header>


    <!-- isi -->
    <section class="max-w-6xl mx-auto px-6 py-6">

        <!-- pesan sukses -->
        <?php if ($pesan_sukses !== ""): ?>
            <div
                id="alertSuccess"
                class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($pesan_sukses); ?>
            </div>
        <?php endif; ?>

        <!-- pesan error -->
        <?php if ($pesan_error !== ""): ?>
            <div
                id="alertError"
                class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-xmark"></i>
                <?php echo htmlspecialchars($pesan_error); ?>
            </div>
        <?php endif; ?>

        <!-- script pesan 3 detik -->
        <script>
            (function () {
                var alerts = document.querySelectorAll('#alertSuccess, #alertError');
                alerts.forEach(function (alert) {
                    alert.style.transition = "opacity 0.5s ease";
                    setTimeout(function () {
                        alert.style.opacity = "0";
                        setTimeout(function () {
                            alert.classList.add("hidden");
                        }, 500);
                    }, 3000);
                });
            })();
        </script>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- informasi akun -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1">
                    <i class="fa-solid fa-id-card-clip text-gray-700"></i>
                    informasi akun
                </h2>

                <dl class="text-xs text-gray-700 space-y-1">
                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-user"></i>
                            nama lengkap
                        </dt>
                        <dd class="font-semibold">
                            <?php echo htmlspecialchars($akun['nama_lengkap']); ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-regular fa-envelope"></i>
                            email
                        </dt>
                        <dd><?php echo htmlspecialchars($akun['email']); ?></dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-phone"></i>
                            no hp
                        </dt>
                        <dd><?php echo htmlspecialchars($akun['no_hp']); ?></dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-location-dot"></i>
                            alamat
                        </dt>
                        <dd class="whitespace-pre-line">
                            <?php echo htmlspecialchars($akun['alamat']); ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- info sistem -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1">
                    <i class="fa-solid fa-server text-gray-700"></i>
                    info sistem
                </h2>

                <dl class="text-xs text-gray-700 space-y-1">
                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-hashtag"></i>
                            id pengguna
                        </dt>
                        <dd>#<?php echo (int)$akun['id_pengguna']; ?></dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-user-shield"></i>
                            role
                        </dt>
                        <dd class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-900 text-white text-[11px] font-medium">
                            <?php echo htmlspecialchars($akun['role']); ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-regular fa-calendar-plus"></i>
                            dibuat pada
                        </dt>
                        <dd><?php echo $dibuat_pada; ?></dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 flex items-center gap-1">
                            <i class="fa-regular fa-clock"></i>
                            terakhir diperbarui
                        </dt>
                        <dd><?php echo $diperbarui_pada; ?></dd>
                    </div>
                </dl>
            </div>

        </div>
    </section>
</main>

    </div>

    <!-- overlay umum -->
    <div id="overlay" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-40"></div>

  <!-- modal edit profil -->
<div id="modalProfil" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        
        <!-- header -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-user-pen"></i>
                edit profil
            </h2>
            <button type="button" onclick="closeProfilModal()" class="text-gray-500 text-lg hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="update_profil">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-user"></i>
                    nama lengkap <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="nama_lengkap"
                    value="<?php echo htmlspecialchars($akun['nama_lengkap']); ?>"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                    required
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-regular fa-envelope"></i>
                    email <span class="text-red-500">*</span>
                </label>
                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($akun['email']); ?>"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                    required
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-phone"></i>
                    no hp
                </label>
                <input
                    type="text"
                    name="no_hp"
                    value="<?php echo htmlspecialchars($akun['no_hp']); ?>"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-location-dot"></i>
                    alamat
                </label>
                <textarea
                    name="alamat"
                    rows="3"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                ><?php echo htmlspecialchars($akun['alamat']); ?></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeProfilModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-1"
                >
                    <i class="fa-solid fa-circle-xmark"></i> batal
                </button>

                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 flex items-center gap-1"
                >
                    <i class="fa-solid fa-floppy-disk"></i> simpan perubahan
                </button>
            </div>
        </form>
    </div>
</div>


<!-- modal ganti password -->
<div id="modalPassword" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">

        <!-- header -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-key"></i>
                ganti password
            </h2>
            <button type="button" onclick="closePasswordModal()" class="text-gray-500 text-lg hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="ganti_password">

            <!-- password lama -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-lock"></i>
                    password lama
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="pw_old"
                        name="password_lama"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                        required
                    >
                    <button type="button" onclick="togglePw('pw_old')" class="absolute right-3 top-2.5 text-gray-500 text-xs">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- password baru -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-unlock-keyhole"></i>
                    password baru (min. 6 karakter)
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="pw_new"
                        name="password_baru"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                        required
                    >
                    <button type="button" onclick="togglePw('pw_new')" class="absolute right-3 top-2.5 text-gray-500 text-xs">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- konfirmasi password -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-check-double"></i>
                    konfirmasi password baru
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="pw_confirm"
                        name="password_konfirmasi"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:ring-gray-500"
                        required
                    >
                    <button type="button" onclick="togglePw('pw_confirm')" class="absolute right-3 top-2.5 text-gray-500 text-xs">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closePasswordModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-1"
                >
                    <i class="fa-solid fa-circle-xmark"></i> batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 flex items-center gap-1"
                >
                    <i class="fa-solid fa-floppy-disk"></i> simpan password
                </button>
            </div>
        </form>
    </div>
</div>


    <script>
        const overlay       = document.getElementById('overlay');
        const modalProfil   = document.getElementById('modalProfil');
        const modalPassword = document.getElementById('modalPassword');

        function showOverlay() {
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        }
        function hideOverlay() {
            overlay.classList.remove('flex');
            overlay.classList.add('hidden');
        }

        function openProfilModal() {
            modalProfil.classList.remove('hidden');
            modalProfil.classList.add('flex');
            showOverlay();
        }
        function closeProfilModal() {
            modalProfil.classList.remove('flex');
            modalProfil.classList.add('hidden');
            hideOverlay();
        }

        function openPasswordModal() {
            modalPassword.classList.remove('hidden');
            modalPassword.classList.add('flex');
            showOverlay();
        }
        function closePasswordModal() {
            modalPassword.classList.remove('flex');
            modalPassword.classList.add('hidden');
            hideOverlay();
        }
        function togglePw(id) {
    const input = document.getElementById(id);
    const isPw = input.type === "password";
    input.type = isPw ? "text" : "password";
}
    </script>
</body>
<script>
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !overlay) return;

    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

(function () {
    const header = document.getElementById('mainHeader');
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', function () {
        const currentScrollY = window.scrollY;

        if (currentScrollY > lastScrollY && currentScrollY > 80) {
            // scroll ke bawah → sembunyikan 70%
            header.style.transform = 'translateY(-70%)';
            header.style.opacity = '0.3';
        } else {
            // scroll ke atas → tampil penuh
            header.style.transform = 'translateY(0)';
            header.style.opacity = '1';
        }

        lastScrollY = currentScrollY;
    }, { passive: true });
})();
</script>

</html>
