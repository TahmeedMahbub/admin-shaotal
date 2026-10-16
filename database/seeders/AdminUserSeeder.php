<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL DEVELOPMENT ONLY. Passwords below must be replaced before production.
 * Idempotent: existing accounts (and their passwords) are left untouched.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $host = config('database.connections.'.config('database.default').'.host');

        // if (! app()->environment('local') || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
        //     $this->command?->error('Refusing to seed: only allowed on a local database (APP_ENV=local, DB_HOST=127.0.0.1).');

        //     return;
        // }

        $domain = config('admin.username_domain');
        dd("Seeding admin users for domain: $domain");

        foreach ([['tahmeed', 'Tahmeed Mahbub'], ['antu', 'Israk Antu']] as [$username, $name]) {
            AdminUser::firstOrCreate(
                ['email' => $username.'@'.$domain],
                ['name' => $name, 'password_hash' => Hash::make('123456'), 'status' => 'active'],
            );
        }
    }
}
