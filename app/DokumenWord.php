<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DokumenWord extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;
    protected $table = "dokumen_word";

    const COLLECTION_WORD_FILE = "word_files";
    const COLLECTION_HTML_FILE = "html_files";

    protected $guarded = [];

    public function getHtml(): ?string
    {
        $mediaPath = $this->getFirstMediaPath(self::COLLECTION_HTML_FILE);

        if ($mediaPath) {
            return file_get_contents($mediaPath);
        }

        return null;
    }

    public function getWordXmlDomDocument(): \DOMDocument
    {
        $zipArchive = new \ZipArchive();
        $zipResource = $zipArchive->open($this->getFirstMediaPath(self::COLLECTION_WORD_FILE));

        $domDocument = new \DOMDocument();
        if ($zipResource === true) {
            $domDocument->loadXML($zipArchive->getFromName("word/document.xml"));
            $zipArchive->close();
        } else {
            throw new \Exception("Failed to open zip file.");
        }

        return $domDocument;
    }

    public function saveHtml(string $htmlString): void
    {
        $this
            ->addMediaFromString($htmlString)
            ->usingFileName($this->nama . ".html")
            ->toMediaCollection(self::COLLECTION_HTML_FILE);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION_WORD_FILE)
            ->singleFile();

        $this->addMediaCollection(self::COLLECTION_HTML_FILE)
            ->singleFile();
    }
}
