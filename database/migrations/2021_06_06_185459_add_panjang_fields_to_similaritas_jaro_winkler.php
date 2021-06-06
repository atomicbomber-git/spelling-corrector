<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPanjangFieldsToSimilaritasJaroWinkler extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('similaritas_jaro_winkler', function (Blueprint $table) {
            $table->unsignedInteger("panjang_word_a")
                ->after("word_b")
                ->storedAs("LENGTH(word_a)")
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('similaritas_jaro_winkler', function (Blueprint $table) {
            $table->dropColumn("panjang_word_a");
        });
    }
}
