<?php


namespace App\DocumentProcessing;


class ComponentNode
{
    public \DOMNode $domNode;
    public int $offset;
    public ?int $length;

    public function __construct(
        \DOMNode $domNode,
        int $offset,
        ?int $length
    )
    {
        $this->length = $length;
        $this->offset = $offset;
        $this->domNode = $domNode;
    }
}