    <?php
    // file: pesanan_saya.php
    include "config.php";

    // hanya pembeli login yang boleh akses
    if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'pembeli') {
        header("Location: auth/login.php?pesan=login_dulu&redirect=pesanan_saya.php");
        exit;
    }

    $id_pengguna = (int)$_SESSION['id_pengguna'];
    $pesan       = isset($_GET['pesan']) ? $_GET['pesan'] : "";

    // ambil daftar pesanan milik pembeli
    // sekalian ambil status bukti transfer terbaru (jika ada)
    $sql_pesanan = "
        select
            p.id_pesanan,
            p.kode_pesanan,
            p.total_harga,
            p.status_pesanan,
            p.dibuat_pada,
            bt.status_verifikasi as status_bukti
        from pesanan p
        left join bukti_transfer bt
            on bt.id_pesanan = p.id_pesanan
            and bt.id_bukti_transfer = (
                select max(bt2.id_bukti_transfer)
                from bukti_transfer bt2
                where bt2.id_pesanan = p.id_pesanan
            )
        where p.id_pengguna = $id_pengguna
        order by p.dibuat_pada desc
    ";

    $q_pesanan = mysqli_query($koneksi, $sql_pesanan);

    $daftar_pesanan = [];
    if ($q_pesanan && mysqli_num_rows($q_pesanan) > 0) {
        while ($row = mysqli_fetch_assoc($q_pesanan)) {
            $daftar_pesanan[] = $row;
        }
    }
function boleh_batal_pesanan(array $p): bool {
    // hanya boleh dibatalkan jika status menunggu pembayaran
    if ($p['status_pesanan'] !== 'menunggu_pembayaran') {
        return false;
    }

    // cek waktu (maks 1 hari)
    $dibuat = strtotime($p['dibuat_pada']);
    if (time() - $dibuat > 86400) {
        return false;
    }

    // jika sudah ada bukti transfer (status_bukti tidak null)
    if ($p['status_bukti'] !== null) {
        return false;
    }

    return true;
}

    // fungsi kecil untuk badge status
    function badge_status_pesanan(string $status): string {
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

    function badge_status_bukti(?string $status_bukti): string {
        if ($status_bukti === null) {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">belum ada</span>';
        }
        $kelas = "bg-gray-100 text-gray-700";
        switch ($status_bukti) {
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
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ' . $kelas . '">' . htmlspecialchars($status_bukti) . '</span>';
    }

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>pesanan saya - toko aksesoris</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- tailwind css cdn -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100">

        <?php include "layout/navbar.php"; ?>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <!-- judul & deskripsi -->
        <h1 class="text-xl font-semibold text-gray-800 mb-1 flex items-center gap-2">
            <i class="fa-solid fa-box-archive text-gray-700"></i>
            pesanan saya
        </h1>
        <p class="text-xs text-gray-500 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-circle-info text-gray-400"></i>
            daftar pesanan yang pernah kamu buat di toko aksesoris.
        </p>

        <!-- pesan (opsional, kalau mau pakai) -->
        <?php if ($pesan === "logout_sukses"): ?>
            <div
                class="alert-auto-hide mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2"
            >
                <i class="fa-solid fa-circle-check"></i>
                <span>anda berhasil logout.</span>
            </div>
        <?php endif; ?>
        <?php if ($pesan === "pesanan_dibatalkan"): ?>
    <div class="alert-auto-hide mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-3 text-sm text-green-800 flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        <span>pesanan berhasil dibatalkan.</span>
    </div>

<?php elseif ($pesan === "status_tidak_bisa_batal"): ?>
    <div class="alert-auto-hide mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>pesanan tidak dapat dibatalkan karena sudah diproses.</span>
    </div>

<?php elseif ($pesan === "lewat_batas_waktu"): ?>
    <div class="alert-auto-hide mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
        <i class="fa-solid fa-clock"></i>
        <span>pesanan tidak dapat dibatalkan karena sudah melewati batas 1 hari.</span>
    </div>

<?php elseif ($pesan === "sudah_upload_bukti"): ?>
    <div class="alert-auto-hide mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>pesanan tidak dapat dibatalkan karena bukti transfer sudah diunggah.</span>
    </div>

<?php elseif ($pesan === "pesanan_tidak_ditemukan"): ?>
    <div class="alert-auto-hide mb-4 rounded-md bg-red-100 border border-red-300 px-4 py-3 text-sm text-red-800 flex items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>pesanan tidak ditemukan atau bukan milik anda.</span>
    </div>
<?php endif; ?>


        <!-- script auto-hide + fade-out untuk semua alert-auto-hide -->
        <script>
            (function () {
                var alerts = document.querySelectorAll('.alert-auto-hide');
                alerts.forEach(function (el) {
                    el.style.transition = 'opacity 0.5s ease';
                    setTimeout(function () {
                        el.style.opacity = '0';
                        setTimeout(function () {
                            el.classList.add('hidden');
                        }, 500);
                    }, 3000);
                });
            })();
        </script>

        <?php if (count($daftar_pesanan) === 0): ?>
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-10 text-center">
                <div class="flex justify-center mb-3">
                    <div class="w-14 h-14 rounded-full bg-gray-900 text-white flex items-center justify-center text-2xl shadow">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mb-3">
                    kamu belum memiliki pesanan.
                </p>
                <a
                    href="toko.php"
                    class="inline-flex items-center px-4 py-2 rounded-md bg-gray-900 text-white text-sm font-medium hover:bg-gray-800"
                >
                    <i class="fa-solid fa-bag-shopping mr-2"></i>
                    mulai belanja sekarang
                </a>
            </div>
        <?php else: ?>

            <!-- tampilan tabel di desktop, card di mobile -->
            <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="hidden md:block">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">
                                    <i class="fa-solid fa-hashtag mr-1 text-gray-400"></i>kode pesanan
                                </th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">
                                    <i class="fa-solid fa-calendar-day mr-1 text-gray-400"></i>tanggal
                                </th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">
                                    <i class="fa-solid fa-sack-dollar mr-1 text-gray-400"></i>total
                                </th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">
                                    <i class="fa-solid fa-clipboard-check mr-1 text-gray-400"></i>status pesanan
                                </th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">
                                    <i class="fa-solid fa-file-invoice-dollar mr-1 text-gray-400"></i>status bukti transfer
                                </th>
                                <th class="px-4 py-2 text-right font-semibold text-gray-600">
                                    <i class="fa-solid fa-gear mr-1 text-gray-400"></i>aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_pesanan as $p): ?>
                                <tr class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-800">
                                        <?php echo htmlspecialchars($p['kode_pesanan']); ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">
                                        <?php echo date('d-m-Y H:i', strtotime($p['dibuat_pada'])); ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-800 font-semibold">
                                        rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="px-4 py-2">
                                        <?php echo badge_status_pesanan($p['status_pesanan']); ?>
                                    </td>
                                    <td class="px-4 py-2">
                                        <?php echo badge_status_bukti($p['status_bukti'] ?? null); ?>
                                    </td>
<td class="px-4 py-2 text-right space-x-1">
    <a
        href="pesanan_detail.php?id=<?php echo (int)$p['id_pesanan']; ?>"
        class="inline-flex items-center px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
    >
        <i class="fa-solid fa-eye mr-1"></i>
        detail
    </a>

    <?php if (boleh_batal_pesanan($p)): ?>
        <button
            type="button"
            onclick="openBatalModal(<?php echo (int)$p['id_pesanan']; ?>)"
            class="inline-flex items-center px-3 py-1 rounded-md bg-red-600 text-white text-[11px] font-medium hover:bg-red-700"
        >
            <i class="fa-solid fa-xmark mr-1"></i>
            batalkan
        </button>
    <?php else: ?>
        <button
            type="button"
            onclick="showBatalWarning()"
            class="inline-flex items-center px-3 py-1 rounded-md bg-gray-300 text-gray-600 text-[11px] font-medium cursor-not-allowed"
        >
            <i class="fa-solid fa-ban mr-1"></i>
            batalkan
        </button>
    <?php endif; ?>
</td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- tampilan mobile (card) -->
                <div class="md:hidden divide-y divide-gray-100">
                    <?php foreach ($daftar_pesanan as $p): ?>
                        <div class="px-4 py-3">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                    <i class="fa-solid fa-hashtag text-gray-400"></i>
                                    <?php echo htmlspecialchars($p['kode_pesanan']); ?>
                                </p>
                                <span class="text-[11px] text-gray-500 flex items-center gap-1">
                                    <i class="fa-solid fa-calendar-day text-gray-400"></i>
                                    <?php echo date('d-m-Y H:i', strtotime($p['dibuat_pada'])); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-700 flex items-center gap-1">
                                <i class="fa-solid fa-sack-dollar text-gray-500"></i>
                                total:
                                <span class="font-semibold">
                                    rp <?php echo number_format($p['total_harga'], 0, ',', '.'); ?>
                                </span>
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <?php echo badge_status_pesanan($p['status_pesanan']); ?>
                                <span class="text-[11px] text-gray-500 flex items-center gap-1">
                                    <i class="fa-solid fa-file-invoice text-gray-400"></i>
                                    bukti:
                                </span>
                                <?php echo badge_status_bukti($p['status_bukti'] ?? null); ?>
                            </div>
<div class="mt-2 space-x-1">
    <a
        href="pesanan_detail.php?id=<?php echo (int)$p['id_pesanan']; ?>"
        class="inline-flex items-center px-3 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium hover:bg-gray-800"
    >
        <i class="fa-solid fa-eye mr-1"></i>
        detail
    </a>

    <?php if (boleh_batal_pesanan($p)): ?>
        <button
            type="button"
            onclick="openBatalModal(<?php echo (int)$p['id_pesanan']; ?>)"
            class="inline-flex items-center px-3 py-1 rounded-md bg-red-600 text-white text-[11px] font-medium hover:bg-red-700"
        >
            <i class="fa-solid fa-xmark mr-1"></i>
            batalkan
        </button>
    <?php else: ?>
<button
    type="button"
    disabled
    class="inline-flex items-center px-3 py-1 rounded-md bg-gray-300 text-gray-600 text-[11px] font-medium cursor-not-allowed"
    title="pesanan tidak dapat dibatalkan"
>
    <i class="fa-solid fa-ban mr-1"></i>
    batalkan
</button>

    <?php endif; ?>
</div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>
        <div
    id="modalBatalPesanan"
    class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50"
    onclick="closeBatalModal()"
>
    <div
        class="bg-white rounded-xl shadow-xl w-80 p-6 text-center"
        onclick="event.stopPropagation()"
    >
        <div class="flex justify-center mb-3">
            <div class="w-14 h-14 rounded-full bg-red-600 text-white flex items-center justify-center text-2xl shadow-md">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-800 mb-1">
            batalkan pesanan
        </h3>

        <p class="text-sm text-gray-600 mb-5">
            pesanan akan dibatalkan dan tidak dapat diproses kembali.
        </p>

        <div class="flex items-center justify-center gap-3">
            <button
                type="button"
                onclick="closeBatalModal()"
                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm hover:bg-gray-100"
            >
                batal
            </button>

            <form method="post" action="pesanan_batal.php">
                <input type="hidden" name="id_pesanan" id="batalPesananId">
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700"
                >
                    ya, batalkan
                </button>
            </form>
        </div>
    </div>
</div>

    </main>


        <?php include "layout/footer.php"; ?>

    </body>
<script>
    function openBatalModal(idPesanan) {
        document.getElementById('batalPesananId').value = idPesanan;
        var modal = document.getElementById('modalBatalPesanan');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeBatalModal() {
        var modal = document.getElementById('modalBatalPesanan');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // tutup modal dengan tombol ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeBatalModal();
        }
    });
</script>


    </html>
