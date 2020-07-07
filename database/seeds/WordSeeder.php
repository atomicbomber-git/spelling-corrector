<?php

use App\Support\PdfImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TextAnalysis\Tokenizers\GeneralTokenizer;

class WordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $file = fopen(__DIR__ . "/data/data.csv", "r");
        DB::beginTransaction();

        $count = 0;
        $buffer = [];

        while (!feof($file) && ($raw = fgetcsv($file))) {
            $word = trim($raw[0]);

            $buffer[] = ["content" => $word];

            if (count($buffer) > 1000) {
                DB::table((new \App\Word)->getTable())->insertOrIgnore(
                    $buffer
                );

                $buffer = [];
                $count = 0;
            }
            else {
                ++$count;
            }
        }

        DB::commit();
    }
}
