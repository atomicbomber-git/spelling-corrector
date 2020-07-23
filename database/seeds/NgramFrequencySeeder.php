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
        $lazyCollection = new LazyCollection();

        $filePath = __DIR__ . "/../seeds/data/abstract.txt";
        $fileHandle = fopen($filePath, "r");


        $lazySentenceTokenizer = new LazySentenceTokenizer();

        $val =
            LazyCollection::make(function () {
                $filePath = __DIR__ . "/../seeds/data/abstract.txt";
                $fileHandle = fopen($filePath, "r");

                while (($line = fgets($fileHandle)) !== false) {
                    yield $line;
                }
            })
                ->chunk(10)
                ->each(function ($chunk) use($lazySentenceTokenizer) {
                    DB::beginTransaction();
                    $chunk->map(fn($line) => LazyCollection::make($lazySentenceTokenizer->tokenize($line)))
                        ->map(function (LazyCollection $sentences) {
                            return LazyCollection::make(function () use ($sentences) {
                                foreach ($sentences as $sentence) {
                                    yield tokenize($sentence);
                                }
                            });
                        })
                        ->each(function (LazyCollection $tokenizedSentences) {
                            $tokenizedSentences->each(function ($tokenizedSentence) {

                                $tokens = array_map(fn($token) => trim($token, "[]().\t\n\r\0\x0B"), $tokenizedSentence);

                                $ngrams = array_map(
                                    fn($token) => explode(' ', $token),
                                    Ngram::ngramWithPadding($tokens, N_OF_GRAMS, ' '),
                                );

                                foreach ($ngrams as $ngram) {
                                    $filter = [
                                        "word1" => $ngram[0],
                                        "word2" => $ngram[1],
                                        "word3" => $ngram[2],
                                    ];

                                    $recordCount = DB::table((new NgramFrequency)->getTable())
                                        ->where($filter)
                                        ->count();

                                    if ($recordCount == 0) {
                                        DB::table((new NgramFrequency)->getTable())
                                            ->insertOrIgnore(array_merge($filter, [
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]));
                                        continue;
                                    }

                                    DB::table((new NgramFrequency)->getTable())
                                        ->where($filter)
                                        ->increment('frequency');
                                }

                            });
                        });

                    DB::commit();
                });
    }
}
