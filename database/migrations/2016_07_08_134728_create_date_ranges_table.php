<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDateRangesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('date_ranges', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->date('date_start');
            $table->date('date_end');
            $table->text('description');
            $table->json('events');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::drop('date_ranges');
    }
}
