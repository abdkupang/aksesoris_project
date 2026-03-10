<?php
include "config.php";

// ambil kata kunci pencarian (jika ada)
$kata_kunci = isset($_GET['q']) ? trim($_GET['q']) : "";

// query produk aktif — gunakan kolom produk.gambar_produk sebagai gambar utama (berisi NAMA FILE)
$sql_produk = "
    select 
        p.id_produk,
        p.nama_produk,
        p.deskripsi,
        p.harga,
        p.stok,
        p.gambar_produk as gambar_utama,
        k.nama_kategori
    from produk p
    left join kategori k on k.id_kategori = p.id_kategori
    where p.status_produk = 'aktif'
";

if ($kata_kunci !== "") {
    $kata_kunci_esc = mysqli_real_escape_string($koneksi, $kata_kunci);
    $sql_produk .= " and (p.nama_produk like '%$kata_kunci_esc%' or p.deskripsi like '%$kata_kunci_esc%')";
}

$sql_produk .= " order by p.dibuat_pada desc";

$query_produk = mysqli_query($koneksi, $sql_produk);
if (!$query_produk) {
    die("gagal mengambil data produk: " . mysqli_error($koneksi));
}

// pesan info (misal dari login redirect)
$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Toko Aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

    <?php include "layout/navbar.php"; ?>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <!-- pesan peringatan jika diarahkan untuk login -->
<?php if ($pesan === "login_dulu"): ?>
    <div id="alertPesan" class="mb-4 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-3 text-sm text-yellow-900">
        silakan login terlebih dahulu untuk mengakses keranjang atau melakukan pemesanan.
    </div>

<?php elseif ($pesan === "tambah_sukses"): ?>
    <div id="alertPesan" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
        produk berhasil ditambahkan ke keranjang.
    </div>

<?php elseif ($pesan === "logout_sukses"): ?>
    <div id="alertPesan" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
        anda berhasil logout.
    </div>
<?php endif; ?>

<!-- script auto-hide 3 detik -->
<script>
    const msg = document.getElementById('alertPesan');
    if (msg) {
        setTimeout(() => {
            msg.style.transition = "opacity 0.5s ease";
            msg.style.opacity = "0";
            setTimeout(() => msg.remove(), 500); // hapus dari DOM setelah fade-out
        }, 3000);
    }
</script>

                <?php if (!empty($is_penjual) && $is_penjual): ?>
                    <p class="mt-1 text-xs text-red-500">
                        anda login sebagai penjual. mode ini hanya untuk melihat tampilan toko, bukan untuk melakukan pemesanan.
                    </p>
                <?php endif; ?>
        <?php include "layout/banner.php"; ?>

        <!-- bagian judul dan form pencarian -->
<div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between mb-6 space-y-3 md:space-y-0">
    
    <!-- judul katalog -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-store text-gray-700"></i>
            katalog produk
        </h2>

        <p class="text-sm text-gray-500 flex items-center gap-2">
            <i class="fa-solid fa-compass text-gray-400"></i>
            temukan aksesoris yang kamu inginkan di sini.
        </p>
    </div>

    <!-- form pencarian -->
    <form method="get" class="flex items-center space-x-2 w-full md:w-auto">

        <div class="relative w-full md:w-64">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>

            <input
                type="text"
                name="q"
                value="<?php echo htmlspecialchars($kata_kunci); ?>"
                placeholder="cari produk..."
                class="w-full pl-9 pr-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
            >
        </div>

        <button
            type="submit"
            class="px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 flex items-center gap-2"
        >
            <i class="fa-solid fa-search"></i>
            cari
        </button>

    </form>
</div>

<!-- daftar produk -->
<?php if (mysqli_num_rows($query_produk) > 0): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">

        <?php while ($row = mysqli_fetch_assoc($query_produk)): ?>
            <div class="bg-white rounded-lg shadow-sm overflow-hidden flex flex-col">

                <!-- gambar produk -->
                <?php
                    // gambar_utama berisi NAMA FILE (atau bisa NULL). Pastikan aman dengan basename().
                    $has_image = false;
                    $img_src = "";
                    if (!empty($row['gambar_utama'])) {
                        // ambil nama file saja (melindungi dari path traversal jika ada)
                        $filename = basename($row['gambar_utama']);
                        // path filesystem untuk cek keberadaan file
                        $fs_path = __DIR__ . '/uploads/produk/' . $filename;
                        if (file_exists($fs_path) && is_file($fs_path)) {
                            // path relatif/URL yang digunakan di HTML (toko.php berada di root)
                            $img_src = 'uploads/produk/' . $filename;
                            $has_image = true;
                        }
                    }
                ?>

                <?php if ($has_image): ?>
                    <div class="aspect-[4/3] overflow-hidden bg-gray-100">
                        <img
                            src="<?php echo htmlspecialchars($img_src); ?>"
                            alt="<?php echo htmlspecialchars($row['nama_produk']); ?>"
                            class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                        >
                    </div>
                <?php else: ?>
                    <div class="aspect-[4/3] flex items-center justify-center bg-gray-100 text-gray-400 text-xs">
                        <i class="fa-solid fa-image-slash mr-1"></i> tidak ada gambar
                    </div>
                <?php endif; ?>

                <!-- info produk -->
                <div class="flex-1 p-4 flex flex-col">

                    <!-- nama produk -->
                    <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 flex items-center gap-2">
                        <i class="fa-solid fa-tag text-gray-500"></i>
                        <?php echo htmlspecialchars($row['nama_produk']); ?>
                    </h3>

                    <!-- kategori -->
                    <?php if (!empty($row['nama_kategori'])): ?>
                        <p class="mt-1 text-xs text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-layer-group text-gray-400"></i>
                            kategori: <?php echo htmlspecialchars($row['nama_kategori']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- harga -->
                    <p class="mt-2 text-base font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-circle-dollar-to-slot text-green-600"></i>
                        rp <?php echo number_format($row['harga'], 0, ',', '.'); ?>
                    </p>

                    <!-- stok -->
                    <p class="mt-1 text-xs text-gray-500 flex items-center gap-1">
                        <i class="fa-solid fa-box text-gray-400"></i>
                        stok: <?php echo (int)$row['stok']; ?>
                    </p>

                    <!-- tombol aksi -->
                    <div class="mt-4 flex items-center justify-between">

                        <!-- tombol detail -->
                        <a
                            href="detail_produk.php?id=<?php echo (int)$row['id_produk']; ?>"
                            class="text-xs font-medium text-gray-700 hover:text-gray-900 underline flex items-center gap-1"
                        >
                            <i class="fa-solid fa-circle-info text-gray-500"></i>
                            detail
                        </a>

                        <?php if ($row['stok'] <= 0): ?>
                            <!-- stok habis -->
                            <span class="text-xs text-red-500 font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-xmark-circle"></i>
                                stok habis
                            </span>

                        <?php else: ?>

                            <?php if (!empty($is_pembeli) && $is_pembeli): ?>
                                <!-- pembeli: tambah ke keranjang -->
                                <form method="post" action="tambah_keranjang.php" class="m-0">
                                    <input type="hidden" name="id_produk" value="<?php echo (int)$row['id_produk']; ?>">
                                    <button
                                        type="submit"
                                        class="px-3 py-1 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 flex items-center gap-1"
                                    >
                                        <i class="fa-solid fa-cart-plus"></i>
                                        tambah
                                    </button>
                                </form>

                            <?php elseif (!empty($is_penjual) && $is_penjual): ?>
                                <!-- penjual hanya bisa melihat -->
                                <button
                                    type="button"
                                    class="px-3 py-1 rounded-md bg-gray-300 text-gray-500 text-xs font-medium cursor-not-allowed flex items-center gap-1"
                                    title="penjual tidak dapat melakukan pemesanan"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                    hanya lihat
                                </button>

                            <?php else: ?>
                                <!-- belum login: minta login -->
                                <a
                                    href="auth/login.php?pesan=login_dulu&redirect=../toko.php"
                                    class="px-3 py-1 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800 flex items-center gap-1"
                                    title="silakan login terlebih dahulu untuk memesan"
                                >
                                    <i class="fa-solid fa-right-to-bracket"></i>
                                    login untuk memesan
                                </a>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>

    </div>

<?php else: ?>
    <div class="mt-10 text-center text-gray-500 text-sm flex justify-center items-center gap-2">
        <i class="fa-solid fa-box-open text-gray-400"></i>
        produk belum tersedia atau tidak ditemukan.
    </div>
<?php endif; ?>

        <!-- bagian bawah: contact, informasi, komentar pelanggan -->
        <?php include "layout/contact.php"; ?>
        <?php include "layout/informasi.php"; ?>
        <?php include "layout/komentar.php"; ?>

    </main>

    <?php include "layout/footer.php"; ?>

</body>
</html>
