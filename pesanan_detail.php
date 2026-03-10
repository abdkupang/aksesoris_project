<?php
// file: pesanan_detail.php
include "config.php";

// hanya pembeli yang login
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pembeli') {
    // redirect ke login, setelah login balik ke halaman ini
    header("Location: auth/login.php?pesan=login_dulu&redirect=pesanan_saya.php");
    exit;
}

$id_pengguna = (int)$_SESSION['id_pengguna'];

// dukung id atau id_pesanan di url
$id_pesanan = 0;
if (isset($_GET['id'])) {
    $id_pesanan = (int)$_GET['id'];
} elseif (isset($_GET['id_pesanan'])) {
    $id_pesanan = (int)$_GET['id_pesanan'];
}

$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";

if ($id_pesanan <= 0) {
    die("pesanan tidak ditemukan.");
}

// ambil data pesanan milik pembeli ini
$sql_pesanan = "
    select *
    from pesanan
    where id_pesanan = $id_pesanan
    and id_pengguna = $id_pengguna
    limit 1
";
$q_pesanan = mysqli_query($koneksi, $sql_pesanan);

if (!$q_pesanan || mysqli_num_rows($q_pesanan) === 0) {
    die("pesanan tidak ditemukan atau tidak sesuai akun anda.");
}

$pesanan_data = mysqli_fetch_assoc($q_pesanan);

// ambil item pesanan
$sql_item = "
    select *
    from pesanan_item
    where id_pesanan = $id_pesanan
";
$q_item = mysqli_query($koneksi, $sql_item);

$items = [];
if ($q_item && mysqli_num_rows($q_item) > 0) {
    while ($row = mysqli_fetch_assoc($q_item)) {
        $items[] = $row;
    }
}

// ambil bukti transfer terbaru (jika ada)
// Note: tabel bukti_transfer menyimpan nama_file (bukan path)
$sql_bukti = "
    select *
    from bukti_transfer
    where id_pesanan = $id_pesanan
    order by diunggah_pada desc
    limit 1
";
$q_bukti     = mysqli_query($koneksi, $sql_bukti);
$bukti_data  = ($q_bukti && mysqli_num_rows($q_bukti) > 0) ? mysqli_fetch_assoc($q_bukti) : null;

// pesan error upload
$pesan_error_upload = "";

// proses upload bukti transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'upload_bukti') {

    // hanya boleh upload jika status pesanan masih menunggu pembayaran / konfirmasi
    if (!in_array($pesanan_data['status_pesanan'], ['menunggu_pembayaran', 'menunggu_konfirmasi'])) {
        $pesan_error_upload = "bukti transfer hanya dapat diunggah untuk pesanan dengan status menunggu pembayaran atau menunggu konfirmasi.";
    } elseif (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
        $pesan_error_upload = "terjadi kesalahan saat mengunggah file. silakan coba lagi.";
    } else {
        $file = $_FILES['bukti_transfer'];

        // cek ukuran (misal maksimal 2mb)
        $max_size = 2 * 1024 * 1024; // 2mb
        if ($file['size'] > $max_size) {
            $pesan_error_upload = "ukuran file terlalu besar. maksimal 2mb.";
        } else {
            // cek ekstensi
            $ext_valid   = ['jpg', 'jpeg', 'png', 'webp'];
            $ext_asli    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext_asli, $ext_valid)) {
                $pesan_error_upload = "format file tidak didukung. gunakan jpg, jpeg, png, atau webp.";
            } else {
                // pastikan folder tujuan ada
                $target_dir = __DIR__ . "/uploads/bukti/";
                if (!is_dir($target_dir)) {
                    @mkdir($target_dir, 0777, true);
                }

                // nama file unik (simpan hanya nama file di DB)
                try {
                    $nama_file = "bukti_" . $id_pesanan . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext_asli;
                } catch (Exception $e) {
                    $nama_file = "bukti_" . $id_pesanan . "_" . time() . "_" . substr(md5(uniqid('', true)),0,8) . "." . $ext_asli;
                }

                $target_path_fs = $target_dir . $nama_file;

                if (!move_uploaded_file($file['tmp_name'], $target_path_fs)) {
                    $pesan_error_upload = "gagal menyimpan file bukti transfer.";
                } else {
                    // simpan NAMA FILE ke tabel bukti_transfer dan update status pesanan
                    $nama_file_esc = mysqli_real_escape_string($koneksi, $nama_file);

                    mysqli_begin_transaction($koneksi);
                    try {
                        // insert menyimpan nama_file (bukan path)
                        $sql_insert_bukti = "
                            insert into bukti_transfer (id_pesanan, nama_file, status_verifikasi, catatan)
                            values ($id_pesanan, '$nama_file_esc', 'menunggu', '')
                        ";
                        $q_insert_bukti = mysqli_query($koneksi, $sql_insert_bukti);
                        if (!$q_insert_bukti) {
                            throw new Exception("gagal menyimpan data bukti transfer: " . mysqli_error($koneksi));
                        }

                        // update status pesanan menjadi menunggu_konfirmasi
                        $sql_update_pesanan = "
                            update pesanan
                            set status_pesanan = 'menunggu_konfirmasi'
                            where id_pesanan = $id_pesanan
                        ";
                        $q_update_pesanan = mysqli_query($koneksi, $sql_update_pesanan);
                        if (!$q_update_pesanan) {
                            throw new Exception("gagal mengupdate status pesanan: " . mysqli_error($koneksi));
                        }

                        mysqli_commit($koneksi);

                        // redirect agar tidak re-post form dan tampilkan pesan sukses
                        header("Location: pesanan_detail.php?id=" . $id_pesanan . "&pesan=upload_sukses");
                        exit;

                    } catch (Exception $e) {
                        // rollback DB dan hapus file yang sudah dipindah
                        mysqli_rollback($koneksi);
                        if (file_exists($target_path_fs) && is_file($target_path_fs)) {
                            @unlink($target_path_fs);
                        }
                        $pesan_error_upload = $e->getMessage();
                    }
                }
            }
        }
    }
}

// jika ada bukti di DB, siapkan path tampilannya (relatif)
$display_bukti_path = null;
if ($bukti_data && !empty($bukti_data['nama_file'])) {
    $nama_file = basename($bukti_data['nama_file']);
    $fs_path = __DIR__ . '/uploads/bukti/' . $nama_file;
    if (file_exists($fs_path) && is_file($fs_path)) {
        // path relatif untuk HTML
        $display_bukti_path = 'uploads/bukti/' . $nama_file;
    } else {
        $display_bukti_path = null;
    }
}
function badge_status_pesanan(string $status): string
{
    $map = [
        'menunggu_pembayaran' => 'bg-yellow-100 text-yellow-800',
        'menunggu_konfirmasi' => 'bg-amber-100 text-amber-800',
        'diproses'            => 'bg-blue-100 text-blue-800',
        'dikirim'             => 'bg-indigo-100 text-indigo-800',
        'selesai'             => 'bg-emerald-100 text-emerald-800',
        'dibatalkan'          => 'bg-red-100 text-red-800',
    ];

    $class = $map[$status] ?? 'bg-gray-100 text-gray-700';

    return sprintf(
        '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium %s">%s</span>',
        $class,
        htmlspecialchars($status)
    );
}
function badge_status_verifikasi(?string $status): string
{
    if ($status === null) {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">belum ada</span>';
    }

    $map = [
        'menunggu' => 'bg-yellow-100 text-yellow-800',
        'diterima' => 'bg-emerald-100 text-emerald-800',
        'ditolak'  => 'bg-red-100 text-red-800',
    ];

    $class = $map[$status] ?? 'bg-gray-100 text-gray-700';

    return sprintf(
        '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium %s">%s</span>',
        $class,
        htmlspecialchars($status)
    );
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>detail pesanan - <?php echo htmlspecialchars($pesanan_data['kode_pesanan']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

    <?php include "layout/navbar.php"; ?>

<main class="max-w-5xl mx-auto px-4 py-6">
    <!-- judul + kode pesanan -->
    <h1 class="text-xl font-semibold text-gray-800 mb-1 flex items-center gap-2">
        <i class="fa-solid fa-file-invoice text-gray-700"></i>
        detail pesanan
    </h1>
    <p class="text-xs text-gray-500 mb-4 flex items-center gap-2">
        <i class="fa-solid fa-hashtag text-gray-400"></i>
        kode pesanan: <?php echo htmlspecialchars($pesanan_data['kode_pesanan']); ?>
    </p>

    <!-- pesan umum -->
    <?php if ($pesan === "pesanan_berhasil"): ?>
        <div class="alert-auto-hide mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span>pesanan berhasil dibuat. silakan lakukan pembayaran sesuai nominal dan upload bukti transfer.</span>
        </div>
    <?php elseif ($pesan === "upload_sukses"): ?>
        <div class="alert-auto-hide mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
            <i class="fa-solid fa-file-circle-check"></i>
            <span>bukti transfer berhasil diunggah, menunggu konfirmasi penjual.</span>
        </div>
    <?php endif; ?>

    <!-- pesan error upload -->
    <?php if ($pesan_error_upload !== ""): ?>
        <div class="alert-auto-hide mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo htmlspecialchars($pesan_error_upload); ?></span>
        </div>
    <?php endif; ?>

    <!-- script auto-hide + fade-out untuk semua .alert-auto-hide -->
    <script>
        (function () {
            var alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function (el) {
                el.style.transition = 'opacity 0.5s ease';
                setTimeout(function () {
                    el.style.opacity = '0';
                    setTimeout(function () {
                        el.classList.add('hidden');
                    }, 500);
                }, 3000);
            });
        })();
    </script>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- informasi pesanan -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-receipt text-gray-700"></i>
                informasi pesanan
            </h2>
            <dl class="text-xs text-gray-700 space-y-1">
                <div class="flex justify-between items-center">
                    <dt class="flex items-center gap-1">
                        <i class="fa-solid fa-clipboard-check text-gray-500"></i>
                        status pesanan
                    </dt>
<dd>
    <?php echo badge_status_pesanan($pesanan_data['status_pesanan']); ?>
</dd>

                </div>
                <div class="flex justify-between items-center">
                    <dt class="flex items-center gap-1">
                        <i class="fa-solid fa-sack-dollar text-gray-500"></i>
                        total harga
                    </dt>
                    <dd class="font-semibold">
                        rp <?php echo number_format($pesanan_data['total_harga'], 0, ',', '.'); ?>
                    </dd>
                </div>
                <div class="flex justify-between items-center">
                    <dt class="flex items-center gap-1">
                        <i class="fa-solid fa-credit-card text-gray-500"></i>
                        metode pembayaran
                    </dt>
                    <dd><?php echo htmlspecialchars($pesanan_data['metode_pembayaran']); ?></dd>
                </div>
                <div class="mt-1">
                    <dt class="mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-clock text-gray-500"></i>
                        tanggal dibuat
                    </dt>
                    <dd>
                        <?php echo date('d-m-Y H:i', strtotime($pesanan_data['dibuat_pada'])); ?>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- informasi pengiriman -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-truck-fast text-gray-700"></i>
                informasi pengiriman
            </h2>
            <dl class="text-xs text-gray-700 space-y-1">
                <div>
                    <dt class="mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-user text-gray-500"></i>
                        nama penerima
                    </dt>
                    <dd class="font-semibold">
                        <?php echo htmlspecialchars($pesanan_data['nama_penerima']); ?>
                    </dd>
                </div>
                <div>
                    <dt class="mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-phone text-gray-500"></i>
                        no hp penerima
                    </dt>
                    <dd>
                        <?php echo htmlspecialchars($pesanan_data['no_hp_penerima']); ?>
                    </dd>
                </div>
                <div>
                    <dt class="mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-location-dot text-gray-500"></i>
                        alamat pengiriman
                    </dt>
                    <dd class="whitespace-pre-line">
                        <?php echo htmlspecialchars($pesanan_data['alamat_pengiriman']); ?>
                    </dd>
                </div>
                <?php if (!empty($pesanan_data['catatan'])): ?>
                    <div>
                        <dt class="mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-note-sticky text-gray-500"></i>
                            catatan
                        </dt>
                        <dd class="whitespace-pre-line">
                            <?php echo htmlspecialchars($pesanan_data['catatan']); ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- daftar item pesanan -->
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-list text-gray-700"></i>
            item dalam pesanan
        </h2>

        <?php if (count($items) === 0): ?>
            <p class="text-xs text-gray-500 flex items-center gap-1">
                <i class="fa-solid fa-box-open text-gray-400"></i>
                tidak ada item dalam pesanan ini.
            </p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($items as $item): ?>
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 last:border-b-0 last:pb-0">
                        <div>
                            <p class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                <i class="fa-solid fa-tag text-gray-500"></i>
                                <?php echo htmlspecialchars($item['nama_produk']); ?>
                            </p>
                            <p class="text-[11px] text-gray-500">
                                <?php echo (int)$item['jumlah']; ?> x
                                rp <?php echo number_format($item['harga'], 0, ',', '.'); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold text-gray-900">
                                rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- blok bukti transfer + instruksi pembayaran -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- instruksi pembayaran -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-money-check-dollar text-gray-700"></i>
                instruksi pembayaran
            </h2>
            <p class="text-xs text-gray-600 mb-2 flex items-center gap-1">
                <i class="fa-solid fa-circle-info text-gray-400"></i>
                silakan transfer sesuai nominal total harga ke rekening berikut:
            </p>
            <ul class="text-xs text-gray-700 list-disc list-inside mb-2">
                <li>bank contoh: 123 456 789 a.n. toko aksesoris</li>
                <li>jumlah transfer: <strong>rp <?php echo number_format($pesanan_data['total_harga'], 0, ',', '.'); ?></strong></li>
            </ul>
            <p class="text-[11px] text-gray-500 flex items-start gap-1">
                <i class="fa-solid fa-circle-exclamation text-yellow-500 mt-[2px]"></i>
                setelah melakukan pembayaran, upload bukti transfer menggunakan form di samping.
                pesanan akan diproses setelah pembayaran terverifikasi oleh penjual.
            </p>
        </div>

        <!-- upload & status bukti transfer -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar text-gray-700"></i>
                bukti transfer
            </h2>

            <?php if ($bukti_data): ?>
                <div class="mb-3 text-xs text-gray-600">
                    <p class="mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-shield-halved text-gray-500"></i>
                        status verifikasi:
<?php echo badge_status_verifikasi($bukti_data['status_verifikasi']); ?>

                    </p>
                    <?php if (!empty($bukti_data['catatan'])): ?>
                        <p class="mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-note-sticky text-gray-500"></i>
                            catatan: <?php echo htmlspecialchars($bukti_data['catatan']); ?>
                        </p>
                    <?php endif; ?>
                    <p class="mb-2 text-[11px] text-gray-400 flex items-center gap-1">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        diunggah pada: <?php echo date('d-m-Y H:i', strtotime($bukti_data['diunggah_pada'])); ?>
                    </p>

                    <?php if ($display_bukti_path): ?>
                        <div class="border border-gray-200 rounded-md overflow-hidden max-h-64">
                            <img
                                src="<?php echo htmlspecialchars($display_bukti_path); ?>"
                                alt="bukti transfer"
                                class="w-full object-contain"
                            >
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-gray-500">
                            gambar bukti tidak ditemukan di server.
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-xs text-gray-500 mb-3 flex items-center gap-1">
                    <i class="fa-regular fa-image text-gray-400"></i>
                    belum ada bukti transfer yang diunggah.
                </p>
            <?php endif; ?>

            <?php if (in_array($pesanan_data['status_pesanan'], ['menunggu_pembayaran', 'menunggu_konfirmasi'])): ?>
                <form method="post" enctype="multipart/form-data" class="mt-3 space-y-2">
                    <input type="hidden" name="aksi" value="upload_bukti">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                            <i class="fa-solid fa-upload text-gray-500"></i>
                            unggah bukti transfer (jpg, jpeg, png, webp, maks. 2mb)
                        </label>
                        <input
                            type="file"
                            name="bukti_transfer"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="w-full text-xs"
                            required
                        >
                    </div>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 inline-flex items-center gap-2"
                    >
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        kirim bukti transfer
                    </button>
                </form>
            <?php else: ?>
                <p class="mt-2 text-[11px] text-gray-400 flex items-center gap-1">
                    <i class="fa-solid fa-circle-info text-gray-400"></i>
                    upload bukti transfer tidak tersedia untuk status pesanan ini.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <a
            href="pesanan_saya.php"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50"
        >
            <i class="fa-solid fa-arrow-left"></i>
            kembali ke daftar pesanan
        </a>
    </div>
</main>

    <?php include "layout/footer.php"; ?>

</body>
</html>
