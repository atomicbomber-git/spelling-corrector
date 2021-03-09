<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFrekuensiBigramTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('frekuensi_bigram', function (Blueprint $table) {
            $table->increments('id');
            $table->string('gram_1')->nullable();
            $table->string('gram_2')->nullable();
            $table->unsignedInteger('frekuensi')->default(0);
            $table->unique(['gram_1', 'gram_2']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('frekuensi_bigram');
    }
}
