# P2H Kedatangan

## Tujuan
Menambahkan pemeriksaan P2H kedatangan per unit yang harus disetujui Coordinator sebelum pengguna dapat mengisi dan mengirim laporan kedatangan. Coordinator dapat mengunduh PDF pemeriksaan tersebut.

## Keputusan pengguna
- Checklist dan format PDF mengikuti P2H keberangkatan yang sudah ada.
- P2H kedatangan merupakan pemeriksaan tambahan dengan persetujuan Coordinator.
- Persetujuan harus mendahului pengisian form kedatangan.

## Pendekatan
Rekomendasi: simpan pemeriksaan kedatangan dalam entitas tersendiri yang terkait unit izin, menggunakan checklist dan format PDF bersama. Ini mempertahankan bukti dan persetujuan keberangkatan serta memungkinkan review sebelum laporan kedatangan terbentuk.
Alternatif menimpa pemeriksaan keberangkatan menghilangkan pemisahan bukti persetujuan vendor dan Coordinator. Menyimpan hanya pada laporan kedatangan tidak cocok karena laporan belum boleh diisi sebelum persetujuan. Pengguna menyetujui rekomendasi penyimpanan terpisah dan meminta implementasi.

## Alur yang diusulkan
1. Setelah P2H keberangkatan disetujui dan keberangkatan dikonfirmasi, pengguna yang berhak melapor kedatangan mengisi P2H Kedatangan pada kartu unit.
2. Checklist, kondisi BAIK/RUSAK/NIHIL, catatan, posisi ban, dan keputusan LAIK/TIDAK_LAIK mengikuti validasi P2H keberangkatan.
3. Pengiriman mencatat identitas driver/kendaraan, pengirim, revisi, waktu, dan status PENDING. Form kedatangan tetap terkunci.
4. Coordinator dengan izin coordinator.expedition pada site yang sama melihat antrean P2H sebelum ada laporan kedatangan, lalu menyetujui atau menolak. Penolakan wajib disertai alasan.
5. Setelah ditolak, pengguna dapat mengirim revisi. Riwayat pemeriksaan sebelumnya dipertahankan. PENDING tidak dapat dikirim ulang dan APPROVED tidak dapat diubah melalui form pengiriman.
6. Hanya revisi terbaru berstatus APPROVED yang membuka pemilihan unit dan form kedatangan. Server juga menolak pengiriman tanpa persetujuan, termasuk permintaan langsung.
7. Laporan baru mereferensikan pemeriksaan yang disetujui serta membawa status dan identitas review Coordinator agar pemeriksaan P2H tidak perlu disetujui ulang untuk pengawalan. Aturan waktu tunggu pengawalan yang ada tetap berlaku.
8. Coordinator dapat mengunduh PDF revisi terbaru saat PENDING, APPROVED, atau REJECTED. Dokumen memakai format keberangkatan dengan label kedatangan, pemeriksa Coordinator, status, waktu review, dan alasan penolakan.

## Batasan dan kompatibilitas
- Pembatasan site, perusahaan, akses laporan, izin Released yang berlaku, planning, dan pencegahan laporan ganda tetap digunakan.
- Data keberangkatan dan laporan lama tidak diubah. Review P2H lama tetap tersedia untuk laporan lama.
- Izin lintas internal dan alur lain di luar pelaporan kedatangan unit vendor tidak ditambahkan syarat baru.
- Migrasi hanya menambah struktur; tidak menghapus data dan tidak dijalankan pada database produksi tanpa instruksi.
- Tetap memakai Laravel, Inertia/Vue, Dompdf, dan komponen visual yang tersedia.

## Verifikasi
Uji regresi untuk pengiriman checklist, revisi setelah penolakan, alasan wajib, review lintas site/role yang ditolak, larangan review ulang, penguncian kedatangan sebelum approval, keberhasilan setelah approval, hubungan laporan ke P2H yang disetujui, dan unduh PDF beserta otorisasinya. Jalankan pemeriksaan terkait keberangkatan, kedatangan, pengawalan, serta build frontend sesuai lingkungan yang tersedia.

## Status
Disetujui pengguna pada 29 Oktober 2026 melalui instruksi “ya implementasikan”.
