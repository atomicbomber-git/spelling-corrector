<?php


namespace App\Support;


use DOMNode;
use Illuminate\Support\Collection;

class StringUtil
{
    public static function trimUnicode(string $text): string {
        $temp = $text;
        $temp = preg_replace("/^[\p{P}\p{S}\p{Z}]*/u", "", $temp);
        $temp = preg_replace("/[\p{P}\p{S}\p{Z}]*$/u", "", $temp);
        return $temp;
    }

    public static function trimAndLowercaseUnicode(string $text): string
    {
        return mb_strtolower(static::trimUnicode($text));
    }

    public static function replaceAllRegexMultipleInXmlNode(string $pattern, array $replacements, DOMNode $domNode): DOMNode
    {
        $replacements = (new Collection($replacements))
            ->pluck("correction", "index")
            ->toArray();

        $pendingReplacements = [];

        $counter = 0;
        DomNodeTraverser::traverse($domNode, function (DOMNode $currentNode) use ($pattern, $replacements, &$counter, &$pendingReplacements) {
            if ($currentNode->nodeType === XML_TEXT_NODE) {

                $oldText = $currentNode->wholeText;

                $matches = [];
                preg_match_all($pattern, $oldText, $matches, PREG_OFFSET_CAPTURE);

                $newText = "";

                if (count($matches[0]) > 0) {
                    $prevTextPos = 0;
                    foreach ($matches[0] as $match) {
                        $offset = $prevTextPos;
                        $length = $match[1] - $prevTextPos;

                        $preceding = substr($oldText, $offset, $length);

                        $content = $match[0];
                        if (isset($replacements[$counter])) {
                            $content = $replacements[$counter];
                        }

                        $newText .= $preceding . $content;

                        ++$counter;
                        $prevTextPos = $match[1] + strlen($match[0]);
                    }

                    $newText .= substr($oldText, $prevTextPos);
                    $pendingReplacements[] = [
                        "new" => $currentNode->ownerDocument->createTextNode($newText),
                        "old" => $currentNode,
                    ];
                }
            }

        });

        foreach ($pendingReplacements as $pendingReplacement) {
            $pendingReplacement["old"]->parentNode->replaceChild(
                $pendingReplacement["new"],
                $pendingReplacement["old"],
            );
        }

        return $domNode;
    }
}