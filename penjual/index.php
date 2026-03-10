<?php
// file: penjual/index.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/index.php");
    exit;
}

if ($_SESSION['role'] !== 'penjual') {
    // kalau pembeli atau role lain mencoba akses, lempar ke toko
    header("Location: ../toko.php");
    exit;
}

$id_pengguna   = (int)$_SESSION['id_pengguna'];
$nama_penjual  = $_SESSION['nama_lengkap'] ?? "penjual";

// ambil ringkasan data untuk dashboard
// total produk
$sql_total_produk = "select count(*) as jml from produk";
$q_total_produk   = mysqli_query($koneksi, $sql_total_produk);
$row_produk       = mysqli_fetch_assoc($q_total_produk);
$total_produk     = (int)$row_produk['jml'];

// pesanan menunggu (pembayaran + konfirmasi)
$sql_pesanan_menunggu = "
    select count(*) as jml
    from pesanan
    where status_pesanan in ('menunggu_pembayaran','menunggu_konfirmasi')
";
$q_pesanan_menunggu = mysqli_query($koneksi, $sql_pesanan_menunggu);
$row_menunggu       = mysqli_fetch_assoc($q_pesanan_menunggu);
$total_pesanan_menunggu = (int)$row_menunggu['jml'];

// pesanan aktif (diproses + dikirim)
$sql_pesanan_aktif = "
    select count(*) as jml
    from pesanan
    where status_pesanan in ('diproses','dikirim')
";
$q_pesanan_aktif = mysqli_query($koneksi, $sql_pesanan_aktif);
$row_aktif       = mysqli_fetch_assoc($q_pesanan_aktif);
$total_pesanan_aktif = (int)$row_aktif['jml'];

// total pelanggan (distinct id_pengguna di pesanan)
$sql_pelanggan = "
    select count(distinct id_pengguna) as jml
    from pesanan
";
$q_pelanggan   = mysqli_query($koneksi, $sql_pelanggan);
$row_pelanggan = mysqli_fetch_assoc($q_pelanggan);
$total_pelanggan = (int)$row_pelanggan['jml'];

// beberapa pesanan terbaru untuk list kecil
$sql_pesanan_terbaru = "
    select id_pesanan, kode_pesanan, total_harga, status_pesanan, dibuat_pada
    from pesanan
    order by dibuat_pada desc
    limit 5
";
$q_pesanan_terbaru = mysqli_query($koneksi, $sql_pesanan_terbaru);
$pesanan_terbaru   = [];
if ($q_pesanan_terbaru && mysqli_num_rows($q_pesanan_terbaru) > 0) {
    while ($row = mysqli_fetch_assoc($q_pesanan_terbaru)) {
        $pesanan_terbaru[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>dashboard penjual - toko aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="flex min-h-screen relative">
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
>  
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">

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
                    <i class="fa-solid fa-gauge text-gray-700"></i>
                    dashboard penjual
                </h1>
                <p class="text-xs text-gray-500 hidden sm:block">
                    ringkasan aktivitas toko aksesoris kamu.
                </p>
            </div>
        </div>

        <div class="hidden sm:block text-right text-xs text-gray-500">
            <?php echo date('d-m-Y'); ?>
        </div>

    </div>
</header>


    <!-- isi -->
    <section class="max-w-6xl mx-auto px-6 py-6">
        <!-- kartu ringkasan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <!-- total produk -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
                <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-boxes-stacked text-gray-500"></i>
                    total produk
                </p>
                <p class="text-2xl font-semibold text-gray-900">
                    <?php echo $total_produk; ?>
                </p>
                <p class="text-[11px] text-gray-400 mt-1">
                    jumlah produk yang aktif dan nonaktif.
                </p>
            </div>

            <!-- pesanan menunggu -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
                <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-hourglass-half text-yellow-500"></i>
                    pesanan menunggu
                </p>
                <p class="text-2xl font-semibold text-yellow-600">
                    <?php echo $total_pesanan_menunggu; ?>
                </p>
                <p class="text-[11px] text-gray-400 mt-1">
                    butuh tindakan konfirmasi pembayaran.
                </p>
            </div>

            <!-- pesanan aktif -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
                <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-truck-fast text-blue-500"></i>
                    pesanan aktif
                </p>
                <p class="text-2xl font-semibold text-blue-600">
                    <?php echo $total_pesanan_aktif; ?>
                </p>
                <p class="text-[11px] text-gray-400 mt-1">
                    sedang diproses atau dikirim.
                </p>
            </div>

            <!-- total pelanggan -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
                <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-users text-emerald-500"></i>
                    total pelanggan
                </p>
                <p class="text-2xl font-semibold text-emerald-600">
                    <?php echo $total_pelanggan; ?>
                </p>
                <p class="text-[11px] text-gray-400 mt-1">
                    pelanggan yang pernah melakukan pesanan.
                </p>
            </div>
        </div>

        <!-- pesanan terbaru -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-gray-700"></i>
                    pesanan terbaru
                </h2>
                <a href="pesanan.php" class="text-[11px] text-gray-600 hover:text-gray-800 hover:underline inline-flex items-center gap-1">
                    lihat semua pesanan
                    <i class="fa-solid fa-angles-right text-[10px]"></i>
                </a>
            </div>

<?php if (count($pesanan_terbaru) === 0): ?>

    <p class="text-xs text-gray-500 flex items-center gap-1">
        <i class="fa-regular fa-circle-check text-gray-400"></i>
        belum ada pesanan yang masuk.
    </p>

<?php else: ?>

    <!-- ========================= -->
    <!-- MOBILE VIEW (CARD) -->
    <!-- ========================= -->
    <div class="space-y-3 md:hidden">
        <?php foreach ($pesanan_terbaru as $p): ?>
            <div class="border border-gray-200 rounded-lg p-3 text-sm">
                <div class="flex justify-between items-start mb-1">
                    <div>
                        <p class="text-xs text-gray-500">kode pesanan</p>
                        <p class="font-semibold text-gray-800">
                            <?php echo htmlspecialchars($p['kode_pesanan']); ?>
                        </p>
                    </div>
                    <span class="text-[11px] text-gray-600 capitalize">
                        <?php echo htmlspecialchars($p['status_pesanan']); ?>
                    </span>
                </div>

                <div class="text-xs text-gray-600 mb-2">
                    <?php echo date('d-m-Y H:i', strtotime($p['dibuat_pada'])); ?>
                </div>

                <div class="flex items-center justify-between">
                    <p class="font-semibold text-gray-900">
                        Rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?>
                    </p>

                    <a
                        href="pesanan.php?detail=<?php echo (int)$p['id_pesanan']; ?>"
                        class="inline-flex items-center px-3 py-1 rounded-md bg-gray-900 text-white text-xs font-medium"
                    >
                        <i class="fa-regular fa-eye mr-1"></i>
                        detail
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ========================= -->
    <!-- DESKTOP VIEW (TABLE) -->
    <!-- ========================= -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">kode</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">tanggal</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">total</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">status</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-600">aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pesanan_terbaru as $p): ?>
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <?php echo htmlspecialchars($p['kode_pesanan']); ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php echo date('d-m-Y H:i', strtotime($p['dibuat_pada'])); ?>
                        </td>
                        <td class="px-3 py-2 font-semibold">
                            Rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?>
                        </td>
                        <td class="px-3 py-2 capitalize text-[11px]">
                            <?php echo htmlspecialchars($p['status_pesanan']); ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a
                                href="pesanan.php?detail=<?php echo (int)$p['id_pesanan']; ?>"
                                class="inline-flex items-center px-3 py-1 rounded-md bg-gray-900 text-white text-[11px]"
                            >
                                                    <i class="fa-regular fa-eye mr-1"></i>

                                detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

        </div>
    </section>
</main>

    </div>

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
