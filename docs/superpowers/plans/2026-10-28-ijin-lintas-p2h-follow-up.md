# Izin Lintas P2H Follow-up

**Goal:** Monitoring izin lintas menunjukkan tindak lanjut operasional dan dapat menghubungkan akun ekspedisi lama yang belum memiliki vendor_company_id, melalui pilihan eksplisit koordinator berizin.

**Evidence:** Screenshot production menampilkan warning akun ekspedisi belum terhubung; vendor dapat memilih izin yang sama; monitoring koordinator hanya menampilkan data dokumen. Perbaikan sebelumnya mencegah relasi dibuang saat edit akun, tetapi tidak memperbarui akun lama.

**Approach:** Pertahankan workflow, filter perusahaan/site dan foto tensi. Tambah panel P2H per izin Released pada hasil filter/paginasi monitoring. Panel menampilkan akun ekspedisi terkait, status dan langkah berikut tiap unit, serta link detail/PDF. Koordinator dengan users.manage dapat memilih akun ekspedisi aktif yang belum terhubung pada site sama untuk dihubungkan ke perusahaan pemilik izin. Jangan menebak perusahaan berdasarkan nama akun, jangan overwrite relasi perusahaan lain, jangan memberi vendor akses mengisi P2H.

**Alternatives:** Instruksi edit user saja belum cukup terlihat (sudah terjadi pada screenshot); auto-link berdasarkan nama berisiko memberi akses perusahaan salah. Panel tindak lanjut dengan pemilihan eksplisit mempertahankan hak akses dan menyelesaikan data akun lama.

## Tasks

- [x] RED: uji monitoring akun belum terhubung dan progress P2H; uji pengaitan akun membuka izin/P2H pada ekspedisi; tolak lintas site/perusahaan, role lain, akun nonaktif/tanpa permission, aktor tanpa users.manage; uji status JS.
- [x] GREEN: tambahkan payload monitoring terbatasi dan endpoint pengaitan eksplisit dengan row lock; panel tindak lanjut yang terlihat tanpa scroll tabel; petunjuk vendor membuka detail untuk approve/reject.
- [x] Verify: PHP suite SQLite memory + GD CLI, JS tests, build, lint, independent review. Tidak menjalankan database production, commit, merge atau push kecuali diminta lagi.

**Boundary:** Tidak ada akses database produksi dari sesi ini. Akun lama baru berubah setelah koordinator memilih akun dan menekan Hubungkan pada UI yang telah dipasang. Alur foto tensi dan kedatangan tetap utuh.

## Verification evidence

- Initial RED: 6 PHP regression tests failed because follow-up payload and linking route did not exist; JS status module absent.
- Targeted GREEN: 6 tests / 143 assertions, including explicit legacy-account linking followed by visible permit and successful P2H submission, permission/site/company protections and filter boundaries.
- Full PHP suite: `php -d extension=gd vendor/phpunit/phpunit/phpunit --no-progress` passed, **160 tests / 1612 assertions**.
- JS: `node --test tests/js/*.test.mjs` passed, **12 tests**, including 3 real Vue SSR rendering tests.
- Independent review found linking hidden when another expedition account was already linked. Reproduced with failing Vue SSR regression, moved linking controls outside missing-account branch, verified passing and reviewer confirmed addressed.
- `npm.cmd run build`: passed, existing login-background.webp runtime-resolution warning remains. Pint and git diff --check passed.
- No live production access or operational database changes; no new commit/push/merge/deployment in this task.

## Current production unblock

Before deploying this panel, the existing Manajemen User screen already supports editing the expedition account and selecting its vendor company. After saving the correct company and matching site, refresh the expedition P2H & Kedatangan page and choose the released, unexpired permit. This explicit data update is still required for old unlinked accounts; source-code fixes do not infer or silently assign their company.
