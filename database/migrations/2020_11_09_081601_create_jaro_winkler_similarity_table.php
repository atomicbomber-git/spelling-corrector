<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateJaroWinklerSimilarityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('similaritas_jaro_winkler', function (Blueprint $table) {
            $table->string('word_a');
            $table->foreign('word_a')->references('content')->on('words')
                ->cascadeOnDelete();

            $table->string('word_b');
            $table->foreign('word_b')->references('content')->on('words')
                ->cascadeOnDelete();

            $table->unsignedDouble("similaritas");
            $table->primary(["word_a", "word_b"]);
            $table->index(["word_a", "similaritas"]);
        });

        DB::unprepared(<<<HERE
DROP TRIGGER IF EXISTS TR_INSERT_similaritas_jaro_winkler;

CREATE TRIGGER TR_INSERT_similaritas_jaro_winkler
    AFTER INSERT
    ON words FOR EACH ROW
    INSERT INTO similaritas_jaro_winkler (word_a, word_b, similaritas)
        SELECT NEW.content AS a, words.content AS b, jaro_winkler_similarity(NEW.content, words.content) from words
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
        Schema::dropIfExists('similaritas_jaro_winkler');
        DB::unprepared("DROP TRIGGER IF EXISTS TR_similaritas_jaro_winkler");
    }
}
