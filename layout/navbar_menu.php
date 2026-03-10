<a href="toko.php" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
    <i class="fa-solid fa-house"></i>
    <span>beranda</span>
</a>

<a href="toko.php#kontak" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
    <i class="fa-solid fa-phone"></i>
    <span>kontak</span>
</a>

<a href="toko.php#informasi" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
    <i class="fa-solid fa-circle-info"></i>
    <span>informasi</span>
</a>

<a href="toko.php#komentar" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
    <i class="fa-solid fa-comments"></i>
    <span>komentar</span>
</a>

<?php if ($is_login && $is_pembeli): ?>

<a href="keranjang.php" class="relative flex items-center gap-2 text-gray-700 hover:text-gray-900">
    <i class="fa-solid fa-cart-shopping text-lg"></i>
    <span>keranjang</span>

    <?php if ($jumlah_keranjang > 0): ?>
        <span
            class="absolute -top-2 -right-3 min-w-[18px] h-[18px]
                   bg-red-600 text-white text-[11px]
                   rounded-full flex items-center justify-center px-1"
        >
            <?php echo $jumlah_keranjang; ?>
        </span>
    <?php endif; ?>
</a>


    <a href="pesanan_saya.php" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
        <i class="fa-solid fa-file-invoice"></i>
        <span>pesanan saya</span>
    </a>

    <a href="akun.php" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
        <i class="fa-solid fa-user"></i>
        <span>akun</span>
    </a>

    <button
        type="button"
        onclick="showLogoutModal()"
        class="flex items-center gap-2 text-red-600 hover:text-red-700"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>logout</span>
    </button>

<?php elseif ($is_login && $is_penjual): ?>

    <a href="penjual/index.php" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
        <i class="fa-solid fa-chart-line"></i>
        <span>dashboard</span>
    </a>

    <button
        type="button"
        onclick="showLogoutModal()"
        class="flex items-center gap-2 text-red-600 hover:text-red-700"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>logout</span>
    </button>

<?php else: ?>

    <a href="auth/login.php" class="flex items-center gap-2 text-gray-700 hover:text-gray-900">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span>login</span>
    </a>

    <a href="auth/daftar.php"
       class="flex items-center gap-2 px-3 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800">
        <i class="fa-solid fa-user-plus"></i>
        <span>daftar</span>
    </a>

<?php endif; ?>
