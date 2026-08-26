# Dokumentasi Sistem Pelaporan Ekspedisi & Pengajuan Dokumen Vendor

**PT Putra Perkasa Abadi — Site Borneo Indobara (BIB)**  
**Kolaborasi SCM · FAW · ICT**  
Versi dokumentasi: 1.3 — 26 Agustus 2026

---

## 1. Ringkasan Eksekutif

Sistem ini merupakan portal operasional terintegrasi untuk:

- mencatat kedatangan kendaraan ekspedisi di Underpass;
- mengelola pengajuan Visitor, Waskat, Permit Vendor Non Simper, dan Ijin Lintas;
- memeriksa dokumen per orang atau per unit;
- mengatur masa berlaku, release, revisi, penolakan, penutupan, dan pengajuan ulang;
- menyusun Planning Escort satu bulan ke depan dengan drag-and-drop;
- menjalankan escort kendaraan ekspedisi oleh Driver Escort;
- menyimpan bukti foto dan koordinat maksimal ±100 meter serta nilai presisi aktual;
- menampilkan pembaruan dan notifikasi secara realtime melalui Laravel Reverb;
- mengelola pengguna, hak akses, serta master vendor.
- mengelola Request Ekspedisi, nomor PR/PO, dan relasinya dengan Izin Lintas;
- menyinkronkan tanggal planning Izin Lintas antara Ekspedisi, Vendor, dan Koordinator.

Antarmuka menggunakan identitas merah-putih lembut, responsif untuk desktop, tablet, dan mobile. Pada mobile, navigasi utama berada di bawah dan menu tambahan tersedia melalui **Lainnya**.

## 2. Ruang Lingkup Modul

| Modul | Fungsi utama | Pengguna utama |
|---|---|---|
| Autentikasi | Login username, logout, pembatasan percobaan login | Semua role |
| Ekspedisi | Input kedatangan, tensi, foto, GPS, riwayat per akun | Ekspedisi |
| Monitoring Ekspedisi | Dashboard, chart interaktif, filter, DataTable, CSV, informasi escort | Koordinator/Developer |
| Pengajuan Vendor | Visitor, Waskat, Permit Non Simper, Ijin Lintas, upload PDF per subjek | Vendor |
| Review Dokumen | Verifikasi dokumen, catatan, status, unduh per file/semua file | Koordinator/Developer |
| Pengajuan Ulang | Pemilihan orang/unit dan pemeriksaan Surat Sehat | Koordinator dan Vendor |
| Request Ekspedisi | Request kendaraan, vendor tujuan, rute, PR/PO, dan relasi Izin Lintas | Purchasing/Koordinator/Developer/Vendor |
| Planning Escort | Kalender 30 hari, drag-and-drop, evidence WA | Koordinator/Developer |
| Driver Escort | Ambil unit ekspedisi, proses, close dengan foto dan GPS | Driver |
| Manajemen User | CRUD akun, permission koordinator, relasi vendor, nomor lambung | Koordinator/Developer |
| Manajemen Vendor | Master 35 vendor, kategori dan status | Koordinator/Developer |
| Profil | Perubahan profil dan password sendiri | Semua role |
| Realtime | Notifikasi dan refresh data tanpa polling | Semua role terkait |

## 3. Teknologi dan Arsitektur

### 3.1 Stack

- Backend: PHP 8.2+, Laravel 12, Eloquent ORM.
- Frontend: Vue 3, Inertia.js, Tailwind CSS, Vite.
- Database: SQLite untuk default development; MySQL/MariaDB direkomendasikan untuk produksi.
- Realtime: Laravel Reverb, Laravel Echo, Pusher protocol.
- Queue: Laravel database queue; Redis direkomendasikan untuk produksi skala lebih besar.
- File privat: Laravel local disk di `storage/app/private`.
- Geocoding: OpenStreetMap Nominatim dengan cache 30 hari.
- Pengujian: PHPUnit 11 dan Laravel Feature Test.

### 3.2 Aliran Sistem

```text
Browser / Mobile
      │ HTTPS + Inertia
      ▼
Web Server (Nginx/Apache)
      │
      ├── Laravel HTTP ── Database
      ├── Storage privat ── Dokumen & foto
      ├── Queue Worker ── Event broadcast
      └── Reverb WebSocket ── Update realtime
```

Semua aksi sensitif tetap divalidasi di server. Pemeriksaan role di UI hanya untuk pengalaman pengguna dan bukan pengganti middleware backend.

### 3.3 Model Data Utama

| Entitas/tabel | Tanggung jawab | Relasi penting |
|---|---|---|
| `users` | Identitas akun, role, site, status aktif, permission, perusahaan Vendor, dan nomor lambung Driver | Vendor mengarah ke `vendor_companies`; laporan/pengajuan menyimpan pembuat; escort menyimpan driver mulai dan driver close |
| `vendor_companies` | Master perusahaan Vendor, singkatan, kategori Regular/Non Regular, dan status aktif | Memiliki banyak akun Vendor dan menjadi tujuan Request Ekspedisi |
| `expedition_requests` | Request dari Purchasing/Koordinator/Developer, jenis kendaraan, rute, PR, PO, status, dan evidence | Ditujukan ke satu perusahaan Vendor dan dapat dipilih satu Ijin Lintas |
| `vendor_submissions` | Header pengajuan Visitor, Waskat, Permit Non Simper, atau Ijin Lintas | Memiliki banyak subjek, dokumen, dan planning; opsional mengarah ke Request Ekspedisi |
| `vendor_submission_units` | Orang atau unit di dalam satu pengajuan | Orang memakai `person_name`; unit Ijin Lintas memakai `plate_number` |
| `vendor_documents` | Metadata dan lokasi file privat per subjek | Mengarah ke pengajuan dan subjek; memiliki status verifikasi |
| `planning_items` | Agenda tanggal Planning Escort, posisi kartu, dan evidence | Mengarah ke pengajuan Vendor dan diperbarui Koordinator/Ekspedisi |
| `expedition_reports` | Kedatangan kendaraan, tensi, foto, GPS, vendor/pengirim, serta seluruh siklus escort | Dibuat akun Ekspedisi; dapat diklaim dan ditutup akun Driver |
| `push_subscriptions` | Endpoint Web Push per user dan perangkat/browser | Dipakai untuk push ketika halaman berada di background atau tertutup |
| `jobs` / `failed_jobs` | Antrean event broadcast dan Web Push | Diproses oleh queue worker |

Relasi bisnis ringkas:

```text
VendorCompany ──< User(Vendor)
      │
      ├──< ExpeditionRequest ──0..1── VendorSubmission(Ijin Lintas)
      │                                  ├──< SubmissionSubject
      │                                  │       └──< VendorDocument
      │                                  └──< PlanningItem
      │
User(Expedition) ──< ExpeditionReport >── User(Driver mulai/close)

User ──< PushSubscription
```

File fisik tidak disimpan sebagai blob database. Database menyimpan metadata dan path, sedangkan file berada di storage privat serta hanya dikirim melalui controller yang memeriksa autentikasi dan kepemilikan/hak akses.

## 4. Role dan Hak Akses

### 4.1 Developer

Role tertinggi. Developer melewati pemeriksaan role/permission dan dapat mengakses seluruh modul Koordinator. Akun Developer:

- tidak tampil bagi Koordinator;
- tidak dapat dibuat atau diedit oleh Koordinator;
- hanya dibuat/diperbarui melalui CLI server;
- mempunyai permission wildcard `*`.

Perintah aman:

```bash
php artisan developer:create developer --name="Developer Sistem" --site=BIB
```

Password diminta secara tersembunyi dan minimal 12 karakter. Hindari opsi `--password` pada production karena dapat masuk ke shell history.

### 4.2 Koordinator

Super admin operasional pada site yang sama. Permission dapat dibatasi per modul:

- `coordinator.expedition`: dashboard dan monitoring ekspedisi;
- `coordinator.vendor`: pengajuan vendor dan Planning Escort;
- `coordinator.requests`: Request Ekspedisi dan pembaruan PR/PO;
- `coordinator.planning`: kalender Planning Escort;
- `users.manage`: manajemen akun selain Developer;
- `vendors.manage`: manajemen master perusahaan Vendor.

### 4.3 Vendor

- hanya melihat pengajuan yang dibuat oleh akunnya sendiri;
- terhubung ke satu `vendor_company_id` dari master vendor;
- membuat pengajuan dan mengunggah dokumen PDF;
- menerima permintaan pengajuan ulang/Surat Sehat;
- melihat Request Ekspedisi yang ditujukan ke perusahaan akunnya, termasuk PR, PO, dan tanggal planning;
- melihat Monitoring Sarana yang terkait akun;
- dapat mengganti password sendiri.

### 4.4 Ekspedisi

- membuat laporan kedatangan kendaraan;
- nama perusahaan ekspedisi berasal dari akun login;
- Vendor/Pengirim tetap berupa input manual;
- riwayat laporan dibatasi pada data buatan akun sendiri;
- mengambil foto kedatangan dan foto alat tensi dari kamera.
- melihat Izin Lintas Released yang masih berlaku dan menentukan tanggal planning utama.

### 4.5 Driver

- melihat kendaraan **ekspedisi** berstatus menunggu, seluruh escort aktif pada site, serta riwayat yang dimulai/ditutup olehnya;
- memilih kendaraan yang akan diproses;
- satu kendaraan hanya dapat diambil satu driver (database row locking);
- akun Driver wajib mempunyai nomor lambung; satu sarana/nomor lambung dapat digunakan beberapa akun Driver;
- menutup escort tanpa upload foto, dengan koordinat maksimal ±100 meter dan presisi aktual yang tetap tercatat.
- driver yang memulai dan driver yang menutup escort dapat berbeda; keduanya disimpan sebagai audit operasional.

### 4.6 Admin Purchasing

- membuat Request Ekspedisi untuk perusahaan Vendor tujuan;
- menentukan jenis kendaraan, rute asal, rute tujuan, serta keterangan operasional;
- mengisi dan memperbarui nomor PR dan/atau PO setelah diterbitkan;
- memantau status request pada site akunnya;
- menerima pembaruan realtime terkait Request Ekspedisi;
- tidak memiliki akses ke pengajuan Vendor, escort, atau administrasi pengguna kecuali diberikan melalui role lain pada pengembangan berikutnya.

## 5. Alur Operasional

### 5.1 Pelaporan Kedatangan Ekspedisi

1. Akun Ekspedisi login.
2. Isi driver, nomor HP, nomor polisi, vendor/pengirim, tujuan, dan jenis kendaraan.
3. Isi sistolik dan diastolik. Sistem menilai status kesehatan secara realtime.
4. Ambil foto kedatangan dan foto layar alat tensi melalui kamera.
5. Untuk setiap foto, browser meminta GPS hingga akurasi maksimal ±100 meter agar proses lebih cepat; nilai presisi aktual tetap disimpan.
6. Sistem melakukan reverse geocoding untuk menampilkan nama area.
7. Kirim laporan. Jam kedatangan dicatat server.
8. Koordinator dan Driver menerima event realtime.

Validasi penting:

- sistolik 60–250 mmHg;
- diastolik 30–150 mmHg dan harus lebih kecil dari sistolik;
- foto JPG/JPEG/PNG/WebP maksimal 8 MB;
- koordinat latitude/longitude valid;
- akurasi GPS maksimal 100 meter; semakin kecil nilainya semakin presisi.

### 5.2 Penilaian Tensi Otomatis

Sistem selalu menggunakan hasil yang paling berat dari sistolik atau diastolik.

| Kondisi | Status | Alasan |
|---|---|---|
| Sistolik ≥180 atau diastolik ≥110 | Unfit | Sangat tinggi; perlu evaluasi medis segera |
| Sistolik 160–179 atau diastolik 100–109 | Unfit / Pending | Cukup tinggi; perlu evaluasi dokter |
| Sistolik 140–159 atau diastolik 90–99 | Pending | Perlu pemeriksaan/monitoring lanjutan |
| Sistolik 120–139 atau diastolik 80–89 | Fit dengan Catatan Medis | Dapat bekerja dengan catatan/rekomendasi |
| Sistolik <120 dan diastolik <80 | Fit | Tekanan darah normal |

Nilai seperti `10/10` ditolak oleh validasi batas minimum server dan tidak dapat disimpan.

### 5.3 Pengajuan Vendor

Jenis subjek:

- Visitor: orang;
- Waskat: orang;
- Permit Vendor Non Simper: orang;
- Ijin Lintas: unit/kendaraan.

Satu pengajuan dapat berisi satu atau beberapa orang/unit. Dokumen disimpan per subjek agar review dan pengajuan ulang dapat dilakukan sebagian.

#### Dokumen Visitor

- Surat Tugas — wajib;
- KTP — wajib.

#### Dokumen Waskat

- KTP Vendor — wajib;
- Foto 4x4 Latar Merah — wajib;
- Surat Sehat Vendor — wajib;
- Mine Permit Pengawas 1/2, SPDK, JSA/IK, Sosialisasi JSA — opsional.

#### Dokumen Permit Vendor Non Simper

- Foto 4x4, KTP, MCU — wajib;
- SPDK dan Mine Permit Custodian — opsional.

#### Dokumen Ijin Lintas

- Foto Unit 4 Sisi, KTP, SIM, STNK, KIR, Surat Ijin Lintas, Surat Sehat — wajib.
- Kategori layanan: Regular atau Non Regular.
- Frekuensi planning: Daily, Weekly, atau Non Regular.

Upload mendukung drag-and-drop, paste file, dan pemilihan file. Hanya PDF dengan ukuran maksimal 3 MB per dokumen.

### 5.4 Request Ekspedisi dan PR/PO

1. Admin Purchasing, Koordinator, atau Developer membuat Request Ekspedisi.
2. Data wajib mencakup vendor tujuan, jenis kendaraan, rute asal, dan rute tujuan. Keterangan muatan/PIC bersifat opsional.
3. Nomor request dibuat otomatis dengan pola `REQ-EXP-YYYYMMDD-XXXXXX`.
4. Hanya akun Vendor yang terhubung ke perusahaan tujuan yang dapat melihat dan memilih request tersebut pada Izin Lintas.
5. Nomor PR dan PO dapat diisi atau diperbarui langsung dari tabel Request Ekspedisi. Salah satu atau keduanya boleh belum terbit.
6. Perubahan PR/PO muncul pada Request Vendor, tabel/detail Izin Lintas Koordinator, dan notifikasi realtime.
7. Ketika request dipilih dalam pengajuan Izin Lintas, relasi disimpan melalui `expedition_request_id`; nomor PR/PO tidak disalin agar selalu menampilkan nilai terbaru.

Status Request Ekspedisi:

- `OPEN`: tersedia untuk dipilih vendor tujuan;
- `USED`: sudah dipakai pada pengajuan Izin Lintas;
- `CLOSED`: proses request telah selesai.

### 5.5 Status Pengajuan

```text
SUBMITTED → APPROVED / REVISION / REJECTED
APPROVED  → RELEASED
RELEASED  → CLOSED atau kedaluwarsa/pengajuan ulang
```

- `SUBMITTED`: baru diajukan.
- `APPROVED`: dokumen disetujui.
- `REVISION`: vendor perlu memperbaiki data/dokumen.
- `REJECTED`: pengajuan ditolak dan tidak masuk planning.
- `RELEASED`: izin telah dirilis; berlaku 7 hari.
- `CLOSED`: siklus pengajuan selesai.

Koordinator dapat memverifikasi setiap dokumen sebagai pending/valid/tidak valid, memberi catatan, mengubah status, serta mengunduh satu dokumen atau seluruh dokumen sebagai arsip.

### 5.6 Pengajuan Ulang

- Pengajuan ulang dimulai oleh Koordinator.
- Koordinator memilih satu atau beberapa orang/unit yang benar-benar diajukan ulang.
- Dokumen lama dipertahankan sesuai subjek.
- Vendor hanya memperbarui Surat Sehat ketika diminta dan dokumen lain tetap memakai data terdahulu.
- Untuk aksi **Minta Dokumen Kesehatan Baru**, Koordinator wajib mengisi alasan minimal 10 dan maksimal 1.500 karakter.
- Alasan ditampilkan pada akun Vendor, dikirim melalui notifikasi realtime, dan dipertahankan sebagai riwayat setelah dokumen baru diunggah.
- Setiap release berlaku 7 hari dari `released_at` sampai `release_expired_at`.
- Waskat maksimal dua kali pengajuan ulang; pengajuan berikutnya wajib Permit.
- Ijin Lintas Regular dapat diteruskan; Non Regular bersifat opsional sesuai kebutuhan.

### 5.7 Planning Escort

- Semua pengajuan non-ditolak dapat tersedia di planning sesuai aturan aplikasi.
- Ijin Lintas `RELEASED` Regular Daily dibuat untuk setiap hari masa berlaku.
- Ijin Lintas Weekly dibuat pada tanggal release dan dapat dipindahkan.
- Non Regular tidak dibuat berulang secara otomatis.
- Koordinator dapat menarik kartu ke tanggal lain dalam kalender satu bulan ke depan.
- Pada layar sentuh, kartu dipilih lalu tanggal tujuan dipilih.
- Evidence WhatsApp dapat dilampirkan dan dibuka kembali.
- Filter mencakup kata kunci, tipe, status, waktu, dan urutan.
- Akun Ekspedisi dapat menentukan tanggal planning utama untuk Izin Lintas `RELEASED` yang belum kedaluwarsa.
- Perubahan dari akun Ekspedisi menggunakan item planning yang sama dan otomatis terlihat pada Koordinator serta Vendor.
- Koordinator tetap dapat memindahkan tanggal dengan drag-and-drop; perubahan kembali tersinkron ke Ekspedisi dan Vendor.

### 5.8 Driver Escort

Hanya laporan kedatangan ekspedisi yang dapat di-escort. Pengajuan Visitor, Waskat, Permit, dan Ijin Lintas tidak muncul sebagai pekerjaan Driver.

```text
WAITING → PROCESS → CLOSED
```

1. Driver memilih kendaraan berstatus `WAITING`.
2. Sistem mengunci row untuk mencegah dua driver mengambil kendaraan yang sama.
3. Status menjadi `PROCESS`; tanggal/jam mulai serta identitas driver pemulai/nomor lambung tersimpan.
4. Seluruh driver berizin pada site dapat melihat escort aktif. Driver yang menutup boleh berbeda dari driver yang memulai.
5. Driver penutup membuka konfirmasi close di lokasi tujuan; sistem langsung mengambil koordinat tanpa meminta upload foto.
6. GPS wajib mencapai akurasi maksimal ±100 meter dan sistem menampilkan nilai presisi aktual.
7. Status menjadi `CLOSED`; identitas driver penutup, jam selesai, koordinat, akurasi, dan nama lokasi tersimpan.
8. Penutupan memakai database row locking sehingga dua driver tidak dapat menutup escort yang sama secara bersamaan.
9. Tabel Koordinator menampilkan **Driver Mulai Escort** dan **Driver Close Escort**, lalu diperbarui realtime tanpa mengembalikan pengguna ke atas halaman.

## 6. Dashboard dan DataTable

Seluruh tabel utama menggunakan pemrosesan server-side:

- pencarian, filter, sorting, pagination, dan jumlah baris diproses backend;
- interaksi tidak melakukan full page refresh;
- posisi scroll dan konteks halaman dipertahankan;
- teks panjang dibungkus (`wrap`) dan tidak dipotong keluar kartu;
- ekspor CSV mengikuti filter aktif;
- status loading tampil untuk operasi lambat.

Chart pola kedatangan bersifat interaktif: hover/touch menampilkan detail per jam. Label harus menggunakan wrapping/ruang yang cukup agar tidak terpotong.

## 7. Realtime dengan Laravel Reverb

### 7.1 Channel Privat

| Channel | Pengguna | Tujuan |
|---|---|---|
| `coordinator.{site}` | Developer/Koordinator berizin pada site | Pengajuan, laporan, escort |
| `vendor.{userId}` | User vendor itu sendiri | Perubahan status/pengajuan ulang |
| `driver.{site}` | Driver berizin pada site | Laporan ekspedisi dan perubahan escort |
| `expedition.{site}` | Ekspedisi berizin pada site | Sinkronisasi tanggal planning |
| `purchasing.{site}` | Admin Purchasing berizin pada site | Request Ekspedisi dan perubahan PR/PO |
| `App.Models.User.{id}` | User itu sendiri | Channel privat Laravel |

### 7.2 Event

- `ExpeditionReportCreated`
- `ExpeditionEscortUpdated`
- `VendorSubmissionCreated`
- `VendorSubmissionUpdated`
- `VendorSubmissionStatusChanged`
- `VendorResubmissionRequested`
- `ExpeditionRequestCreated`
- `ExpeditionRequestUpdated`
- `PlanningUpdated`

Event menggunakan broadcasting melalui queue. `SafeBroadcast` memastikan gangguan sementara Reverb/ngrok tidak mengubah penyimpanan bisnis yang sudah berhasil menjadi HTTP 500. Queue worker tetap wajib aktif agar event terkirim.

### 7.3 Konfigurasi Lokal

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
REVERB_APP_ID=ppa-expedition
REVERB_APP_KEY=ganti-dengan-key-acak
REVERB_APP_SECRET=ganti-dengan-secret-kuat
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS="127.0.0.1,localhost"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Jalankan:

```bash
php artisan reverb:start --debug
php artisan queue:work --tries=3 --timeout=90
npm run dev
php artisan serve
```

### 7.4 Konfigurasi Produksi

Gunakan host publik yang sama atau subdomain WebSocket, TLS `https/wss`, origin eksplisit, dan Supervisor/systemd agar Reverb serta queue selalu hidup.

Contoh nilai bila WebSocket diproksi melalui domain utama:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ekspedisi.ppabib.com
REVERB_HOST=ekspedisi.ppabib.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS="https://ekspedisi.ppabib.com"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Sesudah mengubah variabel `VITE_*`, wajib menjalankan `npm run build` karena nilainya ditanam ke aset frontend.

### 7.5 Web Push Chrome dan Mobile

Web Push melengkapi Reverb untuk kondisi tab berada di background atau aplikasi telah ditutup:

```text
Laravel business action
  ├─ Reverb → Laravel Echo → refresh/toast saat halaman aktif
  └─ Web Push (VAPID) → browser push service → Service Worker → notifikasi Chrome/mobile
```

Subscription disimpan per akun dan per perangkat pada tabel `push_subscriptions`. Setelah login, pengguna menekan **Aktifkan Notifikasi Chrome** dan menyetujui izin browser. Saat halaman sedang terlihat, Service Worker tidak menampilkan notifikasi OS agar tidak ganda dengan toast Reverb. Saat tab tersembunyi atau ditutup, notifikasi persisten ditampilkan dan dapat membuka aplikasi melalui `/dashboard` sesuai role.

Event Web Push mencakup Request Ekspedisi, perubahan PR/PO, pengajuan dan status Vendor, permintaan Surat Sehat beserta alasan, Planning Escort, kedatangan Ekspedisi, dan perubahan status Escort. Penerima dibatasi berdasarkan role, site, perusahaan Vendor, izin modul, status akun aktif, dan subscription perangkat.

Konfigurasi production:

```env
VAPID_SUBJECT="https://ekspedisi.ppabib.com"
VAPID_PUBLIC_KEY="isi-public-key"
VAPID_PRIVATE_KEY="isi-private-key-rahasia"
```

Generate key satu kali dengan `php artisan webpush:vapid`. Jika OpenSSL Windows tidak dapat membuat EC key, gunakan `npx web-push generate-vapid-keys --json`, lalu simpan hasilnya ke secret production. **Jangan mengganti VAPID key** setelah pengguna berlangganan karena subscription lama tidak akan dapat menerima push.

Persyaratan operasional:

- domain HTTPS valid; localhost hanya untuk pengembangan;
- `php artisan migrate --force` sudah menjalankan migration `push_subscriptions`;
- queue worker selalu hidup karena pengiriman berjalan asynchronous;
- Service Worker `/service-worker.js` dan manifest tidak diblokir web server/CDN;
- Chrome Android mendukung notifikasi browser; pada iPhone/iPad, pengguna perlu memasang web sebagai Home Screen dan menggunakan iOS/iPadOS yang mendukung Web Push;
- pengguna harus memberikan izin pada setiap browser/perangkat yang ingin menerima notifikasi.

## 8. Instalasi Development

Persyaratan: PHP 8.2+, Composer, Node.js 20+, npm, ekstensi PHP sesuai Laravel, dan database.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Untuk data awal:

```bash
php artisan db:seed
```

Untuk reset total development beserta seeder:

```bash
php artisan migrate:fresh --seed
```

**Peringatan:** `migrate:fresh` menghapus seluruh tabel. Jangan digunakan pada production.

Jalankan seluruh proses development melalui:

```bash
composer run dev
```

### 8.1 Konfigurasi `.env` Lokal

Contoh minimum untuk MySQL lokal:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pelaporan_ekspedisi
DB_USERNAME=root
DB_PASSWORD=
```

Nama database harus sama persis. Kesalahan seperti `pelaporan_ekpedisi` dan `pelaporan_ekspedisi` akan mengarah ke dua database berbeda. Setelah mengubah `.env`, jalankan:

```bash
php artisan optimize:clear
php artisan migrate
```

Periksa koneksi dan jumlah akun:

```bash
php artisan migrate:status
php artisan tinker --execute="dump(\App\Models\User::count());"
```

Jika hasil jumlah akun `0`, aplikasi tidak dapat menerima login apa pun sampai akun dibuat atau seeder development dijalankan.

## 9. Data Awal dan Akun Demo

Seeder standar membuat role dasar dengan username sesuai nama role dan password `password`, kecuali Developer. Seeder akun vendor operasional juga membuat contoh:

| Jenis | Username contoh | Password contoh |
|---|---|---|
| Vendor DMA | `vendor_dma` | `Vendor123!` |
| Vendor BUMDES | `vendor_bumdes` | `Vendor123!` |
| Vendor SJPL | `vendor_sinar` | `Vendor123!` |
| Ekspedisi Borneo | `expedition_borneo` | `Expedition123!` |
| Ekspedisi Mitra | `expedition_mitra` | `Expedition123!` |
| Ekspedisi Trans | `expedition_trans` | `Expedition123!` |

Semua kredensial tersebut hanya untuk development/demo. Pada production, jangan menjalankan seeder dummy dan wajib ganti seluruh password.

`VendorAccountDataSeeder` bersifat destruktif terhadap data operasional terkait: direktori dokumen/laporan privat dibersihkan dan tabel operasional ditruncate. Gunakan hanya pada database development yang telah dipastikan.

## 10. Deployment Produksi

### 10.1 Persiapan

1. Siapkan Linux server, Nginx/Apache, PHP-FPM 8.2+, MySQL/MariaDB, Node/npm untuk build, dan TLS.
2. Arahkan document root ke folder `public`, bukan root repository.
3. Salin `.env`, isi kredensial produksi, dan buat `APP_KEY` satu kali.
4. Pastikan user web server dapat menulis `storage` dan `bootstrap/cache`.

### 10.2 Perintah Deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`storage:link` hanya untuk file publik. Dokumen/foto sensitif tetap di disk privat dan disajikan melalui controller berotorisasi.

### 10.3 Proses Latar Belakang

Supervisor/systemd harus menjaga proses berikut:

```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=90
php artisan reverb:start --host=127.0.0.1 --port=8080
```

Setelah deployment baru:

```bash
php artisan queue:restart
```

Reverb juga perlu direstart agar kode/config baru terambil.

### 10.4 Nginx WebSocket Proxy

```nginx
location /app/ {
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_pass http://127.0.0.1:8080;
}

location /apps/ {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_pass http://127.0.0.1:8080;
}
```

Sesuaikan path dengan konfigurasi Reverb yang digunakan dan uji endpoint broadcasting authentication dalam keadaan login.

## 11. Ngrok untuk Uji Publik

Ngrok hanya untuk staging/uji, bukan pengganti konfigurasi production permanen.

1. Jalankan aplikasi, Reverb, queue, dan Vite/build.
2. Jalankan gateway proyek jika digunakan: `npm run gateway:ngrok`.
3. Jalankan tunnel HTTPS ke gateway/web server.
4. Ubah `APP_URL`, `REVERB_HOST`, `REVERB_SCHEME=https`, port publik 443, `VITE_REVERB_*`, dan allowed origins.
5. Jalankan `php artisan optimize:clear` dan `npm run build`.
6. Restart Reverb dan queue.

Key/secret Reverb dibuat sendiri. Key dikirim ke browser dan bukan rahasia; secret hanya berada di server dan tidak boleh menggunakan nilai contoh.

## 12. Keamanan

- Password disimpan dalam bentuk hash melalui cast Laravel.
- Password asli tidak dapat dan tidak boleh ditampilkan kembali oleh Manajemen User.
- Gunakan fitur reset/ganti password, bukan penyimpanan plain text.
- Login dibatasi 5 percobaan berdasarkan username + IP.
- Akun nonaktif tidak dapat login.
- Authorization diterapkan melalui middleware role, permission, site, dan kepemilikan data.
- File sensitif berada di storage privat dan hanya dibuka melalui route terautentikasi.
- Gunakan HTTPS agar kamera, geolocation, session, dan WebSocket aman.
- Set `APP_DEBUG=false`, secure cookie, dan kredensial database terbatas pada production.
- Backup database dan `storage/app/private` sebagai satu kesatuan.

## 13. Backup dan Pemulihan

Backup minimum harian:

- database;
- `storage/app/private/vendor-documents`;
- `storage/app/private/expedition-reports`;
- `storage/app/private/escort-close`;
- `.env` melalui secret manager yang aman (bukan arsip publik).

Lakukan uji restore berkala pada lingkungan terpisah. Setelah restore, jalankan `php artisan optimize:clear` dan verifikasi relasi file-database.

## 14. Pengujian dan Quality Gate

```bash
php artisan optimize:clear
php artisan test
npm run build
./vendor/bin/pint --test
```

Jalankan `optimize:clear` **sebelum** test. Jangan menjalankan test setelah `config:cache` production tanpa membersihkan cache terlebih dahulu karena konfigurasi session/CSRF production dapat menyebabkan respons 419 palsu pada test HTTP.

Hasil verifikasi versi 1.2 pada 26 Agustus 2026:

- 64 test lulus;
- 503 assertions lulus;
- Laravel Pint lulus tanpa pelanggaran format;
- Vite production build berhasil (821 modul);
- seluruh 27 migration berstatus `Ran`;
- route, configuration, dan Blade cache berhasil dibuat setelah build.
- broadcast nyata ke Reverb berhasil (`REVERB_BROADCAST_OK`), queue worker aktif, tanpa failed/pending job.

Cakupan pengujian yang tersedia mencakup:

- autentikasi dan password;
- hak akses Developer/Koordinator/Vendor/Ekspedisi/Driver;
- filter, sorting, dan aksi DataTable;
- alur pengajuan dan Surat Sehat;
- alasan wajib permintaan Surat Sehat dan visibilitas alasan pada Vendor;
- Request Ekspedisi, pembaruan PR/PO, dan relasi Izin Lintas;
- planning Izin Lintas dari akun Ekspedisi serta sinkronisasi ke Vendor/Koordinator;
- notifikasi belum dibuka;
- escort eksklusif saat claim, pemulai/penutup berbeda, close tanpa foto, dan GPS ≤100 meter;
- penilaian tensi berdasarkan tingkat terberat;
- metadata SEO dan sitemap publik.
- subscription Web Push per perangkat, keamanan endpoint, dan pemetaan penerima berdasarkan role/site/vendor.

## 15. Troubleshooting

### Tidak dapat login di lokal

1. Pastikan halaman `http://127.0.0.1:8000/login` dapat dibuka.
2. Periksa `APP_URL`; untuk lokal gunakan `http://127.0.0.1:8000`, bukan URL ngrok/production.
3. Periksa ejaan `DB_DATABASE` dan pastikan menunjuk database yang benar.
4. Jalankan `php artisan migrate:status` untuk memastikan skema tersedia.
5. Periksa jumlah akun dengan `php artisan tinker --execute="dump(\App\Models\User::count());"`.
6. Jika database development masih kosong, jalankan `php artisan db:seed`.
7. Bersihkan rate limiter dan cache melalui `php artisan optimize:clear` jika sudah gagal login berulang kali.
8. Pastikan akun memiliki `is_active = 1`; login hanya menerima username aktif.

`DatabaseSeeder` memanggil `VendorAccountDataSeeder` yang membersihkan data operasional dummy dan file privat terkait. Seeder ini hanya boleh dijalankan pada database development yang memang boleh diisi ulang, bukan pada production.

### Halaman putih setelah deployment

1. Periksa `storage/logs/laravel.log` dan log web server.
2. Pastikan `public/build/manifest.json` ada (`npm run build`).
3. Pastikan permission `storage` dan `bootstrap/cache` benar.
4. Jalankan `php artisan optimize:clear`.
5. Periksa `APP_URL`, proxy HTTPS, dan session cookie.

### Realtime tidak masuk

1. Pastikan indikator **Realtime terhubung**.
2. Pastikan Reverb dan queue worker hidup.
3. Periksa Console/Network browser: koneksi harus `wss://` pada HTTPS.
4. Pastikan `REVERB_ALLOWED_ORIGINS` memuat domain penuh.
5. Pastikan konfigurasi `VITE_REVERB_*` sudah di-build ulang.
6. Pastikan route `/broadcasting/auth` tidak 401/403.
7. Jalankan `php scripts/verify-reverb.php` sesuai lingkungan.

### Notifikasi Chrome tidak masuk ketika web ditutup

1. Login lalu tekan **Aktifkan Notifikasi Chrome** dan pastikan notifikasi uji diterima.
2. Pastikan izin Notifications untuk domain bernilai **Allow**, bukan Block.
3. Pastikan tabel `push_subscriptions` berisi endpoint untuk akun dan perangkat tersebut.
4. Pastikan `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, dan `VAPID_SUBJECT` tersedia pada proses queue worker.
5. Restart worker setelah konfigurasi berubah: `php artisan queue:restart`.
6. Pastikan worker memproses queue `default` dan tidak ada data pada `failed_jobs`.
7. Periksa log Laravel; kegagalan Web Push dicatat tanpa membatalkan transaksi bisnis utama.
8. Pada Android, pastikan notifikasi Chrome tidak dimatikan oleh sistem atau battery optimization.

### Kamera/GPS tidak bekerja

- wajib HTTPS atau localhost;
- izinkan Camera dan Precise Location pada browser;
- matikan mode hemat lokasi;
- ambil foto di area terbuka;
- tunggu hingga akurasi ≤100 meter; nilai aktual tetap ditampilkan dan disimpan;
- jangan memilih gambar lama jika bukti wajib diambil langsung.

### DataTable tampak lama/tidak berubah

- pastikan request server-side berhasil;
- periksa filter tanggal dan site;
- periksa event Reverb;
- gunakan Muat Ulang tanpa full page refresh;
- hard refresh setelah deployment aset frontend baru.

### Gagal menyimpan user

- username/email harus unik;
- password akun baru minimal 8 karakter;
- Driver wajib memiliki nomor lambung; beberapa akun Driver boleh menggunakan nomor lambung yang sama;
- Vendor wajib dipasangkan ke Master Vendor;
- Koordinator tidak dapat membuat/mengubah Developer.

### Test HTTP banyak gagal dengan status 419

1. Jalankan `php artisan optimize:clear`.
2. Pastikan `.env.testing` atau konfigurasi PHPUnit menggunakan session dan database test.
3. Jalankan kembali `php artisan test`.
4. Buat cache production hanya setelah test selesai.

## 16. Operasional Harian

### Checklist awal shift

- login dan pastikan indikator realtime terhubung;
- periksa badge pengajuan belum dibuka;
- periksa kedatangan hari ini dan status tensi;
- periksa kendaraan `WAITING` dan `PROCESS`;
- periksa planning hari ini serta evidence;
- tindak lanjuti pengajuan mendekati kedaluwarsa.

### Checklist akhir shift

- pastikan escort proses telah ditutup atau diserahterimakan;
- periksa foto dan lokasi close escort;
- ekspor data bila diperlukan;
- catat pengajuan revisi/ditolak;
- pastikan queue dan Reverb sehat.

## 17. Struktur Direktori Penting

| Lokasi | Isi |
|---|---|
| `app/Http/Controllers` | Alur HTTP dan otorisasi data |
| `app/Http/Requests` | Validasi input |
| `app/Models` | Model dan relasi database |
| `app/Events` | Event realtime |
| `app/Services` | Persyaratan dokumen dan planning |
| `app/Support` | Penilaian tensi |
| `resources/js/Pages` | Halaman Vue per role |
| `resources/js/Components` | Komponen UI bersama |
| `resources/js/echo.js` | Bootstrap realtime client |
| `database/migrations` | Evolusi skema |
| `database/seeders` | Master dan data development |
| `routes/web.php` | Route aplikasi |
| `routes/channels.php` | Otorisasi channel privat |
| `storage/app/private` | Dokumen dan foto privat |
| `tests` | Unit dan feature tests |

## 18. Catatan Pengembangan Lanjutan

- Tambahkan audit log immutable untuk perubahan status, user, dan dokumen.
- Tambahkan retention policy dan checksum file.
- Gunakan antivirus scanning untuk seluruh upload di production.
- Pindahkan file privat ke object storage dengan signed URL bila volume meningkat.
- Tambahkan health check queue/Reverb dan alert monitoring.
- Tambahkan persetujuan medis formal bila status tensi digunakan sebagai keputusan keselamatan. Penilaian saat ini adalah aturan sistem dan bukan diagnosis dokter.

## 19. Riwayat Versi Dokumentasi

| Versi | Tanggal | Perubahan utama |
|---|---|---|
| 1.0 | 25 Agustus 2026 | Dokumentasi awal seluruh modul, role, deployment, Reverb, keamanan, dan operasional. |
| 1.1 | 25 Agustus 2026 | Request Ekspedisi dan PR/PO, planning oleh Ekspedisi, sinkronisasi Vendor/Koordinator, escort dengan driver mulai/close berbeda, alasan wajib Surat Sehat, serta hasil quality gate terbaru. |
| 1.2 | 26 Agustus 2026 | Web Push Chrome/mobile berbasis VAPID dan Service Worker, subscription per perangkat, ikon ringan, pemetaan penerima, serta fallback Reverb untuk layar aktif. |
| 1.3 | 26 Agustus 2026 | Sinkronisasi dokumentasi implementasi terbaru, role Admin Purchasing, pemisahan permission Koordinator, konfigurasi database lokal, serta troubleshooting login lokal dan database kosong. |

---

**Pemilik proses:** SCM & FAW  
**Dukungan aplikasi:** ICT  
**Identitas:** PT Putra Perkasa Abadi — Site Borneo Indobara
