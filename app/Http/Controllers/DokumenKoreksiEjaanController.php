<?php

namespace App\Http\Controllers;

use App\DokumenWord;
use App\Support\FileConverter;
use App\Support\MessageState;
use App\Support\SessionHelper;
use App\Support\StringUtil;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DokumenKoreksiEjaanController extends Controller
{
    private ResponseFactory $responseFactory;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param DokumenWord $dokumen_word
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, DokumenWord $dokumen_word)
    {
        $data = $request->validate([
            "corrections" => ["array", "required"],
            "corrections.*.original" => ["required", "string"],
            "corrections.*.replacements" => ["required", "array"],
            "corrections.*.replacements.*.index" => ["required", "integer"],
            "corrections.*.replacements.*.correction" => ["required", "string"],
        ]);

        $replacementPairs = (new Collection($data["corrections"]))
            ->pluck("replacements", "original")
            ->toArray();

        $domDocument = new DOMDocument();
        $domDocument->loadHTML($dokumen_word->konten_html);

        foreach ($replacementPairs as $original => $replacements) {
            $original = preg_quote($original, "/");

            $domDocument = StringUtil::replaceAllRegexMultipleInXmlNode(
                "/(\b)({$original})(\b)/i",
                $replacements,
                $domDocument,
            );
        }

        DB::beginTransaction();

        $dokumen_word->update([
            "konten_html" => $domDocument->saveHTML()
        ]);

        /*
         * Send wrapped HTML content to server, get docx data
         * Replace current docx data with data obtained from server
         * */
        $wrappedHTML = $this->getWrappedHTMLFromDokumenWord($dokumen_word);

        $docxFileContentInStringForm = FileConverter::HTMLToWord($wrappedHTML);

        $dokumen_word
            ->addMediaFromString($docxFileContentInStringForm)
            ->usingFileName(
                pathinfo($dokumen_word->getFirstMediaPath(DokumenWord::COLLECTION_WORD_FILE))["basename"]
            )
            ->toMediaCollection(DokumenWord::COLLECTION_WORD_FILE);


        DB::commit();

        SessionHelper::flashMessage(
            __("messages.update.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->noContent(200);
    }

    /**
     * @param DokumenWord $dokumen_word
     * @return string
     */
    public function getWrappedHTMLFromDokumenWord(DokumenWord $dokumen_word): string
    {
        return <<<HERE
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"
    >
    <meta http-equiv="X-UA-Compatible"
          content="ie=edge"
    >
    <title> $dokumen_word->nama </title>
</head>
<body>
    $dokumen_word->konten_html
</body>
</html>
HERE;
    }
}
