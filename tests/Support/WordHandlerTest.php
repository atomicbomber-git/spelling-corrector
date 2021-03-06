<?php

namespace Tests\Support;

use App\DocumentProcessing\SubstitutionList;
use App\DocumentProcessing\WordXmlProcessor;
use DOMDocument;
use PHPUnit\Framework\TestCase;


class WordHandlerTest extends TestCase
{
    public function testCanOpenWordDocument()
    {
        $xmlDocumentContent = /** @lang XML */
            <<<HERE
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
            mc:Ignorable="w14 wp14">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Normal"/>
                <w:bidi w:val="0"/>
                <w:jc w:val="left"/>
                <w:rPr></w:rPr>
            </w:pPr>
            <w:r>
                <w:rPr></w:rPr>
                <w:t>Negsra</w:t>
                <w:t xml:space="preserve"> Kesatuan</w:t>
                <w:t xml:space="preserve"> Republik</w:t>
                <w:t xml:space="preserve"> Indo</w:t>
                <w:t xml:space="preserve">nei</w:t>
                <w:t xml:space="preserve">sa</w:t>
                <w:t xml:space="preserve"> Salph</w:t>
                <w:t xml:space="preserve"> Salph</w:t>
                <w:t xml:space="preserve"> Salph</w:t>
            </w:r>
        </w:p>
        <w:sectPr>
            <w:type w:val="nextPage"/>
            <w:pgSz w:w="11906" w:h="16838"/>
            <w:pgMar w:left="1134" w:right="1134" w:header="0" w:top="1134" w:footer="0" w:bottom="1134" w:gutter="0"/>
            <w:pgNumType w:fmt="decimal"/>
            <w:formProt w:val="false"/>
            <w:textDirection w:val="lrTb"/>
            <w:docGrid w:type="default" w:linePitch="100" w:charSpace="0"/>
        </w:sectPr>
    </w:body>
</w:document>
HERE;

        $substitutions = new SubstitutionList();
        $substitutions->addEntry("Indoneisa", 0, "Batu");

        $processor = new WordXmlProcessor();
        $domDocument = new DOMDocument();
        $domDocument->loadXML($xmlDocumentContent);


        $zipArchive = new \ZipArchive();
        $zipResource = $zipArchive->open(__DIR__ . "/article.docx");
        $pathToDocumentInsideZip = "word/document.xml";

        if ($zipResource === true) {
            $fileContentAsText = $zipArchive->getFromName($pathToDocumentInsideZip);
            $domDocument->loadXML($fileContentAsText);

            $newDocument = $processor->substituteWords($domDocument, $substitutions);
            $zipArchive->deleteName($pathToDocumentInsideZip);
            $zipArchive->addFromString($pathToDocumentInsideZip, $newDocument->saveXML());
            $zipArchive->close();
        } else {
            throw new \Exception("Failed to open zip file.");
        }


    }
}
