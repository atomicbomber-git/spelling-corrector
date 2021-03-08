<?php


namespace App\DocumentProcessing;

use DOMNode;

class ComponentNode
{
    public DOMNode $domNode;
    public ?int $wordPosition;

    public function __construct(DOMNode $domNode, ?int $wordPosition = null)
    {
        $this->domNode = $domNode;
        $this->wordPosition = $wordPosition;
    }
}