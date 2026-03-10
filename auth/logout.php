<?php
include "../config.php";

// hapus seluruh sesi
session_unset();
session_destroy();

// redirect ke halaman toko
header("Location: ../toko.php?pesan=logout_sukses");
exit;
?>
