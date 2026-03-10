# 🤝 Panduan Kontribusi

Terima kasih telah tertarik untuk berkontribusi pada proyek **Toko Aksesoris Online**! Berikut panduan untuk membantu Anda memulai.

## 📋 Cara Berkontribusi

### 1. Fork & Clone

```bash
# Fork repository ini, lalu clone fork Anda
git clone https://github.com/<username-anda>/aksesoris_project.git
cd aksesoris_project
```

### 2. Buat Branch Baru

```bash
git checkout -b fitur/nama-fitur
# atau
git checkout -b perbaikan/deskripsi-bug
```

### 3. Lakukan Perubahan

- Pastikan kode mengikuti konvensi yang ada di proyek
- Gunakan bahasa Indonesia untuk penamaan variabel dan komentar
- Tes perubahan Anda di environment lokal (Laragon/XAMPP)

### 4. Commit & Push

```bash
git add .
git commit -m "feat: menambahkan fitur baru"
git push origin fitur/nama-fitur
```

### 5. Buat Pull Request

- Buka repository asli di GitHub
- Klik **"New Pull Request"**
- Jelaskan perubahan yang Anda buat

## 📝 Konvensi Commit

Gunakan format berikut untuk pesan commit:

| Prefix     | Deskripsi                        |
| ---------- | -------------------------------- |
| `feat:`    | Fitur baru                       |
| `fix:`     | Perbaikan bug                    |
| `docs:`    | Perubahan dokumentasi            |
| `style:`   | Perubahan tampilan/CSS           |
| `refactor:`| Refaktor kode                    |
| `test:`    | Penambahan/perbaikan tes         |
| `chore:`   | Tugas maintenance lainnya        |

## 🐛 Melaporkan Bug

Jika menemukan bug, silakan buat **Issue** dengan informasi:

1. Deskripsi singkat bug
2. Langkah untuk mereproduksi
3. Perilaku yang diharapkan vs yang terjadi
4. Screenshot (jika ada)
5. Environment (browser, PHP version, OS)

## 💡 Mengusulkan Fitur

Untuk mengusulkan fitur baru:

1. Cek dulu apakah fitur sudah diusulkan di **Issues**
2. Buat Issue baru dengan label `enhancement`
3. Jelaskan fitur yang diinginkan dan alasannya

## ⚙️ Setup Development

```bash
# 1. Clone repository
git clone https://github.com/abdkupang/aksesoris_project.git

# 2. Letakkan di folder web server (Laragon)
# C:\laragon\www\aksesoris_project

# 3. Import database
# Buka phpMyAdmin → Buat database → Import db_penjualan_aksesoris.sql

# 4. Install dependensi
composer install

# 5. Sesuaikan config.php
```

## 📜 Lisensi

Dengan berkontribusi, Anda setuju bahwa kontribusi Anda akan dilisensikan di bawah [MIT License](LICENSE).

---

Terima kasih atas kontribusinya! 🎉
