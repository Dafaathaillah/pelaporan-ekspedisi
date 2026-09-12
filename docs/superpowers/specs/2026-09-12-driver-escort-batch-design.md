# Desain Batch Escort Multi-Unit

## Tujuan

Driver dapat memilih satu atau lebih unit tanpa batas, mengambil satu foto P5M untuk seluruh pilihan, memulai escort secara atomik, dan menutup seluruh unit batch sekaligus dengan satu lokasi GPS.

## Model Data

Tambahkan UUID nullable `escort_batch_id` berindeks pada `expedition_reports`. Semua laporan dalam satu pemilihan menerima batch ID, driver mulai, waktu mulai, dan path P5M yang sama. Data lama tanpa batch ID diperlakukan sebagai batch satu unit.

## Klaim Batch

Frontend mengirim `report_ids[]` dan satu `p5m_photo`. Backend mewajibkan minimal satu ID, memastikan ID unik, memvalidasi gambar maksimal 8 MB, lalu mengunci seluruh laporan terpilih dalam urutan ID. Seluruh laporan harus ditemukan, satu site dengan driver, berstatus `WAITING`, dan belum memiliki driver escort. Jika satu pemeriksaan gagal, tidak ada laporan yang berubah. Foto disimpan sekali, lalu satu UUID batch diterapkan ke semua laporan dalam transaksi yang sama.

## Penutupan Batch

Frontend mengirim satu `report_id` representatif beserta koordinat, akurasi maksimal 100 meter, nama lokasi, dan waktu tangkap. Backend mengambil batch ID laporan tersebut dan mengunci semua anggotanya. Untuk data lama tanpa batch ID, hanya laporan itu yang diproses. Semua anggota harus masih `PROCESS` dan satu site; jika tidak, seluruh penutupan dibatalkan. Driver penutup, waktu, dan lokasi yang sama diterapkan ke semua unit.

## UI Driver

Kartu kendaraan menampilkan kontrol **Pilih Unit**. Pilihan boleh satu atau lebih tanpa batas. Bar tindakan sticky menampilkan jumlah unit dan tombol **Ambil P5M & Mulai Escort**. Modal kamera yang sudah ada digunakan satu kali. Escort aktif dikelompokkan menurut `escort_batch_id`; record lama menjadi kelompok satu unit. Setiap kelompok menampilkan daftar unit dan satu tombol **Tutup Escort Batch**.

## UI Koordinator

Setiap baris tetap mewakili laporan agar tabel dan ekspor tidak berubah. Detail escort menampilkan identitas batch dan daftar seluruh unit dari batch yang sama. Foto P5M tetap satu, dikaitkan dengan driver mulai, dan dapat dipreview atau diunduh. Backend menyertakan ringkasan anggota batch pada payload laporan.

## Error dan Keamanan

Pilihan kosong ditolak. Konflik satu anggota membatalkan seluruh klaim/penutupan. File yang sudah tersimpan dihapus jika transaksi gagal. Pemeriksaan site dan akses foto privat tetap berlaku. Endpoint lama satu-unit dipertahankan sebagai kompatibilitas bila diperlukan, tetapi UI menggunakan endpoint batch.

## Pengujian

Uji klaim satu dan banyak unit, penggunaan satu path foto, konflik atomik, site berbeda, penutupan satu batch, kegagalan status parsial, legacy tanpa batch ID, payload anggota batch, akses foto, build frontend, dan seluruh suite Laravel.

## Kriteria Penerimaan

- Driver bebas memilih satu atau lebih unit tanpa batas.
- Satu foto kamera P5M berlaku untuk seluruh batch.
- Klaim dan penutupan seluruh batch bersifat atomik.
- Unit aktif tampil sebagai kelompok batch pada driver.
- Koordinator dapat melihat anggota batch serta foto P5M dan driver terkait.
- Data lama tetap berfungsi sebagai batch satu unit.
