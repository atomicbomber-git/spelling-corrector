<?php

namespace Database\Seeders;

use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MahasiswaSeeder extends Seeder
{
    const PREFIX = "mahasiswa_";
    const COUNT = 20;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::beginTransaction();

        User::query()
            ->where("level", User::LEVEL_MAHASISWA)
            ->delete();

        foreach (range(1, self::COUNT + 1) as $counter) {
            User::query()->create([
                "name" => self::PREFIX . $counter,
                "username" => self::PREFIX . $counter,
                "password" => Hash::make(self::PREFIX . $counter),
                "level" => User::LEVEL_MAHASISWA,
            ]);
        }

        DB::commit();
    }
}
