# Desain Direct Non-Reguler, Release Izin, dan Planning Lintas

## Tujuan

Menyempurnakan siklus Ijin Lintas agar Vendor dapat mengajukan kebutuhan Non-Reguler tanpa Incoming Request, reviewer wajib melampirkan izin resmi saat release, dan Vendor dapat menyusun beberapa tanggal planning setelah izin dirilis. Coordinator dan Admin Purchasing mengendalikan perubahan agenda melalui lock/unlock per tanggal.

## Ruang Lingkup

Perubahan mencakup pengajuan Ijin Lintas Vendor, review dan release oleh Coordinator/Admin Purchasing, notifikasi release, halaman Planning Lintas Vendor, serta kontrol agenda pada halaman planning reviewer. Jenis pengajuan selain Ijin Lintas tidak berubah.

## Aturan Pengajuan Ijin Lintas

- Pengajuan Reguler wajib terhubung ke Incoming Request berstatus aktif yang ditujukan kepada perusahaan Vendor tersebut.
- Pengajuan Non-Reguler dapat dibuat langsung tanpa Incoming Request, PR, atau PO.
- Pemilihan Non-Reguler menyembunyikan dan mengosongkan `expedition_request_id`.
- Dokumen pendukung Vendor tetap mengikuti persyaratan Ijin Lintas yang berlaku. Surat Ijin Lintas resmi tidak diunggah Vendor.
- Tanggal planning belum dapat dimasukkan sebelum pengajuan berstatus `Released`.

## Release dan Lampiran Izin

- Transisi dari `APPROVED` ke `RELEASED` mewajibkan satu lampiran izin resmi.
- Lampiran harus berupa PDF dan berukuran maksimal 5 MB.
- File disimpan di storage privat dan metadata disimpan pada pengajuan Ijin Lintas.
- Perubahan status dan penyimpanan metadata file dilakukan secara konsisten. Jika proses gagal, status tidak boleh berubah menjadi `RELEASED`.
- Coordinator dan Admin Purchasing yang memiliki akses review Ijin Lintas dapat melakukan release.
- Vendor pemilik pengajuan, reviewer satu site, dan Developer dapat melihat atau mengunduh lampiran sesuai otorisasinya.

## Notifikasi Release

- Setelah release berhasil, event status mengirim notifikasi realtime dan web push kepada Vendor dengan tautan menuju halaman Planning Lintas.
- Pengajuan menyimpan penanda kapan release pertama kali dibuka Vendor.
- Menu **Planning Lintas** Vendor menampilkan badge jumlah izin Released yang belum dibuka.
- Ketika Vendor membuka detail/daftar izin Released pada halaman Planning, izin yang ditampilkan ditandai sudah dibaca sehingga badge diperbarui.

## Model Planning

`planning_items` menjadi sumber tunggal tanggal operasional. Setiap item mewakili satu tanggal planning dan memiliki:

- `planned_date`;
- `is_locked`;
- `locked_at`;
- `locked_by`;
- `updated_by` untuk audit perubahan terakhir.

Satu pengajuan dapat memiliki beberapa item planning. Setiap item dapat dikunci atau dibuka secara terpisah.

Agenda Daily/Weekly tidak dibuat otomatis ketika izin dirilis. Nilai frekuensi tetap menjadi klasifikasi pengajuan, sedangkan tanggal operasional aktual dimasukkan Vendor setelah release. Placeholder planning tanpa tanggal boleh dipakai oleh implementasi internal, tetapi tidak ditampilkan sebagai agenda aktual.

## Planning Lintas Vendor

- Vendor mendapat menu **Planning Lintas**.
- Halaman hanya menampilkan Ijin Lintas milik Vendor yang berstatus `Released` dan belum kedaluwarsa, serta riwayat relevan yang diperlukan untuk melihat lampiran.
- Setiap izin menampilkan nomor pengajuan, kategori/frekuensi, masa berlaku, unit, lampiran PDF release, dan daftar agenda.
- Vendor dapat menambahkan beberapa tanggal.
- Vendor dapat mengubah atau menghapus agenda yang belum dikunci.
- Vendor tidak dapat mengubah atau menghapus agenda terkunci. Pembatasan harus ditegakkan di backend dan dicerminkan dengan disabled state di UI.
- Tanggal wajib sama dengan atau setelah hari ini dan tidak boleh melewati `release_expired_at`.
- Vendor hanya dapat mengakses pengajuan yang dibuat oleh akunnya dan berada pada site yang sama.

## Planning Reviewer

- Halaman Planning Coordinator tetap menggunakan kalender yang ada.
- Admin Purchasing mendapat akses planning khusus Ijin Lintas pada site yang sama.
- Reviewer dapat mengubah tanggal agenda yang belum terkunci.
- Reviewer dapat mengunci dan membuka setiap agenda secara terpisah.
- Agenda terkunci tidak dapat dipindahkan melalui drag-and-drop sampai dibuka kembali.
- Lock menyimpan waktu dan pengguna yang menguncinya. Unlock membersihkan status lock, waktu, dan pengguna pengunci.
- Perubahan tanggal dan lock/unlock memicu event planning realtime sehingga tampilan Vendor dan reviewer tetap sinkron.

## Antarmuka

### Pengajuan Vendor

- Pilihan kategori mengontrol tampilan Incoming Request.
- Reguler menampilkan field Incoming Request dan pesan wajib.
- Non-Reguler menampilkan keterangan bahwa pengajuan dapat dikirim langsung tanpa PR/PO.
- Tidak ada input planning pada form pengajuan.

### Review dan Release

- Ketika reviewer memilih status `Released`, form menampilkan dropzone PDF "Lampiran Izin Released".
- Tombol simpan menampilkan processing state dan tidak dapat dikirim tanpa file.
- Kesalahan tipe, ukuran, dan penyimpanan file tampil di dekat input.

### Planning Vendor

- Layout mengikuti komponen panel, tabel/list, badge, dan kontrol form aplikasi saat ini.
- Status agenda menggunakan label jelas `Dapat Diubah` dan `Terkunci`.
- Aksi tambah/edit/hapus tetap nyaman pada desktop dan mobile.

### Planning Reviewer

- Kartu agenda menampilkan status lock dan tombol lock/unlock.
- Agenda terkunci tidak draggable dan memiliki affordance visual yang tetap kontras serta mudah dibaca.

## Otorisasi

- Vendor: hanya pengajuan dan planning miliknya sendiri.
- Coordinator: pengajuan dan planning pada site yang sama, sesuai permission modul.
- Admin Purchasing: hanya pengajuan Ijin Lintas dan planning pada site yang sama, sesuai permission Purchasing.
- Developer: mengikuti akses administratif yang sudah berlaku.
- Semua endpoint file, CRUD planning, perubahan status, dan lock/unlock melakukan pemeriksaan server-side.

## Penanganan Kesalahan

- Reguler tanpa Incoming Request ditolak dengan pesan pada field request.
- Non-Reguler mengabaikan `expedition_request_id` yang disisipkan klien agar tidak tercipta relasi yang keliru.
- Release tanpa PDF, dengan file non-PDF, atau di atas 5 MB ditolak tanpa mengubah status.
- Planning sebelum release, setelah kedaluwarsa, di luar masa berlaku, atau milik Vendor lain ditolak.
- Edit/hapus agenda terkunci oleh Vendor ditolak dengan pesan bahwa reviewer telah mengunci agenda.
- Kegagalan notifikasi tidak membatalkan transaksi utama, mengikuti pola `SafeBroadcast` yang sudah ada.

## Migrasi dan Kompatibilitas

- Tambahkan metadata lampiran release dan penanda notifikasi dibaca pada `vendor_submissions`.
- Tambahkan metadata lock pada `planning_items`.
- Data lama tetap valid. Pengajuan Released lama tanpa lampiran tetap dapat dibaca, tetapi lampiran diwajibkan untuk transisi release baru.
- Hentikan pembuatan otomatis tanggal Daily/Weekly saat release tanpa menghapus planning lama.
- Placeholder tanpa tanggal yang sudah ada tetap aman dan dapat digunakan ketika Vendor menambah agenda pertama.

## Pengujian

Pengujian feature/integration harus membuktikan:

1. Non-Reguler diterima tanpa Incoming Request dan Reguler ditolak tanpanya.
2. Non-Reguler tidak menyimpan request yang disisipkan klien.
3. Release Ijin Lintas gagal tanpa PDF, dengan tipe salah, dan dengan file terlalu besar.
4. Release berhasil menyimpan file privat, mengubah status, dan dapat diunduh pihak berwenang.
5. Vendor menerima event/notifikasi release dan badge belum dibaca.
6. Vendor dapat menambah beberapa tanggal setelah release.
7. Vendor tidak dapat membuat planning sebelum release, setelah expiry, di luar rentang, atau untuk pengajuan lain.
8. Vendor dapat mengubah/menghapus agenda unlocked dan ditolak ketika locked.
9. Coordinator dan Purchasing dapat lock/unlock per agenda pada site yang benar.
10. Agenda terkunci tidak dapat dipindahkan sampai di-unlock.
11. Realtime payload memuat status lock dan tanggal terbaru.
12. Seluruh regression test dan build frontend tetap lulus.

## Kriteria Selesai

- Ketiga alur dapat digunakan melalui UI desktop dan mobile.
- Validasi dan otorisasi tidak hanya bergantung pada UI.
- Migrasi berhasil pada database development dan fresh test database.
- Formatter, seluruh test suite, build production, dan pemeriksaan diff lulus.
