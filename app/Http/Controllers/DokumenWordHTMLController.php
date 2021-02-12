<?php

namespace App\Http\Controllers;

use App\DokumenWord;
use Illuminate\Routing\ResponseFactory;

class DokumenWordHTMLController extends Controller
{
    private ResponseFactory $responseFactory;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->middleware("auth");
        $this->responseFactory = $responseFactory;
    }

    public function show(DokumenWord $dokumen_word)
    {
        return $this->responseFactory->make($dokumen_word->konten_html);
    }
}
