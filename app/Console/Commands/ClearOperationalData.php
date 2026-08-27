<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearOperationalData extends Command
{
    protected $signature = 'data:clear-dummy
        {--force : Jalankan tanpa meminta konfirmasi}';

    protected $description = 'Menghapus seluruh data operasional/dummy tanpa menghapus user dan data master';

    /**
     * Tabel operasional diurutkan dari child ke parent agar foreign key tetap aman.
     *
     * @var list<string>
     */
    private const OPERATIONAL_TABLES = [
        'planning_items',
        'vendor_documents',
        'vendor_submission_units',
        'vendor_submissions',
        'expedition_reports',
        'expedition_requests',
    ];

    public function handle(): int
    {
        $tables = array_values(array_filter(
            self::OPERATIONAL_TABLES,
            static fn (string $table): bool => Schema::hasTable($table),
        ));

        if ($tables === []) {
            $this->warn('Tidak ada tabel operasional yang tersedia untuk dibersihkan.');

            return self::SUCCESS;
        }

        $this->warn('Semua data operasional akan dihapus permanen. Data users dan vendor_companies tetap dipertahankan.');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan penghapusan?', false)) {
            $this->info('Penghapusan dibatalkan.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(function () use ($tables): array {
            $counts = [];

            // Putuskan relasi self-reference sebelum semua submission dihapus.
            if (in_array('vendor_submissions', $tables, true)) {
                DB::table('vendor_submissions')->update([
                    'parent_submission_id' => null,
                    'expedition_request_id' => null,
                ]);
            }

            foreach ($tables as $table) {
                $counts[$table] = DB::table($table)->delete();
            }

            return $counts;
        });

        $this->newLine();
        $this->table(
            ['Tabel', 'Baris dihapus'],
            collect($deleted)->map(fn (int $count, string $table): array => [$table, $count])->values()->all(),
        );
        $this->info('Data operasional berhasil dibersihkan. Data master dan user tidak diubah.');

        return self::SUCCESS;
    }
}
