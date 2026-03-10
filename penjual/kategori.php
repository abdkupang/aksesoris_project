<?php
// file: penjual/kategori.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/kategori.php");
    exit;
}

if ($_SESSION['role'] !== 'penjual') {
    header("Location: ../toko.php");
    exit;
}

$id_pengguna  = (int)$_SESSION['id_pengguna'];
$nama_penjual = $_SESSION['nama_lengkap'] ?? "penjual";

// pesan notifikasi
$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";

// proses aksi (tambah / edit / hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    // tambah kategori
    if ($aksi === 'tambah_kategori') {
        $nama_kategori = trim($_POST['nama_kategori'] ?? "");
        $deskripsi     = trim($_POST['deskripsi_kategori'] ?? "");

        if ($nama_kategori === "") {
            $pesan = "nama kategori wajib diisi.";
        } else {
            $nama_esc = mysqli_real_escape_string($koneksi, $nama_kategori);
            $desk_esc = mysqli_real_escape_string($koneksi, $deskripsi);

            $sql_ins = "
                INSERT INTO kategori (nama_kategori, deskripsi)
                VALUES ('$nama_esc', '$desk_esc')
            ";

            if (mysqli_query($koneksi, $sql_ins)) {
                header("Location: kategori.php?pesan=tambah_sukses");
                exit;
            } else {
                $pesan = "gagal menambah kategori: " . mysqli_error($koneksi);
            }
        }
    }

    // edit kategori
    if ($aksi === 'edit_kategori') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $nama_kategori = trim($_POST['nama_kategori'] ?? "");
        $deskripsi     = trim($_POST['deskripsi_kategori'] ?? "");

        if ($id_kategori <= 0) {
            $pesan = "kategori tidak valid.";
        } elseif ($nama_kategori === "") {
            $pesan = "nama kategori wajib diisi.";
        } else {
            $nama_esc = mysqli_real_escape_string($koneksi, $nama_kategori);
            $desk_esc = mysqli_real_escape_string($koneksi, $deskripsi);

            $sql_upd = "
                UPDATE kategori
                SET nama_kategori = '$nama_esc',
                    deskripsi = '$desk_esc',
                    diperbarui_pada = CURRENT_TIMESTAMP
                WHERE id_kategori = $id_kategori
            ";

            if (mysqli_query($koneksi, $sql_upd)) {
                header("Location: kategori.php?pesan=edit_sukses");
                exit;
            } else {
                $pesan = "gagal mengedit kategori: " . mysqli_error($koneksi);
            }
        }
    }

    // hapus kategori
    if ($aksi === 'hapus_kategori') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);

        if ($id_kategori <= 0) {
            $pesan = "kategori tidak valid.";
        } else {
            // karena produk.id_kategori on delete set null, aman untuk menghapus
            $sql_del = "DELETE FROM kategori WHERE id_kategori = $id_kategori";
            if (mysqli_query($koneksi, $sql_del)) {
                header("Location: kategori.php?pesan=hapus_sukses");
                exit;
            } else {
                $pesan = "gagal menghapus kategori: " . mysqli_error($koneksi);
            }
        }
    }
}

// ambil daftar kategori
$sql = "SELECT id_kategori, nama_kategori, deskripsi, dibuat_pada, diperbarui_pada
        FROM kategori
        ORDER BY nama_kategori ASC";
$q = mysqli_query($koneksi, $sql);

$kategori_list = [];
if ($q && mysqli_num_rows($q) > 0) {
    while ($r = mysqli_fetch_assoc($q)) {
        $kategori_list[] = $r;
    }
}

// halaman aktif untuk sidebar (jika sidebar menggunakan $current_page)
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>kategori - panel penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    />
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
<header
    id="mainHeader"
    class="bg-white shadow-sm fixed top-0 left-0 md:left-64 right-0 z-30 transition-all duration-300"
>    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">

        <!-- kiri: hamburger + judul -->
        <div class="flex items-center gap-3 min-w-0">

            <!-- hamburger mobile -->
            <button
                type="button"
                onclick="toggleSidebar()"
                class="md:hidden text-gray-700 text-xl shrink-0"
                aria-label="Toggle sidebar"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="min-w-0">
                <h1 class="text-base md:text-lg font-semibold text-gray-800 flex items-center gap-2 truncate">
                    <i class="fa-solid fa-layer-group text-gray-700 shrink-0"></i>
                    <span class="truncate">kelola kategori</span>
                </h1>

                <!-- deskripsi hanya desktop -->
                <p class="hidden md:flex text-xs text-gray-500 items-center gap-1">
                    <i class="fa-solid fa-wrench text-gray-500"></i>
                    tambahkan, ubah, atau hapus kategori produk.
                </p>
            </div>
        </div>

        <!-- kanan: tombol tambah -->
        <div class="flex items-center gap-2">

            <!-- mobile: icon only -->
            <button
                type="button"
                onclick="openAddModal()"
                class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-md bg-gray-900 text-white hover:bg-gray-800"
                aria-label="Tambah kategori"
            >
                <i class="fa-solid fa-circle-plus"></i>
            </button>

            <!-- desktop: icon + text -->
            <button
                type="button"
                onclick="openAddModal()"
                class="hidden md:inline-flex items-center gap-1 px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800"
            >
                <i class="fa-solid fa-circle-plus"></i>
                tambah kategori
            </button>

        </div>
    </div>
</header>

            <section class="max-w-6xl mx-auto px-6 py-6">

                <!-- pesan -->
                <?php if ($pesan === "tambah_sukses"): ?>
                    <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-check"></i>
                        kategori berhasil ditambahkan.
                    </div>
                <?php elseif ($pesan === "edit_sukses"): ?>
                    <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
                        <i class="fa-solid fa-pen-to-square"></i>
                        data kategori berhasil diperbarui.
                    </div>
                <?php elseif ($pesan === "hapus_sukses"): ?>
                    <div id="alertMsg" class="mb-4 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-3 text-sm text-yellow-900 flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        kategori berhasil dihapus.
                    </div>
                <?php elseif ($pesan !== ""): ?>
                    <div id="alertMsg" class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-xmark"></i>
                        <?php echo htmlspecialchars($pesan); ?>
                    </div>
                <?php endif; ?>

                <!-- auto hide pesan -->
                <script>
                    (function(){
                        var alertBox = document.getElementById("alertMsg");
                        if (alertBox) {
                            alertBox.style.transition = "opacity 0.5s ease";
                            setTimeout(function () {
                                alertBox.style.opacity = "0";
                                setTimeout(function(){
                                    alertBox.classList.add("hidden");
                                }, 500);
                            }, 3000);
                        }
                    })();
                </script>
<?php if (count($kategori_list) > 0): ?>
<div class="space-y-3 md:hidden">
    <?php foreach ($kategori_list as $k): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-3">

            <!-- nama kategori -->
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-layer-group text-gray-500"></i>
                <p class="font-semibold text-sm text-gray-800">
                    <?php echo htmlspecialchars($k['nama_kategori']); ?>
                </p>
            </div>

            <!-- deskripsi -->
            <?php if (!empty($k['deskripsi'])): ?>
                <p class="text-xs text-gray-600 mb-2">
                    <?php echo nl2br(htmlspecialchars($k['deskripsi'])); ?>
                </p>
            <?php endif; ?>

            <!-- meta -->
            <div class="text-[11px] text-gray-500 space-y-1 mb-2">
                <div class="flex items-center gap-1">
                    <i class="fa-regular fa-calendar-plus"></i>
                    dibuat: <?php echo date('d/m/Y H:i', strtotime($k['dibuat_pada'])); ?>
                </div>
                <div class="flex items-center gap-1">
                    <i class="fa-regular fa-calendar-check"></i>
                    diubah: <?php echo date('d/m/Y H:i', strtotime($k['diperbarui_pada'])); ?>
                </div>
            </div>

            <!-- aksi -->
            <div class="flex items-center justify-end gap-1">

                <!-- edit -->
                <button
                    type="button"
                    onclick="openEditModal(
                        <?php echo (int)$k['id_kategori']; ?>,
                        '<?php echo htmlspecialchars(addslashes($k['nama_kategori'])); ?>',
                        '<?php echo htmlspecialchars(addslashes($k['deskripsi'])); ?>'
                    )"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-900 text-white text-[11px]"
                >
                    <i class="fa-solid fa-pen"></i>
                    edit
                </button>

                <!-- hapus -->
                <form
                    method="post"
                    onsubmit="return confirm('yakin ingin menghapus kategori ini? Produk yang terkait akan kehilangan kategori.');"
                >
                    <input type="hidden" name="aksi" value="hapus_kategori">
                    <input type="hidden" name="id_kategori" value="<?php echo (int)$k['id_kategori']; ?>">

                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-red-600 text-white text-[11px]"
                    >
                        <i class="fa-solid fa-trash"></i>
                        hapus
                    </button>
                </form>

            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">nama kategori</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">deskripsi</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">dibuat</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">terakhir diubah</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600">aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($kategori_list) === 0): ?>
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-xs text-gray-500">
                                        <i class="fa-regular fa-folder-open mr-1"></i>
                                        belum ada kategori. klik "tambah kategori" untuk membuat.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kategori_list as $k): ?>
                                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                                        <td class="px-3 py-3 text-gray-800"><?php echo htmlspecialchars($k['nama_kategori']); ?></td>
                                        <td class="px-3 py-3 text-gray-600"><?php echo nl2br(htmlspecialchars($k['deskripsi'])); ?></td>
                                        <td class="px-3 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($k['dibuat_pada'])); ?></td>
                                        <td class="px-3 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($k['diperbarui_pada'])); ?></td>
                                        <td class="px-3 py-3 text-right space-x-2">
                                            <button
                                                type="button"
                                                onclick="openEditModal(<?php echo (int)$k['id_kategori']; ?>, '<?php echo htmlspecialchars(addslashes($k['nama_kategori'])); ?>', '<?php echo htmlspecialchars(addslashes($k['deskripsi'])); ?>')"
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-900 text-white text-xs hover:bg-gray-800"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                                edit
                                            </button>

                                            <form method="post" class="inline" onsubmit="return confirm('yakin ingin menghapus kategori ini? Produk yang terkait akan kehilangan kategori.');">
                                                <input type="hidden" name="aksi" value="hapus_kategori">
                                                <input type="hidden" name="id_kategori" value="<?php echo (int)$k['id_kategori']; ?>">
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-red-600 text-white text-xs hover:bg-red-700">
                                                    <i class="fa-solid fa-trash"></i>
                                                    hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- overlay umum -->
    <div id="overlay" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-40"></div>

    <!-- modal tambah kategori -->
    <div id="modalAdd" class="fixed inset-0 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-circle-plus text-gray-700"></i>
                    tambah kategori
                </h2>
                <button type="button" onclick="closeAddModal()" class="text-gray-500 text-lg hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="post" class="px-4 py-4 space-y-3">
                <input type="hidden" name="aksi" value="tambah_kategori">

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        nama kategori <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="nama_kategori"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        required
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        deskripsi (opsional)
                    </label>
                    <textarea name="deskripsi_kategori" rows="3"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">
                        batal
                    </button>

                    <button type="submit" class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs hover:bg-gray-800">
                        simpan kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- modal edit kategori -->
    <div id="modalEdit" class="fixed inset-0 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-gray-700"></i>
                    edit kategori
                </h2>
                <button type="button" onclick="closeEditModal()" class="text-gray-500 text-lg hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="post" class="px-4 py-4 space-y-3">
                <input type="hidden" name="aksi" value="edit_kategori">
                <input type="hidden" name="id_kategori" id="edit_id_kategori">

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        nama kategori <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="nama_kategori"
                        id="edit_nama_kategori"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        required
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        deskripsi (opsional)
                    </label>
                    <textarea name="deskripsi_kategori" id="edit_deskripsi_kategori" rows="3"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">
                        batal
                    </button>

                    <button type="submit" class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs hover:bg-gray-800">
                        simpan perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
<!-- modal tambah kategori dari sidebar -->
<div id="modalAddKategori" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-gray-700"></i>
                tambah kategori
            </h2>
            <button type="button" onclick="closeAddKategoriModal()" class="text-gray-500 text-lg hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="tambah_kategori">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    nama kategori <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="nama_kategori"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    deskripsi (opsional)
                </label>
                <textarea
                    name="deskripsi_kategori"
                    rows="3"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                ></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeAddKategoriModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50"
                >
                    batal
                </button>

                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs hover:bg-gray-800"
                >
                    simpan kategori
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const overlay     = document.getElementById('overlay');
    const modalAdd    = document.getElementById('modalAdd');
    const modalEdit   = document.getElementById('modalEdit');

    function showOverlay() {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }
    function hideOverlay() {
        overlay.classList.remove('flex');
        overlay.classList.add('hidden');
    }

    function openAddModal() {
        modalAdd.classList.remove('hidden');
        modalAdd.classList.add('flex');
        showOverlay();
    }
    function closeAddModal() {
        modalAdd.classList.remove('flex');
        modalAdd.classList.add('hidden');
        hideOverlay();
    }

    function openEditModal(id, nama, deskripsi) {
        document.getElementById('edit_id_kategori').value = id;
        document.getElementById('edit_nama_kategori').value = nama.replace(/\\'/g, "'");
        document.getElementById('edit_deskripsi_kategori').value = deskripsi.replace(/\\'/g, "'");
        modalEdit.classList.remove('hidden');
        modalEdit.classList.add('flex');
        showOverlay();
    }
    function closeEditModal() {
        modalEdit.classList.remove('flex');
        modalEdit.classList.add('hidden');
        hideOverlay();
    }
    function openAddKategoriModal() {
        const m = document.getElementById('modalAddKategori');
        if (!m) return;
        m.classList.remove('hidden');
        m.classList.add('flex');

        // gunakan overlay yang sudah ada
        const overlay = document.getElementById('overlay');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        }
    }

    function closeAddKategoriModal() {
        const m = document.getElementById('modalAddKategori');
        if (!m) return;
        m.classList.remove('flex');
        m.classList.add('hidden');

        const overlay = document.getElementById('overlay');
        if (overlay) {
            overlay.classList.remove('flex');
            overlay.classList.add('hidden');
        }
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
