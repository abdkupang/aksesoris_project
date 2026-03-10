<?php
// status login dan role
$is_login   = isset($_SESSION['id_pengguna']);
$is_pembeli = $is_login && isset($_SESSION['role']) && $_SESSION['role'] === 'pembeli';
$is_penjual = $is_login && isset($_SESSION['role']) && $_SESSION['role'] === 'penjual';

$jumlah_keranjang = 0;

if ($is_login && $is_pembeli) {
    $id_pengguna = (int) $_SESSION['id_pengguna'];

    $sql = "
        SELECT
            COALESCE(SUM(ki.jumlah), 0) AS total_item
        FROM keranjang_item ki
        INNER JOIN keranjang k
            ON k.id_keranjang = ki.id_keranjang
        WHERE k.id_pengguna = $id_pengguna
    ";

    $query = mysqli_query($koneksi, $sql);

    if ($query) {
        $row = mysqli_fetch_assoc($query);
        $jumlah_keranjang = (int) $row['total_item'];
    }
}
?>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
/>
<script>
function toggleNavbar() {
    var menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
}
</script>

<header
    id="mainNavbar"
    class="bg-white shadow fixed top-0 left-0 right-0 z-40 transition-transform duration-300"
>
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">

        <!-- logo -->
        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-900 text-white font-bold">
                A
            </span>
            <div>
                <h1 class="text-lg font-semibold text-gray-800">toko aksesoris</h1>
                <p class="text-xs text-gray-500">jual berbagai aksesoris pilihan</p>
            </div>
        </div>

        <!-- hamburger (mobile) -->
        <button
            type="button"
            onclick="toggleNavbar()"
            class="md:hidden text-gray-700 text-2xl focus:outline-none"
            aria-label="Toggle navigation"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <!-- menu desktop -->
        <nav class="hidden md:flex items-center space-x-5">
            <?php include __DIR__ . '/navbar_menu.php'; ?>
        </nav>
    </div>

    <!-- menu mobile -->
    <nav
        id="mobileMenu"
        class="md:hidden hidden border-t bg-white px-4 py-4 space-y-3"
    >
        <?php include __DIR__ . '/navbar_menu.php'; ?>
    </nav>
</header>
<div class="h-20"></div>

<!-- modal konfirmasi logout -->
<!-- modal konfirmasi logout (pelanggan) -->
<div id="modalLogout" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-80 p-6 text-center">

        <!-- ikon logout -->
        <div class="flex justify-center mb-3">
            <div class="w-14 h-14 rounded-full bg-red-600 text-white flex items-center justify-center text-2xl shadow-md">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-800 mb-1">
            konfirmasi logout
        </h3>

        <p class="text-sm text-gray-600 mb-5">
            anda akan keluar dari akun pembeli.<br>
            pastikan semua transaksi sudah tersimpan.
        </p>

        <div class="flex items-center justify-center gap-3">
            <button
                type="button"
                onclick="hideLogoutModal()"
                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm hover:bg-gray-100"
            >
                batal
            </button>

            <a 
                href="auth/logout.php"
                class="px-4 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700"
            >
                ya, logout
            </a>
        </div>

    </div>
</div>


<script>
function showLogoutModal() {
    document.getElementById('modalLogout').classList.remove('hidden');
    document.getElementById('modalLogout').classList.add('flex');
}
function hideLogoutModal() {
    document.getElementById('modalLogout').classList.remove('flex');
    document.getElementById('modalLogout').classList.add('hidden');
}

(function () {
    var navbar = document.getElementById('mainNavbar');
    var lastScrollTop = 0;
    var threshold = 10;

    window.addEventListener('scroll', function () {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (Math.abs(scrollTop - lastScrollTop) <= threshold) {
            return;
        }

        if (scrollTop > lastScrollTop && scrollTop > 80) {
            // scroll ke bawah → sembunyikan navbar
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // scroll ke atas → tampilkan navbar
            navbar.style.transform = 'translateY(0)';
        }

        lastScrollTop = scrollTop;
    });
})();
</script>
