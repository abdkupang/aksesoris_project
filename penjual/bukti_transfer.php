<?php
// file: penjual/bukti_transfer.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/bukti_transfer.php");
    exit;
}

if ($_SESSION['role'] !== 'penjual') {
    header("Location: ../toko.php");
    exit;
}

$id_pengguna  = (int)$_SESSION['id_pengguna'];
$nama_penjual = $_SESSION['nama_lengkap'] ?? "penjual";

$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";

// daftar status bukti
$status_bukti_all = ['menunggu','diterima','ditolak'];

// proses verifikasi bukti (setujui / tolak)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'verifikasi_bukti') {
    $id_bukti  = (int)($_POST['id_bukti'] ?? 0);
    $tindakan  = $_POST['tindakan'] ?? '';
    $catatan   = trim($_POST['catatan'] ?? '');

    if ($id_bukti <= 0 || !in_array($tindakan, ['terima','tolak'], true)) {
        header("Location: bukti_transfer.php?pesan=verifikasi_tidak_valid");
        exit;
    }

    // ambil info bukti + pesanan
    $sql_get = "
        select bt.*, p.id_pesanan, p.status_pesanan
        from bukti_transfer bt
        inner join pesanan p on p.id_pesanan = bt.id_pesanan
        where bt.id_bukti_transfer = $id_bukti
        limit 1
    ";
    $q_get = mysqli_query($koneksi, $sql_get);
    if (!$q_get || mysqli_num_rows($q_get) === 0) {
        header("Location: bukti_transfer.php?pesan=bukti_tidak_ditemukan");
        exit;
    }

    $row_bukti   = mysqli_fetch_assoc($q_get);
    $id_pesanan  = (int)$row_bukti['id_pesanan'];

    $status_bukti_baru   = ($tindakan === 'terima') ? 'diterima' : 'ditolak';
    $status_pesanan_baru = ($tindakan === 'terima') ? 'diproses' : 'dibatalkan';

    $status_bukti_esc   = mysqli_real_escape_string($koneksi, $status_bukti_baru);
    $status_pesanan_esc = mysqli_real_escape_string($koneksi, $status_pesanan_baru);
    $catatan_esc        = mysqli_real_escape_string($koneksi, $catatan);

    mysqli_begin_transaction($koneksi);
    try {
        // update bukti_transfer
        $sql_update_bukti = "
            update bukti_transfer
            set status_verifikasi = '$status_bukti_esc',
                catatan = '$catatan_esc',
                diverifikasi_pada = now()
            where id_bukti_transfer = $id_bukti
        ";
        if (!mysqli_query($koneksi, $sql_update_bukti)) {
            throw new Exception("gagal update bukti: " . mysqli_error($koneksi));
        }

        // update status pesanan
        $sql_update_pesanan = "
            update pesanan
            set status_pesanan = '$status_pesanan_esc'
            where id_pesanan = $id_pesanan
        ";
        if (!mysqli_query($koneksi, $sql_update_pesanan)) {
            throw new Exception("gagal update pesanan: " . mysqli_error($koneksi));
        }

        mysqli_commit($koneksi);
        header("Location: bukti_transfer.php?pesan=verifikasi_sukses");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        header("Location: bukti_transfer.php?pesan=" . urlencode("gagal_verifikasi: " . $e->getMessage()));
        exit;
    }
}

// filter status bukti
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'semua';
$filter_valid  = in_array($filter_status, $status_bukti_all, true);

// ambil daftar bukti transfer
$sql_bukti_list = "
    select
        bt.id_bukti_transfer,
        bt.id_pesanan,
        bt.status_verifikasi,
        bt.diunggah_pada,
        bt.diverifikasi_pada,
        bt.nama_file,
        bt.catatan,
        p.kode_pesanan,
        p.total_harga,
        p.status_pesanan,
        u.nama_lengkap as nama_pembeli
    from bukti_transfer bt
    inner join pesanan p on p.id_pesanan = bt.id_pesanan
    inner join pengguna u on u.id_pengguna = p.id_pengguna
    where 1=1
";

if ($filter_valid) {
    $status_esc = mysqli_real_escape_string($koneksi, $filter_status);
    $sql_bukti_list .= " and bt.status_verifikasi = '$status_esc' ";
}

$sql_bukti_list .= " order by bt.diunggah_pada desc";

$q_bukti_list = mysqli_query($koneksi, $sql_bukti_list);
$daftar_bukti = [];
if ($q_bukti_list && mysqli_num_rows($q_bukti_list) > 0) {
    while ($row = mysqli_fetch_assoc($q_bukti_list)) {
        // siapkan path tampilan untuk masing-masing baris (relatif ke folder penjual/)
        $nama_file = $row['nama_file'] ?? '';
        $display_path = null;
        if (!empty($nama_file)) {
            $safe_name = basename($nama_file);
            $fs_path = __DIR__ . "/../uploads/bukti/" . $safe_name; // penjual/* jadi naik ke root proyek
            if (file_exists($fs_path) && is_file($fs_path)) {
                $display_path = '../uploads/bukti/' . $safe_name; // path yang bisa dipakai di <img> dari file penjual/*
            }
        }
        $row['display_bukti'] = $display_path; // bisa null jika file tidak ditemukan
        $daftar_bukti[] = $row;
    }
}

// detail bukti untuk modal
$show_detail_modal = false;
$detail_bukti      = null;

if (isset($_GET['detail'])) {
    $id_detail = (int)$_GET['detail'];
    if ($id_detail > 0) {
        $sql_detail = "
            select
                bt.*,
                p.kode_pesanan,
                p.total_harga,
                p.status_pesanan,
                p.dibuat_pada,
                u.nama_lengkap,
                u.email,
                u.no_hp
            from bukti_transfer bt
            inner join pesanan p on p.id_pesanan = bt.id_pesanan
            inner join pengguna u on u.id_pengguna = p.id_pengguna
            where bt.id_bukti_transfer = $id_detail
            limit 1
        ";
        $q_detail = mysqli_query($koneksi, $sql_detail);
        if ($q_detail && mysqli_num_rows($q_detail) > 0) {
            $detail_bukti      = mysqli_fetch_assoc($q_detail);

            // siapkan path tampilan untuk modal (gunakan nama_file dari DB)
            $nama_file = $detail_bukti['nama_file'] ?? '';
            $detail_bukti['display_bukti'] = null;
            if (!empty($nama_file)) {
                $safe_name = basename($nama_file);
                $fs_path = __DIR__ . "/../uploads/bukti/" . $safe_name;
                if (file_exists($fs_path) && is_file($fs_path)) {
                    $detail_bukti['display_bukti'] = '../uploads/bukti/' . $safe_name;
                }
            }

            $show_detail_modal = true;
        }
    }
}

// helper badge
function badge_status_bukti(?string $status): string {
    if ($status === null) {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">belum ada</span>';
    }
    $kelas = "bg-gray-100 text-gray-700";
    switch ($status) {
        case 'menunggu':
            $kelas = "bg-yellow-100 text-yellow-800";
            break;
        case 'diterima':
            $kelas = "bg-emerald-100 text-emerald-800";
            break;
        case 'ditolak':
            $kelas = "bg-red-100 text-red-800";
            break;
    }
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ' . $kelas . '">' . htmlspecialchars($status) . '</span>';
}

function badge_status_pesanan(string $status): string {
    $kelas = "bg-gray-100 text-gray-700";
    switch ($status) {
        case 'menunggu_pembayaran':
            $kelas = "bg-yellow-100 text-yellow-800";
            break;
        case 'menunggu_konfirmasi':
            $kelas = "bg-amber-100 text-amber-800";
            break;
        case 'diproses':
            $kelas = "bg-blue-100 text-blue-800";
            break;
        case 'dikirim':
            $kelas = "bg-indigo-100 text-indigo-800";
            break;
        case 'selesai':
            $kelas = "bg-emerald-100 text-emerald-800";
            break;
        case 'dibatalkan':
            $kelas = "bg-red-100 text-red-800";
            break;
    }
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ' . $kelas . '">' . htmlspecialchars($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>kelola bukti transfer - panel penjual</title>
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
>      <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
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
            <div>
                <h1 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-gray-700"></i>
                    kelola bukti transfer
                </h1>
                <p class="text-xs text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-money-check-dollar text-gray-500"></i>
                    cek dan verifikasi pembayaran dari pembeli.
                </p>
            </div>
                    </div>

        </div>
    </header>

    <!-- isi -->
    <section class="max-w-6xl mx-auto px-6 py-6">
        <!-- pesan -->
        <?php if ($pesan === "verifikasi_sukses"): ?>
            <div
                id="alertMsg"
                class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-check"></i>
                verifikasi bukti transfer berhasil disimpan.
            </div>
        <?php elseif ($pesan !== ""): ?>
            <div
                id="alertMsg"
                class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-xmark"></i>
                <?php echo htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <!-- script pesan 3 detik + opacity transisi -->
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

        <!-- filter status -->
        <form method="get" class="mb-4 flex flex-wrap items-center gap-3 text-xs">
            <div>
                <label class="block mb-1 text-gray-600 flex items-center gap-1">
                    <i class="fa-solid fa-filter text-gray-600"></i>
                    filter status bukti
                </label>
                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="px-3 py-2 rounded-md border border-gray-300 text-xs focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
                    <option value="semua" <?php echo ($filter_status === 'semua' ? 'selected' : ''); ?>>semua</option>
                    <?php foreach ($status_bukti_all as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($filter_status === $s ? 'selected' : ''); ?>>
                            <?php echo $s; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
<?php if (count($daftar_bukti) > 0): ?>
<div class="space-y-3 md:hidden">
    <?php foreach ($daftar_bukti as $b): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-3">

            <!-- KODE PESANAN -->
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-file-invoice text-gray-500"></i>
                <p class="font-semibold text-sm text-gray-800">
                    <?php echo htmlspecialchars($b['kode_pesanan']); ?>
                </p>
            </div>

            <!-- PEMBELI -->
            <div class="text-xs text-gray-600 flex items-center gap-1 mb-1">
                <i class="fa-solid fa-user text-gray-500"></i>
                <?php echo htmlspecialchars($b['nama_pembeli']); ?>
            </div>

            <!-- TANGGAL UPLOAD -->
            <div class="text-[11px] text-gray-500 flex items-center gap-1 mb-1">
                <i class="fa-regular fa-clock"></i>
                <?php echo date('d-m-Y H:i', strtotime($b['diunggah_pada'])); ?>
            </div>

            <!-- TOTAL -->
            <div class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1">
                <i class="fa-solid fa-money-bill-wave text-gray-500"></i>
                Rp <?php echo number_format($b['total_harga'], 0, ',', '.'); ?>
            </div>

            <!-- STATUS -->
            <div class="flex flex-wrap gap-2 mb-2">
                <div>
                    <?php echo badge_status_pesanan($b['status_pesanan']); ?>
                </div>
                <div>
                    <?php echo badge_status_bukti($b['status_verifikasi']); ?>
                </div>
            </div>

            <!-- AKSI -->
            <div class="flex flex-wrap gap-2 justify-end">

                <!-- detail -->
                <a
                    href="bukti_transfer.php?detail=<?php echo (int)$b['id_bukti_transfer']; ?>&status=<?php echo urlencode($filter_status); ?>"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
                >
                    <i class="fa-regular fa-eye text-[11px]"></i>
                    detail
                </a>

                <!-- lihat gambar -->
                <?php if (!empty($b['display_bukti'])): ?>
                    <a
                        href="<?php echo htmlspecialchars($b['display_bukti']); ?>"
                        target="_blank"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-md border border-gray-300 text-[11px] hover:bg-gray-50"
                    >
                        <i class="fa-regular fa-image"></i>
                        lihat gambar
                    </a>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php elseif (count($daftar_bukti) === 0): ?>
<div class="md:hidden text-center text-xs text-gray-500 py-6">
    <i class="fa-regular fa-inbox mr-1"></i>
    belum ada bukti transfer atau tidak ada bukti dengan status yang dipilih.
</div>
<?php endif; ?>

        <!-- daftar bukti -->
<div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-file-invoice text-gray-500 mr-1"></i>
                            kode pesanan
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-user text-gray-500 mr-1"></i>
                            pembeli
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-regular fa-clock text-gray-500 mr-1"></i>
                            tanggal upload
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-money-bill-wave text-gray-500 mr-1"></i>
                            total pesanan
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-clipboard-check text-gray-500 mr-1"></i>
                            status pesanan
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-shield-halved text-gray-500 mr-1"></i>
                            status bukti
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-600">
                            <i class="fa-solid fa-gear text-gray-500 mr-1"></i>
                            aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($daftar_bukti) === 0): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1 justify-center">
                                    <i class="fa-regular fa-inbox"></i>
                                    belum ada bukti transfer atau tidak ada bukti dengan status yang dipilih.
                                </span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftar_bukti as $b): ?>
                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-800">
                                    <?php echo htmlspecialchars($b['kode_pesanan']); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    <?php echo htmlspecialchars($b['nama_pembeli']); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-600">
                                    <?php echo date('d-m-Y H:i', strtotime($b['diunggah_pada'])); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-800 font-semibold">
                                    rp <?php echo number_format($b['total_harga'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php echo badge_status_pesanan($b['status_pesanan']); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php echo badge_status_bukti($b['status_verifikasi']); ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a
                                        href="bukti_transfer.php?detail=<?php echo (int)$b['id_bukti_transfer']; ?>&status=<?php echo urlencode($filter_status); ?>"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
                                    >
                                        <i class="fa-regular fa-eye text-[11px]"></i>
                                        detail
                                    </a>

                                    <?php if (!empty($b['display_bukti'])): ?>
                                        <a
                                            href="<?php echo htmlspecialchars($b['display_bukti']); ?>"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-md border border-gray-300 text-[11px] ml-2 hover:bg-gray-50"
                                        >
                                            <i class="fa-regular fa-image"></i>
                                            lihat gambar
                                        </a>
                                    <?php endif; ?>
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

    <!-- overlay & modal detail -->
    <div id="overlayDetail" class="fixed inset-0 bg-black/40 <?php echo $show_detail_modal ? 'flex' : 'hidden'; ?> items-center justify-center z-40"></div>

    <?php if ($show_detail_modal && $detail_bukti): ?>
    <div id="modalDetail" class="fixed inset-0 <?php echo $show_detail_modal ? 'flex' : 'hidden'; ?> items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-gray-700"></i>
                        bukti transfer - <?php echo htmlspecialchars($detail_bukti['kode_pesanan']); ?>
                    </h2>
                    <p class="text-[11px] text-gray-500 flex items-center gap-1 mt-0.5">
                        <i class="fa-regular fa-clock"></i>
                        diunggah pada <?php echo date('d-m-Y H:i', strtotime($detail_bukti['diunggah_pada'])); ?>
                    </p>
                </div>
                <button
                    type="button"
                    onclick="closeDetailModal()"
                    class="text-gray-500 text-lg hover:text-gray-700"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="px-4 py-4 space-y-4 text-xs">
                <!-- info pesanan & pembeli -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-file-invoice text-gray-700"></i>
                            info pesanan
                        </h3>
                        <p>
                            <span class="text-gray-500">kode: </span>
                            <span class="font-semibold">
                                <?php echo htmlspecialchars($detail_bukti['kode_pesanan']); ?>
                            </span>
                        </p>
                        <p>
                            <span class="text-gray-500">total: </span>
                            <span class="font-semibold">
                                <i class="fa-solid fa-money-bill-wave text-gray-600 mr-1"></i>
                                rp <?php echo number_format($detail_bukti['total_harga'], 0, ',', '.'); ?>
                            </span>
                        </p>
                        <p class="mt-1">
                            <span class="text-gray-500">status pesanan: </span>
                            <?php echo badge_status_pesanan($detail_bukti['status_pesanan']); ?>
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-user text-gray-700"></i>
                            data pembeli
                        </h3>
                        <p>
                            <span class="text-gray-500">nama: </span>
                            <span class="font-semibold">
                                <?php echo htmlspecialchars($detail_bukti['nama_lengkap']); ?>
                            </span>
                        </p>
                        <p>
                            <span class="text-gray-500">email: </span>
                            <span>
                                <i class="fa-regular fa-envelope text-gray-500 mr-1"></i>
                                <?php echo htmlspecialchars($detail_bukti['email']); ?>
                            </span>
                        </p>
                        <p>
                            <span class="text-gray-500">no hp: </span>
                            <span>
                                <i class="fa-solid fa-phone text-gray-500 mr-1"></i>
                                <?php echo htmlspecialchars($detail_bukti['no_hp']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- gambar bukti -->
                <div class="border border-gray-200 rounded-lg p-3">
                    <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                        <i class="fa-solid fa-image text-gray-700"></i>
                        gambar bukti transfer
                    </h3>
                    <div class="border border-gray-200 rounded-md overflow-hidden max-h-[60vh]">
                        <?php
                            // tampilkan gambar berdasarkan nama_file (DB menyimpan nama_file)
                            if (!empty($detail_bukti['display_bukti'])) {
                                echo '<img src="' . htmlspecialchars($detail_bukti['display_bukti']) . '" alt="bukti transfer" class="w-full object-contain">';
                            } else {
                                echo '<div class="p-6 text-center text-gray-500 text-sm">';
                                echo '<i class="fa-regular fa-image text-2xl mb-2"></i><br>';
                                echo 'Gambar bukti tidak tersedia atau telah dihapus dari server.';
                                echo '</div>';
                            }
                        ?>
                    </div>
                </div>

                <!-- status & verifikasi -->
                <div class="border border-gray-200 rounded-lg p-3">
                    <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                        <i class="fa-solid fa-shield-halved text-gray-700"></i>
                        status & verifikasi
                    </h3>

                    <p class="mb-2 flex items-center gap-1">
                        <span class="text-gray-500">status bukti: </span>
                        <?php echo badge_status_bukti($detail_bukti['status_verifikasi']); ?>
                    </p>

                    <?php if (!empty($detail_bukti['diverifikasi_pada'])): ?>
                        <p class="text-[11px] text-gray-500 mb-2 flex items-center gap-1">
                            <i class="fa-regular fa-clock"></i>
                            diverifikasi pada: <?php echo date('d-m-Y H:i', strtotime($detail_bukti['diverifikasi_pada'])); ?>
                        </p>
                    <?php endif; ?>

                    <!-- form catatan + tombol tindakan -->
                    <form method="post" class="space-y-2">
                        <input type="hidden" name="aksi" value="verifikasi_bukti">
                        <input type="hidden" name="id_bukti" value="<?php echo (int)$detail_bukti['id_bukti_transfer']; ?>">

                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-note-sticky text-gray-600"></i>
                                catatan (opsional, misal alasan penolakan atau keterangan lain)
                            </label>
                            <textarea
                                name="catatan"
                                rows="3"
                                class="w-full px-3 py-2 rounded-md border border-gray-300 text-[11px] focus:outline-none focus:ring-1 focus:ring-gray-500"
                            ><?php echo htmlspecialchars($detail_bukti['catatan'] ?? ''); ?></textarea>
                        </div>

                        <?php if ($detail_bukti['status_verifikasi'] === 'menunggu'): ?>
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <button
                                    type="submit"
                                    name="tindakan"
                                    value="terima"
                                    class="px-3 py-1 rounded-md bg-emerald-600 text-white text-[11px] font-medium hover:bg-emerald-700 inline-flex items-center gap-1"
                                >
                                    <i class="fa-solid fa-circle-check"></i>
                                    setujui pembayaran
                                </button>

                                <button
                                    type="submit"
                                    name="tindakan"
                                    value="tolak"
                                    onclick="return confirm('yakin ingin menolak bukti ini? status pesanan akan dibatalkan.');"
                                    class="px-3 py-1 rounded-md bg-red-600 text-white text-[11px] font-medium hover:bg-red-700 inline-flex items-center gap-1"
                                >
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    tolak pembayaran
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">
                                setujui: status pesanan menjadi <strong>diproses</strong>.  
                                tolak: status pesanan menjadi <strong>dibatalkan</strong>.
                            </p>
                        <?php else: ?>
                            <p class="text-[11px] text-gray-500 mt-1 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info text-gray-500"></i>
                                bukti ini sudah diverifikasi. anda masih dapat mengubah catatan lalu klik salah satu tombol di bawah
                                untuk mengubah keputusan jika diperlukan (disarankan hati-hati).
                            </p>
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <button
                                    type="submit"
                                    name="tindakan"
                                    value="terima"
                                    class="px-3 py-1 rounded-md bg-emerald-600 text-white text-[11px] font-medium hover:bg-emerald-700 inline-flex items-center gap-1"
                                >
                                    <i class="fa-solid fa-circle-check"></i>
                                    tandai diterima
                                </button>

                                <button
                                    type="submit"
                                    name="tindakan"
                                    value="tolak"
                                    onclick="return confirm('yakin ingin menandai bukti ini sebagai ditolak? status pesanan akan dibatalkan.');"
                                    class="px-3 py-1 rounded-md bg-red-600 text-white text-[11px] font-medium hover:bg-red-700 inline-flex items-center gap-1"
                                >
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    tandai ditolak
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-end">
                <button
                    type="button"
                    onclick="closeDetailModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1"
                >
                    <i class="fa-solid fa-circle-xmark"></i>
                    tutup
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>


    <script>
        const overlayDetail = document.getElementById('overlayDetail');
        const modalDetail   = document.getElementById('modalDetail');

        function openDetailModal() {
            if (!overlayDetail || !modalDetail) return;
            overlayDetail.classList.remove('hidden');
            overlayDetail.classList.add('flex');
            modalDetail.classList.remove('hidden');
            modalDetail.classList.add('flex');
        }

        function closeDetailModal() {
            if (!overlayDetail || !modalDetail) return;
            overlayDetail.classList.remove('flex');
            overlayDetail.classList.add('hidden');
            modalDetail.classList.remove('flex');
            modalDetail.classList.add('hidden');

            // hapus parameter ?detail= dari url tanpa reload
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('detail');
                window.history.replaceState({}, document.title, url.toString());
            }
        }

        <?php if ($show_detail_modal): ?>
        window.addEventListener('load', function () {
            openDetailModal();
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
