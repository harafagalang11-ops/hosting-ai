# Hosting AI

Aplikasi web berbasis Laravel untuk membantu pemantauan website/hosting, pencatatan riwayat pemeriksaan, analisis kondisi monitoring melalui chatbot, dan pengiriman notifikasi.

> **Status proyek:** Dalam tahap pengembangan. Integrasi dengan panel/API hosting pihak ketiga belum diklaim tersedia; konfigurasi dan akses layanan eksternal perlu disesuaikan dengan lingkungan pengguna.

## Fitur

- **Dashboard monitoring** untuk melihat ringkasan kondisi website yang terdaftar.
- **Pemeriksaan website terjadwal** dan pencatatan hasil pemeriksaan ke database.
- **Riwayat monitoring** untuk meninjau hasil pemeriksaan sebelumnya.
- **Chatbot analisis** untuk membantu menjelaskan hasil monitoring dan pertanyaan seputar hosting berdasarkan data aplikasi.
- **Notifikasi Telegram** untuk menyampaikan perubahan status monitoring, termasuk mekanisme percobaan ulang ketika pengiriman gagal.
- **Laporan harian** yang dijadwalkan pada pukul 20.30 WIB.
- **Integrasi Hermes** melalui perintah lokal, jika Hermes Agent telah terpasang dan dikonfigurasi pada server aplikasi.
- **Pengelolaan website, profil, dan preferensi tampilan** melalui antarmuka aplikasi.

## Teknologi

- PHP 8.3 atau lebih baru
- Laravel 13
- SQLite (konfigurasi awal) atau database lain yang didukung Laravel dan telah dikonfigurasi
- Composer
- Node.js dan npm
- Vite

## Persyaratan

Pastikan perangkat pengembangan telah memiliki:

- PHP >= 8.3 beserta ekstensi yang dibutuhkan Laravel
- Composer
- Node.js dan npm
- Database yang sesuai dengan konfigurasi aplikasi

## Instalasi lokal

1. Clone repository:

   ```bash
   git clone https://github.com/harafagalang11-ops/hosting-ai.git
   cd hosting-ai
   ```

2. Instal dependensi PHP:

   ```bash
   composer install
   ```

3. Buat file environment:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Siapkan database. Untuk konfigurasi SQLite bawaan:

   ```bash
   touch database/database.sqlite
   ```

   Pastikan bagian database di file `.env` menggunakan:

   ```env
   DB_CONNECTION=sqlite
   ```

   Jalankan migrasi:

   ```bash
   php artisan migrate
   ```

5. Instal dependensi frontend dan build aset:

   ```bash
   npm install --ignore-scripts
   npm run build
   ```

6. Jalankan aplikasi:

   ```bash
   php artisan serve
   ```

   Buka alamat lokal yang ditampilkan oleh Artisan (umumnya http://127.0.0.1:8000).

## Menjalankan scheduler

Aplikasi menggunakan Laravel Scheduler untuk menjalankan pekerjaan monitoring, laporan harian, dan percobaan ulang notifikasi Telegram.

Jadwal yang didefinisikan:

| Pekerjaan | Jadwal |
|---|---|
| Refresh monitoring | Setiap 3 jam |
| Laporan harian | Setiap hari pukul 20.30 WIB |
| Percobaan ulang notifikasi Telegram | Setiap 5 menit |

Untuk pengujian lokal, jalankan scheduler:

```bash
php artisan schedule:work
```

Pada server Linux, Laravel Scheduler perlu dipanggil secara berkala melalui cron sesuai dokumentasi Laravel.

## Konfigurasi layanan opsional

Atur nilai yang diperlukan melalui file `.env`. Jangan menaruh token, password, API key, atau kredensial pribadi ke dalam source code maupun repository.

- **Telegram:** isi konfigurasi bot token dan chat ID sesuai variabel yang dibaca oleh `config/services.php`.
- **Hermes Agent:** Hermes harus terpasang pada mesin yang menjalankan aplikasi. Atur variabel `HERMES_EXECUTABLE` pada `.env` jika executable tidak tersedia melalui `PATH` (contoh: `HERMES_EXECUTABLE=/path/ke/hermes`).
- **Direktori laporan dan status monitoring:** sesuaikan konfigurasi pada `config/hosting.php` dengan lokasi penyimpanan yang tersedia.

Jangan gunakan kredensial produksi pada lingkungan pengembangan. Gunakan akun dan layanan uji sampai konfigurasi operasional telah diverifikasi.

## Pengujian

Jalankan automated test dengan:

```bash
php artisan test
```

## Keamanan

- File `.env`, database lokal, log, cache, dan file backup diabaikan oleh Git melalui `.gitignore`.
- Gunakan `.env.example` sebagai template konfigurasi tanpa memasukkan kredensial asli.
- Sebelum deployment atau publikasi, periksa kembali source code, riwayat commit, screenshot, dan konfigurasi agar tidak memuat rahasia atau data operasional.
- Aplikasi ini merupakan alat bantu monitoring. Status HTTP saja tidak membuktikan seluruh komponen aplikasi, database, atau layanan hosting dalam keadaan sehat.

## Lisensi

Lisensi proyek belum ditetapkan secara khusus. Hubungi pemilik repository sebelum menggunakan atau mendistribusikan ulang kode ini.


## Keamanan Pembacaan Laporan Obsidian

Chatbot menerapkan beberapa lapisan perlindungan ketika membaca laporan Markdown dari Obsidian:

- Hanya membaca file dari direktori laporan yang diizinkan.
- Menolak symbolic link dan file yang bukan file reguler.
- Memeriksa ukuran file maksimum 1 MB.
- Memverifikasi identitas file melalui device dan inode sebelum membaca.
- Membatasi pembacaan hingga 32.000 byte atau maksimal 8.000 karakter.
- Membatasi jumlah laporan yang diproses dalam satu permintaan hingga 7 file.
- Memperlakukan seluruh isi laporan sebagai data tidak tepercaya, bukan instruksi yang boleh diikuti AI.
- Mengabaikan instruksi di dalam laporan yang meminta pengungkapan kredensial, eksekusi perintah, akses server, atau perubahan konfigurasi.

Perlindungan prompt injection merupakan lapisan mitigasi tambahan dan bukan jaminan mutlak terhadap seluruh serangan.
