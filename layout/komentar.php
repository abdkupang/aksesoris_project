<?php
// pastikan variabel role tersedia (dari toko.php)
// jika tidak ada, set default
$is_login   = isset($is_login)   ? $is_login   : (isset($_SESSION['id_pengguna']));
$is_pembeli = isset($is_pembeli) ? $is_pembeli : (isset($_SESSION['role']) && $_SESSION['role'] === 'pembeli');
$is_penjual = isset($is_penjual) ? $is_penjual : (isset($_SESSION['role']) && $_SESSION['role'] === 'penjual');

$komentar_sukses = false;
$pesan_komentar = "";

// proses tambah komentar / balas komentar (tanpa redirect, biar tidak butuh file baru)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_komentar']) && isset($koneksi)) {
    $aksi_komentar = $_POST['aksi_komentar'];

    // tambah komentar baru oleh pembeli
    if ($aksi_komentar === 'tambah' && $is_login && $is_pembeli) {
        $nama_pelanggan = $_SESSION['nama_lengkap'] ?? "pembeli";
        $isi_komentar   = trim($_POST['isi_komentar'] ?? "");
        $rating         = trim($_POST['rating'] ?? "");

        if ($isi_komentar === "") {
            $pesan_komentar = "isi komentar tidak boleh kosong.";
        } else {
            $nama_esc = mysqli_real_escape_string($koneksi, $nama_pelanggan);
            $isi_esc  = mysqli_real_escape_string($koneksi, $isi_komentar);

            if ($rating === "" || !is_numeric($rating)) {
                $rating_sql = "null";
            } else {
                $rating_int = max(1, min(5, (int)$rating));
                $rating_sql = (string)$rating_int;
            }

            $sql_insert = "
                insert into komentar_pelanggan (nama_pelanggan, isi_komentar, rating, status_tampil)
                values ('$nama_esc', '$isi_esc', $rating_sql, 'ya')
            ";

if (mysqli_query($koneksi, $sql_insert)) {
    $pesan_komentar  = "terima kasih, komentar kamu berhasil dikirim.";
    $komentar_sukses = true;
} else {
    $pesan_komentar = "gagal menyimpan komentar: " . mysqli_error($koneksi);
}

        }
    }

    // balasan penjual
    if ($aksi_komentar === 'balas' && $is_login && $is_penjual) {
        $id_komentar     = (int)($_POST['id_komentar'] ?? 0);
        $balasan_penjual = trim($_POST['balasan_penjual'] ?? "");

        if ($id_komentar <= 0) {
            $pesan_komentar = "komentar tidak valid.";
        } else {
            $balasan_esc = mysqli_real_escape_string($koneksi, $balasan_penjual);
            if ($balasan_penjual === "") {
                // kosongkan balasan
                $sql_update = "
                    update komentar_pelanggan
                    set balasan_penjual = null,
                        dijawab_pada = null
                    where id_komentar = $id_komentar
                ";
            } else {
                $sql_update = "
                    update komentar_pelanggan
                    set balasan_penjual = '$balasan_esc',
                        dijawab_pada = now()
                    where id_komentar = $id_komentar
                ";
            }

            if (mysqli_query($koneksi, $sql_update)) {
                $pesan_komentar = "balasan penjual berhasil disimpan.";
            } else {
                $pesan_komentar = "gagal menyimpan balasan: " . mysqli_error($koneksi);
            }
        }
    }
}

// mengambil komentar pelanggan terbaru
$komentar_data = [];
$ada_komentar  = false;

if (isset($koneksi)) {
    $sql_komentar = "
        select 
            id_komentar,
            nama_pelanggan,
            isi_komentar,
            balasan_penjual,
            rating,
            status_tampil,
            dibuat_pada,
            dijawab_pada
        from komentar_pelanggan
        where status_tampil = 'ya'
        order by dibuat_pada desc
        limit 6
    ";

    $query_komentar = @mysqli_query($koneksi, $sql_komentar);

    if ($query_komentar && mysqli_num_rows($query_komentar) > 0) {
        $ada_komentar = true;
        while ($row_k = mysqli_fetch_assoc($query_komentar)) {
            $komentar_data[] = $row_k;
        }
    }
}
?>

<section id="komentar" class="mt-10 mb-10">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-6">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-comments text-gray-700"></i>
                    komentar pelanggan
                </h3>
                <p class="text-sm text-gray-500 flex items-center gap-2">
                    <i class="fa-solid fa-quote-left text-gray-400"></i>
                    beberapa ulasan dan pengalaman dari pelanggan yang sudah berbelanja di toko aksesoris.
                </p>
            </div>

            <?php if ($is_login && $is_pembeli): ?>
                <div class="mt-3 md:mt-0">
                    <button
                        type="button"
                        onclick="openKomentarModal()"
                        class="inline-flex items-center px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800"
                    >
                        <i class="fa-solid fa-pen-to-square mr-2"></i>
                        tulis komentar
                    </button>
                </div>
            <?php endif; ?>
        </div>

<!-- pesan setelah aksi komentar -->
<?php if (!empty($pesan_komentar)): ?>
    <div
        id="alertKomentar"
        class="mt-3 mb-4 rounded-md bg-blue-50 border border-blue-200 px-4 py-2 text-xs text-blue-800 flex items-center gap-2"
    >
        <i class="fa-solid fa-circle-info"></i>
        <span><?php echo htmlspecialchars($pesan_komentar); ?></span>
    </div>

<script>
    (function () {
        // jika komentar sukses, langsung scroll ke section komentar
        <?php if (!empty($komentar_sukses) && $komentar_sukses): ?>
        window.location.hash = 'komentar';
        <?php endif; ?>

        // ambil elemen pesan
        var alertEl = document.getElementById('alertKomentar');

        if (alertEl) {
            // tambahkan transition CSS
            alertEl.style.transition = "opacity 0.5s ease";

            // setelah 3 detik → fade-out
            setTimeout(function () {
                alertEl.style.opacity = "0";

                // setelah fade-out selesai → sembunyikan
                setTimeout(function () {
                    alertEl.classList.add('hidden');
                }, 500);

            }, 3000);
        }
    })();
</script>

<?php endif; ?>


        <?php if ($ada_komentar): ?>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($komentar_data as $k): ?>
                    <div class="border border-gray-200 rounded-lg px-4 py-3 text-sm bg-gray-50 flex flex-col justify-between">
                        <div>
                            <!-- nama + icon user -->
                            <p class="font-semibold text-gray-800 flex items-center gap-2">
                                <i class="fa-solid fa-user-circle text-gray-500"></i>
                                <?php echo htmlspecialchars($k['nama_pelanggan']); ?>
                            </p>

                            <!-- rating bintang -->
                            <?php if (!empty($k['rating'])): ?>
                                <?php $rating_int = (int)$k['rating']; ?>
                                <div class="flex items-center text-xs mt-1 mb-1">
                                    <div class="flex items-center gap-[2px] text-yellow-400">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $rating_int): ?>
                                                <i class="fa-solid fa-star"></i>
                                            <?php else: ?>
                                                <i class="fa-regular fa-star text-gray-300"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="ml-2 text-[11px] text-gray-500">
                                        <?php echo $rating_int; ?>/5
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- isi komentar -->
                            <p class="text-xs text-gray-600 mt-1">
                                <i class="fa-solid fa-quote-left text-gray-300 mr-1"></i>
                                <?php echo nl2br(htmlspecialchars($k['isi_komentar'])); ?>
                            </p>

                            <!-- tanggal komentar -->
                            <?php if (!empty($k['dibuat_pada'])): ?>
                                <p class="mt-2 text-[10px] text-gray-400 flex items-center gap-1">
                                    <i class="fa-solid fa-clock"></i>
                                    <?php echo date('d-m-Y H:i', strtotime($k['dibuat_pada'])); ?>
                                </p>
                            <?php endif; ?>

                            <!-- balasan penjual -->
                            <?php if (!empty($k['balasan_penjual'])): ?>
                                <div class="mt-3 border-l-2 border-gray-300 pl-3">
                                    <p class="text-[11px] font-semibold text-gray-800 mb-1 flex items-center gap-1">
                                        <i class="fa-solid fa-reply text-gray-600"></i>
                                        balasan penjual:
                                    </p>
                                    <p class="text-[11px] text-gray-700">
                                        <?php echo nl2br(htmlspecialchars($k['balasan_penjual'])); ?>
                                    </p>
                                    <?php if (!empty($k['dijawab_pada'])): ?>
                                        <p class="mt-1 text-[10px] text-gray-400 flex items-center gap-1">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                            dibalas pada: <?php echo date('d-m-Y H:i', strtotime($k['dijawab_pada'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- tombol balas penjual -->
                        <?php if ($is_login && $is_penjual): ?>
                            <div class="mt-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-1 rounded-md border border-gray-300 text-[11px] text-gray-700 hover:bg-gray-100"
                                    data-id="<?php echo (int)$k['id_komentar']; ?>"
                                    data-nama="<?php echo htmlspecialchars($k['nama_pelanggan'], ENT_QUOTES); ?>"
                                    data-isi="<?php echo htmlspecialchars($k['isi_komentar'], ENT_QUOTES); ?>"
                                    data-balasan="<?php echo htmlspecialchars($k['balasan_penjual'] ?? '', ENT_QUOTES); ?>"
                                    onclick="openBalasanModal(this)"
                                >
                                    <i class="fa-solid fa-reply mr-1"></i>
                                    <?php echo !empty($k['balasan_penjual']) ? 'edit balasan' : 'balas komentar'; ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="mt-4 text-xs text-gray-500 flex items-center gap-2">
                <i class="fa-regular fa-comment-dots"></i>
                belum ada komentar dari pelanggan.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- overlay untuk modal komentar / balasan -->
<div id="overlayKomentar" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-40"></div>

<!-- modal tambah komentar (pembeli) -->
<div id="modalKomentar" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square"></i>
                tulis komentar
            </h2>
            <button type="button" onclick="closeKomentarModal()" class="text-gray-500 text-lg">&times;</button>
        </div>
        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi_komentar" value="tambah">
            <!-- rating dikirim di sini -->
            <input type="hidden" name="rating" id="rating_input" value="">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    nama
                </label>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-900 text-white text-xs">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input
                        type="text"
                        value="<?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'pembeli'); ?>"
                        class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm bg-gray-100 text-gray-700"
                        readonly
                    >
                </div>
            </div>

            <!-- rating bintang -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-star text-yellow-400"></i>
                    rating (opsional)
                </label>
                <div id="ratingStars" class="flex items-center gap-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button
                            type="button"
                            class="star-btn text-gray-300 text-lg"
                            data-value="<?php echo $i; ?>"
                        >
                            <i class="fa-solid fa-star"></i>
                        </button>
                    <?php endfor; ?>
                    <span id="ratingLabel" class="ml-2 text-[11px] text-gray-500">
                        pilih rating (opsional)
                    </span>
                </div>
            </div>

            <!-- komentar -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    komentar <span class="text-red-500">*</span>
                </label>
                <textarea
                    name="isi_komentar"
                    rows="4"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    placeholder="tuliskan pengalaman kamu berbelanja di toko ini..."
                    required
                ></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeKomentarModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50"
                >
                    batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800"
                >
                    kirim komentar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- modal balasan penjual -->
<div id="modalBalasan" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-reply"></i>
                balasan penjual
            </h2>
            <button type="button" onclick="closeBalasanModal()" class="text-gray-500 text-lg">&times;</button>
        </div>
        <form method="post" class="px-4 py-4 space-y-3">
            <input type="hidden" name="aksi_komentar" value="balas">
            <input type="hidden" name="id_komentar" id="balasan_id_komentar">

            <div>
                <p class="text-[11px] text-gray-500 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-user"></i>
                    komentar dari:
                    <span id="balasan_nama_pelanggan" class="font-semibold text-gray-800"></span>
                </p>
                <div class="border border-gray-200 rounded-md px-3 py-2 bg-gray-50">
                    <p id="balasan_isi_komentar" class="text-[11px] text-gray-700 whitespace-pre-line"></p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                    <i class="fa-solid fa-reply-all"></i>
                    balasan penjual
                </label>
                <textarea
                    name="balasan_penjual"
                    id="balasan_teks"
                    rows="4"
                    class="w-full px-3 py-2 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-1 focus:ring-gray-500"
                    placeholder="tuliskan balasan atau tanggapan dari penjual di sini..."
                ></textarea>
                <p class="mt-1 text-[10px] text-gray-400 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    kosongkan dan simpan jika ingin menghapus balasan.
                </p>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeBalasanModal()"
                    class="px-4 py-2 rounded-md border border-gray-300 text-xs text-gray-700 hover:bg-gray-50"
                >
                    batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-gray-900 text-white text-xs font-medium hover:bg-gray-800"
                >
                    simpan balasan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const overlayKomentar = document.getElementById('overlayKomentar');
    const modalKomentar   = document.getElementById('modalKomentar');
    const modalBalasan    = document.getElementById('modalBalasan');

    function showOverlayKomentar() {
        overlayKomentar.classList.remove('hidden');
        overlayKomentar.classList.add('flex');
    }
    function hideOverlayKomentar() {
        overlayKomentar.classList.remove('flex');
        overlayKomentar.classList.add('hidden');
    }

    // modal tulis komentar
    function openKomentarModal() {
        modalKomentar.classList.remove('hidden');
        modalKomentar.classList.add('flex');
        showOverlayKomentar();
    }
    function closeKomentarModal() {
        modalKomentar.classList.remove('flex');
        modalKomentar.classList.add('hidden');
        hideOverlayKomentar();
    }

    // modal balasan penjual
    function openBalasanModal(btn) {
        const id       = btn.dataset.id;
        const nama     = btn.dataset.nama || '';
        const isi      = btn.dataset.isi || '';
        const balasan  = btn.dataset.balasan || '';

        document.getElementById('balasan_id_komentar').value        = id;
        document.getElementById('balasan_nama_pelanggan').innerText = nama;
        document.getElementById('balasan_isi_komentar').innerText   = isi;
        document.getElementById('balasan_teks').value               = balasan;

        modalBalasan.classList.remove('hidden');
        modalBalasan.classList.add('flex');
        showOverlayKomentar();
    }
    function closeBalasanModal() {
        modalBalasan.classList.remove('flex');
        modalBalasan.classList.add('hidden');
        hideOverlayKomentar();
    }

    // ⭐ logika rating bintang
    const ratingInput  = document.getElementById('rating_input');
    const ratingStars  = document.querySelectorAll('#ratingStars .star-btn');
    const ratingLabel  = document.getElementById('ratingLabel');

    if (ratingStars && ratingStars.length > 0) {
        ratingStars.forEach(function(btn) {
            btn.addEventListener('click', function () {
                const val = parseInt(this.dataset.value || "0");
                if (!ratingInput) return;

                ratingInput.value = val;

                ratingStars.forEach(function (b) {
                    const v = parseInt(b.dataset.value || "0");
                    if (v <= val) {
                        b.classList.remove('text-gray-300');
                        b.classList.add('text-yellow-400');
                    } else {
                        b.classList.add('text-gray-300');
                        b.classList.remove('text-yellow-400');
                    }
                });

                let text = '';
                if (val === 5) text = 'sangat puas';
                else if (val === 4) text = 'puas';
                else if (val === 3) text = 'cukup';
                else if (val === 2) text = 'kurang';
                else if (val === 1) text = 'tidak puas';

                if (ratingLabel) {
                    if (val > 0) {
                        ratingLabel.textContent = 'rating: ' + val + ' - ' + text;
                    } else {
                        ratingLabel.textContent = 'pilih rating (opsional)';
                    }
                }
            });
        });
    }
</script>
