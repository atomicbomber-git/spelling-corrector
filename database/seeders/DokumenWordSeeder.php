<?php

namespace Database\Seeders;

use App\DokumenWord;
use Database\Factories\DokumenWordFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DokumenWordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DokumenWord::query()->delete();

        DB::beginTransaction();

        DokumenWordFactory::new()
            ->count(100)
            ->create();

        DB::commit();
    }
}
