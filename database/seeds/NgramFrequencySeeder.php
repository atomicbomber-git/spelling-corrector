<?php

use App\Support\Ngram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use TextAnalysis\Tokenizers\SentenceTokenizer;

class NgramFrequencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $lazyCollection = new \Illuminate\Support\LazyCollection();

        $filePath = __DIR__ . "/../seeds/data/abstract.txt";
        $fileHandle = fopen($filePath, "r");

        DB::beginTransaction();

        while (($line = fgets($fileHandle)) !== false) {
            $sentences = tokenize($line, SentenceTokenizer::class);

            foreach ($sentences as $sentence) {
                $tokens = array_map(fn ($token) => trim($token, "[]().\t\n\r\0\x0B"),  tokenize($sentence));

                $ngrams = array_map(
                    fn($token) => explode(' ', $token),
                    Ngram::ngramWithPadding($tokens, 3, ' '),
                );

                foreach ($ngrams as $ngram) {
                    $filter = [
                        "word1" => $ngram[0],
                        "word2" => $ngram[1],
                        "word3" => $ngram[2],
                    ];

                    $recordCount = DB::table((new \App\NgramFrequency)->getTable())
                        ->where($filter)
                        ->count();

                    if ($recordCount == 0) {
                        DB::table((new \App\NgramFrequency)->getTable())
                            ->insertOrIgnore(array_merge($filter, [
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]));
                        continue;
                    }

                    DB::table((new \App\NgramFrequency)->getTable())
                        ->where($filter)
                        ->increment('frequency');
                }
            }
        }

        DB::commit();
    }
}
