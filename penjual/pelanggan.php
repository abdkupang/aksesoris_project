<?php
// file: penjual/pelanggan.php
include "../config.php";

// hanya penjual yang boleh akses
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php?pesan=login_dulu&redirect=penjual/pelanggan.php");
    exit;
}

if ($_SESSION['role'] !== 'penjual') {
    header("Location: ../toko.php");
    exit;
}

$id_pengguna  = (int)$_SESSION['id_pengguna'];
$nama_penjual = $_SESSION['nama_lengkap'] ?? "penjual";

$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";

// ambil daftar pelanggan: hanya yang pernah punya pesanan
$sql_pelanggan = "
    select
        u.id_pengguna,
        u.nama_lengkap,
        u.email,
        u.no_hp,
        count(p.id_pesanan) as jumlah_pesanan,
        coalesce(sum(p.total_harga), 0) as total_transaksi
    from pesanan p
    inner join pengguna u on u.id_pengguna = p.id_pengguna
    group by u.id_pengguna, u.nama_lengkap, u.email, u.no_hp
    order by total_transaksi desc, jumlah_pesanan desc
";

$q_pelanggan = mysqli_query($koneksi, $sql_pelanggan);
$daftar_pelanggan = [];
if ($q_pelanggan && mysqli_num_rows($q_pelanggan) > 0) {
    while ($row = mysqli_fetch_assoc($q_pelanggan)) {
        $daftar_pelanggan[] = $row;
    }
}

// detail pelanggan untuk modal
$show_detail_modal = false;
$detail_pelanggan  = null;
$detail_pesanan    = [];

if (isset($_GET['detail'])) {
    $id_detail = (int)$_GET['detail'];
    if ($id_detail > 0) {
        // ambil profil pelanggan
        $sql_detail_user = "
            select
                u.id_pengguna,
                u.nama_lengkap,
                u.email,
                u.no_hp,
                u.alamat,
                count(p.id_pesanan) as jumlah_pesanan,
                coalesce(sum(p.total_harga), 0) as total_transaksi
            from pengguna u
            inner join pesanan p on p.id_pengguna = u.id_pengguna
            where u.id_pengguna = $id_detail
            group by u.id_pengguna, u.nama_lengkap, u.email, u.no_hp, u.alamat
            limit 1
        ";
        $q_detail_user = mysqli_query($koneksi, $sql_detail_user);
        if ($q_detail_user && mysqli_num_rows($q_detail_user) > 0) {
            $detail_pelanggan = mysqli_fetch_assoc($q_detail_user);

            // ambil daftar pesanan pelanggan ini (misal 10 terbaru)
            $sql_detail_pesanan = "
                select
                    id_pesanan,
                    kode_pesanan,
                    total_harga,
                    status_pesanan,
                    dibuat_pada
                from pesanan
                where id_pengguna = " . (int)$detail_pelanggan['id_pengguna'] . "
                order by dibuat_pada desc
                limit 10
            ";
            $q_detail_pesanan = mysqli_query($koneksi, $sql_detail_pesanan);
            if ($q_detail_pesanan && mysqli_num_rows($q_detail_pesanan) > 0) {
                while ($row = mysqli_fetch_assoc($q_detail_pesanan)) {
                    $detail_pesanan[] = $row;
                }
            }

            $show_detail_modal = true;
        }
    }
}

// helper badge status pesanan
function badge_status_pesanan_pelanggan(string $status): string {
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
    <title>data pelanggan - panel penjual</title>
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
>         <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
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
                    <i class="fa-solid fa-users text-gray-700"></i>
                    data pelanggan
                </h1>
                <p class="text-xs text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-chart-line text-gray-500"></i>
                    lihat pelanggan yang pernah bertransaksi di toko aksesoris.
                </p>
            </div>
                </div>

        </div>
    </header>

    <!-- isi -->
    <section class="max-w-6xl mx-auto px-6 py-6">
        <?php if ($pesan !== ""): ?>
            <div
                id="alertMsg"
                class="mb-4 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-3 text-sm text-yellow-900 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($pesan); ?>
            </div>

            <!-- script pesan 3 detik + fade -->
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
        <?php endif; ?>
<?php if (count($daftar_pelanggan) > 0): ?>
<div class="space-y-3 md:hidden">
    <?php foreach ($daftar_pelanggan as $pl): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-3">

            <!-- NAMA -->
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-user text-gray-500"></i>
                <p class="font-semibold text-sm text-gray-800">
                    <?php echo htmlspecialchars($pl['nama_lengkap']); ?>
                </p>
            </div>

            <!-- EMAIL -->
            <div class="text-xs text-gray-600 flex items-center gap-1 mb-1">
                <i class="fa-regular fa-envelope text-gray-500"></i>
                <?php echo htmlspecialchars($pl['email']); ?>
            </div>

            <!-- NO HP -->
            <div class="text-xs text-gray-600 flex items-center gap-1 mb-2">
                <i class="fa-solid fa-phone text-gray-500"></i>
                <?php echo htmlspecialchars($pl['no_hp']); ?>
            </div>

            <!-- RINGKASAN -->
            <div class="grid grid-cols-2 gap-2 text-[11px] mb-2">
                <div class="bg-gray-50 rounded-md p-2">
                    <p class="text-gray-500 flex items-center gap-1">
                        <i class="fa-solid fa-cart-shopping"></i>
                        jumlah pesanan
                    </p>
                    <p class="font-semibold text-gray-800">
                        <?php echo (int)$pl['jumlah_pesanan']; ?>
                    </p>
                </div>

                <div class="bg-gray-50 rounded-md p-2">
                    <p class="text-gray-500 flex items-center gap-1">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        total transaksi
                    </p>
                    <p class="font-semibold text-gray-800">
                        Rp <?php echo number_format($pl['total_transaksi'], 0, ',', '.'); ?>
                    </p>
                </div>
            </div>

            <!-- AKSI -->
            <div class="flex justify-end">
                <a
                    href="pelanggan.php?detail=<?php echo (int)$pl['id_pengguna']; ?>"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
                >
                    <i class="fa-regular fa-eye text-[11px]"></i>
                    detail
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php elseif (count($daftar_pelanggan) === 0): ?>
<div class="md:hidden text-center text-xs text-gray-500 py-6">
    <i class="fa-regular fa-face-frown mr-1"></i>
    belum ada pelanggan yang melakukan pesanan.
</div>
<?php endif; ?>

<div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-user text-gray-500 mr-1"></i>
                            nama
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-regular fa-envelope text-gray-500 mr-1"></i>
                            email
                        </th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-600">
                            <i class="fa-solid fa-phone text-gray-500 mr-1"></i>
                            no hp
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-600">
                            <i class="fa-solid fa-cart-shopping text-gray-500 mr-1"></i>
                            jumlah pesanan
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-600">
                            <i class="fa-solid fa-money-bill-wave text-gray-500 mr-1"></i>
                            total transaksi
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-600">
                            <i class="fa-solid fa-gear text-gray-500 mr-1"></i>
                            aksi
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($daftar_pelanggan) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1 justify-center">
                                    <i class="fa-regular fa-face-frown"></i>
                                    belum ada pelanggan yang melakukan pesanan.
                                </span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftar_pelanggan as $pl): ?>
                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-800">
                                    <?php echo htmlspecialchars($pl['nama_lengkap']); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    <?php echo htmlspecialchars($pl['email']); ?>
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    <?php echo htmlspecialchars($pl['no_hp']); ?>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-800 font-semibold">
                                    <?php echo (int)$pl['jumlah_pesanan']; ?>
                                </td>
                                <td class="px-3 py-2 text-right text-gray-800 font-semibold">
                                    rp <?php echo number_format($pl['total_transaksi'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a
                                        href="pelanggan.php?detail=<?php echo (int)$pl['id_pengguna']; ?>"
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

    <!-- overlay & modal detail pelanggan -->
    <div id="overlayDetail" class="fixed inset-0 bg-black/40 <?php echo $show_detail_modal ? 'flex' : 'hidden'; ?> items-center justify-center z-40"></div>

                <?php if ($show_detail_modal && $detail_pelanggan): ?>
            <div id="modalDetail" class="fixed inset-0 <?php echo $show_detail_modal ? 'flex' : 'hidden'; ?> items-center justify-center z-50">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">

        <!-- HEADER -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-user-circle text-gray-700"></i>
                    detail pelanggan - <?php echo htmlspecialchars($detail_pelanggan['nama_lengkap']); ?>
                </h2>

                <p class="text-[11px] text-gray-500 flex items-center gap-2 mt-0.5">
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-cart-shopping"></i> 
                        <?php echo (int)$detail_pelanggan['jumlah_pesanan']; ?> pesanan
                    </span>
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        total transaksi: rp <?php echo number_format($detail_pelanggan['total_transaksi'], 0, ',', '.'); ?>
                    </span>
                </p>
            </div>

            <button type="button" onclick="closeDetailModal()" class="text-gray-500 text-lg hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- BODY -->
        <div class="px-4 py-4 space-y-4 text-xs">

            <!-- INFORMASI KONTAK -->
            <div class="border border-gray-200 rounded-lg p-3">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                    <i class="fa-solid fa-address-card text-gray-700"></i>
                    informasi kontak
                </h3>

                <p>
                    <span class="text-gray-500">nama: </span>
                    <span class="font-semibold"><?php echo htmlspecialchars($detail_pelanggan['nama_lengkap']); ?></span>
                </p>

                <p>
                    <span class="text-gray-500">email: </span>
                    <i class="fa-regular fa-envelope mr-1 text-gray-600"></i>
                    <?php echo htmlspecialchars($detail_pelanggan['email']); ?>
                </p>

                <p>
                    <span class="text-gray-500">no hp: </span>
                    <i class="fa-solid fa-phone mr-1 text-gray-600"></i>
                    <?php echo htmlspecialchars($detail_pelanggan['no_hp']); ?>
                </p>

                <?php if (!empty($detail_pelanggan['alamat'])): ?>
                    <p class="mt-1 flex items-start gap-1">
                        <span class="text-gray-500">alamat:</span>
                        <span class="whitespace-pre-line"><?php echo htmlspecialchars($detail_pelanggan['alamat']); ?></span>
                    </p>
                <?php endif; ?>
            </div>

            <!-- RIWAYAT PESANAN -->
            <div class="border border-gray-200 rounded-lg p-3">
                <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1">
                    <i class="fa-solid fa-clock-rotate-left text-gray-700"></i>
                    riwayat pesanan terbaru
                </h3>

                <?php if (count($detail_pesanan) === 0): ?>
                    <p class="text-[11px] text-gray-500 flex items-center gap-1">
                        <i class="fa-regular fa-folder-closed"></i>
                        belum ada pesanan yang tercatat untuk pelanggan ini.
                    </p>
                <?php else: ?>
<!-- MOBILE CARD VIEW -->
<div class="space-y-2 md:hidden">
    <?php foreach ($detail_pesanan as $ps): ?>
        <div class="border border-gray-200 rounded-md p-2 text-[11px]">

            <div class="flex justify-between items-center mb-1">
                <span class="font-semibold text-gray-800 flex items-center gap-1">
                    <i class="fa-solid fa-hashtag text-gray-500"></i>
                    <?php echo htmlspecialchars($ps['kode_pesanan']); ?>
                </span>

                <?php echo badge_status_pesanan_pelanggan($ps['status_pesanan']); ?>
            </div>

            <div class="text-gray-600 flex items-center gap-1 mb-1">
                <i class="fa-regular fa-calendar text-gray-500"></i>
                <?php echo date('d-m-Y H:i', strtotime($ps['dibuat_pada'])); ?>
            </div>

            <div class="font-semibold text-gray-800 flex items-center gap-1">
                <i class="fa-solid fa-money-bill text-gray-500"></i>
                rp <?php echo number_format($ps['total_harga'], 0, ',', '.'); ?>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-[11px]">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-1 text-left font-semibold text-gray-600">
                                        <i class="fa-solid fa-hashtag text-gray-500 mr-1"></i> kode
                                    </th>
                                    <th class="px-2 py-1 text-left font-semibold text-gray-600">
                                        <i class="fa-regular fa-calendar text-gray-500 mr-1"></i> tanggal
                                    </th>
                                    <th class="px-2 py-1 text-right font-semibold text-gray-600">
                                        <i class="fa-solid fa-money-bill text-gray-500 mr-1"></i> total
                                    </th>
                                    <th class="px-2 py-1 text-left font-semibold text-gray-600">
                                        <i class="fa-solid fa-clipboard-check text-gray-500 mr-1"></i> status
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($detail_pesanan as $ps): ?>
                                    <tr class="border-t border-gray-100">
                                        <td class="px-2 py-1 text-gray-800">
                                            <?php echo htmlspecialchars($ps['kode_pesanan']); ?>
                                        </td>
                                        <td class="px-2 py-1 text-gray-600">
                                            <?php echo date('d-m-Y H:i', strtotime($ps['dibuat_pada'])); ?>
                                        </td>
                                        <td class="px-2 py-1 text-right text-gray-800 font-semibold">
                                            rp <?php echo number_format($ps['total_harga'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="px-2 py-1">
                                            <?php echo badge_status_pesanan_pelanggan($ps['status_pesanan']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-2 text-[11px] text-gray-400 flex items-center gap-1">
                        <i class="fa-regular fa-circle-info"></i>
                        menampilkan maksimal 10 pesanan terbaru.
                    </p>

                <?php endif; ?>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-end">
            <button
                type="button"
                onclick="closeDetailModal()"
                class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-1"
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
