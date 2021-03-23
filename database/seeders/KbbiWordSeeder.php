<?php

namespace Database\Seeders;

use App\Kata;
use Illuminate\Database\Seeder;

class KbbiWordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fileHandle = fopen(__DIR__ . "/kata_dasar_kbbi.csv", "r");

        $wordList = [];
        while (($line = fgetcsv($fileHandle)) !== false) {
            $wordList[] = ["teks" => $line[0]];
        }

        fclose($fileHandle);

        Kata::query()->insertOrIgnore($wordList);
    }
}
