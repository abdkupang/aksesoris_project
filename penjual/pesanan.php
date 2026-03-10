<?php
// file: penjual/pesanan.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/pesanan.php");
    exit;
}

if ($_SESSION['role'] !== 'penjual') {
    header("Location: ../toko.php");
    exit;
}

$id_pengguna  = (int)$_SESSION['id_pengguna'];
$nama_penjual = $_SESSION['nama_lengkap'] ?? "penjual";

$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";

// daftar status yang diizinkan
$status_all = [
    'menunggu_pembayaran',
    'menunggu_konfirmasi',
    'diproses',
    'dikirim',
    'selesai',
    'dibatalkan'
];

// proses aksi post (ubah status / verifikasi bukti)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    // ubah status pesanan
    if ($aksi === 'update_status_pesanan') {
        $id_pesanan   = (int)($_POST['id_pesanan'] ?? 0);
        $status_baru  = $_POST['status_pesanan'] ?? '';

        if ($id_pesanan <= 0 || !in_array($status_baru, $status_all, true)) {
            header("Location: pesanan.php?pesan=status_tidak_valid");
            exit;
        }

        $status_esc = mysqli_real_escape_string($koneksi, $status_baru);

        $sql_update = "
            update pesanan
            set status_pesanan = '$status_esc'
            where id_pesanan = $id_pesanan
        ";
        if (!mysqli_query($koneksi, $sql_update)) {
            header("Location: pesanan.php?pesan=gagal_update_status");
            exit;
        }

        header("Location: pesanan.php?pesan=update_status_sukses");
        exit;
    }

    // verifikasi bukti transfer
    if ($aksi === 'verifikasi_bukti') {
        $id_pesanan = (int)($_POST['id_pesanan'] ?? 0);
        $id_bukti   = (int)($_POST['id_bukti'] ?? 0);
        $tindakan   = $_POST['tindakan'] ?? '';

        if ($id_pesanan <= 0 || $id_bukti <= 0 || !in_array($tindakan, ['terima', 'tolak'], true)) {
            header("Location: pesanan.php?pesan=verifikasi_tidak_valid");
            exit;
        }

        mysqli_begin_transaction($koneksi);
        try {
            if ($tindakan === 'terima') {
                // set bukti diterima & pesanan diproses
                $sql_bukti = "
                    update bukti_transfer
                    set status_verifikasi = 'diterima',
                        diverifikasi_pada = now()
                    where id_bukti_transfer = $id_bukti
                    and id_pesanan = $id_pesanan
                ";
                if (!mysqli_query($koneksi, $sql_bukti)) {
                    throw new Exception("gagal update bukti: " . mysqli_error($koneksi));
                }

                $sql_pesanan = "
                    update pesanan
                    set status_pesanan = 'diproses'
                    where id_pesanan = $id_pesanan
                ";
                if (!mysqli_query($koneksi, $sql_pesanan)) {
                    throw new Exception("gagal update status pesanan: " . mysqli_error($koneksi));
                }

            } elseif ($tindakan === 'tolak') {
                // set bukti ditolak & pesanan dibatalkan
                $sql_bukti = "
                    update bukti_transfer
                    set status_verifikasi = 'ditolak',
                        diverifikasi_pada = now()
                    where id_bukti_transfer = $id_bukti
                    and id_pesanan = $id_pesanan
                ";
                if (!mysqli_query($koneksi, $sql_bukti)) {
                    throw new Exception("gagal update bukti: " . mysqli_error($koneksi));
                }

                $sql_pesanan = "
                    update pesanan
                    set status_pesanan = 'dibatalkan'
                    where id_pesanan = $id_pesanan
                ";
                if (!mysqli_query($koneksi, $sql_pesanan)) {
                    throw new Exception("gagal update status pesanan: " . mysqli_error($koneksi));
                }
            }

            mysqli_commit($koneksi);
            header("Location: pesanan.php?pesan=verifikasi_sukses");
            exit;

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            header("Location: pesanan.php?pesan=" . urlencode("gagal_verifikasi: " . $e->getMessage()));
            exit;
        }
    }
}

// filter status
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'semua';
$filter_status_valid = in_array($filter_status, $status_all, true);

// ambil daftar pesanan
$sql_pesanan = "
    select
        p.id_pesanan,
        p.kode_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.dibuat_pada,
        u.nama_lengkap as nama_pembeli,
        bt.status_verifikasi as status_bukti
    from pesanan p
    inner join pengguna u on u.id_pengguna = p.id_pengguna
    left join bukti_transfer bt
        on bt.id_pesanan = p.id_pesanan
        and bt.id_bukti_transfer = (
            select max(bt2.id_bukti_transfer)
            from bukti_transfer bt2
            where bt2.id_pesanan = p.id_pesanan
        )
    where 1=1
";

if ($filter_status_valid) {
    $status_esc = mysqli_real_escape_string($koneksi, $filter_status);
    $sql_pesanan .= " and p.status_pesanan = '$status_esc' ";
}

$sql_pesanan .= " order by p.dibuat_pada desc";

$q_pesanan = mysqli_query($koneksi, $sql_pesanan);

$daftar_pesanan = [];
if ($q_pesanan && mysqli_num_rows($q_pesanan) > 0) {
    while ($row = mysqli_fetch_assoc($q_pesanan)) {
        $daftar_pesanan[] = $row;
    }
}

// detail pesanan (untuk modal)
$show_detail_modal = false;
$detail_pesanan = null;
$detail_items   = [];
$detail_bukti   = null;

if (isset($_GET['detail'])) {
    $id_detail = (int)$_GET['detail'];
    if ($id_detail > 0) {
        // ambil header pesanan
        $sql_detail = "
            select
                p.*,
                u.nama_lengkap,
                u.email,
                u.no_hp,
                u.alamat
            from pesanan p
            inner join pengguna u on u.id_pengguna = p.id_pengguna
            where p.id_pesanan = $id_detail
            limit 1
        ";
        $q_detail = mysqli_query($koneksi, $sql_detail);
        if ($q_detail && mysqli_num_rows($q_detail) > 0) {
            $detail_pesanan = mysqli_fetch_assoc($q_detail);

            // ambil item
            $sql_items = "
                select *
                from pesanan_item
                where id_pesanan = $id_detail
            ";
            $q_items = mysqli_query($koneksi, $sql_items);
            if ($q_items && mysqli_num_rows($q_items) > 0) {
                while ($row = mysqli_fetch_assoc($q_items)) {
                    $detail_items[] = $row;
                }
            }

            // ambil bukti transfer terbaru (jika ada)
            $sql_bukti = "
                select *
                from bukti_transfer
                where id_pesanan = $id_detail
                order by diunggah_pada desc
                limit 1
            ";
            $q_bukti = mysqli_query($koneksi, $sql_bukti);
            if ($q_bukti && mysqli_num_rows($q_bukti) > 0) {
                $detail_bukti = mysqli_fetch_assoc($q_bukti);
            }

            $show_detail_modal = true;
        }
    }
}

// helper badge
function badge_status_pesanan_admin(string $status): string {
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

function badge_status_bukti_admin(?string $status): string {
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>kelola pesanan - panel penjual</title>
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
>   <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            
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
                    <i class="fa-solid fa-clipboard-list text-gray-700"></i>
                    kelola pesanan
                </h1>
                <p class="text-xs text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-truck text-gray-500"></i>
                    pantau dan proses semua pesanan masuk di toko aksesoris.
                </p>
            </div>
                    </div>

        </div>
    </header>

    <!-- isi -->
    <section class="max-w-6xl mx-auto px-6 py-6">
        <!-- pesan -->
        <?php if ($pesan === "update_status_sukses"): ?>
            <div
                id="alertMsg"
                class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-check"></i>
                status pesanan berhasil diperbarui.
            </div>
        <?php elseif ($pesan === "verifikasi_sukses"): ?>
            <div
                id="alertMsg"
                class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-file-circle-check"></i>
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
                    // tambahkan efek transisi
                    alertBox.style.transition = "opacity 0.5s ease";

                    // setelah 3 detik → mulai fade-out
                    setTimeout(function () {
                        alertBox.style.opacity = "0";

                        // setelah fade-out selesai → sembunyikan elemen
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
                    filter status pesanan
                </label>
                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="px-3 py-2 rounded-md border border-gray-300 text-xs focus:outline-none focus:ring-1 focus:ring-gray-500"
                >
                    <option value="semua" <?php echo ($filter_status === 'semua' ? 'selected' : ''); ?>>semua</option>
                    <?php foreach ($status_all as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($filter_status === $s ? 'selected' : ''); ?>>
                            <?php echo $s; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
<?php if (count($daftar_pesanan) > 0): ?>
<div class="space-y-3 md:hidden">
    <?php foreach ($daftar_pesanan as $p): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-3">

            <!-- KODE PESANAN -->
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-receipt text-gray-500"></i>
                <p class="font-semibold text-sm text-gray-800">
                    <?php echo htmlspecialchars($p['kode_pesanan']); ?>
                </p>
            </div>

            <!-- PEMBELI -->
            <div class="text-xs text-gray-600 flex items-center gap-1 mb-1">
                <i class="fa-solid fa-user text-gray-500"></i>
                <?php echo htmlspecialchars($p['nama_pembeli']); ?>
            </div>

            <!-- TANGGAL -->
            <div class="text-[11px] text-gray-500 flex items-center gap-1 mb-1">
                <i class="fa-regular fa-calendar"></i>
                <?php echo date('d-m-Y H:i', strtotime($p['dibuat_pada'])); ?>
            </div>

            <!-- TOTAL -->
            <div class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1">
                <i class="fa-solid fa-money-bill-wave text-gray-500"></i>
                Rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?>
            </div>

            <!-- STATUS -->
            <div class="flex flex-wrap gap-2 mb-2">
                <div>
                    <?php echo badge_status_pesanan_admin($p['status_pesanan']); ?>
                </div>
                <div>
                    <?php echo badge_status_bukti_admin($p['status_bukti'] ?? null); ?>
                </div>
            </div>

            <!-- AKSI -->
            <div class="flex justify-end">
                <a
                    href="pesanan.php?detail=<?php echo (int)$p['id_pesanan']; ?>&status=<?php echo urlencode($filter_status); ?>"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
                >
                    <i class="fa-regular fa-eye text-[11px]"></i>
                    detail
                </a>
            </div>

        </div>
    <?php endforeach; ?>
</div>
<?php elseif (count($daftar_pesanan) === 0): ?>
<div class="md:hidden text-center text-xs text-gray-500 py-6">
    <i class="fa-regular fa-inbox mr-1"></i>
    belum ada pesanan atau tidak ada pesanan dengan status yang dipilih.
</div>
<?php endif; ?>

        <!-- daftar pesanan -->
<div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
                <table class="w-full text-xs">
<thead class="bg-gray-50">
    <tr>
        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-receipt mr-1 text-gray-500"></i>
            kode pesanan
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-user mr-1 text-gray-500"></i>
            pembeli
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-regular fa-calendar mr-1 text-gray-500"></i>
            tanggal
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-money-bill-wave mr-1 text-gray-500"></i>
            total
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-clipboard-check mr-1 text-gray-500"></i>
            status pesanan
        </th>

        <th class="px-3 py-2 text-left font-semibold text-gray-600">
            <i class="fa-solid fa-file-invoice-dollar mr-1 text-gray-500"></i>
            status bukti
        </th>

        <th class="px-3 py-2 text-right font-semibold text-gray-600">
            <i class="fa-solid fa-ellipsis-vertical mr-1 text-gray-500"></i>
            aksi
        </th>
    </tr>
</thead>

                <tbody>
                    <?php if (count($daftar_pesanan) === 0): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1 justify-center">
                                    <i class="fa-regular fa-inbox"></i>
                                    belum ada pesanan atau tidak ada pesanan dengan status yang dipilih.
                                </span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftar_pesanan as $p): ?>
                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-800">
                                    <?php echo htmlspecialchars($p['kode_pesanan']); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    <?php echo htmlspecialchars($p['nama_pembeli']); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-600">
                                    <?php echo date('d-m-Y H:i', strtotime($p['dibuat_pada'])); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-800 font-semibold">
                                    rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php echo badge_status_pesanan_admin($p['status_pesanan']); ?>
                                </td>
                                <td class="px-3 py-2">
                                    <?php echo badge_status_bukti_admin($p['status_bukti'] ?? null); ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a
                                        href="pesanan.php?detail=<?php echo (int)$p['id_pesanan']; ?>&status=<?php echo urlencode($filter_status); ?>"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
                                    >
                                        <i class="fa-regular fa-eye text-[11px]"></i>
                                        detail
                                    </a>
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

    <!-- overlay & modal detail pesanan -->
    <div id="overlayDetail" class="fixed inset-0 bg-black/40 <?php echo $show_detail_modal ? 'flex' : 'hidden'; ?> items-center justify-center z-40"></div>

    <?php if ($show_detail_modal && $detail_pesanan): ?>
    <div id="modalDetail" class="fixed inset-0 <?php echo $show_detail_modal ? 'flex' : 'hidden'; ?> items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice text-gray-700"></i>
                        detail pesanan - <?php echo htmlspecialchars($detail_pesanan['kode_pesanan']); ?>
                    </h2>
                    <p class="text-[11px] text-gray-500 flex items-center gap-1 mt-0.5">
                        <i class="fa-regular fa-clock"></i>
                        dibuat pada <?php echo date('d-m-Y H:i', strtotime($detail_pesanan['dibuat_pada'])); ?>
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
                <!-- info pembeli & pengiriman -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-user text-gray-700"></i>
                            data pembeli
                        </h3>
                        <p>
                            <span class="text-gray-500">nama: </span>
                            <span class="font-semibold">
                                <?php echo htmlspecialchars($detail_pesanan['nama_lengkap']); ?>
                            </span>
                        </p>
                        <p>
                            <span class="text-gray-500">email: </span>
                            <span>
                                <i class="fa-regular fa-envelope text-gray-500 mr-1"></i>
                                <?php echo htmlspecialchars($detail_pesanan['email']); ?>
                            </span>
                        </p>
                        <p>
                            <span class="text-gray-500">no hp: </span>
                            <span>
                                <i class="fa-solid fa-phone text-gray-500 mr-1"></i>
                                <?php echo htmlspecialchars($detail_pesanan['no_hp']); ?>
                            </span>
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-gray-700"></i>
                            alamat pengiriman
                        </h3>
                        <p>
                            <span class="text-gray-500">penerima: </span>
                            <span class="font-semibold">
                                <?php echo htmlspecialchars($detail_pesanan['nama_penerima']); ?>
                            </span>
                        </p>
                        <p>
                            <span class="text-gray-500">no hp penerima: </span>
                            <span>
                                <i class="fa-solid fa-phone text-gray-500 mr-1"></i>
                                <?php echo htmlspecialchars($detail_pesanan['no_hp_penerima']); ?>
                            </span>
                        </p>
                        <p class="mt-1 whitespace-pre-line">
                            <?php echo htmlspecialchars($detail_pesanan['alamat_pengiriman']); ?>
                        </p>
                        <?php if (!empty($detail_pesanan['catatan'])): ?>
                            <p class="mt-1 text-[11px] text-gray-500 flex items-center gap-1">
                                <i class="fa-solid fa-note-sticky text-gray-500"></i>
                                catatan: <?php echo htmlspecialchars($detail_pesanan['catatan']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- item pesanan -->
                <div class="border border-gray-200 rounded-lg p-3">
                    <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                        <i class="fa-solid fa-cart-shopping text-gray-700"></i>
                        item pesanan
                    </h3>

                    <?php if (count($detail_items) === 0): ?>
                        <p class="text-[11px] text-gray-500 flex items-center gap-1">
                            <i class="fa-regular fa-circle-question"></i>
                            tidak ada item.
                        </p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
<table class="w-full text-[11px]">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-2 py-1 text-left font-semibold text-gray-600">
                <i class="fa-solid fa-box-open text-gray-500 mr-1"></i> produk
            </th>
            <th class="px-2 py-1 text-right font-semibold text-gray-600">
                <i class="fa-solid fa-money-bill-wave text-gray-500 mr-1"></i> harga
            </th>
            <th class="px-2 py-1 text-right font-semibold text-gray-600">
                <i class="fa-solid fa-layer-group text-gray-500 mr-1"></i> jumlah
            </th>
            <th class="px-2 py-1 text-right font-semibold text-gray-600">
                <i class="fa-solid fa-tag text-gray-500 mr-1"></i> subtotal
            </th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($detail_items as $it): ?>
            <tr class="border-t border-gray-100">
                <td class="px-2 py-1 text-gray-800">
                    <?php echo htmlspecialchars($it['nama_produk']); ?>
                </td>
                <td class="px-2 py-1 text-gray-700 text-right">
                    rp <?php echo number_format($it['harga'], 0, ',', '.'); ?>
                </td>
                <td class="px-2 py-1 text-gray-700 text-right">
                    <?php echo (int)$it['jumlah']; ?>
                </td>
                <td class="px-2 py-1 text-gray-800 text-right font-semibold">
                    rp <?php echo number_format($it['subtotal'], 0, ',', '.'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

                        </div>

                        <div class="mt-2 flex items-center justify-end">
                            <p class="text-xs text-gray-700 flex items-center gap-1">
                                <i class="fa-solid fa-money-bill-wave text-gray-700"></i>
                                total harga:
                                <span class="font-semibold text-gray-900">
                                    rp <?php echo number_format($detail_pesanan['total_harga'], 0, ',', '.'); ?>
                                </span>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- status & bukti transfer -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- status pesanan & aksi -->
                    <div class="border border-gray-200 rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-clipboard-check text-gray-700"></i>
                            status pesanan
                        </h3>
                        <p class="mb-2">
                            <?php echo badge_status_pesanan_admin($detail_pesanan['status_pesanan']); ?>
                        </p>

                        <form method="post" class="space-y-2">
                            <input type="hidden" name="aksi" value="update_status_pesanan">
                            <input type="hidden" name="id_pesanan" value="<?php echo (int)$detail_pesanan['id_pesanan']; ?>">

                            <div>
                                <label class="block text-[11px] text-gray-600 mb-1 flex items-center gap-1">
                                    <i class="fa-solid fa-rotate text-gray-600"></i>
                                    ubah status pesanan
                                </label>
                                <select
                                    name="status_pesanan"
                                    class="w-full px-2 py-1 rounded-md border border-gray-300 text-[11px] focus:outline-none focus:ring-1 focus:ring-gray-500"
                                >
                                    <?php foreach ($status_all as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo ($detail_pesanan['status_pesanan'] === $s ? 'selected' : ''); ?>>
                                            <?php echo $s; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button
                                type="submit"
                                class="px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800 inline-flex items-center gap-1"
                            >
                                <i class="fa-solid fa-floppy-disk"></i>
                                simpan status
                            </button>
                        </form>
                    </div>

                    <!-- bukti transfer -->
                    <div class="border border-gray-200 rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                            <i class="fa-solid fa-receipt text-gray-700"></i>
                            bukti transfer
                        </h3>

                        <?php if (!$detail_bukti): ?>
                            <p class="text-[11px] text-gray-500 flex items-center gap-1">
                                <i class="fa-regular fa-circle-xmark"></i>
                                belum ada bukti transfer yang diunggah oleh pembeli.
                            </p>
                        <?php else: ?>
                            <p class="text-[11px] text-gray-600 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-shield-halved text-gray-600"></i>
                                status verifikasi:
                                <span class="font-semibold">
                                    <?php echo htmlspecialchars($detail_bukti['status_verifikasi']); ?>
                                </span>
                            </p>

                            <?php if (!empty($detail_bukti['catatan'])): ?>
                                <p class="text-[11px] text-gray-500 mb-1 flex items-center gap-1">
                                    <i class="fa-solid fa-note-sticky text-gray-500"></i>
                                    catatan: <?php echo htmlspecialchars($detail_bukti['catatan']); ?>
                                </p>
                            <?php endif; ?>

                            <p class="text-[11px] text-gray-400 mb-2 flex items-center gap-1">
                                <i class="fa-regular fa-clock"></i>
                                diunggah pada: <?php echo date('d-m-Y H:i', strtotime($detail_bukti['diunggah_pada'])); ?>
                            </p>

<?php
$nama_file = $detail_bukti['nama_file'] ?? '';
$img_path  = '../uploads/bukti/' . $nama_file;

// path filesystem (UNTUK file_exists)
$fs_path = __DIR__ . '/../uploads/bukti/' . $nama_file;
?>

<div class="border border-gray-200 rounded-md overflow-hidden max-h-60 mb-2">
    <?php if ($nama_file !== '' && file_exists($fs_path)): ?>
        <img
            src="<?php echo htmlspecialchars($img_path); ?>"
            alt="bukti transfer"
            class="w-full object-contain"
        >
    <?php else: ?>
        <div class="flex items-center justify-center h-40 text-xs text-gray-400 bg-gray-50">
            bukti transfer tidak ditemukan
        </div>
    <?php endif; ?>
</div>



                            <?php if ($detail_bukti['status_verifikasi'] === 'menunggu'): ?>
                                <div class="space-y-1">
                                    <form method="post" class="inline-block">
                                        <input type="hidden" name="aksi" value="verifikasi_bukti">
                                        <input type="hidden" name="id_pesanan" value="<?php echo (int)$detail_pesanan['id_pesanan']; ?>">
                                        <input type="hidden" name="id_bukti" value="<?php echo (int)$detail_bukti['id_bukti_transfer']; ?>">
                                        <input type="hidden" name="tindakan" value="terima">
                                        <button
                                            type="submit"
                                            class="px-3 py-1 rounded-md bg-emerald-600 text-white text-[11px] font-medium hover:bg-emerald-700 inline-flex items-center gap-1"
                                        >
                                            <i class="fa-solid fa-circle-check"></i>
                                            terima pembayaran
                                        </button>
                                    </form>

                                    <form
                                        method="post"
                                        class="inline-block ml-1"
                                        onsubmit="return confirm('yakin menolak bukti transfer ini? pesanan akan dibatalkan.');"
                                    >
                                        <input type="hidden" name="aksi" value="verifikasi_bukti">
                                        <input type="hidden" name="id_pesanan" value="<?php echo (int)$detail_pesanan['id_pesanan']; ?>">
                                        <input type="hidden" name="id_bukti" value="<?php echo (int)$detail_bukti['id_bukti_transfer']; ?>">
                                        <input type="hidden" name="tindakan" value="tolak">
                                        <button
                                            type="submit"
                                            class="px-3 py-1 rounded-md bg-red-600 text-white text-[11px] font-medium hover:bg-red-700 inline-flex items-center gap-1"
                                        >
                                            <i class="fa-solid fa-circle-xmark"></i>
                                            tolak pembayaran
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p class="text-[11px] text-gray-500 mt-1 flex items-center gap-1">
                                    <i class="fa-solid fa-circle-info text-gray-500"></i>
                                    bukti ini sudah diverifikasi, status tidak dapat diubah dari sini.
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
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
