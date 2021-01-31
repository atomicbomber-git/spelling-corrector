<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNgramFrequenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ngram_frequencies', function (Blueprint $table) {
            $table->increments('id');

            $table->string('word1')->nullable();
            $table->string('word2')->nullable();
            $table->string('word3')->nullable();;
            $table->unsignedInteger('frequency')->default(0);

            $table->unique(['word1', 'word2', 'word3']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ngram_frequencies');
    }
}
