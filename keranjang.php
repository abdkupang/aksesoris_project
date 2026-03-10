<?php
// file: keranjang.php
include "config.php";

// cek login dan role
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pembeli') {
    // jika belum login atau bukan pembeli → paksa login dulu
    header("Location: auth/login.php?pesan=login_dulu&redirect=../keranjang.php");
    exit;
}

$id_pengguna = (int)$_SESSION['id_pengguna'];

// ambil / buat keranjang milik pembeli
$sql_keranjang = "
    select id_keranjang
    from keranjang
    where id_pengguna = $id_pengguna
    limit 1
";
$q_keranjang = mysqli_query($koneksi, $sql_keranjang);

if ($q_keranjang && mysqli_num_rows($q_keranjang) > 0) {
    $row_keranjang = mysqli_fetch_assoc($q_keranjang);
    $id_keranjang  = (int)$row_keranjang['id_keranjang'];
} else {
    // belum pernah punya keranjang → buat baru (boleh juga dibuat saat pertama kali tambah_keranjang)
    $sql_buat_keranjang = "
        insert into keranjang (id_pengguna)
        values ($id_pengguna)
    ";
    $q_buat = mysqli_query($koneksi, $sql_buat_keranjang);

    if (!$q_buat) {
        die("gagal membuat keranjang: " . mysqli_error($koneksi));
    }

    $id_keranjang = (int)mysqli_insert_id($koneksi);
}

// proses update / hapus item keranjang
$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// ===============================
// PROSES HAPUS SATU ITEM KERANJANG
// ===============================
if (isset($_POST['hapus'])) {

    $id_item = (int) $_POST['hapus'];

    if ($id_item <= 0 || $id_keranjang <= 0) {
        header("Location: keranjang.php?pesan=update_sukses");
        exit;
    }

    mysqli_begin_transaction($koneksi);

    try {

        // 1. Hapus item keranjang
        $sql_hapus_item = "
            DELETE ki
            FROM keranjang_item ki
            INNER JOIN keranjang k
                ON k.id_keranjang = ki.id_keranjang
            WHERE ki.id_keranjang_item = $id_item
              AND k.id_keranjang = $id_keranjang
              AND k.id_pengguna = $id_pengguna
        ";

        if (!mysqli_query($koneksi, $sql_hapus_item)) {
            throw new Exception(mysqli_error($koneksi));
        }

        // 2. Cek sisa item
        $sql_cek = "
            SELECT COUNT(*) AS total
            FROM keranjang_item
            WHERE id_keranjang = $id_keranjang
        ";

        $q_cek = mysqli_query($koneksi, $sql_cek);
        if (!$q_cek) {
            throw new Exception(mysqli_error($koneksi));
        }

        $row = mysqli_fetch_assoc($q_cek);
        $total_item = (int) $row['total'];

        // 3. Jika kosong → hapus keranjang
        if ($total_item === 0) {

            $sql_hapus_keranjang = "
                DELETE FROM keranjang
                WHERE id_keranjang = $id_keranjang
                  AND id_pengguna = $id_pengguna
            ";

            if (!mysqli_query($koneksi, $sql_hapus_keranjang)) {
                throw new Exception(mysqli_error($koneksi));
            }

            mysqli_commit($koneksi);

            header("Location: keranjang.php?pesan=keranjang_dihapus");
            exit;
        }

        // 4. Commit jika masih ada item
        mysqli_commit($koneksi);

        header("Location: keranjang.php?pesan=update_sukses");
        exit;

    } catch (Exception $e) {

        mysqli_rollback($koneksi);
        die("Gagal menghapus keranjang: " . $e->getMessage());
    }
}



    // update jumlah item
    if (isset($_POST['update']) && isset($_POST['jumlah']) && is_array($_POST['jumlah'])) {
        foreach ($_POST['jumlah'] as $id_item => $jml) {
            $id_item = (int)$id_item;
if ($jml === '' || !is_numeric($jml)) {
    continue; // abaikan input kosong
}

$jumlah = (int)$jml;


if ($jumlah <= 0) {

    // mulai transaksi
    mysqli_begin_transaction($koneksi);

    try {

        // 1. hapus item keranjang
        $sql_hapus_item = "
            DELETE FROM keranjang_item
            WHERE id_keranjang_item = $id_item
              AND id_keranjang = $id_keranjang
        ";
        if (!mysqli_query($koneksi, $sql_hapus_item)) {
            throw new Exception('Gagal menghapus item keranjang');
        }

        // 2. cek apakah masih ada item di keranjang
        $sql_cek_sisa = "
            SELECT COUNT(*) AS total
            FROM keranjang_item
            WHERE id_keranjang = $id_keranjang
        ";
        $q_cek = mysqli_query($koneksi, $sql_cek_sisa);
        if (!$q_cek) {
            throw new Exception('Gagal cek sisa item keranjang');
        }

        $row = mysqli_fetch_assoc($q_cek);
        $total_item = (int)$row['total'];

        // 3. jika tidak ada item lagi → hapus keranjang
        if ($total_item === 0) {

            $sql_hapus_keranjang = "
                DELETE FROM keranjang
                WHERE id_keranjang = $id_keranjang
                  AND id_pengguna = $id_pengguna
            ";
            if (!mysqli_query($koneksi, $sql_hapus_keranjang)) {
                throw new Exception('Gagal menghapus keranjang');
            }
        }

        // 4. commit
        mysqli_commit($koneksi);

    } catch (Exception $e) {

        // rollback jika ada error
        mysqli_rollback($koneksi);
        die($e->getMessage());
    }
}
 else {
                // update jumlah, tapi cek stok produk dulu
                $sql_cek_stok = "
                    select p.stok
                    from keranjang_item ki
                    inner join produk p on p.id_produk = ki.id_produk
                    where ki.id_keranjang_item = $id_item
                    and ki.id_keranjang = $id_keranjang
                    limit 1
                ";
                $q_stok = mysqli_query($koneksi, $sql_cek_stok);
                if ($q_stok && mysqli_num_rows($q_stok) > 0) {
                    $row_stok = mysqli_fetch_assoc($q_stok);
                    $stok     = (int)$row_stok['stok'];

                    if ($jumlah > $stok) {
                        $jumlah = $stok;
                    }

                    $sql_update = "
                        update keranjang_item
                        set jumlah = $jumlah,
                            diperbarui_pada = now()
                        where id_keranjang_item = $id_item
                        and id_keranjang = $id_keranjang
                    ";
                    mysqli_query($koneksi, $sql_update);
                }
            }
        }

        header("Location: keranjang.php?pesan=update_sukses");
        exit;
    }
}

// ambil item keranjang untuk tampilan
$sql_item = "
    select 
        ki.id_keranjang_item,
        ki.id_produk,
        ki.jumlah,
        ki.harga_saat_ini,
        p.nama_produk,
        p.stok,
        p.gambar_produk as gambar_utama -- ambil nama file dari kolom produk
    from keranjang_item ki
    inner join keranjang k on k.id_keranjang = ki.id_keranjang
    inner join produk p on p.id_produk = ki.id_produk
    where k.id_keranjang = $id_keranjang
    and k.id_pengguna = $id_pengguna
    order by ki.dibuat_pada desc
";
$q_item = mysqli_query($koneksi, $sql_item);

$items       = [];
$total_harga = 0;

if ($q_item && mysqli_num_rows($q_item) > 0) {
    while ($row = mysqli_fetch_assoc($q_item)) {
        // hitung subtotal
        $subtotal       = $row['harga_saat_ini'] * $row['jumlah'];
        $row['subtotal'] = $subtotal;
        $total_harga    += $subtotal;

        // proses gambar: $row['gambar_utama'] berisi NAMA FILE (atau NULL)
        $gambar_file = $row['gambar_utama'] ?? "";
        if (!empty($gambar_file)) {
            // lindungi -> ambil basename (menghapus kemungkinan path yang tidak diinginkan)
            $filename = basename($gambar_file);
            $fs_path  = __DIR__ . '/uploads/produk/' . $filename;
            if (file_exists($fs_path) && is_file($fs_path)) {
                // path relatif yang aman untuk HTML
                $row['gambar_utama'] = 'uploads/produk/' . $filename;
            } else {
                $row['gambar_utama'] = null;
            }
        } else {
            $row['gambar_utama'] = null;
        }

        $items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>keranjang belanja - toko aksesoris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- tailwind css cdn -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

    <?php include "layout/navbar.php"; ?>

    <main class="max-w-6xl mx-auto px-4 py-6">
<h1 class="text-xl font-semibold text-gray-800 mb-1 flex items-center gap-2">
    <i class="fa-solid fa-cart-shopping text-gray-700"></i>
    keranjang belanja
</h1>

<p class="text-xs text-gray-500 mb-4 flex items-center gap-2">
    <i class="fa-solid fa-circle-info text-gray-400"></i>
    cek kembali produk yang akan kamu pesan sebelum melanjutkan ke checkout.
</p>


<!-- pesan -->
<?php if ($pesan === "update_sukses"): ?>
    <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
        keranjang berhasil diperbarui.
    </div>

<?php elseif ($pesan === "tambah_sukses"): ?>
    <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
        produk berhasil ditambahkan ke keranjang.
    </div>

<?php elseif ($pesan === "logout_sukses"): ?>
    <div id="alertMsg" class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
        anda berhasil logout.
    </div>

<?php elseif ($pesan === "keranjang_kosong"): ?>
    <div id="alertMsg" class="mb-4 rounded-md bg-yellow-100 border border-yellow-300 px-4 py-3 text-sm text-yellow-900">
        keranjang masih kosong, silakan pilih produk terlebih dahulu.
    </div>
    <?php elseif ($pesan === "pilih_item_dulu"): ?>
    <div id="alertMsg" class="mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800">
        minimal pilih salah satu item di keranjang.
    </div>
    <?php elseif ($pesan === "keranjang_dihapus"): ?>
    <div id="alertMsg"
         class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800">
        semua item di keranjang telah dihapus.
    </div>
<?php endif; ?>



<!-- script fade-out 3 detik -->
<script>
    
    (function () {
        var alertBox = document.getElementById("alertMsg");
        if (alertBox) {

            // Tambahkan transisi opacity halus
            alertBox.style.transition = "opacity 0.5s ease";

            // Mulai fade-out setelah 3 detik
            setTimeout(function () {

                alertBox.style.opacity = "0";

                // Hilangkan setelah animasi selesai (0.5s)
                setTimeout(function () {
                    alertBox.classList.add("hidden");
                }, 500);

            }, 3000);
        }
    })();
    
</script>


        <?php if (count($items) === 0): ?>
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-10 text-center">
        
        <div class="flex justify-center mb-3">
            <div class="w-14 h-14 flex items-center justify-center rounded-full bg-gray-900 text-white text-3xl shadow">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>

        <p class="text-sm text-gray-500 mb-4">
            keranjang kamu masih kosong.
        </p>

        <a
            href="toko.php"
            class="inline-flex items-center px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800"
        >
            <i class="fa-solid fa-bag-shopping mr-2"></i>
            lanjut belanja
        </a>
    </div>

<?php else: ?>

<form method="post" action="checkout.php" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-5">

    <!-- ============================= -->
    <!-- BAGIAN LIST ITEM KERANJANG -->
    <!-- ============================= -->

    <div class="md:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <!-- pilih semua -->
    <div class="mb-3 flex items-start gap-2">
        <input
            type="checkbox"
            id="checkAllItems"
            class="mt-1 w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
        >
        <div>
            <label for="checkAllItems" class="text-sm font-medium text-gray-800 cursor-pointer">
                pilih semua produk
            </label>
            <p class="text-[11px] text-gray-500">
                centang untuk memilih seluruh produk di keranjang sekaligus.
            </p>
        </div>
    </div>
        <div class="space-y-4">

            <?php foreach ($items as $item): ?>
                <div class="flex items-start gap-3 border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
<!-- checkbox pilih item -->
<div class="pt-1">
<input
    type="checkbox"
    name="checkout_items[]"
    value="<?php echo (int)$item['id_keranjang_item']; ?>"
    data-subtotal="<?php echo (int)$item['subtotal']; ?>"
    class="checkout-item w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
>
</div>

                    <!-- gambar -->
                    <div class="w-20 h-20 rounded-md overflow-hidden bg-gray-100 flex-shrink-0">
                        <?php if (!empty($item['gambar_utama'])): ?>
                            <img
                                src="<?php echo htmlspecialchars($item['gambar_utama']); ?>"
                                alt="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                                class="w-full h-full object-cover"
                            >
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-[10px] text-gray-400">
                                <i class="fa-regular fa-image mr-1"></i> tidak ada gambar
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- info item -->
                    <div class="flex-1">

                        <p class="text-sm font-semibold text-gray-800">
                            <?php echo htmlspecialchars($item['nama_produk']); ?>
                        </p>

                        <p class="text-xs text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-tag text-gray-400"></i>
                            harga: rp <?php echo number_format($item['harga_saat_ini'], 0, ',', '.'); ?>
                        </p>

                        <p class="text-[11px] text-gray-400 mt-1 flex items-center gap-1">
                            <i class="fa-solid fa-box-open text-gray-400"></i>
                            stok tersedia: <?php echo (int)$item['stok']; ?>
                        </p>

                        <div class="mt-2 flex items-center gap-3">

                            <!-- input jumlah -->
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-1 flex items-center gap-1">
                                    <i class="fa-solid fa-sort-numeric-up text-gray-400"></i> jumlah
                                </label>

<input
    type="number"
    name="jumlah[<?= (int)$item['id_keranjang_item'] ?>]"
    value="<?= (int)$item['jumlah'] ?>"
    min="1"
    max="<?= (int)$item['stok'] ?>"
                                        class="w-20 px-2 py-1 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"

    required
    
>
<input type="hidden" name="update" value="1">

                            </div>

                            <!-- subtotal -->
                            <div class="ml-auto text-right">
                                <p class="text-xs text-gray-500 flex items-center justify-end gap-1 mb-1">
                                    <i class="fa-solid fa-wallet text-gray-400"></i> subtotal
                                </p>
                                <p class="text-sm font-semibold text-gray-900">
                                    rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                </p>
                            </div>

                        </div>

                        <!-- hapus -->
                        <div class="mt-2">
<button
    type="submit"
    name="hapus"
    value="<?php echo (int)$item['id_keranjang_item']; ?>"
    formaction="keranjang.php"
    class="text-[11px] text-red-500 hover:text-red-600 hover:underline inline-flex items-center gap-1"
>
    <i class="fa-solid fa-trash-can"></i>
    hapus item
</button>

                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

    <!-- ============================= -->
    <!-- RINGKASAN BELANJA -->
    <!-- ============================= -->

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col">
            <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-receipt text-gray-700"></i>
                ringkasan belanja
            </h2>

<div class="flex items-center justify-between mb-2 text-sm text-gray-700">
    <span>total harga</span>
    <span class="font-semibold flex items-center gap-1">
        <i class="fa-solid fa-wallet text-gray-600"></i>
        <span id="totalHargaText">rp 0</span>
    </span>
</div>



            <p class="text-[11px] text-gray-400 mb-4 flex items-start gap-1">
                <i class="fa-solid fa-triangle-exclamation text-yellow-500 mt-[2px]"></i>
                total belum termasuk ongkos kirim. ongkir diinformasikan saat pengiriman.
            </p>

                    <div class="mt-auto space-y-2">

                        <!-- update -->
                        <button
                type="submit"
                name="update"
                value="1"
                formaction="keranjang.php"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800"
                        >
                            <i class="fa-solid fa-rotate-right"></i>
                update keranjang
            </button>

            <!-- lanjut belanja -->
            <a
                href="toko.php"
                class="block w-full text-center px-4 py-2 rounded-md border flex items-center justify-center gap-2 border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
            >
                <i class="fa-solid fa-bag-shopping"></i>
                lanjut belanja
            </a>

            <!-- checkout -->
                <button
                    type="submit"
                    name="checkout"
                    value="1"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                >


                                <i class="fa-solid fa-credit-card"></i>
                                checkout
                </button>

        </div>
    </div>
</form>

<?php endif; ?>

    </main>

    <?php include "layout/footer.php"; ?>

</body>
<script>
    (function () {
        var checkAll = document.getElementById('checkAllItems');
        var items = document.querySelectorAll('.checkout-item');
        var totalText = document.getElementById('totalHargaText');

        if (!totalText || items.length === 0) {
            return;
        }

        function formatRupiah(n) {
            return 'rp ' + n.toLocaleString('id-ID');
        }

        function hitungTotal() {
            var total = 0;

            items.forEach(function (cb) {
                if (cb.checked) {
                    total += parseInt(cb.dataset.subtotal || '0', 10);
                }
            });

            totalText.textContent = formatRupiah(total);
        }

        // default: pastikan semua checkbox tidak tercentang
        items.forEach(function (cb) {
            cb.checked = false;
            cb.addEventListener('change', hitungTotal);
        });

        // pilih semua
        if (checkAll) {
            checkAll.checked = false;
            checkAll.addEventListener('change', function () {
                items.forEach(function (cb) {
                    cb.checked = checkAll.checked;
                });
                hitungTotal();
            });
        }
    })();
    document.querySelectorAll('input[type="number"]').forEach(function (input) {
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            return false;
        }
    });
});
</script>

</html>
