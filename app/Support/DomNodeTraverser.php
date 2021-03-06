<?php


namespace App\Support;

use DOMNode;


class DomNodeTraverser
{
    public static function traverse(DOMNode $node, Callable $callable)
    {
        if ($node->hasChildNodes() && ($callable($node) !== false)) {
            foreach ($node->childNodes as $childNode) {
                self::traverse($childNode, $callable);
            }
        }
    }
}