<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateDeveloperAccount extends Command
{
    protected $signature = 'developer:create
        {username=developer : Username login Developer}
        {--name=Developer Sistem : Nama akun}
        {--email= : Email opsional}
        {--site=BIB : Site akun}
        {--password= : Password (lebih aman dikosongkan agar diminta secara tersembunyi)}';

    protected $description = 'Membuat atau memperbarui akun Developer melalui CLI secara aman';

    public function handle(): int
    {
        $username = trim((string) $this->argument('username'));
        $password = (string) ($this->option('password') ?: $this->secret('Password Developer (minimal 12 karakter)'));

        if (! $this->option('password')) {
            $confirmation = (string) $this->secret('Ulangi password Developer');
            if ($password !== $confirmation) {
                $this->error('Konfirmasi password tidak sama.');

                return self::FAILURE;
            }
        }

        $data = [
            'username' => $username,
            'name' => trim((string) $this->option('name')),
            'email' => $this->option('email') ?: null,
            'site' => strtoupper(trim((string) $this->option('site'))),
            'password' => $password,
        ];
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'site' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:12'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $developer = User::firstOrNew(['username' => $username]);
        if ($developer->exists && $developer->role !== UserRole::DEVELOPER) {
            $this->error('Username sudah digunakan oleh akun non-Developer.');

            return self::FAILURE;
        }

        $developer->fill([
            ...$validator->validated(),
            'role' => UserRole::DEVELOPER,
            'is_active' => true,
            'permissions' => ['*'],
            'vendor_company_id' => null,
            'hull_number' => null,
        ])->save();

        $this->info($developer->wasRecentlyCreated ? 'Akun Developer berhasil dibuat.' : 'Akun Developer berhasil diperbarui.');
        $this->line('Username: '.$developer->username.' | Site: '.$developer->site);

        return self::SUCCESS;
    }
}
