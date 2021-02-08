<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWordDistancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('word_distances', function (Blueprint $table) {
            $table->id();

            $table->string('word1');
            $table->foreign("word1")->references("content")->on("words");

            $table->string('word2');
            $table->foreign("word2")->references("content")->on("words");

            $table->float('distance');
            $table->index(['word1', 'word2', 'distance']);
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
        Schema::dropIfExists('word_distances');
    }
}
