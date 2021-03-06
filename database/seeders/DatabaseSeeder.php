<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(AdminUserSeeder::class);
        $this->call(MahasiswaSeeder::class);
        $this->call(NgramAndWordSeeder::class);
        $this->call(KbbiWordSeeder::class);
        $this->call(SimilaritasSeeder::class);
    }
}
