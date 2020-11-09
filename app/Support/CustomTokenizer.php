<?php


namespace App\Support;


class CustomTokenizer
{
    public function tokenize($text, $delimiters=[" ", "\n", "\t", "\""])
    {
        $i = 0;
        $parts = [];
        $temp = "";

        while ($i < strlen($text)) {
            if (in_array($text[$i], $delimiters)) {
                if ($temp !== "") {
                    $parts[] = ["string" => $temp, "isToken" => true];
                }

                $parts[] = ["string" => $text[$i], "isToken" => false];
                $temp = "";
            } else {
                $temp .= $text[$i];
            }

            $i++;
        }

        if ($temp !== "") {
            $parts[] = [
                "string" => $temp,
                "isToken" => true,
            ];
        }

        return $parts;
    }
}
