<?php
include "config.php";

// ambil id produk dari url
$id_produk = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_produk <= 0) {
    die("produk tidak ditemukan.");
}

// ambil data produk (termasuk gambar_produk)
$sql_produk = "
    select 
        p.id_produk,
        p.nama_produk,
        p.deskripsi,
        p.harga,
        p.stok,
        p.status_produk,
        p.gambar_produk,
        k.nama_kategori
    from produk p
    left join kategori k on k.id_kategori = p.id_kategori
    where p.id_produk = $id_produk
    limit 1
";

$q_produk = mysqli_query($koneksi, $sql_produk);
if (!$q_produk) {
    die("gagal mengambil data produk: " . mysqli_error($koneksi));
}

if (mysqli_num_rows($q_produk) === 0) {
    die("produk tidak ditemukan.");
}

$produk = mysqli_fetch_assoc($q_produk);

// proses gambar
$gambar_file     = $produk['gambar_produk'] ?? "";
$gambar_utama    = "";
$has_image       = false;

if (!empty($gambar_file)) {
    $filename = basename($gambar_file);
    $fs_path  = __DIR__ . "/uploads/produk/" . $filename;
    if (file_exists($fs_path) && is_file($fs_path)) {
        // path URL relatif (karena detail_produk.php ada di root)
        $gambar_utama = "uploads/produk/" . $filename;
        $has_image = true;
    }
}

// pesan
$pesan = $_GET['pesan'] ?? "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>detail produk - <?php echo htmlspecialchars($produk['nama_produk']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
<?php include "layout/navbar.php"; ?>

<main class="max-w-5xl mx-auto px-4 py-6">

    <!-- breadcrumb -->
    <nav class="text-xs text-gray-500 mb-4">
        <a href="toko.php" class="hover:underline">toko</a>
        <span class="mx-1">/</span>
        <span class="text-gray-700"><?php echo htmlspecialchars($produk['nama_produk']); ?></span>
    </nav>

    <!-- pesan sukses -->
    <?php if ($pesan === "tambah_sukses"): ?>
        <div id="alertSuccess"
             class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
            produk berhasil ditambahkan ke keranjang.
        </div>

        <script>
            setTimeout(() => {
                const box = document.getElementById("alertSuccess");
                if (box) {
                    box.style.transition = "opacity .5s";
                    box.style.opacity = "0";
                    setTimeout(() => box.remove(), 600);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- gambar produk -->
        <div>
            <?php if ($has_image): ?>
                <div class="aspect-[4/3] bg-gray-100 rounded-lg overflow-hidden mb-3 relative">
                    <img src="<?php echo htmlspecialchars($gambar_utama); ?>"
                         alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>"
                         class="w-full h-full object-cover">
                    <div class="absolute top-2 left-2 inline-flex items-center gap-1 px-2 py-1 rounded-full bg-black/60 text-white text-[10px]">
                        <i class="fa-solid fa-star text-yellow-400"></i>
                        gambar utama
                    </div>
                </div>
            <?php else: ?>
                <div class="aspect-[4/3] bg-gray-100 rounded-lg flex flex-col items-center justify-center text-xs text-gray-400 gap-1">
                    <i class="fa-solid fa-image-slash text-lg"></i>
                    tidak ada gambar produk.
                </div>
            <?php endif; ?>
        </div>

        <!-- informasi produk -->
        <div class="flex flex-col">

            <h1 class="text-xl font-semibold text-gray-800 mb-1 flex items-center gap-2">
                <i class="fa-solid fa-tag text-gray-500"></i>
                <?php echo htmlspecialchars($produk['nama_produk']); ?>
            </h1>

            <!-- kategori -->
            <?php if (!empty($produk['nama_kategori'])): ?>
                <p class="text-xs text-gray-500 mb-2 flex items-center gap-1">
                    <i class="fa-solid fa-layer-group text-gray-400"></i>
                    kategori: <?php echo htmlspecialchars($produk['nama_kategori']); ?>
                </p>
            <?php endif; ?>

            <!-- harga -->
            <p class="text-lg font-bold text-gray-900 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-circle-dollar-to-slot text-green-600"></i>
                rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?>
            </p>

            <!-- stok -->
            <p class="text-xs text-gray-500 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-box text-gray-400"></i>
                stok: <?php echo (int)$produk['stok']; ?>
            </p>

            <div class="border-t border-gray-200 my-3"></div>

            <!-- deskripsi -->
            <div class="mb-4 text-sm text-gray-700 leading-relaxed">
                <p class="text-xs font-semibold text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-align-left text-gray-400"></i>
                    deskripsi produk
                </p>
                <p class="mt-1">
                    <?php echo nl2br(htmlspecialchars($produk['deskripsi'])); ?>
                </p>
            </div>

            <!-- aksi -->
            <div class="mt-auto flex flex-wrap items-center gap-3">
                <a href="toko.php"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-arrow-left"></i>
                    kembali ke toko
                </a>

                <?php if ($produk['stok'] <= 0): ?>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-red-100 text-xs font-semibold text-red-700">
                        <i class="fa-solid fa-circle-xmark"></i>
                        stok habis
                    </span>

                <?php else: ?>

                    <?php if (!empty($is_pembeli) && $is_pembeli): ?>

                        <!-- pembeli: tambah ke keranjang -->
                        <form method="post" action="tambah_keranjang.php" class="m-0 flex items-center gap-2">
                            <input type="hidden" name="id_produk" value="<?php echo (int)$produk['id_produk']; ?>">

                            <div class="flex items-center gap-1 text-xs text-gray-600">
                                <i class="fa-solid fa-sort-numeric-up"></i>
                                <input
                                        type="number"
                                        name="jumlah"
                                        value="1"
                                        min="1"
                                        max="<?php echo (int)$produk['stok']; ?>"
                                        class="w-20 px-2 py-1 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                                >
                            </div>

                            <input type="hidden" name="redirect"
                                   value="detail_produk.php?id=<?php echo (int)$produk['id_produk']; ?>&pesan=tambah_sukses">

                            <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800">
                                <i class="fa-solid fa-cart-plus"></i>
                                tambah ke keranjang
                            </button>
                        </form>

                    <?php elseif (!empty($is_penjual) && $is_penjual): ?>

                        <button class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-gray-300 text-gray-500 text-sm font-medium cursor-not-allowed"
                                title="penjual tidak dapat melakukan pemesanan">
                            <i class="fa-solid fa-eye"></i>
                            hanya lihat (mode penjual)
                        </button>

                    <?php else: ?>

                        <a href="auth/login.php?pesan=login_dulu&redirect=../detail_produk.php?id=<?php echo (int)$produk['id_produk']; ?>"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            login untuk memesan
                        </a>

                    <?php endif; ?>

                <?php endif; ?>
            </div>

        </div>
    </div>
</main>

<?php include "layout/footer.php"; ?>
</body>
</html>
