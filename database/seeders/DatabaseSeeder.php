<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $user->company()->updateOrCreate([], [
            'company_name' => 'Demo SME Sdn Bhd',
            'registration_no' => '202601000001',
            'address' => 'Kuala Lumpur, Malaysia',
            'email' => 'admin@example.com',
            'phone' => '+60 12-345 6789',
            'default_currency_code' => 'MYR',
        ]);
    }
}
