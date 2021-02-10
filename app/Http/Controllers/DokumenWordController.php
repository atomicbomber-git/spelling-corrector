<?php

namespace App\Http\Controllers;

use App\DokumenWord;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;

class DokumenWordController extends Controller
{
    private ResponseFactory $responseFactory;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->middleware("auth");
        $this->responseFactory = $responseFactory;
    }

    public function index()
    {
        return $this->responseFactory->view("dokumen-word.index", [
            "dokumen_words" => DokumenWord::query()->paginate()
        ]);
    }
}
