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

            $this->command->info("Load ngram dari {$sentence_file}.");

            $filepath = "{$sentence_files_root_dir}/{$sentence_file}";
            $file_handle = fopen($filepath, "r");

            DB::beginTransaction();

            if ($file_handle) {
                while (($line = fgets($file_handle)) !== false) {

                    $words = explode(' ', $line);

                    for ($i = 0; $i < count($words) + 2; ++$i) {
                        $ngramFrequency = NgramFrequency::query()->firstOrNew([
                            "word1" => $words[$i - 2] ?? "<EMPTY>",
                            "word2" => $words[$i - 1] ?? "<EMPTY>",
                            "word3" => $words[$i - 0] ?? "<EMPTY>",
                        ]);

                        if (!$ngramFrequency->exists) {
                            $ngramFrequency->fill([
                                "frequency" => 1,
                            ])->save();
                        }
                        else {
                            $ngramFrequency->increment("frequency");
                        }
                    }
                }
            } else {
                $this->command->error("Error opening {$filepath}.");
            }

            DB::commit();
        }
    }
}
