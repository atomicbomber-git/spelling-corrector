<?php

use App\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\PdfToText\Pdf;
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
        $directoryPath = __DIR__ . DIRECTORY_SEPARATOR . "pdfs";
        $pdfDirectoryContents = scandir($directoryPath);
        $tokenizer = new GeneralTokenizer();

        DB::beginTransaction();

        foreach ($pdfDirectoryContents as $pdfDirectoryContent) {
            if (Str::endsWith($pdfDirectoryContent, ".pdf")) {
                $text = Pdf::getText($directoryPath . DIRECTORY_SEPARATOR . $pdfDirectoryContent);
                $tokens = $tokenizer->tokenize($text);
                $tokens = array_filter($tokens, fn ($token) => ctype_alpha($token));
                $tokens = array_map(fn ($token) => strtolower($token), $tokens);
                $datetime = now();

                DB::table((new Word)->getTable())
                    ->insertOrIgnore(
                        array_map(fn ($token) => [
                            "content" => $token,
                            "created_at" => $datetime,
                            "updated_at" => $datetime,
                        ], $tokens)
                    );
            }
        }

        DB::commit();
    }
}
