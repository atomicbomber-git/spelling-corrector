<?php


namespace App\DocumentProcessing;


class ComponentNode
{
    public function __construct(
        public \DOMNode $domNode,
        public int $offset,
        public ?int $length,
    )
    {
    }
}