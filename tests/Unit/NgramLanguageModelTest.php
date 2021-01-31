<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NgramLanguageModelTest extends TestCase
{
    public function testNgramWithPadding(array $tokens, bool  $padLeft = false, bool $padRight = false): array
    {

    }

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testCreateLanguageModel()
    {
        $filePath = __DIR__ . "/../../database/seeders/data/abstract.txt";
        $fileHandle = fopen($filePath, "r");








//        if (!$fileHandle) {
//            return;
//        }
//
//        while (($line = fgets($fileHandle)) !== false) {
//            dump($line);
//        }
    }
}
