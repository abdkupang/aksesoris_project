<?php
// file: checkout.php
include "config.php";

// cek login dan role
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: auth/login.php?pesan=login_dulu&redirect=checkout.php");
    exit;
}

$id_pengguna = (int)$_SESSION['id_pengguna'];
// =======================================================
// VALIDASI ITEM TERPILIH DARI KERANJANG
// =======================================================

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['checkout_items']) ||
    !is_array($_POST['checkout_items']) ||
    count($_POST['checkout_items']) === 0
) {
    header("Location: keranjang.php?pesan=pilih_item_dulu");
    exit;
}

// sanitasi id item
$checkout_item_ids = array_map('intval', $_POST['checkout_items']);
$checkout_item_ids = array_filter($checkout_item_ids);

if (count($checkout_item_ids) === 0) {
    header("Location: keranjang.php?pesan=pilih_item_dulu");
    exit;
}

// buat list id untuk SQL IN (...)
$checkout_item_list = implode(',', $checkout_item_ids);

// ambil / pastikan keranjang pembeli ada
$sql_keranjang = "
    select id_keranjang
    from keranjang
    where id_pengguna = $id_pengguna
    limit 1
";
$q_keranjang = mysqli_query($koneksi, $sql_keranjang);

if (!$q_keranjang || mysqli_num_rows($q_keranjang) === 0) {
    header("Location: keranjang.php?pesan=keranjang_kosong");
    exit;
}

$row_keranjang = mysqli_fetch_assoc($q_keranjang);
$id_keranjang  = (int)$row_keranjang['id_keranjang'];

// ambil item keranjang
function ambil_item_keranjang(
    mysqli $koneksi,
    int $id_keranjang,
    int $id_pengguna,
    string $checkout_item_list
): array {
    // gunakan kolom produk.gambar_produk (berisi NAMA FILE) — tabel produk_gambar tidak dipakai
    $sql_item = "
        select 
            ki.id_keranjang_item,
            ki.id_produk,
            ki.jumlah,
            ki.harga_saat_ini,
            p.nama_produk,
            p.stok,
            p.gambar_produk as gambar_utama
        from keranjang_item ki
        inner join keranjang k on k.id_keranjang = ki.id_keranjang
        inner join produk p on p.id_produk = ki.id_produk
where k.id_keranjang = $id_keranjang
and k.id_pengguna = $id_pengguna
and ki.id_keranjang_item in ($checkout_item_list)

        order by ki.dibuat_pada desc
    ";

    $q_item = mysqli_query($koneksi, $sql_item);

    $items       = [];
    $total_harga = 0;

    if ($q_item && mysqli_num_rows($q_item) > 0) {
        while ($row = mysqli_fetch_assoc($q_item)) {
            // pastikan jumlah tidak melebihi stok saat ini
            $jumlah = (int)$row['jumlah'];
            $stok   = (int)$row['stok'];
            if ($stok <= 0) {
                $jumlah = 0; // nanti diabaikan
            } elseif ($jumlah > $stok) {
                $jumlah = $stok;
            }

            if ($jumlah <= 0) {
                continue;
            }

            // proses gambar: kolom gambar_utama berisi NAMA FILE (atau NULL)
            $gambar_file = $row['gambar_utama'] ?? "";
            if (!empty($gambar_file)) {
                $filename = basename($gambar_file);
                $fs_path  = __DIR__ . '/uploads/produk/' . $filename;
                if (file_exists($fs_path) && is_file($fs_path)) {
                    // path relatif untuk HTML
                    $row['gambar_utama'] = 'uploads/produk/' . $filename;
                } else {
                    $row['gambar_utama'] = null;
                }
            } else {
                $row['gambar_utama'] = null;
            }

            $subtotal         = $row['harga_saat_ini'] * $jumlah;
            $row['jumlah']    = $jumlah;
            $row['subtotal']  = $subtotal;
            $total_harga     += $subtotal;
            $items[]          = $row;
        }
    }

    return ['items' => $items, 'total_harga' => $total_harga];
}

$data_keranjang = ambil_item_keranjang(
    $koneksi,
    $id_keranjang,
    $id_pengguna,
    $checkout_item_list
);
$items          = $data_keranjang['items'];
$total_harga    = $data_keranjang['total_harga'];

if (count($items) === 0) {
    header("Location: keranjang.php?pesan=keranjang_kosong");
    exit;
}

// ambil data pengguna sebagai default form
$sql_pengguna = "
    select nama_lengkap, no_hp, alamat
    from pengguna
    where id_pengguna = $id_pengguna
    limit 1
";
$q_pengguna = mysqli_query($koneksi, $sql_pengguna);
$pengguna   = mysqli_fetch_assoc($q_pengguna);

$nama_penerima      = $pengguna['nama_lengkap'] ?? "";
$no_hp_penerima     = $pengguna['no_hp'] ?? "";
$alamat_pengiriman  = $pengguna['alamat'] ?? "";
$catatan            = "";
$pesan_error        = "";

// fungsi generate kode pesanan (contoh: INV20251203-0001)
function generate_kode_pesanan(mysqli $koneksi): string {
    $tanggal = date('Ymd');
    $sql     = "
        select count(*) as jumlah
        from pesanan
        where date(dibuat_pada) = curdate()
    ";
    $q       = mysqli_query($koneksi, $sql);
    $row     = mysqli_fetch_assoc($q);
    $urutan  = (int)$row['jumlah'] + 1;
    $kode    = "INV" . $tanggal . "-" . str_pad($urutan, 4, "0", STR_PAD_LEFT);
    return $kode;
}

// proses ketika form submit
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['buat_pesanan'])
) {
    $nama_penerima     = trim($_POST['nama_penerima'] ?? "");
    $no_hp_penerima    = trim($_POST['no_hp_penerima'] ?? "");
    $alamat_pengiriman = trim($_POST['alamat_pengiriman'] ?? "");
    $catatan           = trim($_POST['catatan'] ?? "");

    if ($nama_penerima === "" || $no_hp_penerima === "" || $alamat_pengiriman === "") {
        $pesan_error = "nama penerima, no hp, dan alamat pengiriman wajib diisi.";
    } else {
        // ambil ulang item keranjang (supaya data terbaru)
$data_keranjang = ambil_item_keranjang(
    $koneksi,
    $id_keranjang,
    $id_pengguna,
    $checkout_item_list
);
        $items          = $data_keranjang['items'];
        $total_harga    = $data_keranjang['total_harga'];

        if (count($items) === 0) {
            $pesan_error = "keranjang kamu sudah kosong, silakan pilih produk kembali.";
        } else {
            // mulai proses buat pesanan
            mysqli_begin_transaction($koneksi);

            try {
                $kode_pesanan = generate_kode_pesanan($koneksi);

                $nama_esc      = mysqli_real_escape_string($koneksi, $nama_penerima);
                $hp_esc        = mysqli_real_escape_string($koneksi, $no_hp_penerima);
                $alamat_esc    = mysqli_real_escape_string($koneksi, $alamat_pengiriman);
                $catatan_esc   = mysqli_real_escape_string($koneksi, $catatan);
                $total_harga_f = (float)$total_harga;

                $sql_pesanan = "
                    insert into pesanan (
                        kode_pesanan,
                        id_pengguna,
                        total_harga,
                        status_pesanan,
                        metode_pembayaran,
                        nama_penerima,
                        no_hp_penerima,
                        alamat_pengiriman,
                        catatan
                    ) values (
                        '$kode_pesanan',
                        $id_pengguna,
                        $total_harga_f,
                        'menunggu_pembayaran',
                        'transfer_bank',
                        '$nama_esc',
                        '$hp_esc',
                        '$alamat_esc',
                        '$catatan_esc'
                    )
                ";

                $q_pesanan = mysqli_query($koneksi, $sql_pesanan);
                if (!$q_pesanan) {
                    throw new Exception("gagal membuat pesanan: " . mysqli_error($koneksi));
                }

                $id_pesanan = (int)mysqli_insert_id($koneksi);

                // insert pesanan_item + kurangi stok
                foreach ($items as $item) {
                    $id_produk   = (int)$item['id_produk'];
                    $nama_produk = mysqli_real_escape_string($koneksi, $item['nama_produk']);
                    $harga       = (float)$item['harga_saat_ini'];
                    $jumlah      = (int)$item['jumlah'];
                    $subtotal    = (float)$item['subtotal'];

                    $sql_pesanan_item = "
                        insert into pesanan_item (
                            id_pesanan,
                            id_produk,
                            nama_produk,
                            harga,
                            jumlah,
                            subtotal
                        ) values (
                            $id_pesanan,
                            $id_produk,
                            '$nama_produk',
                            $harga,
                            $jumlah,
                            $subtotal
                        )
                    ";
                    $q_item = mysqli_query($koneksi, $sql_pesanan_item);
                    if (!$q_item) {
                        throw new Exception("gagal menyimpan item pesanan: " . mysqli_error($koneksi));
                    }

                    // kurangi stok produk
                    $sql_kurang_stok = "
                        update produk
                        set stok = stok - $jumlah
                        where id_produk = $id_produk
                        and stok >= $jumlah
                    ";
                    $q_stok = mysqli_query($koneksi, $sql_kurang_stok);
                    if (!$q_stok || mysqli_affected_rows($koneksi) === 0) {
                        throw new Exception("stok produk tidak mencukupi atau gagal diperbarui.");
                    }
                }

                // kosongkan keranjang_item milik pembeli
                $sql_kosongkan = "
                    delete ki
                    from keranjang_item ki
                    inner join keranjang k on k.id_keranjang = ki.id_keranjang
                    where k.id_keranjang = $id_keranjang
                    and k.id_pengguna = $id_pengguna
                ";
                $q_kosongkan = mysqli_query($koneksi, $sql_kosongkan);
                if (!$q_kosongkan) {
                    throw new Exception("gagal mengosongkan keranjang: " . mysqli_error($koneksi));
                }

                mysqli_commit($koneksi);

                // redirect ke halaman detail pesanan
                header("Location: pesanan_detail.php?id=" . $id_pesanan . "&pesan=pesanan_berhasil");
                exit;

            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $pesan_error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>checkout - toko aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

    <?php include "layout/navbar.php"; ?>

<main class="max-w-6xl mx-auto px-4 py-6">
    <!-- judul & deskripsi -->
    <h1 class="text-xl font-semibold text-gray-800 mb-1 flex items-center gap-2">
        <i class="fa-solid fa-file-invoice text-gray-700"></i>
        checkout pesanan
    </h1>
    <p class="text-xs text-gray-500 mb-4 flex items-center gap-2">
        <i class="fa-solid fa-circle-info text-gray-400"></i>
        pastikan data pengiriman dan pesanan kamu sudah benar sebelum membuat pesanan.
    </p>

    <!-- pesan error -->
    <?php if ($pesan_error !== ""): ?>
        <div
            id="alertErrorCheckout"
            class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2"
        >
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo htmlspecialchars($pesan_error); ?></span>
        </div>

        <script>
            (function () {
                var alertBox = document.getElementById("alertErrorCheckout");
                if (alertBox) {
                    // tambahkan transisi opacity
                    alertBox.style.transition = "opacity 0.5s ease";

                    // mulai fade-out setelah 3 detik
                    setTimeout(function () {
                        alertBox.style.opacity = "0";

                        // setelah animasi selesai, sembunyikan
                        setTimeout(function () {
                            alertBox.classList.add("hidden");
                        }, 500);
                    }, 3000);
                }
            })();
        </script>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- form data pengiriman -->
        <div class="md:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-truck-fast text-gray-700"></i>
                data pengiriman
            </h2>

            <form method="post" class="space-y-3">
                <?php foreach ($checkout_item_ids as $idItem): ?>
    <input type="hidden" name="checkout_items[]" value="<?php echo (int)$idItem; ?>">
<?php endforeach; ?>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-user text-gray-500"></i>
                        nama penerima <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="nama_penerima"
                        value="<?php echo htmlspecialchars($nama_penerima); ?>"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        required
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-phone text-gray-500"></i>
                        no hp penerima <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="no_hp_penerima"
                        value="<?php echo htmlspecialchars($no_hp_penerima); ?>"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        required
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-location-dot text-gray-500"></i>
                        alamat pengiriman <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="alamat_pengiriman"
                        rows="3"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                        required
                    ><?php echo htmlspecialchars($alamat_pengiriman); ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-note-sticky text-gray-500"></i>
                        catatan (opsional)
                    </label>
                    <textarea
                        name="catatan"
                        rows="3"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    ><?php echo htmlspecialchars($catatan); ?></textarea>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <a
                        href="keranjang.php"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        kembali ke keranjang
                    </a>
<button
    type="submit"
    name="buat_pesanan"
    value="1"
    class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
>
    <i class="fa-solid fa-check"></i>
    buat pesanan
</button>

                </div>
            </form>
        </div>

        <!-- ringkasan pesanan + info pembayaran -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col">
            <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-receipt text-gray-700"></i>
                ringkasan pesanan
            </h2>

            <div class="max-h-64 overflow-y-auto mb-3 space-y-2">
                <?php foreach ($items as $item): ?>
                    <div class="border-b border-gray-100 pb-2 last:border-b-0 last:pb-0">
                        <p class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                            <i class="fa-solid fa-tag text-gray-500"></i>
                            <?php echo htmlspecialchars($item['nama_produk']); ?>
                        </p>
                        <p class="text-[11px] text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-cart-plus text-gray-400"></i>
                            <?php echo (int)$item['jumlah']; ?> x
                            rp <?php echo number_format($item['harga_saat_ini'], 0, ',', '.'); ?>
                        </p>
                        <p class="text-[11px] text-gray-700 font-medium flex items-center gap-1">
                            <i class="fa-solid fa-wallet text-gray-500"></i>
                            subtotal: rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center justify-between mb-3 text-sm text-gray-800">
                <span>total harga</span>
                <span class="font-semibold flex items-center gap-1">
                    <i class="fa-solid fa-sack-dollar text-gray-700"></i>
                    rp <?php echo number_format($total_harga, 0, ',', '.'); ?>
                </span>
            </div>

            <div class="mt-2 border-t border-gray-200 pt-3 text-xs text-gray-600">
                <p class="font-semibold mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-money-check-dollar text-gray-700"></i>
                    informasi pembayaran
                </p>
                <p class="flex items-center gap-2">
                    <i class="fa-solid fa-credit-card text-gray-500"></i>
                    metode pembayaran: transfer bank
                </p>
                <p class="mt-1 flex items-center gap-2">
                    <i class="fa-solid fa-building-columns text-gray-500"></i>
                    bank contoh: 123 456 789 a.n. toko aksesoris
                </p>
                <p class="mt-1 text-[11px] text-gray-500 flex items-start gap-2">
                    <i class="fa-solid fa-circle-exclamation text-yellow-500 mt-[2px]"></i>
                    setelah pesanan dibuat, silakan transfer sesuai total harga, lalu upload bukti transfer
                    dari halaman detail pesanan.
                </p>
            </div>
        </div>
    </div>
</main>


    <?php include "layout/footer.php"; ?>

</body>
</html>
