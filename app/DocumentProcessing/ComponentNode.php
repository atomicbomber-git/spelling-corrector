<?php


namespace App\DocumentProcessing;

use DOMNode;

class ComponentNode
{
    public DOMNode $domNode;

    public function __construct(DOMNode $domNode)
    {
        $this->domNode = $domNode;
    }
}