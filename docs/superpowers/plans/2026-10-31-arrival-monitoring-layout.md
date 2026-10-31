# Rencana perapihan Monitoring Kedatangan

Spec: ../specs/2026-10-31-arrival-monitoring-layout-design.md

- [x] Pertahankan semua data dan aksi; gabungkan lokasi dan escort dalam detail yang bisa dibuka.
- [x] Rapikan lebar kolom, jarak, dan susunan tombol bukti tanpa mengubah logika.
- [x] Verifikasi build frontend, regresi JavaScript, dan diff.

Implementasi langsung di workspace sesuai persetujuan pengguna. Tidak melakukan deploy, merge, atau push pada tugas visual ini.

## Penyempurnaan lanjutan
- Header tabel tetap terlihat saat digulir dalam area tabel; petunjuk geser dan rentang baris ditampilkan.
- Tombol bukti memiliki ukuran konsisten, fokus keyboard, dan target sentuh lebih besar pada layar kecil.
- Detail perjalanan memakai panah buka/tutup, keadaan terbuka yang jelas, dan tetap menggunakan kontrol native.
- Filter diberi nama aksesibel; tabel diberi caption dan header scope.
- Verifikasi terbaru: npm.cmd run build berhasil; 12 tes JavaScript lulus; 15 tes PHP terkait (302 assertions) lulus; git diff --check bersih.
- Build tetap memberi peringatan aset login-background.webp yang sudah ada sebelumnya.
- Pemeriksaan visual browser belum dilakukan: tidak ada browser tool maupun Playwright/Puppeteer di lingkungan ini.
