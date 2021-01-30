<?php

use App\NgramFrequency;
use App\Support\LazySentenceTokenizer;
use App\Support\Ngram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use TextAnalysis\Tokenizers\SentenceTokenizer;

const N_OF_GRAMS = 3;
class NgramFrequencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sentence_files_root_dir = database_path("seeds/sentences");
        $sentence_filenames = scandir($sentence_files_root_dir);

        foreach ($sentence_filenames as $sentence_file) {
            if (in_array($sentence_file, [".", ".."])) {
                continue;
            }

//            $filepath =

            $file = file_get_contents("{$sentence_files_root_dir}/{$sentence_file}");




        }



    }
}
