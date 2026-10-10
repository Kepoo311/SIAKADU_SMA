# SIAKADU

SIAKADU adalah aplikasi sistem informasi akademik berbasis PHP dan MySQL/MariaDB untuk membantu pengelolaan data dan kegiatan akademik sekolah.

## Fitur

- **Admin:** dashboard, pengelolaan data siswa dan guru, kelas, tahun ajaran, ekstrakurikuler, serta jadwal.
- **Guru:** dashboard, jadwal mengajar, pengelolaan nilai dan absensi, profil, serta fitur wali kelas untuk melihat siswa dan memperbarui status kenaikan kelas.
- **Siswa:** dashboard, profil, kelas, jadwal, nilai/rapor, transkrip, absensi, dan ekstrakurikuler.
- Antarmuka responsif, pencarian tabel, dan notifikasi toast.

Fitur yang tersedia mengikuti data dan akun yang dikonfigurasi pada database.

## Teknologi

- PHP 8.0 atau lebih baru
- MySQL atau MariaDB
- PDO dengan driver `pdo_mysql`
- Apache dengan `mod_rewrite` (aturan URL disediakan melalui `.htaccess`)

## Menjalankan secara lokal

1. Pasang lingkungan PHP dan MySQL/MariaDB, misalnya Laragon.
2. Salin atau buka proyek di folder web server, seperti `C:\laragon\www\SIAKADU`.
3. Buat database bernama `siakadu`.
4. Untuk database baru yang kosong, impor [`DB/schema.sql`](DB/schema.sql).
5. Sesuaikan konfigurasi koneksi di [`DB/koneksi.php`](DB/koneksi.php), atau isi environment variables berikut:

   | Variable | Contoh nilai |
   | --- | --- |
   | `SIAKADU_DB_HOST` | `localhost` |
   | `SIAKADU_DB_PORT` | `3306` |
   | `SIAKADU_DB_NAME` | `siakadu` |
   | `SIAKADU_DB_USER` | `root` |
   | `SIAKADU_DB_PASSWORD` | kosong untuk konfigurasi Laragon default |

6. Jalankan Apache dan MySQL/MariaDB, lalu buka `http://localhost/SIAKADU/`.

`DB/schema.sql` membuat tabel dan role Admin, Guru, dan Siswa, tetapi tidak membuat akun pengguna atau data sekolah. Siapkan akun administrator dengan mengikuti petunjuk di [`DB/README.md`](DB/README.md).

> **Penting:** schema ditujukan untuk database baru/kosong. Jangan impor schema ke database yang sudah berisi data. Untuk memindahkan data dari instalasi lain, buat backup dan ikuti prosedur migrasi/impor yang sesuai.

## Deploy ke Ubuntu Server

Panduan langkah demi langkah, termasuk penjelasan perintah, impor data dari Laragon, konfigurasi Apache, pembaruan aplikasi, dan pemeriksaan setelah VM dinyalakan kembali, tersedia di [`PANDUAN_DEPLOY_UBUNTU.txt`](PANDUAN_DEPLOY_UBUNTU.txt).

Petunjuk schema, pembuatan akun awal, koneksi database, dan migrasi juga tersedia di [`DB/README.md`](DB/README.md).

## Keamanan

- Nilai koneksi default di [`DB/koneksi.php`](DB/koneksi.php) ditujukan untuk pengembangan lokal. Jangan gunakan akun database `root` untuk aplikasi di server.
- Untuk server, gunakan akun database khusus dengan hak minimum dan simpan password di konfigurasi environment/server—jangan commit password ke repository.
- Jangan commit dump database, file `.env` yang berisi rahasia, backup, log, atau data pribadi siswa.
- Jangan unggah file dump ke webroot.
- Untuk penggunaan publik, siapkan HTTPS, firewall, backup rutin, dan konfigurasi server produksi sebelum membuka akses internet.

## Struktur proyek

```text
Admin/                  Halaman dan fitur administrator
DB/                     Koneksi, schema, migrasi, dan petunjuk database
Guru/                   Halaman dan fitur guru
Middleware/             Pemeriksaan akses
Siswa/                  Halaman dan fitur siswa
Views/                  Login, logout, aset UI, dan komponen tampilan
components/             Navbar dan sidebar bersama
public/                 Gambar dan aset publik
index.php               Entry point aplikasi
```

## Catatan

- `.htaccess` memerlukan Apache dengan `mod_rewrite` dan konfigurasi `AllowOverride` yang mengizinkan aturan tersebut.
- File schema dapat dipakai untuk instalasi baru; perubahan pada database yang telah berjalan harus dilakukan melalui migrasi yang sesuai.
- Pastikan konfigurasi web server mencegah akses langsung ke file konfigurasi dan data yang tidak ditujukan untuk publik.
