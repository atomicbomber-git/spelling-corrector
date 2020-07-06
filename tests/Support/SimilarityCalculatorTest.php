<?php

namespace Tests\Support;

use App\Support\SimilarityCalculator;
use PHPUnit\Framework\TestCase;

class SimilarityCalculatorTest extends TestCase
{

    public function testJaroDistance()
    {
        $this->assertEquals(0.9611111111111111, SimilarityCalculator::jaroWinklerDistance("martha", "marhta"));
        $this->assertEquals(0.7333333333333334, SimilarityCalculator::jaroWinklerDistance("CRATE", "TRACE"));
        $this->assertEquals(0.0, SimilarityCalculator::jaroWinklerDistance("test", "dbdbdbdb"));
        $this->assertEquals(1.0, SimilarityCalculator::jaroWinklerDistance("test", "test"));
        $this->assertEquals(0.6363636363636364, SimilarityCalculator::jaroWinklerDistance("hello world", "HeLLo W0rlD"));
        $this->assertEquals(0.0, SimilarityCalculator::jaroWinklerDistance("test", ""));
        $this->assertEquals(0.4666666666666666, SimilarityCalculator::jaroWinklerDistance("hello", "world"));
        $this->assertEquals(0.4365079365079365, SimilarityCalculator::jaroWinklerDistance("hell**o", "*world"));
    }

    public function testCommonPrefix()
    {
        $this->assertEquals("CRE", SimilarityCalculator::commonPrefix("CRE", "CREA"));
        $this->assertEquals("CREA", SimilarityCalculator::commonPrefix("CREATURA", "CREATURA"));
        $this->assertEquals("", SimilarityCalculator::commonPrefix("XXXXX", "YYYYYY"));
    }
}
