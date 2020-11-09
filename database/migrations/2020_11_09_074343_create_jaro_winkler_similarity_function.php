<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateJaroWinklerSimilarityFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS `jaro_winkler_similarity`");

        DB::unprepared(/** @lang MariaDB */ <<<HERE
CREATE
    FUNCTION `jaro_winkler_similarity`(string_a varchar(255), string_b varchar(255)) RETURNS float
    DETERMINISTIC
BEGIN
    declare `window`, curString, curSub, maxSub, transpositions, prefixLen, maxPrefix int;
    declare char1, char2 char(1);
    declare common1, common2, old1, old2 varchar(255);
    declare found boolean;
    declare returnValue, jaro float;
    set maxPrefix = 6;
    set common1 = "";
    set common2 = "";
    set `window` = (length(string_a) + length(string_b) - abs(length(string_a) - length(string_b))) DIV 4
        + ((length(string_a) + length(string_b) - abs(length(string_a) - length(string_b))) / 2) mod 2;
    set old1 = string_a;
    set old2 = string_b;

    set curString = 1;
    while curString <= length(string_a) and (curString <= (length(string_b) + `window`))
        do
            set curSub = curstring - `window`;
            if (curSub) < 1 then
                set curSub = 1;
            end if;
            set maxSub = curstring + `window`;
            if (maxSub) > length(string_b) then
                set maxSub = length(string_b);
            end if;
            set found = false;
            while curSub <= maxSub and found = false
                do
                    if substr(string_a, curString, 1) = substr(string_b, curSub, 1) then
                        set common1 = concat(common1, substr(string_a, curString, 1));
                        set string_b = concat(substr(string_b, 1, curSub - 1), concat("0",
                                                                                      substr(string_b, curSub + 1, length(string_b) - curSub + 1)));
                        set found = true;
                    end if;
                    set curSub = curSub + 1;
                end while;
            set curString = curString + 1;
        end while;
    set string_b = old2;
    set curString = 1;
    while curString <= length(string_b) and (curString <= (length(string_a) + `window`))
        do
            set curSub = curstring - `window`;
            if (curSub) < 1 then
                set curSub = 1;
            end if;
            set maxSub = curstring + `window`;
            if (maxSub) > length(string_a) then
                set maxSub = length(string_a);
            end if;
            set found = false;
            while curSub <= maxSub and found = false
                do
                    if substr(string_b, curString, 1) = substr(string_a, curSub, 1) then
                        set common2 = concat(common2, substr(string_b, curString, 1));
                        set string_a = concat(substr(string_a, 1, curSub - 1), concat("0",
                                                                                      substr(string_a, curSub + 1, length(string_a) - curSub + 1)));
                        set found = true;
                    end if;
                    set curSub = curSub + 1;
                end while;
            set curString = curString + 1;
        end while;
    set string_a = old1;

    if length(common1) <> length(common2)
    then
        set jaro = 0;
    elseif length(common1) = 0 or length(common2) = 0
    then
        set jaro = 0;
    else
        set transpositions = 0;
        set curString = 1;
        while curString <= length(common1)
            do
                if (substr(common1, curString, 1) <> substr(common2, curString, 1)) then
                    set transpositions = transpositions + 1;
                end if;
                set curString = curString + 1;
            end while;
        set jaro =
                    (
                            length(common1) / length(string_a) +
                            length(common2) / length(string_b) +
                            (length(common1) - transpositions / 2) / length(common1)
                        ) / 3;

    end if;

    set prefixLen = 0;
    while (substring(string_a, prefixLen + 1, 1) = substring(string_b, prefixLen + 1, 1)) and (prefixLen < 6)
        do
            set prefixLen = prefixLen + 1;
        end while;
    return jaro + (prefixLen * 0.1 * (1 - jaro));
END        
HERE
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS `jaro_winkler_similarity`");
    }
}
