# Desain Kamera P5M Wajib pada Escort Driver

## Tujuan

Setiap driver wajib mengambil foto P5M baru melalui kamera ketika memilih kendaraan dan sebelum escort dimulai. Foto disimpan secara privat, terikat pada laporan dan driver yang memulai escort, serta dapat dilihat dan diunduh oleh koordinator yang berwenang.

## Ruang Lingkup

- Mengubah foto P5M saat klaim escort dari opsional menjadi wajib.
- Mengganti pemilih berkas pada layar driver dengan kamera langsung di dalam aplikasi.
- Menampilkan preview P5M dan aksi download pada detail escort koordinator.
- Mempertahankan data lama yang belum memiliki foto agar tetap dapat dibaca.
- Tidak mengubah alur close escort, GPS, atau bukti close escort.

## Kondisi Saat Ini

Kolom `p5m_photo_path`, penyimpanan privat, endpoint pembacaan foto, dan hubungan `escort_driver_id` sudah tersedia. Form driver saat ini menggunakan input berkas opsional, sedangkan halaman koordinator hanya memiliki tautan sederhana pada detail escort. Implementasi akan memperkuat alur yang ada tanpa membuat subsistem penyimpanan baru.

## Alur Driver

1. Driver menekan **Pilih & Mulai Escort** pada kendaraan berstatus menunggu.
2. Modal **Ambil Foto P5M** terbuka dan meminta akses ke kamera belakang melalui `navigator.mediaDevices.getUserMedia` dengan preferensi `facingMode: environment`.
3. Tidak ada input galeri atau pemilih berkas pada alur ini.
4. Driver menekan **Ambil Foto**. Frame video diubah menjadi JPEG melalui canvas dan ditampilkan sebagai preview.
5. Driver dapat memilih **Foto Ulang** atau **Gunakan Foto & Mulai Escort**.
6. Tombol mulai escort hanya aktif setelah blob foto tersedia.
7. Foto dikirim sebagai `p5m_photo` bersama request klaim escort. Stream kamera dihentikan ketika modal ditutup, foto diterima, navigasi terjadi, atau komponen dilepas.

Browser tidak dapat membuktikan secara kriptografis bahwa isi gambar belum pernah ada sebelumnya. Jaminan operasional diberikan dengan hanya menyediakan pengambilan frame dari stream kamera aktif, tanpa akses galeri, dan backend mencatat waktu mulai escort ketika upload diterima.

## Validasi dan Konsistensi Data

Backend memvalidasi `p5m_photo` sebagai wajib, berupa gambar JPEG/PNG/WebP, dan maksimal 8 MB. Validasi backend tetap menjadi sumber kebenaran sehingga request tanpa foto atau file tidak valid tidak dapat memulai escort.

Klaim tetap menggunakan transaksi dan penguncian baris yang sudah ada. Urutannya:

1. validasi request;
2. kunci laporan dan periksa site serta status `WAITING`;
3. simpan foto ke storage lokal privat;
4. ubah status menjadi `PROCESS`, isi `escort_driver_id`, `escort_started_at`, dan `p5m_photo_path`;
5. hapus foto yang baru tersimpan apabila pembaruan database gagal.

Dengan urutan tersebut, kegagalan validasi atau penyimpanan tidak boleh mengubah status escort. Hanya driver pertama dengan klaim dan foto valid yang dapat mengambil kendaraan.

## Penamaan dan Akses Foto

Path internal tetap privat dan tidak diekspos langsung. Respons preview menggunakan disposition `inline`. Respons download menggunakan disposition `attachment` dengan nama aman dan informatif:

`P5M-<nomor-laporan>-<nama-driver>.<ekstensi>`

Nama laporan dan driver dinormalisasi agar aman sebagai nama file. Endpoint foto menerima mode preview atau download, tetapi keduanya memakai pemeriksaan akses yang sama:

- site pengguna harus sama dengan site laporan;
- driver hanya dapat mengakses foto jika menjadi driver yang memulai atau menutup escort tersebut;
- koordinator dan developer dapat mengakses foto pada site mereka;
- file dan `p5m_photo_path` harus benar-benar tersedia;
- kegagalan pemeriksaan dikembalikan sebagai 404 agar lokasi file privat tidak bocor.

## Tampilan Koordinator

Kolom **Informasi Escort** tetap menjadi ringkasan utama dan tetap menampilkan driver mulai/close escort. Pada escort yang memiliki `p5m_photo_path`, detail escort menampilkan bagian **Foto P5M — diambil oleh <nama driver mulai escort>** dengan dua aksi:

- **Preview P5M** membuka modal gambar tanpa meninggalkan halaman;
- **Download P5M** mengunduh file dengan nama yang telah ditentukan.

Foto selalu dikaitkan dengan `escort_driver_id`, bukan `escort_closed_by`, karena P5M diambil ketika escort dimulai. Untuk data lama tanpa foto, detail menampilkan keterangan **Foto P5M tidak tersedia** dan halaman tetap berfungsi normal.

Modal preview mempunyai judul, identitas driver, nomor laporan/kendaraan, gambar dengan batas tinggi responsif, tombol download, serta tombol tutup. Tampilan mengikuti komponen modal dan bahasa visual aplikasi yang sudah ada.

## Penanganan Error

- Izin kamera ditolak: tampilkan pesan spesifik dan tombol **Coba Aktifkan Kamera Lagi**; klaim diblokir.
- Kamera atau API tidak tersedia: jelaskan bahwa perangkat berkamera dan konteks aman HTTPS/localhost diperlukan.
- Kamera sedang digunakan aplikasi lain atau gagal dibuka: tampilkan pesan kegagalan dan izinkan percobaan ulang.
- Foto belum diambil: tombol submit nonaktif dan backend tetap menolak request tanpa foto.
- File tidak valid atau terlalu besar: tampilkan error validasi pada modal dan pertahankan kendaraan sebagai `WAITING`.
- Kendaraan lebih dahulu diambil driver lain: tampilkan error konflik yang sudah ada dan hentikan stream kamera.
- Preview gagal dimuat: tampilkan keadaan gagal tanpa membuka akses langsung ke storage.

## Kompatibilitas dan Keamanan

Tidak diperlukan migrasi baru karena `p5m_photo_path` dan relasi driver sudah tersedia. Keharusan foto berlaku pada klaim baru; record lama yang sudah `PROCESS` atau `CLOSED` tanpa foto tidak diubah. File tetap berada pada disk lokal privat dan hanya disajikan melalui controller berotorisasi.

Kamera browser memerlukan HTTPS pada perangkat jaringan; localhost tetap didukung untuk pengembangan. Aplikasi wajib menghentikan semua track media setelah tidak digunakan agar indikator kamera perangkat tidak terus aktif.

## Pengujian

Pengujian backend mencakup:

- klaim tanpa foto ditolak dan status tetap `WAITING`;
- klaim dengan gambar valid menyimpan file dan mengaitkan driver yang memulai;
- file invalid atau terlalu besar ditolak;
- kegagalan/konflik klaim tidak mengganti driver;
- koordinator satu site dapat melakukan preview dan download;
- nama download memuat identitas laporan dan driver secara aman;
- driver terkait dapat mengakses, sedangkan driver lain dan pengguna beda site ditolak;
- record lama tanpa foto tetap dapat ditampilkan dan endpoint mengembalikan 404.

Verifikasi frontend mencakup build produksi serta pemeriksaan manual pada viewport desktop dan mobile untuk izin kamera, pengambilan foto, preview, foto ulang, submit, penutupan stream, modal koordinator, dan download. Seluruh test Laravel dijalankan untuk memastikan alur escort yang sudah ada tidak mengalami regresi.

## Kriteria Penerimaan

- Driver tidak dapat memulai escort tanpa mengambil foto P5M melalui kamera dalam aplikasi.
- Alur driver tidak menawarkan pemilihan foto dari galeri.
- Foto tersimpan privat dan dikaitkan dengan driver yang memulai escort.
- Koordinator melihat identitas driver pengambil foto, dapat melakukan preview, dan dapat mengunduh foto.
- Pengguna tanpa hak atau dari site berbeda tidak dapat mengakses foto.
- Data lama tanpa foto tidak rusak dan tetap dapat dilihat.
