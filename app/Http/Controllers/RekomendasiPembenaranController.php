<?php

namespace App\Http\Controllers;

use App\BusinessLogic\RekomendatorKoreksiEjaan;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;

class RekomendasiPembenaranController extends Controller
{
    private ResponseFactory $responseFactory;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            "text" => ["required", "string"]
        ]);

        $recommender = new RekomendatorKoreksiEjaan($data["text"]);

        return $recommender->recommendations();
    }
}