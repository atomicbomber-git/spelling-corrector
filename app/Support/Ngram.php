<?php


namespace App\Support;


class Ngram
{
    public static function padArray($array, $left = 0, $right = 0, $value = null) {
        $result = [];

        for ($i = 0; $i < $left; ++$i) {
            $result[] = $value;
        }

        foreach ($array as $member) {
            $result[] = $member;
        }

        for ($i = 0; $i < $right; ++$i) {
            $result[] = $value;
        }

        return $result;
    }

    public static function ngramWithPadding(array $tokens, $n = 1, $separator = ' ', bool $padLeft = true, bool $padRight = true): array
    {
        $paddedTokens = static::padArray(
            $tokens,
            $padLeft ? $n - 1 : false,
            $padRight ? $n - 1: false,
            '<empty>'
        );

        return ngrams($paddedTokens, $n, $separator);
    }
}
