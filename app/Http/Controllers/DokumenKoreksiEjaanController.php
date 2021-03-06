<?php

namespace App\Http\Controllers;

use App\DocumentProcessing\SubstitutionList;
use App\DocumentProcessing\WordXmlProcessor;
use App\DokumenWord;
use App\Support\FileConverter;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use ZipArchive;

class DokumenKoreksiEjaanController extends Controller
{
    private ResponseFactory $responseFactory;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

//    /**
//     * Handle the incoming request.
//     *
//     * @param \Illuminate\Http\Request $request
//     * @param DokumenWord $dokumen_word
//     * @return \Illuminate\Http\Response
//     */
//    public function __invoke(Request $request, DokumenWord $dokumen_word)
//    {
//        $data = $request->validate([
//            "corrections" => ["array", "required"],
//            "corrections.*.original" => ["required", "string"],
//            "corrections.*.replacements" => ["required", "array"],
//            "corrections.*.replacements.*.index" => ["required", "integer"],
//            "corrections.*.replacements.*.correction" => ["required", "string"],
//        ]);
//
//        $replacementPairs = (new Collection($data["corrections"]))
//            ->pluck("replacements", "original")
//            ->toArray();
//
//        $domDocument = new DOMDocument();
//        $domDocument->loadHTML($dokumen_word->getHtml());
//
//        foreach ($replacementPairs as $original => $replacements) {
//            $original = preg_quote($original, "/");
//
//            $domDocument = StringUtil::replaceAllRegexMultipleInXmlNode(
//                "/(\b)({$original})(\b)/i",
//                $replacements,
//                $domDocument,
//            );
//        }
//
//        DB::beginTransaction();
//
//        $dokumen_word->saveHtml($domDocument->saveHTML());
//
//        /*
//         * Send wrapped HTML content to server, get docx data
//         * Replace current docx data with data obtained from server
//         * */
//        $wrappedHTML = $this->getWrappedHTMLFromDokumenWord($dokumen_word);
//
//        $docxFileContentInStringForm = FileConverter::HTMLToWord($wrappedHTML);
//
//        $dokumen_word
//            ->addMediaFromString($docxFileContentInStringForm)
//            ->usingFileName(
//                pathinfo($dokumen_word->getFirstMediaPath(DokumenWord::COLLECTION_WORD_FILE))["basename"]
//            )
//            ->toMediaCollection(DokumenWord::COLLECTION_WORD_FILE);
//
//
//        DB::commit();
//
//        SessionHelper::flashMessage(
//            __("messages.update.success"),
//            MessageState::STATE_SUCCESS,
//        );
//
//        return $this->responseFactory->noContent(200);
//    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param DokumenWord $dokumen_word
     * @return Response
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

        $substitutionList = new SubstitutionList();
        foreach ($data["corrections"] as $correction) {
            foreach ($correction["replacements"] as $replacement) {
                $substitutionList->addEntry(
                    strtolower($correction["original"]),
                    $replacement["index"],
                    $replacement["correction"],
                );
            }
        }

        $wordDocumentPath = $dokumen_word->getFirstMediaPath(DokumenWord::COLLECTION_WORD_FILE);
        $zipArchive = new ZipArchive();
        $zipResource = $zipArchive->open($wordDocumentPath);
        $pathToDocumentInsideZip = "word/document.xml";

        if ($zipResource === true) {
            $domDocument = new DOMDocument();
            $domDocument->loadXML($zipArchive->getFromName($pathToDocumentInsideZip));
            $newDocument = (new WordXmlProcessor)->substituteWords($domDocument, $substitutionList);
            $zipArchive->deleteName($pathToDocumentInsideZip);
            $zipArchive->addFromString($pathToDocumentInsideZip, $newDocument->saveXML());
            $zipArchive->close();
        } else {
            throw new Exception("Failed to open zip file.");
        }

        $dokumen_word->saveHtml(
            FileConverter::wordToHTML($wordDocumentPath)
        );

        return $this->responseFactory->noContent(200);
    }

    /**
     * @param DokumenWord $dokumen_word
     * @return string
     */
    public function getWrappedHTMLFromDokumenWord(DokumenWord $dokumen_word): string
    {
        $contentHtml = $dokumen_word->getHtml();

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
    $contentHtml
</body>
</html>
HERE;
    }
}
