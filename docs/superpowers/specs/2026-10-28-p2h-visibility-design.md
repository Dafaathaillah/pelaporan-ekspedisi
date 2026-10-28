# P2H: akses izin perusahaan dan pelacakan perjalanan

## Kebutuhan

Setelah request ekspedisi Ready dan izin lintas Released, ekspedisi perusahaan terkait dapat memilih izin dan mengisi P2H web. Vendor pemilik izin approve/reject; rejection dapat direvisi. Purchasing/coordinator dapat mengunduh PDF. Tampilkan waktu pengisian P2H, approval, keberangkatan, dan kedatangan. Checklist mengikuti form P2H yang sudah tersedia (termasuk kondisi, keterangan, rem, ban 1-20, dan keputusan laik).

Instruksi pengguna: **jangan ubah alur bagian foto tensi**. Foto tensi dan foto kedatangan tetap pada laporan kedatangan; tidak dipindah, tidak dijadikan syarat baru P2H. Database operasional tidak diubah dalam pekerjaan ini.

## Temuan

- Alur P2H sudah diimplementasikan pada DepartureP2hController dan DepartureP2hCard.
- ArrivalPlanningService dan otorisasi P2H mensyaratkan vendor_company_id pada akun ekspedisi. UserManagementController justru mengosongkan relasi untuk semua role selain vendor, dan UI hanya menawarkan perusahaan untuk vendor. Akun ekspedisi yang dibuat dari UI tidak dapat mengakses izin perusahaan.
- Form sebelum keberangkatan berada pada halaman berjudul Pelaporan Kedatangan. PDF tidak ditampilkan pada detail izin reviewer.
- Timestamp tersimpan, tetapi monitoring belum menunjukkan perjalanan P2H sampai kedatangan.
- Pemeriksaan data BAS lokal terhalang database MySQL yang belum tersedia; sebab kode harus dibuktikan melalui regression test.

## Pendekatan

Pertahankan model, rute P2H, approval dan foto yang ada. Perbaiki pemilihan serta penyimpanan perusahaan pada manajemen akun ekspedisi, tanpa fallback berdasarkan nama atau membuka semua vendor. Akun lama tanpa perusahaan diberi petunjuk jelas untuk diperbarui melalui manajemen pengguna; jangan menebak perusahaan dari nama akun. Perjelas judul/menu P2H sebelum keberangkatan, tautan review vendor, PDF reviewer, dan timeline bersama berbasis timestamp server.

Alternatif membuka seluruh izin site bagi akun tanpa perusahaan ditolak karena melanggar batas perusahaan. Membangun ulang workflow ditolak karena approval, revisi, PDF dan gerbang kedatangan sudah ada.

## Verifikasi

Regression test create/update akun ekspedisi dan visibilitas izin BAS; isolasi perusahaan/site; kondisi akun belum ditautkan; timestamp perjalanan pada monitoring dan detail reviewer; alur P2H existing dan unduhan PDF. Gunakan SQLite in-memory untuk tests, GD CLI sementara, install dependensi dari lock bila diperlukan. Build frontend dan review diff. Tidak deploy, push atau menjalankan migrasi database operasional.
