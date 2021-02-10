<?php

namespace App\Http\Controllers;

use App\DokumenWord;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DOMDocument;
use Illuminate\Routing\ResponseFactory;
use NlpTools\Tokenizers\TokenizerInterface;
use NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;
use NlpTools\Tokenizers\WhitespaceTokenizer;

class DokumenWordController extends Controller
{
    private ResponseFactory $responseFactory;
    private TokenizerInterface $tokenizer;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->middleware("auth");
        $this->responseFactory = $responseFactory;
        $this->tokenizer = new WhitespaceTokenizer();
    }

    public function index(): Response
    {
        return $this->responseFactory->view("dokumen-word.index", [
            "dokumen_words" => DokumenWord::query()->paginate()
        ]);
    }

    private function cleanToken()
    {

    }

    public function show(DokumenWord $dokumen_word)
    {
        /* TODO: Clean tokens */

//        $text = strip_tags($dokumen_word->konten_html);
//
//        $tokens = $this->tokenizer->tokenize($text);
//        $tokens = array_map(
//            fn ($token) => preg_replace('/^(\W)*/', '', $token),
//            $tokens
//        );
//
//        return $tokens;








//        ray()->send();
//        strtok()
//        return "OK";
//        return \response(" $dokumen_word->konten_html");

//        return $this->responseFactory->view("dokumen-word.show", [
//            "dokumen_word" => $dokumen_word,
//        ]);
    }
}
