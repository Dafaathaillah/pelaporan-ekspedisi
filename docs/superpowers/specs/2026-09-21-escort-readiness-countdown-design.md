# Escort Readiness Countdown Design

## Goal

Memberlakukan masa tunggu tujuh jam sejak check-in kedatangan untuk setiap unit ekspedisi sebelum unit dapat mulai di-escort, serta menampilkan countdown yang konsisten kepada Driver, Koordinator, Admin Purchasing, dan Developer.

## Scope

- Aturan tujuh jam hanya berlaku untuk laporan kedatangan ekspedisi biasa.
- Laporan yang berasal dari Ijin Lintas internal tidak menggunakan countdown dan tetap mengikuti validasi tanggal escort yang sudah ada.
- Countdown berlaku per unit dan dihitung dari `arrived_at` pada server.
- Tidak ada mekanisme override atau bypass sebelum unit ready.

## Domain Rule

`ready_escort_at` merupakan nilai turunan dari `arrived_at + 7 jam`. Nilai ini tidak disimpan sebagai kolom baru agar tidak menduplikasi sumber data dan agar laporan lama otomatis memperoleh status readiness.

Model `ExpeditionReport` menyediakan atribut turunan berikut:

- `ready_escort_at`: timestamp ISO 8601 untuk unit ekspedisi, `null` untuk Ijin Lintas internal.
- `is_ready_for_escort`: `true` jika laporan internal atau waktu server telah mencapai `ready_escort_at`; selain itu `false`.

## Server Enforcement

Semua jalur mulai escort wajib memvalidasi readiness di dalam transaksi setelah row dikunci:

- Claim satu unit ditolak apabila unit ekspedisi belum mencapai tujuh jam.
- Claim batch ditolak apabila satu atau lebih unit ekspedisi belum ready.
- Pesan validasi menjelaskan bahwa unit belum ready dan menyertakan waktu ready dalam WITA.
- Validasi jadwal untuk Ijin Lintas internal tetap berjalan seperti sekarang.

Server menjadi otoritas utama. Status tombol pada browser hanya membantu pengguna dan tidak menggantikan validasi backend.

## Data Delivery

Endpoint daftar escort Driver dan seluruh daftar monitoring kedatangan mengirim `ready_escort_at` dan `is_ready_for_escort` bersama setiap laporan. Karena Admin Purchasing, Koordinator, dan Developer memakai sumber monitoring yang sama sesuai role dan site, format informasi readiness harus identik.

Timestamp memakai ISO 8601 agar browser dapat menghitung countdown secara deterministik. Waktu tampilan tetap diberi label WITA sesuai bahasa antarmuka yang ada.

## User Interface

### Driver

Pada kartu unit berstatus `WAITING`:

- Sebelum ready: tampil badge peringatan “Ready Escort dalam HH:MM:SS”, waktu ready, dan action pilih unit dalam keadaan disabled.
- Setelah countdown nol: badge berubah menjadi “Ready Escort” dan action aktif otomatis tanpa reload.
- Pemilihan batch hanya dapat memuat unit yang sudah ready.

### Koordinator, Admin Purchasing, dan Developer

Pada setiap baris/kartu Monitoring Kedatangan:

- Unit yang belum ready menampilkan “Ready Escort dalam HH:MM:SS” dan waktu ready.
- Unit yang telah ready tetapi belum dimulai menampilkan “Ready Escort”.
- Unit `PROCESS` dan `CLOSED` tetap mengutamakan status escort yang sekarang; waktu ready dapat tetap tersedia dalam detail tetapi countdown tidak berjalan lagi.
- Ijin Lintas internal tidak menampilkan countdown tujuh jam.

Countdown diperbarui setiap detik oleh komponen bersama agar format dan perilaku konsisten di semua halaman. Interval dibersihkan saat komponen dilepas.

## Realtime and Boundary Behavior

- Browser menggunakan waktu saat ini untuk animasi countdown, tetapi backend tetap memutuskan apakah action sah.
- Saat countdown mencapai nol, tampilan beralih ke ready secara lokal tanpa refresh.
- Jika waktu browser menyimpang dan action ditolak server, pesan validasi server ditampilkan dan data dapat dimuat ulang melalui mekanisme Inertia yang sudah ada.
- Nilai `arrived_at` yang tidak tersedia diperlakukan belum ready untuk laporan ekspedisi, walaupun data normal selalu memilikinya.

## Testing

- Unit test/model test membuktikan timestamp ready adalah tepat tujuh jam setelah `arrived_at` dan internal dikecualikan.
- Feature test membuktikan single claim sebelum tujuh jam ditolak dan setelah tujuh jam diterima.
- Feature test membuktikan batch campuran ditolak secara atomik jika ada satu unit belum ready.
- Feature test membuktikan Ijin Lintas internal tidak terkena aturan tujuh jam, tetapi tetap terkena aturan tanggal.
- Inertia response test membuktikan properti readiness tersedia bagi Driver dan halaman monitoring role terkait.
- Frontend build dijalankan untuk memverifikasi komponen Vue dan integrasinya.

## Non-Goals

- Tidak ada notifikasi push saat countdown selesai.
- Tidak ada konfigurasi durasi per site atau per perusahaan ekspedisi.
- Tidak ada override manual.
- Tidak mengubah alur close escort, P5M, ataupun bukti lokasi.
