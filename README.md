# Pelaporan Ekspedisi & Pengajuan Dokumen Vendor

Portal operasional PT Putra Perkasa Abadi — Site Borneo Indobara untuk pelaporan kedatangan ekspedisi, pengajuan dokumen vendor, Planning Escort, Driver Escort, monitoring kesehatan, dan pembaruan realtime.

## Dokumentasi

- [Dokumentasi lengkap (Markdown)](docs/DOKUMENTASI_SISTEM.md)
- [Dokumentasi siap cetak (HTML)](docs/DOKUMENTASI_SISTEM.html)
- [Dokumentasi resmi (PDF)](docs/DOKUMENTASI_SISTEM_PELAPORAN_EKSPEDISI_PPA_BIB.pdf)

Dokumentasi mencakup arsitektur, seluruh role dan hak akses, alur operasional, persyaratan dokumen, DataTable server-side, realtime Laravel Reverb, instalasi, deployment production, keamanan, backup, testing, dan troubleshooting.

## Quick Start

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
composer run dev
```

Untuk membuat akun Developer yang tidak dapat dikelola Koordinator:

```bash
php artisan developer:create developer --name="Developer Sistem" --site=BIB
```

## Quality Gate

```bash
php artisan test
npm run build
./vendor/bin/pint --test
```

**Kolaborasi SCM · FAW · ICT**
