<?php

namespace Database\Seeders;

use App\FrekuensiBigram;
use App\FrekuensiTrigram;
use App\Kata;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;

class NgramAndWordSeeder extends Seeder
{
    private array $dictionary;

    /** @var array | string */
    private array $trigramFrequencies = [];

    /** @var array | string */
    private array $bigramFrequencies = [];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        FrekuensiBigram::query()->delete();
        FrekuensiTrigram::query()->delete();

        $sentence_files_root_dir = database_path("seeders/sentences");
        $sentence_filenames = scandir($sentence_files_root_dir);
        $this->dictionary = [];

        $this->command->line("Loading data dari file teks kalimat.");

        $fileLoadProgressBar = $this->createProgressBar($sentence_filenames);

        foreach ($sentence_filenames as $sentence_file) {
            if (in_array($sentence_file, [".", ".."])) {
                continue;
            }

            $fileLoadProgressBar->setMessage("Load berkas " . $sentence_file);

            $filepath = "{$sentence_files_root_dir}/{$sentence_file}";
            $file_handle = fopen($filepath, "r");

            DB::beginTransaction();

            if ($file_handle) {
                while (($line = fgets($file_handle)) !== false) {
                    $line = preg_replace("/[\W_]/", " ", $line);
                    $line = preg_replace("/\s+/", " ", $line);
                    $line = trim($line);

                    $words = explode(' ', $line);
                    foreach ($words as $word) {
                        $this->dictionary[$word] ??= 0;
                        ++$this->dictionary[$word];
                    }

                    $this->extractTrigramFrequencies($words);
                    $this->extractBigramFrequencies($words);
                }
            } else {
                $this->command->error("Error opening {$filepath}.");
            }

            $fileLoadProgressBar->advance();
            DB::commit();
        }
        $fileLoadProgressBar->finish();

        $this->dictionary = array_filter($this->dictionary, fn($frequency, $word) => $frequency > 3, ARRAY_FILTER_USE_BOTH);
        $words = array_filter($this->dictionary, fn($word) => strlen($word) > 1 && $this->symbolToWholeTextRatio($word) < 0.2, ARRAY_FILTER_USE_KEY);
        $words = array_map(fn($word) => ["teks" => $word], array_keys($words));

        Kata::query()->insert($words);

        $flattenedBigramFrequencies = $this->getFlattenedBigramFrequencies($this->bigramFrequencies);
        $this->storeBigramFrequenciesToDatabase($flattenedBigramFrequencies);
    }

    /**
     * @param array $sentence_filenames
     * @return ProgressBar
     */
    public function createProgressBar(array $sentence_filenames): ProgressBar
    {
        $progress_bar = $this->command->getOutput()->createProgressBar(count($sentence_filenames));
        $progress_bar->setFormat("%current%/%max% [%bar%] %percent:3s%%\n%message%\n");
        return $progress_bar;
    }

    /**
     * @param array $words
     */
    public function extractTrigramFrequencies(array $words): void
    {
        for ($i = 0; $i < count($words) + 2; ++$i) {
            $word1 = ($words[$i - 2] ?? null);
            $word2 = ($words[$i - 1] ?? null);
            $word3 = ($words[$i - 0] ?? null);

            $this->trigramFrequencies[$word1] ??= [];
            $this->trigramFrequencies[$word1][$word2] ??= [];
            $this->trigramFrequencies[$word1][$word2][$word3] ??= 0;
            ++$this->trigramFrequencies[$word1][$word2][$word3];
        }
    }

    /**
     * @param array $words
     */
    public function extractBigramFrequencies(array $words): void
    {
        for ($i = 0; $i < count($words) + 1; ++$i) {
            $word1 = ($words[$i - 1] ?? null);
            $word2 = ($words[$i] ?? null);

            $this->bigramFrequencies[$word1] ??= [];
            $this->bigramFrequencies[$word1][$word2] ??= 0;
            ++$this->bigramFrequencies[$word1][$word2];
        }
    }

    public function getFlattenedBigramFrequencies(array $bigramFrequencies): array
    {
        $results = [];
        foreach ($bigramFrequencies as $w1 => $word1_subs) {
            foreach ($word1_subs as $w2 => $frequency) {
                if (!isset(
                    $this->dictionary[$w1],
                    $this->dictionary[$w2],
                )) {
                    continue;
                }

                $results[] = [
                    "gram_1" => $w1 != "" ? $w1 : null,
                    "gram_2" => $w2 != "" ? $w2 : null,
                    "frekuensi" => $frequency,
                ];
            }
        }
        return $results;
    }

    private function storeBigramFrequenciesToDatabase(array $flattenedBigramFrequencies)
    {
        $chunk_size = 5000;
        $frequency_chunk_list = array_chunk($flattenedBigramFrequencies, $chunk_size);
        $database_load_progressbar = $this->createProgressBar($frequency_chunk_list);

        foreach (array_chunk($flattenedBigramFrequencies, $chunk_size) as $index => $frequency_chunks) {
            $database_load_progressbar->setMessage(sprintf(
                "Memasukkan data ke %d-%d.",
                $index * $chunk_size + 1,
                ($index + 1) * $chunk_size,
            ));

            FrekuensiBigram::query()->insert(
                $frequency_chunks
            );

            $database_load_progressbar->advance();
        }

        $database_load_progressbar->finish();
    }

    public function getFlattenedTrigramFrequencies(array $trigramFrequencies): array
    {
        $results = [];
        foreach ($trigramFrequencies as $w1 => $word1_subs) {
            foreach ($word1_subs as $w2 => $word2_subs) {
                foreach ($word2_subs as $w3 => $frequency) {

                    if (!isset(
                        $this->dictionary[$w1],
                        $this->dictionary[$w2],
                        $this->dictionary[$w3],
                    )) {
                        continue;
                    }

                    $results[] = [
                        "word1" => $w1 != "" ? $w1 : null,
                        "word2" => $w2 != "" ? $w2 : null,
                        "word3" => $w3 != "" ? $w3 : null,
                        "frequency" => $frequency,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * @param array $flattenedTrigramFrequencies
     */
    public function storeTrigramFrequenciesToDatabase(array $flattenedTrigramFrequencies): void
    {
        $chunk_size = 5000;
        $frequency_chunk_list = array_chunk($flattenedTrigramFrequencies, $chunk_size);
        $database_load_progressbar = $this->createProgressBar($frequency_chunk_list);

        foreach (array_chunk($flattenedTrigramFrequencies, $chunk_size) as $index => $frequency_chunks) {
            $database_load_progressbar->setMessage(sprintf(
                "Memasukkan data ke %d-%d.",
                $index * $chunk_size + 1,
                ($index + 1) * $chunk_size,
            ));

            DB::table("ngram_frequencies")->insert(
                $frequency_chunks
            );

            $database_load_progressbar->advance();
        }

        $database_load_progressbar->finish();
    }

    private function symbolToWholeTextRatio(string $text): float
    {
        $textWithOnlyLetters = preg_replace("/[^\p{L}]/ui", "", $text);
        return (mb_strlen($text) - mb_strlen($textWithOnlyLetters)) / (mb_strlen($text) ?: 1);
    }
}
