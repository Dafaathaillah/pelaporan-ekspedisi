# P2H Keberangkatan per Unit

## Tujuan

Dokumen foto P2H tetap wajib pada pengajuan izin lintas. Setelah izin Released, akun expedition mengisi form P2H digital untuk setiap unit. Form kedatangan terbuka hanya sesudah vendor menyetujui P2H dan expedition mengonfirmasi keberangkatan unit tersebut.

## Alur

1. Expedition memilih izin Released yang masih berlaku dan mengisi semua item pemeriksaan, nomor ban, hasil laik/tidak laik, serta catatan. Identitas driver dan unit selalu dibaca dari izin. Item Rusak dan hasil Tidak Laik tetap dapat dikirim.
2. Setiap kiriman menjadi revisi P2H baru berstatus Pending. PDF menampilkan nama driver dan stempel persetujuan pengiriman, waktu, serta seluruh jawaban.
3. Vendor pemilik izin melihat P2H pada detail izin dan menandai Approved atau Rejected. Penolakan membutuhkan alasan. Setelah ditolak, expedition dapat mengirim revisi baru; revisi lama tetap tersimpan.
4. Expedition hanya dapat mengonfirmasi keberangkatan setelah Approved. Waktu keberangkatan dicatat satu kali per unit.
5. Endpoint kedatangan memeriksa status Approved dan waktu keberangkatan pada revisi terbaru unit sebelum menerima laporan. Foto kedatangan dan foto tensi tetap wajib, sedangkan foto P2H tidak diminta lagi.
6. Vendor, expedition terkait, coordinator, admin purchasing, dan developer pada site terkait dapat mengunduh PDF P2H terbaru. Hak baca dibatasi oleh site dan relasi vendor.

## Penyimpanan dan UI

`departure_p2hs` menyimpan satu baris per revisi dan unit, identitas snapshot, jawaban JSON, keputusan, reviewer, alasan, serta waktu keberangkatan. Form expedition dan review vendor memakai rute terpisah. Monitoring menunjukkan status P2H dan tautan unduhan. Dokumen foto P2H pada pengajuan izin tetap memakai persyaratan dokumen yang sudah ada.

## Verifikasi

Uji penguncian kedatangan, item Rusak, approval, penolakan dan revisi, kepemilikan vendor, akses PDF, regresi pengajuan izin, build frontend, dan seluruh suite Laravel.
