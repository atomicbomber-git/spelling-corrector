<?php


namespace App\Support;

use DOMNode;


class DomNodeTraverser
{
    public static function walk(DOMNode $node, Callable $callable)
    {
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $childNode) {
                $callable($childNode);
                self::walk($childNode, $callable);
            }
        }
    }

    public static function traverse(DOMNode $node, Callable $callable)
    {
        if (($callable($node) !== false) && $node->hasChildNodes()) {
            foreach ($node->childNodes as $childNode) {
                self::traverse($childNode, $callable);
            }
        }
    }
}