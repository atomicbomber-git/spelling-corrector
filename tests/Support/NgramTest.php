<?php

namespace Tests\Support;

use App\Support\Ngram;
use PHPUnit\Framework\TestCase;

class NgramTest extends TestCase
{
    public function testPadArray()
    {
        $array = [1, 2, 3, 4, 5];

        $this->assertEquals($array, Ngram::padArray($array));
        $this->assertEquals([null, null, 1, 2, 3, 4, 5, null, null], Ngram::padArray($array, 2, 2));
        $this->assertEquals(['x', 'x', 1, 2, 3, 4, 5, 'x', 'x'], Ngram::padArray($array, 2, 2, 'x'));

    }

    public function testNgramWithPadding()
    {
        $tokens = ['I', 'go', 'to', 'school'];

        $this->assertEquals(
            ['I go to school'],
            Ngram::ngramWithPadding($tokens, 4)
        );

        $this->assertEquals(
            [
                'I go',
                'go to',
                'to school'
            ],
            Ngram::ngramWithPadding($tokens, 2)
        );

        $this->assertEquals(
            [
                '<empty> I',
                'I go',
                'go to',
                'to school',
            ],
            Ngram::ngramWithPadding($tokens, 2, ' ', true, false)
        );

        $this->assertEquals(
            [
                '<empty> I',
                'I go',
                'go to',
                'to school',
                'school <empty>',
            ],
            Ngram::ngramWithPadding($tokens, 2, ' ', true, true)
        );
    }
}
