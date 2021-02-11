<?php

namespace App\Http\Controllers;

use App\BusinessLogic\RekomendatorKoreksiEjaan;
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

    public function show(DokumenWord $dokumen_word)
    {

        $rekomendator = new RekomendatorKoreksiEjaan("saya pergi ke sekolh");
        return $rekomendator->recommendations();










//        ray()->send();
//        strtok()
//        return "OK";
//        return \response(" $dokumen_word->konten_html");

//        return $this->responseFactory->view("dokumen-word.show", [
//            "dokumen_word" => $dokumen_word,
//        ]);
    }
}
