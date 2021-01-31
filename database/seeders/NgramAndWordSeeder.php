<?php

namespace Database\Seeders;

use App\NgramFrequency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;

class NgramAndWordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        NgramFrequency::query()->delete();

        $sentence_files_root_dir = database_path("seeders/sentences");
        $sentence_filenames = scandir($sentence_files_root_dir);
        $ngram_frequencies = [];

        $this->command->line("Loading data dari file teks kalimat.");


        $fileload_progress_bar = $this->createProgressBar($sentence_filenames);

        foreach ($sentence_filenames as $sentence_file) {
            if (in_array($sentence_file, [".", ".."])) {
                continue;
            }

            $fileload_progress_bar->setMessage("Load berkas " . $sentence_file);

            $filepath = "{$sentence_files_root_dir}/{$sentence_file}";
            $file_handle = fopen($filepath, "r");

            DB::beginTransaction();

            if ($file_handle) {
                while (($line = fgets($file_handle)) !== false) {
                    $line = preg_replace("/[\W_]/", " ", $line);
                    $line = preg_replace("/\s+/", " ", $line);
                    $line = trim($line);

                    $words = explode(' ', $line);

                    for ($i = 0; $i < count($words) + 2; ++$i) {
                        $word1 = ($words[$i - 2] ?? null);
                        $word2 = ($words[$i - 1] ?? null);
                        $word3 = ($words[$i - 0] ?? null);

                        $ngram_frequencies[$word1] ??= [];
                        $ngram_frequencies[$word1][$word2] ??= [];
                        $ngram_frequencies[$word1][$word2][$word3] ??= 0;
                        $ngram_frequencies[$word1][$word2][$word3]++;
                    }
                }
            } else {
                $this->command->error("Error opening {$filepath}.");
            }

            $fileload_progress_bar->advance();
            DB::commit();
        }

        $fileload_progress_bar->finish();


        $flattened_ngram_frequency_values = [];
        foreach ($ngram_frequencies as $word1 => $word1_subs) {
            foreach ($word1_subs as $word2 => $word2_subs) {
                foreach ($word2_subs as $word3 => $frequency) {
                    $flattened_ngram_frequency_values[] = [
                        "word1" => $word1 != "" ? $word1 : null,
                        "word2" => $word2 != "" ? $word2 : null,
                        "word3" => $word3 != "" ? $word3 : null,
                        "frequency" => $frequency,
                    ];
                }
            }
        }

        $chunk_size = 5000;
        $frequency_chunk_list = array_chunk($flattened_ngram_frequency_values, $chunk_size);
        $database_load_progressbar = $this->createProgressBar($frequency_chunk_list);

        foreach (array_chunk($flattened_ngram_frequency_values, $chunk_size) as $index => $frequency_chunks) {
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
}
