# P2H Visibility Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement task-by-task.

**Goal:** Pulihkan pilihan izin perusahaan untuk ekspedisi dan munculkan workflow P2H yang sudah tersedia beserta PDF dan timeline.

**Architecture:** Pertahankan DepartureP2h dan approval vendor. Perbaiki asal vendor_company_id, tampilkan informasi status/timestamp yang sama pada halaman operasional dan reviewer.

**Tech Stack:** Laravel 12, Vue 3/Inertia, PHPUnit SQLite, Vite, Dompdf.

**Spec:** docs/superpowers/specs/2026-10-28-p2h-visibility-design.md

## Global Constraints

- Branch development; pertahankan dua laporan audit lokal.
- Jangan ubah alur foto tensi/kedatangan, gerbang approval, atau data operasional.
- Izin/PDF tetap dibatasi perusahaan/site dan permission.
- Akun lama tanpa relasi diperbaiki eksplisit oleh administrator; jangan cocokkan nama secara otomatis.

## Review Focus

- Create/update ekspedisi tidak boleh membuang perusahaan yang dipilih.
- Perusahaan lain, site lain, dan akun tanpa relasi tidak boleh memperoleh akses izin.
- Purchasing dan coordinator dapat menemukan PDF dari detail izin.
- Waktu perjalanan tidak dihitung dari foto atau tanggal planning; gunakan timestamp tersimpan.
- Akun ekspedisi tanpa perusahaan mendapat penjelasan, bukan dropdown kosong tanpa sebab.

## Task 1: Relasi akun dan izin

- [x] Tambah tes create/update ekspedisi dan daftar izin, perusahaan/site lain, tanpa perusahaan; jalankan dan lihat kegagalan yang tepat.
- [x] Perbaiki SaveUserRequest, UserManagementController, Coordinator/Users.vue, dan petunjuk akun di ExpeditionController/Index.vue.
- [x] Jalankan UserManagementTest serta tes visibilitas izin; pastikan hijau.

## Task 2: P2H yang mudah ditemukan, PDF dan timeline

- [x] Tambah assertion detail reviewer dan monitoring untuk latest P2H serta waktu kedatangan; buktikan gagal bila payload belum lengkap.
- [x] Muat relasi P2H pada detail reviewer; tampilkan PDF dan timeline bersama pada P2H, monitoring, dan detail vendor/reviewer. Perjelas judul navigasi ekspedisi dan arah review vendor.
- [x] Pertahankan checklist existing dan seluruh mekanisme upload foto.
- [x] Jalankan regression test P2H, approval/reject/revisi, akses PDF, dan monitoring.

## Task 3: Verifikasi dan review

- [x] Lengkapi dependensi dari composer.lock tanpa menjalankan setup/migrasi operasional bila diperlukan.
- [x] Jalankan suite PHP dengan GD CLI, tes JS dan build frontend; catat kegagalan lingkungan terpisah.
- [x] Review perubahan dan perbaiki temuan yang terbukti.
- [x] Laporkan hasil dan langkah memperbaiki relasi akun BAS yang sudah terlanjur kosong. Tidak commit/push/deploy tanpa permintaan.

## Bukti awal

DepartureP2hWorkflowTest + ArrivalPlanningTest: 8 passed, 4 failed sebelum perubahan, akibat GD tidak aktif dan Dompdf tidak terpasang. Database operasional lokal tidak tersedia. Implementasi in-place dipilih mengikuti permintaan branch development; otorisasi perbaikan sudah diberikan dalam permintaan pengguna.

## Hasil verifikasi

- RED: lima regression test baru gagal karena vendor_company_id dibuang, perusahaan belum diwajibkan, flag penjelasan akun belum tersedia, dan payload reviewer belum memuat P2H. Assertion waktu kedatangan pada detail vendor/reviewer juga gagal sebelum relasi ditambahkan.
- GREEN: tes terarah 23 tests / 328 assertions; suite lengkap final `php -d extension=gd vendor/phpunit/phpunit/phpunit --no-progress`: **154 tests / 1469 assertions**, lulus.
- `node --test tests/js/*.test.mjs`: **7 tests**, lulus, termasuk durasi yang berhenti saat kedatangan, waktu >24 jam, data tidak valid, dan format WITA.
- `npm.cmd run build`: lulus. Warning aset login-background.webp dari build existing tetap tercatat; bukan perubahan P2H. Build membutuhkan eskalasi sandbox Windows untuk pembacaan direktori induk oleh esbuild.
- Pint pada seluruh PHP yang berubah: lulus. `git diff --check`: lulus.
- Review independen: tidak menemukan masalah actionable. Reviewer memeriksa kode; tidak menjalankan pengujian BAS live.
- Composer install dari lock dengan --no-scripts memasang lima paket PDF yang belum tersedia, tanpa perubahan composer.lock atau migrasi operasional. GD diaktifkan hanya pada proses CLI pengujian; php.ini global tidak diubah.

## Pengujian manual BAS

1. Koordinator: Manajemen Pengguna > edit akun ekspedisi BAS > perusahaan dari master vendor = BAS; pastikan site sama dengan izin > simpan. Relasi akun lama tidak ditebak atau diubah otomatis.
2. Ekspedisi: menu P2H & Kedatangan > pilih izin Released yang belum kedaluwarsa > Isi Form P2H per unit > Kirim P2H ke Vendor.
3. Vendor: buka Detail Pengajuan izin tersebut > periksa/download hasil P2H > Approve, atau Reject dengan alasan. Ekspedisi dapat mengirim revisi setelah penolakan.
4. Ekspedisi: setelah Approved, Konfirmasi Keberangkatan. Foto tensi/foto kedatangan tetap mengikuti alur existing sesudah unit tiba.
5. Purchasing/coordinator: detail izin > Download PDF P2H. Monitoring kedatangan dan detail izin menampilkan timestamp P2H, review, berangkat, dan tiba dalam WITA.

Data BAS live tidak dapat diperiksa karena database MySQL lokal yang dikonfigurasi belum tersedia. Tidak ada data operasional, migrasi, commit, push, maupun deployment dijalankan. Browser/GPS/perangkat kamera tidak diuji secara langsung.
