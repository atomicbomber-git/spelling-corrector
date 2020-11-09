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

        DB::unprepared("DROP TRIGGER IF EXISTS TR_similaritas_jaro_winkler");

        DB::unprepared(<<<HERE
CREATE TRIGGER TR_similaritas_jaro_winkler AFTER INSERT ON words
    INSERT INTO similaritas_jaro_winkler (
    
    
    )
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
