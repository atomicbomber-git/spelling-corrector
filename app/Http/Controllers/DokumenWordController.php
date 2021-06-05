<?php

namespace App\Http\Controllers;

use App\Constants\MessageState;
use App\DokumenWord;
use App\DomDocumentNLPTools\OldTokenizer;
use App\DomDocumentNLPTools\Sentence;
use App\DomDocumentNLPTools\Word;
use App\RecommendationEngine\WordRecommender;
use App\Support\FileConverter;
use App\Support\SessionHelper;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use NlpTools\Tokenizers\TokenizerInterface;
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
            "dokumen_words" => DokumenWord::query()
                ->where("user_id", Auth::id())
                ->orderByDesc("updated_at")
                ->orderByDesc("created_at")
                ->paginate()
        ]);
    }

    public function filterOutShortTokens(Word $token): bool
    {
        return mb_strlen($token->getNormalizedValue()) > 1;
    }

    public function filterOutTokensWithNonLetters(Word $token): bool
    {
        return preg_match_all("/[^\p{L}]/", $token->getNormalizedValue()) === 0;
    }

    public function show(Request $request, DokumenWord $dokumen_word)
    {
        if ($request->wantsJson()) {
            return $this->responseFactory->json([
                "nama" => $dokumen_word->nama,
                "konten_html" => $dokumen_word->getHtml()
            ]);
        }

        $tokenizer = new OldTokenizer();
        $tokenizer->load($dokumen_word->getWordXmlDomDocument());
        $sentences = $tokenizer->tokenize();

        $sentences = array_map([$this, "filterTokensInSentences"], $sentences);

        $sentences = array_values(array_filter($sentences, function (Sentence $sentence) {
            return count($sentence->words) > 0;
        }));


        return $this->responseFactory->view("dokumen-word.show", [
            "dokumen_word" => $dokumen_word->makeHidden("konten_html"),
            "tokens_with_error" => (new WordRecommender)->getRecommendations($sentences),
        ]);
    }

    public function create()
    {
        return $this->responseFactory->view("dokumen-word.create");
    }

    public function edit(DokumenWord $dokumen_word)
    {
        return $this->responseFactory->view("dokumen-word.edit", [
            "dokumen_word" => $dokumen_word,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "nama" => ["required", "string", Rule::unique(DokumenWord::class)->where("user_id", Auth::id())],
            "berkas" => ["required", "file", "mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document"],
        ], [
            "berkas.mimetypes" => "Berkas harus dalam format .docx",
        ]);

        DB::beginTransaction();

        $dokumenWord = DokumenWord::query()
            ->create([
                "user_id" => Auth::id(),
                "nama" => $data["nama"],
            ]);

        try {
            $dokumenWord->saveHtml(
                FileConverter::wordToHTML(
                    $request->file("berkas")->getRealPath(),
                )
            );
        } catch (HttpClientException $exception) {
            throw ValidationException::withMessages([
                "berkas" => "Gagal menghubungi server dokumen di " . config("application.document_server_url") . "."
            ]);
        }

        $dokumenWord
            ->addMediaFromRequest("berkas")
            ->toMediaCollection(DokumenWord::COLLECTION_WORD_FILE);

        DB::commit();

        SessionHelper::flashMessage(
            __("messages.create.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->redirectToRoute(
            "dokumen-word.show",
            $dokumenWord
        );
    }

    public function update(Request $request, DokumenWord $dokumen_word)
    {
        $data = $request->validate([
            "nama" => ["required", "string", Rule::unique(DokumenWord::class)->where("user_id", Auth::id())->ignoreModel($dokumen_word)],
            "berkas" => ["nullable", "file", "mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document"],
        ], [
            "berkas.mimetypes" => "Berkas harus dalam format .docx",
        ]);

        DB::beginTransaction();

        $dokumen_word->fill([
            "nama" => $data["nama"]
        ]);

        if ($request->hasFile("berkas")) {
            $dokumen_word->saveHtml(
                FileConverter::wordToHTML(
                    $request->file("berkas")->getRealPath(),
                )
            );

            $dokumen_word
                ->addMediaFromRequest("berkas")
                ->toMediaCollection(DokumenWord::COLLECTION_WORD_FILE);
        }

        $dokumen_word->save();

        DB::commit();

        SessionHelper::flashMessage(
            __("messages.update.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->redirectToRoute("dokumen-word.show", $dokumen_word);
    }

    public function destroy(DokumenWord $dokumen_word)
    {
        DB::beginTransaction();

        $dokumen_word->media()->delete();
        $dokumen_word->delete();

        DB::commit();

        SessionHelper::flashMessage(
            __("messages.update.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->redirectToRoute("dokumen-word.index");
    }

    private function filterTokensInSentences(Sentence $sentence): Sentence
    {
        $sentence->words = collect($sentence->words)
            ->filter([$this, "filterOutShortTokens"])
            ->filter([$this, "filterOutTokensWithNonLetters"])
            ->values()
            ->toArray();

        return $sentence;
    }
}
