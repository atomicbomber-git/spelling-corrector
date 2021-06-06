<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPanjangTeksFieldToKata extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kata', function (Blueprint $table) {
            $table->unsignedInteger("panjang_teks")
                ->storedAs("LENGTH(teks)")
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
        Schema::table('kata', function (Blueprint $table) {
            $table->dropColumn("panjang_teks");
        });
    }
}
