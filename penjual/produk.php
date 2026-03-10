<?php
// file: penjual/produk.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/produk.php");
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

// ambil daftar kategori untuk form
$sql_kategori = "select id_kategori, nama_kategori from kategori order by nama_kategori asc";
$q_kategori   = mysqli_query($koneksi, $sql_kategori);
$kategori_list = [];
if ($q_kategori && mysqli_num_rows($q_kategori) > 0) {
    while ($row = mysqli_fetch_assoc($q_kategori)) {
        $kategori_list[] = $row;
    }
}

// proses aksi (tambah / edit / hapus / upload gambar / hapus gambar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    // tambah produk
    if ($aksi === 'tambah_produk') {
        $nama_produk   = trim($_POST['nama_produk'] ?? "");
        $id_kategori   = isset($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : null;
        $deskripsi     = trim($_POST['deskripsi'] ?? "");
        $harga         = trim($_POST['harga'] ?? "0");
        $stok          = (int)($_POST['stok'] ?? 0);
        $status_produk = $_POST['status_produk'] ?? 'draft';

        if ($nama_produk === "" || $harga === "") {
            $pesan = "nama produk dan harga wajib diisi.";
        } else {
            $nama_esc      = mysqli_real_escape_string($koneksi, $nama_produk);
            $desk_esc      = mysqli_real_escape_string($koneksi, $deskripsi);
            $status_esc    = mysqli_real_escape_string($koneksi, $status_produk);
            $harga_float   = (float)str_replace(",", "", $harga);

            $kolom_kat = "null";
            if (!empty($id_kategori)) {
                $kolom_kat = (int)$id_kategori;
            }

            $sql_insert = "
                insert into produk (id_kategori, nama_produk, deskripsi, harga, stok, status_produk)
                values ($kolom_kat, '$nama_esc', '$desk_esc', $harga_float, $stok, '$status_esc')
            ";
            $q_insert = mysqli_query($koneksi, $sql_insert);

            if ($q_insert) {
                $id_produk_baru = (int)mysqli_insert_id($koneksi);
                header("Location: produk.php?pesan=tambah_sukses&id_produk=" . $id_produk_baru . "&show_upload=1");
                exit;
            } else {
                $pesan = "gagal menambah produk: " . mysqli_error($koneksi);
            }
        }
    }

    // edit produk
    if ($aksi === 'edit_produk') {
        $id_produk     = (int)($_POST['id_produk'] ?? 0);
        $nama_produk   = trim($_POST['nama_produk'] ?? "");
        $id_kategori   = isset($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : null;
        $deskripsi     = trim($_POST['deskripsi'] ?? "");
        $harga         = trim($_POST['harga'] ?? "0");
        $stok          = (int)($_POST['stok'] ?? 0);
        $status_produk = $_POST['status_produk'] ?? 'draft';

        if ($id_produk <= 0) {
            $pesan = "produk tidak valid.";
        } else {
            $nama_esc      = mysqli_real_escape_string($koneksi, $nama_produk);
            $desk_esc      = mysqli_real_escape_string($koneksi, $deskripsi);
            $status_esc    = mysqli_real_escape_string($koneksi, $status_produk);
            $harga_float   = (float)str_replace(",", "", $harga);

            $kolom_kat = "null";
            if (!empty($id_kategori)) {
                $kolom_kat = (int)$id_kategori;
            }

            $sql_update = "
                update produk
                set id_kategori   = $kolom_kat,
                    nama_produk   = '$nama_esc',
                    deskripsi     = '$desk_esc',
                    harga         = $harga_float,
                    stok          = $stok,
                    status_produk = '$status_esc'
                where id_produk = $id_produk
            ";

            $q_update = mysqli_query($koneksi, $sql_update);

            if ($q_update) {
                header("Location: produk.php?pesan=edit_sukses");
                exit;
            } else {
                $pesan = "gagal mengedit produk: " . mysqli_error($koneksi);
            }
        }
    }

    // "hapus" produk (set status nonaktif)
    if ($aksi === 'hapus_produk') {
        $id_produk = (int)($_POST['id_produk'] ?? 0);

        if ($id_produk > 0) {
            $sql_nonaktif = "
                update produk
                set status_produk = 'nonaktif'
                where id_produk = $id_produk
            ";
            $q_nonaktif = mysqli_query($koneksi, $sql_nonaktif);

            if ($q_nonaktif) {
                header("Location: produk.php?pesan=hapus_sukses");
                exit;
            } else {
                $pesan = "gagal menonaktifkan produk: " . mysqli_error($koneksi);
            }
        }
    }

    // upload gambar produk (simpan NAMA FILE di kolom produk.gambar_produk)
    if ($aksi === 'upload_gambar') {
        $id_produk = (int)($_POST['id_produk'] ?? 0);

        if ($id_produk <= 0) {
            $pesan = "produk tidak valid untuk upload gambar.";
        } elseif (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
            $pesan = "terjadi kesalahan saat mengunggah file gambar.";
        } else {
            $file = $_FILES['gambar'];

            // cek ukuran (misal maksimal 2mb)
            $max_size = 2 * 1024 * 1024; // 2 MB
            if ($file['size'] > $max_size) {
                $pesan = "ukuran file terlalu besar. maksimal 2mb.";
            } else {
                // cek ekstensi
                $ext_valid = ['jpg', 'jpeg', 'png', 'webp'];
                $ext_asli  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext_asli, $ext_valid)) {
                    $pesan = "format file tidak didukung. gunakan jpg, jpeg, png, atau webp.";
                } else {
                    // pastikan folder ada (absolute path)
                    $upload_dir_fs = __DIR__ . "/../uploads/produk/";
                    if (!is_dir($upload_dir_fs)) {
                        @mkdir($upload_dir_fs, 0777, true);
                    }

                    // generate nama file (tanpa path)
                    try {
                        $nama_file   = "produk_" . $id_produk . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext_asli;
                    } catch (Exception $e) {
                        // fallback jika random_bytes gagal
                        $nama_file   = "produk_" . $id_produk . "_" . time() . "_" . substr(md5(uniqid('', true)),0,8) . "." . $ext_asli;
                    }

                    $target_path_fs = $upload_dir_fs . $nama_file;

                    if (!move_uploaded_file($file['tmp_name'], $target_path_fs)) {
                        $pesan = "gagal menyimpan file gambar.";
                    } else {
                        // ambil gambar lama (jika ada) untuk dihapus dari filesystem nanti
                        $sql_old = "select gambar_produk from produk where id_produk = $id_produk limit 1";
                        $q_old = mysqli_query($koneksi, $sql_old);
                        $old_filename = null;
                        if ($q_old && mysqli_num_rows($q_old) > 0) {
                            $r_old = mysqli_fetch_assoc($q_old);
                            $old_filename = $r_old['gambar_produk'];
                        }

                        // simpan hanya NAMA FILE ke DB
                        $nama_file_esc = mysqli_real_escape_string($koneksi, $nama_file);
                        $sql_update_img = "
                            update produk
                            set gambar_produk = '$nama_file_esc',
                                diperbarui_pada = current_timestamp
                            where id_produk = $id_produk
                        ";

                        if (mysqli_query($koneksi, $sql_update_img)) {
                            // hapus file lama (jika ada dan beda)
                            if (!empty($old_filename) && $old_filename !== $nama_file) {
                                $fs_old = $upload_dir_fs . $old_filename;
                                if (file_exists($fs_old) && is_file($fs_old)) {
                                    @unlink($fs_old);
                                }
                            }

                            header("Location: produk.php?pesan=upload_sukses&id_produk=" . $id_produk . "&show_upload=1");
                            exit;
                        } else {
                            // rollback file pada fs jika update db gagal
                            @unlink($target_path_fs);
                            $pesan = "gagal menyimpan data gambar di database: " . mysqli_error($koneksi);
                        }
                    }
                }
            }
        }
    }

    // hapus gambar produk (kosongkan kolom dan hapus file di server)
    if ($aksi === 'hapus_gambar_produk') {
        $id_produk = (int)($_POST['id_produk'] ?? 0);

        if ($id_produk <= 0) {
            $pesan = "produk tidak valid untuk penghapusan gambar.";
        } else {
            // ambil nama file lama
            $sql_get = "select gambar_produk from produk where id_produk = $id_produk limit 1";
            $q_get = mysqli_query($koneksi, $sql_get);
            $old_filename = null;
            if ($q_get && mysqli_num_rows($q_get) > 0) {
                $r = mysqli_fetch_assoc($q_get);
                $old_filename = $r['gambar_produk'];
            }

            // set kolom ke NULL (atau '') terlebih dahulu di DB
            $sql_clear = "update produk set gambar_produk = NULL, diperbarui_pada = current_timestamp where id_produk = $id_produk";
            if (mysqli_query($koneksi, $sql_clear)) {
                // jika ada file lama, hapus dari filesystem
                if (!empty($old_filename)) {
                    $upload_dir_fs = __DIR__ . "/../uploads/produk/";
                    $fs_old = $upload_dir_fs . $old_filename;
                    if (file_exists($fs_old) && is_file($fs_old)) {
                        @unlink($fs_old);
                    }
                }

                header("Location: produk.php?pesan=hapus_sukses&id_produk=" . $id_produk . "&show_upload=1");
                exit;
            } else {
                $pesan = "gagal menghapus referensi gambar di database: " . mysqli_error($koneksi);
            }
        }
    }

    // sebelumnya ada aksi set_gambar_utama (untuk tabel produk_gambar) — sudah dihapus karena hanya satu gambar disimpan di produk.gambar_produk
}

// ambil daftar produk dengan kategori
$sql_produk = "
    select
        p.id_produk,
        p.id_kategori,
        p.nama_produk,
        p.harga,
        p.stok,
        p.status_produk,
        p.gambar_produk,
        k.nama_kategori
    from produk p
    left join kategori k on k.id_kategori = p.id_kategori
    order by p.dibuat_pada desc
";
$q_produk = mysqli_query($koneksi, $sql_produk);

$produk_list = [];
if ($q_produk && mysqli_num_rows($q_produk) > 0) {
    while ($row = mysqli_fetch_assoc($q_produk)) {
        $produk_list[] = $row;
    }
}

// id produk yang sedang difokuskan di modal (misal dari redirect upload / tombol kelola gambar)
$upload_produk_id = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$upload_gambar_list = []; // akan berisi max 1 item dari produk.gambar_produk

if ($upload_produk_id > 0) {
    // ambil gambar dari tabel produk (kolom gambar_produk) — jika ada
    $sql_galeri = "
        select
            id_produk,
            gambar_produk,
            diperbarui_pada
        from produk
        where id_produk = $upload_produk_id
        limit 1
    ";
    $q_galeri = mysqli_query($koneksi, $sql_galeri);
    if ($q_galeri && mysqli_num_rows($q_galeri) > 0) {
        $g = mysqli_fetch_assoc($q_galeri);
        if (!empty($g['gambar_produk'])) {
            // ubah format agar kompatibel dengan tampilan lama (kita simpan nama file, path dibentuk saat render)
            $upload_gambar_list[] = [
                'id_gambar'   => $g['id_produk'],
                'filename'    => $g['gambar_produk'],
                'path_gambar' => "/uploads/produk/" . $g['gambar_produk'], // untuk kemudahan di view gunakan leading slash
                'is_utama'    => 1,
                'dibuat_pada' => $g['diperbarui_pada']
            ];
        }
    }
}


// untuk modal upload: jika ada id_produk dari query, kita pakai untuk prefilling
$upload_focus_id = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$show_upload_modal_auto = isset($_GET['show_upload']) && $_GET['show_upload'] == '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>kelola produk - panel penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- ikon fontawesome (pastikan tersedia di proyek Anda) -->
    <script src="https://kit.fontawesome.com/your-kit.js" crossorigin="anonymous"></script>
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
                    <i class="fa-solid fa-box-open text-gray-700 shrink-0"></i>
                    <span class="truncate">kelola produk</span>
                </h1>

                <!-- deskripsi HANYA desktop -->
                <p class="hidden md:flex text-xs text-gray-500 items-center gap-1">
                    <i class="fa-solid fa-wrench text-gray-500"></i>
                    tambah, ubah, dan atur status produk di toko aksesoris.
                </p>
            </div>
        </div>

        <!-- kanan: tombol tambah -->
        <div class="flex items-center gap-2">

            <!-- MOBILE: icon only -->
            <button
                type="button"
                onclick="openAddModal()"
                class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-md bg-gray-900 text-white hover:bg-gray-800"
                aria-label="Tambah produk"
            >
                <i class="fa-solid fa-plus"></i>
            </button>

            <!-- DESKTOP: icon + text -->
            <button
                type="button"
                onclick="openAddModal()"
                class="hidden md:inline-flex items-center gap-1 px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800"
            >
                <i class="fa-solid fa-plus"></i>
                tambah produk
            </button>

        </div>
    </div>
</header>


    <!-- isi -->
    <section class="max-w-6xl mx-auto px-6 py-6">

        <!-- pesan -->
        <?php if ($pesan === "tambah_sukses"): ?>
            <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i>
                produk baru berhasil ditambahkan. jangan lupa upload gambar produk.
            </div>
        <?php elseif ($pesan === "edit_sukses"): ?>
            <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square"></i>
                data produk berhasil diperbarui.
            </div>
        <?php elseif ($pesan === "hapus_sukses"): ?>
            <div id="alertMsg" class="mb-4 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-3 text-sm text-yellow-900 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                produk berhasil dinonaktifkan. produk nonaktif tidak akan muncul di toko.
            </div>
        <?php elseif ($pesan === "upload_sukses"): ?>
            <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
                <i class="fa-solid fa-image"></i>
                gambar produk berhasil diunggah.
            </div>
        <?php elseif ($pesan !== ""): ?>
            <div id="alertMsg" class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
                <i class="fa-solid fa-circle-xmark"></i>
                <?php echo htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <!-- script auto hide (3 detik + fade) -->
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

<?php if (count($produk_list) > 0): ?>
<div class="space-y-3 md:hidden">
    <?php foreach ($produk_list as $p): ?>
        <?php
            $status = $p['status_produk'];
            $badge_class = "bg-gray-100 text-gray-700";
            $icon = "fa-circle-dot";

            if ($status === 'aktif') {
                $badge_class = "bg-emerald-100 text-emerald-800";
                $icon = "fa-check-circle";
            } elseif ($status === 'draft') {
                $badge_class = "bg-yellow-100 text-yellow-800";
                $icon = "fa-clock";
            } elseif ($status === 'nonaktif') {
                $badge_class = "bg-red-100 text-red-800";
                $icon = "fa-ban";
            }
        ?>

        <div class="bg-white border border-gray-200 rounded-lg p-3">

            <!-- HEADER -->
            <div class="flex gap-3 mb-2">
                <?php if (!empty($p['gambar_produk'])): ?>
                    <img
                        src="../uploads/produk/<?php echo htmlspecialchars($p['gambar_produk']); ?>"
                        class="w-16 h-16 rounded-md object-cover"
                        alt="produk"
                    >
                <?php else: ?>
                    <div class="w-16 h-16 rounded-md bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
                        no image
                    </div>
                <?php endif; ?>

                <div class="flex-1">
                    <p class="font-semibold text-sm text-gray-800">
                        <?php echo htmlspecialchars($p['nama_produk']); ?>
                    </p>
                    <p class="text-xs text-gray-500">
                        <?php echo htmlspecialchars($p['nama_kategori'] ?? '-'); ?>
                    </p>

                    <div class="mt-1 text-xs text-gray-600 flex items-center gap-2">
                        <span>Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></span>
                        <span>stok: <?php echo (int)$p['stok']; ?></span>
                    </div>

                    <!-- STATUS BADGE -->
                    <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[11px] font-medium <?php echo $badge_class; ?>">
                        <i class="fa-solid <?php echo $icon; ?>"></i>
                        <?php echo htmlspecialchars($status); ?>
                    </span>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="flex flex-wrap gap-1 justify-end">

                <!-- upload gambar -->
<button
    type="button"
    data-id="<?php echo (int)$p['id_produk']; ?>"
    data-nama="<?php echo htmlspecialchars($p['nama_produk']); ?>"
    data-gambar="<?php echo htmlspecialchars($p['gambar_produk'] ?? ''); ?>"
    onclick="openUploadModalFromButton(this)"
    class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-gray-300 text-[11px] text-gray-700"
>
    <i class="fa-solid fa-image"></i>
    gambar
</button>


                <!-- edit -->
                <button
                    type="button"
                    onclick="openEditModal(
                        <?php echo (int)$p['id_produk']; ?>,
                        '<?php echo htmlspecialchars(addslashes($p['nama_produk'])); ?>',
                        '<?php echo (int)$p['id_kategori']; ?>',
                        '<?php echo htmlspecialchars(addslashes($p['status_produk'])); ?>',
                        '<?php echo (float)$p['harga']; ?>',
                        '<?php echo (int)$p['stok']; ?>'
                    )"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-900 text-white text-[11px]"
                >
                    <i class="fa-solid fa-pen"></i>
                    edit
                </button>

                <!-- hapus -->
                <form
                    method="post"
                    onsubmit="return confirm('yakin ingin menonaktifkan produk ini?');"
                >
                    <input type="hidden" name="aksi" value="hapus_produk">
                    <input type="hidden" name="id_produk" value="<?php echo (int)$p['id_produk']; ?>">

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


        <!-- tabel produk -->
<div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full text-xs">
<thead class="bg-gray-50">
    <tr>
        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-box-open mr-1 text-gray-500"></i>
            nama produk
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-tags mr-1 text-gray-500"></i>
            kategori
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-money-bill-wave mr-1 text-gray-500"></i>
            harga
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-boxes-stacked mr-1 text-gray-500"></i>
            stok
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-circle-info mr-1 text-gray-500"></i>
            status
        </th>

        <th class="px-3 py-2 text-right font-semibold text-gray-600">
            <i class="fa-solid fa-ellipsis-vertical mr-1 text-gray-500"></i>
            aksi
        </th>
    </tr>
</thead>

                <tbody>
                    <?php if (count($produk_list) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-xs text-gray-500 flex justify-center items-center gap-1">
                                <i class="fa-regular fa-folder-open"></i>
                                belum ada produk. klik tombol "tambah produk" untuk mulai menambahkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produk_list as $p): ?>
                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-800 flex items-center gap-3">
                                    <?php
                                        // tampilkan thumbnail kecil jika ada gambar_produk (simpan nama file)
                                        if (!empty($p['gambar_produk'])):
                                            $img_src = "../uploads/produk/" . $p['gambar_produk'];
                                    ?>
                                        <div class="w-12 h-12 bg-gray-100 rounded-md overflow-hidden">
                                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="thumb" class="w-full h-full object-cover">
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <?php echo htmlspecialchars($p['nama_produk']); ?>
                                    </div>
                                </td>

                                <td class="px-3 py-2 text-gray-600">
                                    <?php echo htmlspecialchars($p['nama_kategori'] ?? "-"); ?>
                                </td>

                                <td class="px-3 py-2 text-gray-800">
                                    rp <?php echo number_format($p['harga'], 0, ',', '.'); ?>
                                </td>

                                <td class="px-3 py-2 text-gray-800">
                                    <?php echo (int)$p['stok']; ?>
                                </td>

                                <td class="px-3 py-2">
                                    <?php
                                        $status = $p['status_produk'];
                                        $badge_class = "bg-gray-100 text-gray-700";
                                        $icon = "fa-circle-dot";

                                        if ($status === 'aktif') {
                                            $badge_class = "bg-emerald-100 text-emerald-800";
                                            $icon = "fa-check-circle";
                                        } elseif ($status === 'draft') {
                                            $badge_class = "bg-yellow-100 text-yellow-800";
                                            $icon = "fa-clock";
                                        } elseif ($status === 'nonaktif') {
                                            $badge_class = "bg-red-100 text-red-800";
                                            $icon = "fa-ban";
                                        }
                                    ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium <?php echo $badge_class; ?>">
                                        <i class="fa-solid <?php echo $icon; ?>"></i>
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <td class="px-3 py-2 text-right space-x-1">

                                    <!-- upload gambar -->
<button
    type="button"
    data-id="<?php echo (int)$p['id_produk']; ?>"
    data-nama="<?php echo htmlspecialchars($p['nama_produk']); ?>"
    data-gambar="<?php echo htmlspecialchars($p['gambar_produk'] ?? ''); ?>"
    onclick="openUploadModalFromButton(this)"
    class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-gray-300 text-[11px] text-gray-700"
>
    <i class="fa-solid fa-image"></i>
    gambar
</button>


                                    <!-- edit -->
                                    <button
                                        type="button"
                                        onclick="openEditModal(
                                            <?php echo (int)$p['id_produk']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($p['nama_produk'])); ?>',
                                            '<?php echo (int)$p['id_kategori']; ?>',
                                            '<?php echo htmlspecialchars(addslashes($p['status_produk'])); ?>',
                                            '<?php echo (float)$p['harga']; ?>',
                                            '<?php echo (int)$p['stok']; ?>'
                                        )"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-900 text-white text-[11px] hover:bg-gray-800"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                        edit
                                    </button>

                                    <!-- hapus -->
                                    <form method="post" class="inline" onsubmit="return confirm('yakin ingin menonaktifkan produk ini?');">
                                        <input type="hidden" name="aksi" value="hapus_produk">
                                        <input type="hidden" name="id_produk" value="<?php echo (int)$p['id_produk']; ?>">

                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-red-600 text-white text-[11px] hover:bg-red-700"
                                        >
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

   <!-- modal tambah produk -->
<div id="modalAdd" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-gray-700"></i>
                tambah produk
            </h2>
            <button
                type="button"
                onclick="closeAddModal()"
                class="text-gray-500 text-lg hover:text-gray-700"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="tambah_produk">

            <!-- nama produk -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-tag text-gray-600"></i>
                    nama produk <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="nama_produk"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- kategori -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-layer-group text-gray-600"></i>
                    kategori
                </label>
                <select
                    name="id_kategori"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
                    <option value="">-- pilih kategori --</option>
                    <?php foreach ($kategori_list as $k): ?>
                        <option value="<?php echo (int)$k['id_kategori']; ?>">
                            <?php echo htmlspecialchars($k['nama_kategori']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- deskripsi -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-align-left text-gray-600"></i>
                    deskripsi
                </label>
                <textarea
                    name="deskripsi"
                    rows="3"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                ></textarea>
            </div>

            <!-- harga & stok -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-money-bill-wave text-gray-600"></i>
                        harga <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        name="harga"
                        min="0"
                        step="100"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        required
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-boxes-stacked text-gray-600"></i>
                        stok
                    </label>
                    <input
                        type="number"
                        name="stok"
                        min="0"
                        step="1"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        value="0"
                    >
                </div>
            </div>

            <!-- status produk -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-toggle-on text-gray-600"></i>
                    status produk
                </label>
                <select
                    name="status_produk"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
                    <option value="draft">draft</option>
                    <option value="aktif">aktif</option>
                    <option value="nonaktif">nonaktif</option>
                </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeAddModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-circle-xmark"></i>
                    batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- modal edit produk -->
<div id="modalEdit" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-gray-700"></i>
                edit produk
            </h2>
            <button
                type="button"
                onclick="closeEditModal()" 
                class="text-gray-500 text-lg hover:text-gray-700"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi" value="edit_produk">
            <input type="hidden" name="id_produk" id="edit_id_produk">

            <!-- nama produk -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-tag text-gray-600"></i>
                    nama produk
                </label>
                <input
                    type="text"
                    name="nama_produk"
                    id="edit_nama_produk"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    required
                >
            </div>

            <!-- kategori -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-layer-group text-gray-600"></i>
                    kategori
                </label>
                <select
                    name="id_kategori"
                    id="edit_id_kategori"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
                    <option value="">-- pilih kategori --</option>
                    <?php foreach ($kategori_list as $k): ?>
                        <option value="<?php echo (int)$k['id_kategori']; ?>">
                            <?php echo htmlspecialchars($k['nama_kategori']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- harga & stok -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-money-bill-wave text-gray-600"></i>
                        harga
                    </label>
                    <input
                        type="number"
                        name="harga"
                        id="edit_harga"
                        min="0"
                        step="100"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-boxes-stacked text-gray-600"></i>
                        stok
                    </label>
                    <input
                        type="number"
                        name="stok"
                        id="edit_stok"
                        min="0"
                        step="1"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    >
                </div>
            </div>

            <!-- status produk -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-toggle-on text-gray-600"></i>
                    status produk
                </label>
                <select
                    name="status_produk"
                    id="edit_status_produk"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
                    <option value="draft">draft</option>
                    <option value="aktif">aktif</option>
                    <option value="nonaktif">nonaktif</option>
                </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeEditModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-circle-xmark"></i>
                    batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    simpan perubahan
                </button>
            </div>
        </form>
    </div>
</div>
<!-- modal upload gambar -->
<div
    id="modalUpload"
    class="fixed inset-0 hidden items-center justify-center z-50"
>          
    <div
        class="bg-white rounded-xl shadow-xl w-full max-w-md md:max-w-sm mx-4
               max-h-[90vh] md:max-h-[80vh]
               flex flex-col"
    >                    
                <!-- header -->
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-image text-gray-700"></i>
                        upload & kelola gambar produk
                    </h2>
                    <button
                        type="button"
                        onclick="closeUploadModal()"
                        class="text-gray-500 text-lg hover:text-gray-700"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
<div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300">

                <!-- form upload -->
                <form method="post" enctype="multipart/form-data" class="px-4 py-4 space-y-3">
                    <input type="hidden" name="aksi" value="upload_gambar">
                    <input type="hidden" name="id_produk" id="upload_id_produk" value="<?php echo (int)$upload_produk_id; ?>">

                    <!-- nama produk (diisi via JS atau pakai teks umum) -->
                    <div>
                        <p class="text-xs text-gray-700 mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-tag text-gray-600"></i>
                            produk: <span id="upload_nama_produk" class="font-semibold"></span>
                        </p>
                    </div>

                    <!-- input file -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-upload text-gray-600"></i>
                            pilih gambar (jpg / jpeg / png / webp, maks. 2mb)
                        </label>
                        <input
                            type="file"
                            name="gambar"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="w-full text-xs"
                            required
                        >
                    </div>

                    <p class="text-[11px] text-gray-400 flex items-center gap-1 mb-1">
                        <i class="fa-regular fa-circle-question"></i>
                        gambar akan menggantikan gambar sebelumnya (jika ada). hanya satu gambar disimpan per produk.
                    </p>

                    <!-- tombol upload -->
                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onclick="closeUploadModal()"
                            class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1"
                        >
                            <i class="fa-solid fa-circle-xmark"></i>
                            batal
                        </button>

                        <button
                            type="submit"
                            class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-1"
                        >
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            upload gambar
                        </button>
                    </div>
                </form>

                <!-- daftar gambar yang sudah diupload -->
            <!-- daftar gambar produk (dikontrol via JS) -->
            <div class="px-4 pb-4 border-t border-gray-200 mt-3">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-images text-gray-700"></i>
                    gambar produk saat ini
                </h3>

                <!-- wrapper gambar -->
                <div
                    id="uploadImageWrapper"
                    class="border rounded-md overflow-hidden bg-gray-50 relative hidden"
                >
                    <div class="aspect-square overflow-hidden">
                        <img
                            id="uploadImagePreview"
                            src=""
                            alt="gambar produk"
                            class="w-full h-full object-cover"
                        >
                    </div>

                    <!-- badge utama -->
                    <div class="absolute top-1 left-1 inline-flex items-center gap-1 px-2 py-[2px] rounded-full bg-black/70 text-[9px] text-yellow-300">
                        <i class="fa-solid fa-star"></i>
                        utama
                    </div>

                    <div class="p-1 flex items-center justify-between gap-1">
                        <span
                            id="uploadImageInfo"
                            class="text-[9px] text-gray-400"
                        ></span>

                        <!-- tombol hapus gambar -->
                        <form
                            method="post"
                            onsubmit="return confirm('hapus gambar produk ini?');"
                            class="m-0 p-0"
                        >
                            <input type="hidden" name="aksi" value="hapus_gambar_produk">
                            <input type="hidden" name="id_produk" id="hapus_id_produk">

                            <button
                                type="submit"
                                class="inline-flex items-center gap-1 px-2 py-[2px] rounded-md bg-red-600 text-white text-[9px] hover:bg-red-700"
                            >
                                <i class="fa-solid fa-trash"></i>
                                hapus gambar
                            </button>
                        </form>
                    </div>
                </div>

                <!-- teks jika tidak ada gambar -->
                <p
                    id="uploadNoImageText"
                    class="text-[11px] text-gray-400"
                >
                    belum ada gambar yang diunggah untuk produk ini.
                </p>
            </div>
</div>

</div>

</div>


    <script>
        const overlay     = document.getElementById('overlay');
        const modalAdd    = document.getElementById('modalAdd');
        const modalEdit   = document.getElementById('modalEdit');
        const modalUpload = document.getElementById('modalUpload');

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

        function openEditModal(id, nama, id_kat, status, harga, stok) {
            document.getElementById('edit_id_produk').value   = id;
            document.getElementById('edit_nama_produk').value = nama.replace(/\\'/g, "'");
            document.getElementById('edit_id_kategori').value = id_kat || "";

            document.getElementById('edit_status_produk').value = status;

            document.getElementById('edit_harga').value = harga;
            document.getElementById('edit_stok').value  = stok;

            modalEdit.classList.remove('hidden');
            modalEdit.classList.add('flex');
            showOverlay();
        }
        function closeEditModal() {
            modalEdit.classList.remove('flex');
            modalEdit.classList.add('hidden');
            hideOverlay();
        }

function openUploadModalFromButton(btn) {
    const id     = btn.dataset.id;
    const nama   = btn.dataset.nama;
    const gambar = btn.dataset.gambar;

    document.getElementById('upload_id_produk').value = id;
    document.getElementById('hapus_id_produk').value  = id;
    document.getElementById('upload_nama_produk').innerText = nama;

    const wrapper = document.getElementById('uploadImageWrapper');
    const img     = document.getElementById('uploadImagePreview');
    const info    = document.getElementById('uploadImageInfo');
    const noText  = document.getElementById('uploadNoImageText');

    if (gambar && gambar !== '') {
        img.src = '../uploads/produk/' + gambar;
        info.innerText = 'gambar utama produk';
        wrapper.classList.remove('hidden');
        noText.classList.add('hidden');
    } else {
        img.src = '';
        wrapper.classList.add('hidden');
        noText.classList.remove('hidden');
    }

    modalUpload.classList.remove('hidden');
    modalUpload.classList.add('flex');
    showOverlay();
}
        function closeUploadModal() {
            modalUpload.classList.remove('flex');
            modalUpload.classList.add('hidden');
            hideOverlay();
        }

        // auto buka modal upload jika datang dari tambah produk
        <?php if ($show_upload_modal_auto && $upload_focus_id > 0): ?>
        window.addEventListener('load', function () {
            openUploadModal(<?php echo $upload_focus_id; ?>, 'produk baru');
        });
        <?php endif; ?>
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
