<?php
// include ini di bagian <head> halaman penjual
// atau kalau mau benar-benar "langsung di sini", kamu bisa taruh
// baris <link> ini sebelum <aside>, tapi idealnya di <head>.
?>
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
/>

<?php
// di atas <aside>, tentukan halaman aktif
$current_page = basename($_SERVER['PHP_SELF']); // contoh: "produk.php"
?>
<?php
// hitung pesanan menunggu konfirmasi
$q_pesanan_menunggu = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM pesanan
     WHERE status_pesanan = 'menunggu_konfirmasi'"
);
$row_pesanan = $q_pesanan_menunggu ? mysqli_fetch_assoc($q_pesanan_menunggu) : ['total' => 0];
$total_pesanan_menunggu = (int) $row_pesanan['total'];

// hitung bukti transfer menunggu verifikasi
$q_bukti_menunggu = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM bukti_transfer
     WHERE status_verifikasi = 'menunggu'"
);
$row_bukti = $q_bukti_menunggu ? mysqli_fetch_assoc($q_bukti_menunggu) : ['total' => 0];
$total_bukti_menunggu = (int) $row_bukti['total'];
?>

<aside
    id="sidebar"
    class="fixed md:static inset-y-0 left-0 z-50 w-64
           bg-gray-900 text-gray-100 flex flex-col
           transform -translate-x-full md:translate-x-0
           transition-transform duration-300 ease-in-out"
>
    <div class="px-4 py-4 border-b border-gray-700 flex items-center space-x-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white text-gray-900 font-bold">
            A
        </span>
        <div>
            <p class="text-sm font-semibold">panel penjual</p>
            <p class="text-[11px] text-gray-400">toko aksesoris</p>
        </div>
    </div>

    <div class="px-4 py-3 border-b border-gray-800">
        <p class="text-xs text-gray-400 mb-1">login sebagai</p>
        <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($nama_penjual); ?></p>
    </div>

    <?php
    // helper kecil untuk class aktif / tidak aktif
    function kelas_nav($page, $current_page) {
        if ($page === $current_page) {
            return "bg-gray-800 text-white font-semibold";
        }
        return "text-gray-300 hover:bg-gray-800 hover:text-white";
    }
    ?>

    <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
        <!-- dashboard -->
        <a
            href="index.php"
            class="flex items-center px-3 py-2 rounded-md <?php echo kelas_nav('index.php', $current_page); ?>"
        >
            <i class="fa-solid fa-gauge mr-2 w-4 text-xs"></i>
            <span>dashboard</span>
        </a>

        <!-- produk -->
        <a
            href="produk.php"
            class="flex items-center px-3 py-2 rounded-md <?php echo kelas_nav('produk.php', $current_page); ?>"
        >
            <i class="fa-solid fa-box-open mr-2 w-4 text-xs"></i>
            <span>produk</span>
        </a>

        <!-- kategori (baru) -->
        <div class="flex items-center justify-between">
            <a
                href="kategori.php"
                class="flex items-center px-3 py-2 rounded-md <?php echo kelas_nav('kategori.php', $current_page); ?> w-full"
            >
                <i class="fa-solid fa-layer-group mr-2 w-4 text-xs"></i>
                <span>kategori</span>
            </a>

            <!-- tombol tambah kategori cepat -->
            <button
                type="button"
                onclick="openAddKategoriModal()"
                class="ml-2 inline-flex items-center px-2 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white"
                title="tambah kategori"
            >
                <i class="fa-solid fa-plus text-xs"></i>
            </button>
        </div>


        <!-- pesanan -->
<a
    href="pesanan.php"
    class="flex items-center justify-between px-3 py-2 rounded-md <?php echo kelas_nav('pesanan.php', $current_page); ?>"
>
    <div class="flex items-center">
        <i class="fa-solid fa-receipt mr-2 w-4 text-xs"></i>
        <span>pesanan</span>
    </div>

    <?php if ($total_pesanan_menunggu > 0): ?>
        <span
            class="min-w-[20px] px-1.5 py-[1px] rounded-full
                   bg-yellow-500 text-black text-[10px] font-semibold
                   text-center leading-none"
        >
            <?php echo $total_pesanan_menunggu; ?>
        </span>
    <?php endif; ?>
</a>


        <!-- bukti transfer -->
<a
    href="bukti_transfer.php"
    class="flex items-center justify-between px-3 py-2 rounded-md <?php echo kelas_nav('bukti_transfer.php', $current_page); ?>"
>
    <div class="flex items-center">
        <i class="fa-solid fa-money-check-dollar mr-2 w-4 text-xs"></i>
        <span>bukti transfer</span>
    </div>

    <?php if ($total_bukti_menunggu > 0): ?>
        <span
            class="min-w-[20px] px-1.5 py-[1px] rounded-full
                   bg-yellow-500 text-gray-900 text-[10px] font-semibold
                   text-center leading-none"
        >
            <?php echo $total_bukti_menunggu; ?>
        </span>
    <?php endif; ?>
</a>

        <!-- pelanggan -->
        <a
            href="pelanggan.php"
            class="flex items-center px-3 py-2 rounded-md <?php echo kelas_nav('pelanggan.php', $current_page); ?>"
        >
            <i class="fa-solid fa-users mr-2 w-4 text-xs"></i>
            <span>pelanggan</span>
        </a>

        <!-- pengaturan akun -->
        <a
            href="akun.php"
            class="flex items-center px-3 py-2 rounded-md <?php echo kelas_nav('akun.php', $current_page); ?>"
        >
            <i class="fa-solid fa-user-gear mr-2 w-4 text-xs"></i>
            <span>pengaturan akun</span>
        </a>

        <!-- lihat toko -->
<!-- tombol buka modal toko -->
<button
    type="button"
    onclick="openModalToko()"
    class="flex items-center px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white w-full text-left"
>
    <i class="fa-solid fa-store mr-2 w-4 text-xs"></i>
    <span>lihat toko</span>
</button>

    </nav>

    <div class="px-3 py-3 border-t border-gray-800">
        <button
            type="button"
            onclick="openLogoutModalPenjual()"
            class="flex items-center justify-center w-full px-3 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700"
        >
            <i class="fa-solid fa-right-from-bracket mr-2"></i>
            <span>logout</span>
        </button>
    </div>
</aside>

<!-- modal konfirmasi logout (penjual) -->
<div
    id="modalLogoutPenjual"
    class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50"
>
    <div class="bg-white rounded-xl shadow-xl w-80 p-6 text-center">

        <!-- ikon logout -->
        <div class="flex justify-center mb-3">
            <div class="w-14 h-14 rounded-full bg-red-600 text-white flex items-center justify-center text-2xl shadow-md">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-800 mb-1">
            keluar dari panel penjual?
        </h3>

        <p class="text-sm text-gray-600 mb-5">
            anda akan logout dari akun penjual dan kembali ke halaman login.
        </p>

        <div class="flex items-center justify-center gap-3">
            <button
                type="button"
                onclick="closeLogoutModalPenjual()"
                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm hover:bg-gray-100"
            >
                batal
            </button>

            <a
                href="../auth/logout.php"
                class="px-4 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700"
            >
                ya, logout
            </a>
        </div>
    </div>
</div>

<!-- modal konfirmasi menuju toko -->
<div
    id="modalToko"
    class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50"
>
    <div class="bg-white rounded-xl shadow-xl w-80 p-6 text-center">
        
        <div class="flex justify-center mb-3">
            <div class="w-14 h-14 rounded-full bg-gray-900 text-white flex items-center justify-center text-2xl">
                <i class="fa-solid fa-store"></i>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-800 mb-1">
            menuju halaman toko
        </h3>

        <p class="text-sm text-gray-600 mb-5">
            anda akan berpindah ke tampilan toko sebagai pembeli.  
            mode ini tidak memungkinkan anda melakukan pemesanan.
        </p>

        <div class="flex items-center justify-center gap-3">
            <button
                type="button"
                onclick="closeModalToko()"
                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm hover:bg-gray-100"
            >
                batal
            </button>

            <a
                href="../toko.php"
                class="px-4 py-2 rounded-md bg-gray-900 text-white text-sm hover:bg-gray-800"
            >
                lanjutkan
            </a>
        </div>
    </div>
</div>


<script>
    function openLogoutModalPenjual() {
        var m = document.getElementById('modalLogoutPenjual');
        if (!m) return;
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeLogoutModalPenjual() {
        var m = document.getElementById('modalLogoutPenjual');
        if (!m) return;
        m.classList.remove('flex');
        m.classList.add('hidden');
    }
        function openModalToko() {
        var m = document.getElementById('modalToko');
        if (!m) return;
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeModalToko() {
        var m = document.getElementById('modalToko');
        if (!m) return;
        m.classList.remove('flex');
        m.classList.add('hidden');
    }
</script>
