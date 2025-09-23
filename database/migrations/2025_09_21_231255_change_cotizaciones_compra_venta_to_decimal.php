<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->decimal('compra', 12, 4)->change();
            $table->decimal('venta', 12, 4)->change();
        });
    }

    public function down()
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('compra', 20)->change();
            $table->string('venta', 20)->change();
        });
    }
};