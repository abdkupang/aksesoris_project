# 🛍️ Toko Aksesoris Online

Aplikasi web e-commerce untuk penjualan aksesoris secara online. Dibangun menggunakan PHP native dengan database MySQL dan berjalan di atas **Laragon**.

## ✨ Fitur Utama

### 👤 Pembeli
- **Registrasi & Login** — dengan verifikasi email
- **Katalog Produk** — lihat produk, filter/pencarian, detail produk dengan galeri gambar
- **Keranjang Belanja** — tambah, ubah jumlah, hapus item
- **Checkout & Pesanan** — buat pesanan dengan data pengiriman
- **Upload Bukti Transfer** — unggah bukti pembayaran
- **Riwayat Pesanan** — pantau status pesanan secara real-time
- **Komentar & Rating** — berikan ulasan pada produk

### 🏪 Penjual (Dashboard Admin)
- **Dashboard** — ringkasan statistik toko
- **Kelola Produk** — CRUD produk dengan upload gambar
- **Kelola Kategori** — atur kategori produk
- **Kelola Pesanan** — proses & update status pesanan
- **Verifikasi Bukti Transfer** — setujui atau tolak pembayaran
- **Data Pelanggan** — lihat daftar pelanggan & riwayat transaksi
- **Pengaturan Akun** — ubah profil & password

## 🛠️ Tech Stack

| Komponen   | Teknologi                       |
| ---------- | ------------------------------- |
| Backend    | PHP 8.x (Native)               |
| Database   | MySQL 8.x                      |
| Frontend   | HTML, CSS, JavaScript           |
| Email      | PHPMailer                        |
| Server     | Laragon (Apache)               |

## 📁 Struktur Proyek

```
aksesoris_project/
├── auth/                    # Autentikasi (login, daftar, verifikasi email, dll.)
│   ├── login.php
│   ├── daftar.php
│   ├── logout.php
│   ├── verifikasi_email.php
│   ├── lupa_password.php
│   ├── reset_password.php
│   └── kirim_ulang_aktivasi.php
├── layout/                  # Komponen layout (navbar, footer, banner, dll.)
│   ├── navbar.php
│   ├── navbar_menu.php
│   ├── footer.php
│   ├── banner.php
│   ├── contact.php
│   ├── informasi.php
│   └── komentar.php
├── penjual/                 # Dashboard penjual (admin)
│   ├── index.php            # Dashboard utama
│   ├── produk.php           # Kelola produk
│   ├── kategori.php         # Kelola kategori
│   ├── pesanan.php          # Kelola pesanan
│   ├── bukti_transfer.php   # Verifikasi pembayaran
│   ├── pelanggan.php        # Data pelanggan
│   ├── akun.php             # Pengaturan akun penjual
│   └── sidebar.php          # Sidebar navigasi
├── uploads/                 # File upload (gambar produk & bukti transfer)
│   ├── produk/
│   └── bukti/
├── vendor/                  # Dependensi Composer (PHPMailer)
├── index.php                # Halaman utama (redirect)
├── toko.php                 # Halaman katalog produk
├── detail_produk.php        # Detail produk
├── keranjang.php            # Keranjang belanja
├── tambah_keranjang.php     # Proses tambah ke keranjang
├── checkout.php             # Proses checkout
├── pesanan_saya.php         # Riwayat pesanan pembeli
├── pesanan_detail.php       # Detail pesanan
├── pesanan_batal.php        # Pembatalan pesanan
├── akun.php                 # Pengaturan akun pembeli
├── config.php               # Konfigurasi database
├── composer.json            # Dependensi PHP
└── db_penjualan_aksesoris.sql  # File SQL database
```

## 🗄️ Struktur Database

Database `db_penjualan_aksesoris` terdiri dari tabel-tabel berikut:

| Tabel                | Deskripsi                                     |
| -------------------- | --------------------------------------------- |
| `pengguna`           | Data pengguna (pembeli & penjual)             |
| `email_tokens`       | Token untuk verifikasi email & reset password |
| `kategori`           | Kategori produk                               |
| `produk`             | Data produk                                   |
| `keranjang`          | Keranjang belanja per pengguna                |
| `keranjang_item`     | Item dalam keranjang                          |
| `pesanan`            | Data pesanan                                  |
| `pesanan_item`       | Item dalam pesanan                            |
| `bukti_transfer`     | Bukti pembayaran transfer                     |
| `komentar_pelanggan` | Komentar & rating pelanggan                   |
| `log_aktivitas`      | Log aktivitas pengguna                        |

## 🚀 Instalasi & Setup

### Prasyarat
- [Laragon](https://laragon.org/) (atau XAMPP/WAMP)
- PHP 8.x
- MySQL 8.x
- Composer

### Langkah Instalasi

1. **Clone repository**
   ```bash
   git clone https://github.com/abdkupang/aksesoris_project.git
   ```

2. **Pindahkan ke direktori web server**
   ```bash
   # Untuk Laragon:
   mv aksesoris_project C:\laragon\www\
   ```

3. **Import database**
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`)
   - Buat database baru: `db_penjualan_aksesoris`
   - Import file `db_penjualan_aksesoris.sql`

4. **Konfigurasi database**
   - Edit file `config.php` sesuai pengaturan database Anda:
     ```php
     $host = "localhost";
     $user = "root";
     $pass = "";  // sesuaikan password
     $db   = "db_penjualan_aksesoris";
     ```

5. **Install dependensi PHP**
   ```bash
   composer install
   ```

6. **Akses aplikasi**
   - Buka browser: `http://localhost/aksesoris_project/`

## 📸 Alur Aplikasi

### Alur Pembeli
1. Registrasi akun → Verifikasi email → Login
2. Lihat katalog produk → Tambah ke keranjang
3. Checkout → Isi data pengiriman → Buat pesanan
4. Upload bukti transfer → Tunggu konfirmasi penjual
5. Pantau status pesanan di "Pesanan Saya"

### Alur Penjual
1. Login sebagai penjual → Masuk ke dashboard
2. Kelola produk (tambah/edit/hapus)
3. Terima pesanan → Verifikasi bukti transfer
4. Proses pesanan → Kirim barang → Selesaikan pesanan

## 👤 Akun Default

| Role    | Email            | Password |
| ------- | ---------------- | -------- |
| Penjual | thesa@gmail.com  | *(sesuai database)* |
| Pembeli | tea@gmail.com    | *(sesuai database)* |

## 📄 Lisensi

Proyek ini dibuat untuk keperluan pembelajaran dan pengembangan.

---

**Dibuat oleh** © 2026 Abdillah Mukhair Ismail
