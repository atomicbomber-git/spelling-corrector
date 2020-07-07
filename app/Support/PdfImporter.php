<?php


namespace App\Support;


use App\Word;
use Illuminate\Support\Facades\DB;
use Sastrawi\Tokenizer\TokenizerInterface;
use Spatie\PdfToText\Pdf;

class PdfImporter
{
    private TokenizerInterface $tokenizer;

    public function __construct(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    public function import(string $filePath): void
    {
        $text = Pdf::getText($filePath);
        $tokens = $this->tokenizer->tokenize($text);
        $tokens = array_filter($tokens, fn($token) => ctype_alpha($token));
        $tokens = array_map(fn($token) => strtolower($token), $tokens);
        $datetime = now();

        DB::table((new Word())->getTable())
            ->insertOrIgnore(
                array_map(fn($token) => [
                    "content" => $token,
                    "created_at" => $datetime,
                    "updated_at" => $datetime,
                ], $tokens)
            );
    }
}
