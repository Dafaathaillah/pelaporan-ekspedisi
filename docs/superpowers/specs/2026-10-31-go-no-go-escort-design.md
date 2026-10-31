# GO/NO GO sebelum mulai escort

Status: disetujui pengguna dengan instruksi "yaps betul sekali, segera implementasikan". Pengguna sudah menentukan form, field otomatis/manual, approval Coordinator dan menu hijau stabilo. Pengguna mengonfirmasi bahwa driver mengisi keputusan kelayakan, lalu Coordinator menyetujui. Implementasi dilanjutkan inline di branch development.

## Alur yang direkomendasikan

1. Driver memilih satu atau beberapa unit yang siap dikawal. Persyaratan P2H, masa tunggu tujuh jam untuk unit ekspedisi, dan tanggal escort izin internal yang sudah ada tetap berlaku.
2. Tombol lanjutan membuka form Checklist Kesiapan Pengawalan Unit (GO/NO GO), bukan langsung memulai escort.
3. Driver mengisi nama escorter, NIK, seluruh checklist, keputusan Layak/Tidak Layak dan catatan; foto P5M yang sekarang diwajibkan tetap diambil sebelum pengajuan. Setelah submit, status PENDING dan unit belum berstatus PROCESS.
4. Coordinator pada site yang sama membuka menu Approval GO NO GO, melihat unit/checklist/foto P5M, lalu menyetujui pemeriksaan atau mengembalikannya untuk diperbaiki dengan alasan. Keputusan driver dan keputusan review disimpan terpisah.
5. Hanya jika keputusan driver Layak dan review Coordinator APPROVED (hasil GO), driver pengaju dapat memulai escort untuk kelompok unit yang persis sama. Endpoint mulai escort tunggal maupun batch juga memvalidasi persetujuan di server.
6. Keputusan driver Tidak Layak, pengajuan yang masih PENDING, atau review yang ditolak memblokir mulai escort. Review APPROVED atas keputusan Tidak Layak tetap menghasilkan NO GO. Setelah perbaikan, driver dapat mengajukan revisi untuk diperiksa kembali; riwayat pengajuan dan keputusan lama tetap tersimpan.
7. Driver dan Coordinator dapat mengunduh PDF pemeriksaan, dengan status/waktu submit dan approval yang sebenarnya.

## Pendekatan

Rekomendasi: entitas pemeriksaan tersendiri beserta daftar unit yang terkait, dibentuk sebelum batch escort dimulai. Satu pemeriksaan meliputi satu pilihan kelompok unit; data identitas dan checklist dicatat sebagai snapshot agar PDF lama tidak berubah karena pembaruan data unit.

Alternatif menambah field di masing-masing laporan akan menggandakan satu form empat kali untuk pilihan empat unit dan menyulitkan review sebagai kelompok. Menyimpan form sesudah mulai batch tidak memenuhi syarat persetujuan sebelum escort.

## Identitas form

| Field | Sumber |
|---|---|
| Departemen | SCM, otomatis dari server |
| Nama Escort Commander / escorter | Input manual driver |
| NIK | Input manual driver |
| Jabatan | Driver LV, otomatis dari server |
| Hari/Tanggal | Waktu submit pada timezone site/WITA; tidak diubah menjadi tanggal unduh |
| No Pol Unit Dikawal | Snapshot nopol seluruh unit terpilih, dipisahkan koma |
| Nama Driver Dikawal | Snapshot nama driver dari unit terpilih, dipisahkan koma |
| Lokasi Tujuan | PPA-BIB, otomatis dari server |
| Nama penandatangan Escort Commander | Sama dengan nama escorter yang diinput |
| Nama Tim Pengesahan | Nama akun Coordinator yang memberi keputusan |
| Tanggal Escort Commander | Tanggal submit |
| Tanggal Tim Pengesahan | Tanggal keputusan Coordinator |

Contoh empat unit: `DA 1111 AB, DA 2222 AB, DA 3333 AB, DA 4444 AB`. Pengguna tidak dapat mengganti daftar ini melalui payload form untuk memasukkan unit yang tidak dipilih.

## Checklist

Sebelas pokok pemeriksaan mengikuti referensi: kompetensi/sertifikat/SIMPER Escort Commander; kategori unit pengawalan; rambu pengawalan; P2H LV escort; kesesuaian identitas unit; kesesuaian identitas driver; kelayakan P2H unit dikawal; FTW escorter; fatigue check driver dikawal; tonase/KIR untuk angkutan yang relevan; P5M dan sosialisasi JSEA.

Usulan implementasi: tiap item diisi manual dengan pilihan Sesuai, Tidak Sesuai, atau Nihil/NA serta keterangan. Kolom bukti/lampiran pada PDF mengikuti form referensi dan menampilkan referensi bukti/catatan yang diisi; foto P5M terkait tersedia di detail pemeriksaan. Kewajiban lampiran per item belum diminta sehingga tidak ditambahkan sebagai syarat tersembunyi.

Keputusan Layak/Tidak Layak diisi manual oleh driver sesuai konfirmasi pengguna. Pada UI dan PDF, kolom contoh yang menyebut Security dilabeli "Keputusan Kelayakan (Diisi Driver)" agar aktor sebenarnya jelas. Review Coordinator dicatat terpisah sebagai APPROVED atau REJECTED dengan identitas/waktu reviewer. GO hanya jika Layak + APPROVED; Tidak Layak tetap NO GO sekalipun pemeriksaannya disetujui. Checklist tercentang tidak berarti sudah disetujui Coordinator.

## Tampilan

- Akun driver: pilih unit, isi GO/NO GO, pantau PENDING/GO/NO GO, unduh form, lalu Mulai Escort setelah GO. Daftar ringkas dan pagination; detail checklist/foto dimuat saat dibuka.
- Akun Coordinator: menu Approval GO NO GO dengan latar hijau stabilo yang tetap terbaca, status teks dan jumlah pengajuan pending. Halaman ringkas dengan pencarian/filter/pagination, detail pemeriksaan, keputusan review, keputusan kelayakan driver, hasil GO/NO GO dan download.
- PDF A4 portrait mengikuti judul Checklist Kesiapan Pengawalan Unit, bagian A Identitas, B Item yang Diperiksa dengan 11 baris dan kolom bukti/keterangan, keputusan kelayakan driver, serta C Pengesahan dengan keputusan review Coordinator dan hasil GO/NO GO. Gunakan stempel persetujuan PPA yang sudah tersedia untuk persetujuan sebenarnya; jangan membuat tanda tangan tulisan tangan palsu.
- Tanggal kirim dan approval terpisah. PDF pending/rejected tidak menampilkan stempel approval Coordinator. PDF keputusan Tidak Layak yang review-nya disetujui tetap memberi label NO GO yang jelas; stempel hanya menunjukkan persetujuan pemeriksaan, bukan izin mulai escort.

## Integritas dan kompatibilitas

- Hanya unit WAITING, belum diambil, pada site yang sama dan memenuhi persyaratan yang dapat diajukan/dimulai.
- Rekomendasi: unit yang berada di pengajuan PENDING/GO dicadangkan untuk pengaju agar driver lain tidak mengajukan unit yang sama. NO GO melepas cadangan. Driver dapat membatalkan pengajuan miliknya yang belum dipakai mulai escort untuk melepas cadangan; riwayat tetap disimpan (keputusan implementasi untuk mencegah unit terkunci tanpa jalan keluar). Pemeriksaan dan mulai escort memakai transaksi/penguncian unit untuk menghindari perebutan unit.
- Persetujuan hanya berlaku untuk pengaju dan daftar unit yang tercatat; mengubah pilihan wajib melalui pengajuan baru.
- Pemeriksaan kesiapan tetap diulang saat mulai escort (P2H, masa tunggu, tanggal internal, status unit).
- Persetujuan tidak otomatis mengubah unit menjadi PROCESS. Driver menekan Mulai Escort agar waktu mulai mencatat kejadian sebenarnya.
- Escort yang sudah PROCESS/CLOSED sebelum fitur ini ditambahkan tetap dapat diselesaikan; syarat baru berlaku untuk mulai escort berikutnya.
- Memakai Laravel/Inertia/Vue/Dompdf dan permission Coordinator expedition yang sudah digunakan untuk approval P2H; pembatasan site/perusahaan dan alur close escort tetap berlaku.
- Migrasi bersifat menambah data, tanpa menghapus database atau deploy production dalam tugas implementasi.

## Verifikasi yang direncanakan

Uji pilihan empat unit dan snapshot koma; field manual wajib dan otomatis tidak dapat dipalsukan; checklist lengkap; keputusan driver Tidak Layak serta PENDING/REJECTED tidak dapat mulai, termasuk review APPROVED atas keputusan Tidak Layak; GO hanya untuk pengaju/unit yang tepat; review lintas site ditolak; permintaan review ganda/stale ditolak; pengajuan bersamaan tidak merebut unit; jadwal internal dan masa tunggu tetap berlaku; validasi gagal mempertahankan form; PDF memuat identitas, submit/approval terpisah dan stempel bersyarat. Verifikasi browser mobile/desktop untuk pemilihan unit, submit, review, modal/scroll dan download.
